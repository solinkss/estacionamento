<?php
/**
 * Parking Core - Gestão de Tickets e Entradas/Saídas
 * Sistema Estacionamento v3.0
 * 
 * - Validação placa Mercosul
 * - Constraint concorrência (placa_aberta UNIQUE)
 * - QR Code ULID/UUIDv7 não previsível
 * - Padrão cadastro auxiliar não-bloqueante
 */

declare(strict_types=1);

class ParkingCore {
    private PDO $db;
    private AuditGuard $audit;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->audit = new AuditGuard();
    }
    
    /**
     * Normaliza placa para padrão Mercosul
     * Remove caracteres especiais, uppercase, valida formato
     */
    public static function normalizarPlaca(string $placa): string {
        // Remove tudo que não é alfanumérico
        $placa = preg_replace('/[^A-Z0-9]/i', '', strtoupper(trim($placa)));
        
        // Limita a 7 caracteres
        return substr($placa, 0, 7);
    }
    
    /**
     * Valida formato placa Mercosul
     * Aceita ambos padrões: ABC1234 (antigo) e ABC1D23 (Mercosul)
     */
    public static function validarPlaca(string $placa): bool {
        $placa = self::normalizarPlaca($placa);
        
        if (strlen($placa) !== 7) {
            return false;
        }
        
        // Padrão antigo: LLLNNNN (3 letras + 4 números)
        $padraoAntigo = '/^[A-Z]{3}[0-9]{4}$/';
        
        // Padrão Mercosul: LLLNLNN (3 letras, 1 número, 1 letra, 2 números)
        $padraoMercosul = '/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/';
        
        return preg_match($padraoAntigo, $placa) || preg_match($padraoMercosul, $placa);
    }
    
    /**
     * Cria entrada de ticket com constraint de concorrência
     * 
     * @param array $dados Dados do ticket
     * @return array Ticket criado
     * @throws Exception Se já existir ticket aberto para mesma placa
     */
    public function criarEntrada(array $dados): array {
        $this->db->beginTransaction();
        
        try {
            $placaNormalizada = self::normalizarPlaca($dados['placa']);
            
            // Valida placa
            if (!self::validarPlaca($placaNormalizada)) {
                throw new Exception('Formato de placa inválido');
            }
            
            // Gera código único (ULID/UUIDv7)
            $codigoUnico = $this->gerarUlid();
            $idempotencyKey = $this->gerarUuidV7();
            
            // Processa cadastros auxiliares (marca, modelo, cor) - padrão não-bloqueante
            $marcaId = $this->processarCadastroAuxiliar(
                'marcas_veiculo',
                $dados['marca'] ?? null,
                $codigoUnico
            );
            
            $modeloId = $this->processarCadastroAuxiliar(
                'modelos_veiculo',
                $dados['modelo'] ?? null,
                $codigoUnico,
                $marcaId
            );
            
            $corId = $this->processarCadastroAuxiliar(
                'cores_veiculo',
                $dados['cor'] ?? null,
                $codigoUnico
            );
            
            // Insert do ticket
            // A constraint UNIQUE(placa_aberta) impedirá duplicidade em caso de race condition
            $sql = "INSERT INTO tickets (
                        id, codigo_unico, placa_normalizada, tipo_veiculo_id,
                        marca_id, modelo_id, cor_id, cliente_id,
                        operador_entrada_id, idempotency_key, hash_qr
                    ) VALUES (
                        :id, :codigo_unico, :placa, :tipo_veiculo_id,
                        :marca_id, :modelo_id, :cor_id, :cliente_id,
                        :operador_id, :idempotency_key, :hash_qr
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $this->gerarUuidV7(),
                ':codigo_unico' => $codigoUnico,
                ':placa' => $placaNormalizada,
                ':tipo_veiculo_id' => $dados['tipo_veiculo_id'],
                ':marca_id' => $marcaId,
                ':modelo_id' => $modeloId,
                ':cor_id' => $corId,
                ':cliente_id' => $dados['cliente_id'] ?? null,
                ':operador_id' => $dados['operador_id'],
                ':idempotency_key' => $idempotencyKey,
                ':hash_qr' => $this->gerarHashQr($codigoUnico)
            ]);
            
            $ticketId = $this->db->lastInsertId();
            
            // Auditoria
            $this->audit->log(
                'ENTRADA_CRIADA',
                'SUCESSO',
                'Ticket de entrada criado para placa ' . AuditGuard::mascararPII($placaNormalizada, 'placa'),
                [
                    'ticket_id' => $ticketId,
                    'placa' => $placaNormalizada,
                    'codigo_unico' => $codigoUnico
                ],
                $dados['operador_id'],
                PERFIL_OPERADOR
            );
            
            $this->db->commit();
            
            return [
                'id' => $ticketId,
                'codigo_unico' => $codigoUnico,
                'placa' => $placaNormalizada,
                'entrada_utc' => date('Y-m-d H:i:s'),
                'qr_code' => $codigoUnico,
                'hash_qr' => $this->gerarHashQr($codigoUnico)
            ];
            
        } catch (PDOException $e) {
            $this->db->rollback();
            
            // Verifica se foi erro de duplicidade da constraint placa_aberta
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new Exception('Já existe um ticket aberto para esta placa');
            }
            
            throw $e;
        }
    }
    
    /**
     * Processa cadastro auxiliar com padrão não-bloqueante
     * Se não existir, cria como PENDENTE sem travar operação
     */
    private function processarCadastroAuxiliar(
        string $tabela,
        ?string $nome,
        string $ticketId,
        ?string $marcaId = null
    ): ?string {
        if (empty($nome)) {
            return null;
        }
        
        // Normaliza: trim, Title Case, remove espaços duplos
        $nomeNormalizado = strtolower(trim(preg_replace('/\s+/', ' ', $nome)));
        $nomeFormatado = ucwords($nomeNormalizado);
        
        // Busca existente (ATIVO ou PENDENTE)
        $sql = "SELECT id FROM {$tabela} 
                WHERE nome_normalizado = :nome_norm 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':nome_norm' => $nomeNormalizado]);
        $existente = $stmt->fetchColumn();
        
        if ($existente) {
            return $existente;
        }
        
        // Não existe → cria PENDENTE
        $novoId = $this->gerarUuidV4();
        
        if ($tabela === 'modelos_veiculo') {
            $insert = "INSERT INTO modelos_veiculo (id, marca_id, nome, nome_normalizado, status, criado_por_ticket_id)
                       VALUES (:id, :marca_id, :nome, :nome_norm, 'PENDENTE', :ticket_id)";
            $stmt = $this->db->prepare($insert);
            $stmt->execute([
                ':id' => $novoId,
                ':marca_id' => $marcaId,
                ':nome' => $nomeFormatado,
                ':nome_norm' => $nomeNormalizado,
                ':ticket_id' => $ticketId
            ]);
            
            $this->audit->log(
                'MODELO_PENDENTE_CRIADA',
                'SUCESSO',
                'Modelo pendente criado: ' . $nomeFormatado,
                ['modelo' => $nomeFormatado, 'ticket_id' => $ticketId],
                null,
                null
            );
        } elseif ($tabela === 'marcas_veiculo') {
            $insert = "INSERT INTO marcas_veiculo (id, nome, nome_normalizado, status, criado_por_ticket_id)
                       VALUES (:id, :nome, :nome_norm, 'PENDENTE', :ticket_id)";
            $stmt = $this->db->prepare($insert);
            $stmt->execute([
                ':id' => $novoId,
                ':nome' => $nomeFormatado,
                ':nome_norm' => $nomeNormalizado,
                ':ticket_id' => $ticketId
            ]);
            
            $this->audit->log(
                'MARCA_PENDENTE_CRIADA',
                'SUCESSO',
                'Marca pendente criada: ' . $nomeFormatado,
                ['marca' => $nomeFormatado, 'ticket_id' => $ticketId],
                null,
                null
            );
        } elseif ($tabela === 'cores_veiculo') {
            $insert = "INSERT INTO cores_veiculo (id, nome, nome_normalizado, status, criado_por_ticket_id)
                       VALUES (:id, :nome, :nome_norm, 'PENDENTE', :ticket_id)";
            $stmt = $this->db->prepare($insert);
            $stmt->execute([
                ':id' => $novoId,
                ':nome' => $nomeFormatado,
                ':nome_norm' => $nomeNormalizado,
                ':ticket_id' => $ticketId
            ]);
            
            $this->audit->log(
                'COR_PENDENTE_CRIADA',
                'SUCESSO',
                'Cor pendente criada: ' . $nomeFormatado,
                ['cor' => $nomeFormatado, 'ticket_id' => $ticketId],
                null,
                null
            );
        }
        
        return $novoId;
    }
    
    /**
     * Busca ticket por QR Code ou placa
     */
    public function buscarTicket(?string $qrCode = null, ?string $placa = null): ?array {
        if ($qrCode) {
            return $this->buscarTicketPorQR($qrCode);
        } elseif ($placa) {
            return $this->buscarTicketPorPlaca($placa);
        }
        return null;
    }
    
    /**
     * Busca ticket por QR Code (ULID)
     */
    public function buscarTicketPorQR(string $qrCode): ?array {
        $sql = "SELECT t.*, 
                       tv.nome as tipo_veiculo_nome,
                       m.nome as marca_nome,
                       mo.nome as modelo_nome,
                       c.nome as cor_nome,
                       u.nome as operador_entrada_nome
                FROM tickets t
                LEFT JOIN tipos_veiculo tv ON t.tipo_veiculo_id = tv.id
                LEFT JOIN marcas_veiculo m ON t.marca_id = m.id
                LEFT JOIN modelos_veiculo mo ON t.modelo_id = mo.id
                LEFT JOIN cores_veiculo c ON t.cor_id = c.id
                LEFT JOIN usuarios u ON t.operador_entrada_id = u.id
                WHERE t.codigo_unico = :qr_code";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':qr_code' => $qrCode]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            $this->audit->registrar(
                'TICKET_BUSCADO',
                'SUCESSO',
                'Ticket buscado por QR Code',
                ['ticket_id' => $ticket['id'], 'codigo_unico' => $qrCode]
            );
        }
        
        return $ticket ?: null;
    }
    
    /**
     * Busca ticket por placa (último ABERTO)
     */
    public function buscarTicketPorPlaca(string $placa): ?array {
        $placaNormalizada = self::normalizarPlaca($placa);
        
        $sql = "SELECT t.*, 
                       tv.nome as tipo_veiculo_nome,
                       m.nome as marca_nome,
                       mo.nome as modelo_nome,
                       c.nome as cor_nome,
                       u.nome as operador_entrada_nome
                FROM tickets t
                LEFT JOIN tipos_veiculo tv ON t.tipo_veiculo_id = tv.id
                LEFT JOIN marcas_veiculo m ON t.marca_id = m.id
                LEFT JOIN modelos_veiculo mo ON t.modelo_id = mo.id
                LEFT JOIN cores_veiculo c ON t.cor_id = c.id
                LEFT JOIN usuarios u ON t.operador_entrada_id = u.id
                WHERE t.placa_normalizada = :placa AND t.status = 'ABERTO'
                ORDER BY t.entrada_utc DESC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':placa' => $placaNormalizada]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            $this->audit->registrar(
                'TICKET_BUSCADO',
                'SUCESSO',
                'Ticket buscado por placa',
                ['ticket_id' => $ticket['id'], 'placa' => $placaNormalizada]
            );
        }
        
        return $ticket ?: null;
    }
    
    /**
     * Registra saída e calcula cobrança
     */
    public function registrarSaida(
        string $ticketId,
        int $tempoMinutos,
        float $valorCalculado,
        string $operadorId,
        BillingEngine $billingEngine
    ): array {
        $this->db->beginTransaction();
        
        try {
            $saidaUtc = date('Y-m-d H:i:s');
            $idempotencyKey = $billingEngine->gerarIdempotencyKey();
            
            // Atualiza ticket
            $update = "UPDATE tickets SET 
                       saida_utc = :saida,
                       tempo_minutos = :tempo,
                       valor_calculado = :valor,
                       status = 'FECHADO',
                       operador_saida_id = :operador,
                       updated_at = NOW()
                       WHERE id = :id AND status = 'ABERTO'";
            
            $stmt = $this->db->prepare($update);
            $stmt->execute([
                ':saida' => $saidaUtc,
                ':tempo' => $tempoMinutos,
                ':valor' => $valorCalculado,
                ':operador' => $operadorId,
                ':id' => $ticketId
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Ticket não encontrado ou já fechado');
            }
            
            // Auditoria
            $this->audit->log(
                'SAIDA_CALCULADA',
                'SUCESSO',
                'Saída registrada - Tempo: ' . $tempoMinutos . 'min, Valor: R$ ' . number_format($valorCalculado, 2, ',', '.'),
                [
                    'ticket_id' => $ticketId,
                    'tempo_minutos' => $tempoMinutos,
                    'valor' => $valorCalculado
                ],
                $operadorId,
                PERFIL_OPERADOR
            );
            
            $this->db->commit();
            
            return [
                'ticket_id' => $ticketId,
                'saida_utc' => $saidaUtc,
                'tempo_minutos' => $tempoMinutos,
                'valor_calculado' => $valorCalculado,
                'idempotency_key' => $idempotencyKey
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Cancela ticket aberto (apenas admin/gestor)
     */
    public function cancelarTicket(string $ticketId, string $justificativa, string $userId): bool {
        $update = "UPDATE tickets SET 
                   status = 'CANCELADO',
                   observacao = :justificativa,
                   updated_at = NOW()
                   WHERE id = :id AND status = 'ABERTO'";
        
        $stmt = $this->db->prepare($update);
        $stmt->execute([
            ':justificativa' => $justificativa,
            ':id' => $ticketId
        ]);
        
        if ($stmt->rowCount() === 0) {
            return false;
        }
        
        $this->audit->log(
            'TICKET_CANCELADO',
            'SUCESSO',
            'Ticket cancelado: ' . $justificativa,
            ['ticket_id' => $ticketId],
            $userId,
            null
        );
        
        return true;
    }
    
    /**
     * Gera ULID para QR Code
     */
    private function gerarUlid(): string {
        // Timestamp em milissegundos (48 bits)
        $timestamp = substr(str_pad(dechex((int)(microtime(true) * 1000)), 12, '0', STR_PAD_LEFT), -12);
        
        // Random (80 bits)
        $random = bin2hex(random_bytes(10));
        
        return strtoupper(substr($timestamp, 0, 8) . '-' . substr($timestamp, 8) . '-' . substr($random, 0, 10));
    }
    
    /**
     * Gera UUID v7 (ordenável por timestamp)
     */
    private function gerarUuidV7(): string {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            time(),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffffffffffff)
        );
    }
    
    /**
     * Gera UUID v4
     */
    private function gerarUuidV4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Gera hash HMAC para QR Code (opcional, para validação extra)
     */
    private function gerarHashQr(string $codigoUnico): string {
        return hash_hmac('sha256', $codigoUnico, QR_SECRET_KEY);
    }
}
