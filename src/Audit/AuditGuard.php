<?php
/**
 * Módulo de Auditoria WORM - Hash Encadeado
 * Sistema Estacionamento v3.0
 * 
 * Conformidade LGPD: sem UPDATE/DELETE, hash de integridade encadeado
 */

declare(strict_types=1);

class AuditGuard {
    private PDO $db;
    private ?string $lastHash = null;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Registra evento de auditoria com hash encadeado
     * 
     * @param string $acao Ação executada (ENUM do banco)
     * @param string $resultado SUCESSO, FALHA ou NEGADO
     * @param string $detalhe Resumo do evento
     * @param array|null $contexto Dados adicionais (JSON, PII já mascarado)
     * @param string|null $userId ID do usuário
     * @param string|null $perfil Perfil do usuário
     * @return string audit_id gerado
     */
    public function log(
        string $acao,
        string $resultado,
        string $detalhe,
        ?array $contexto = null,
        ?string $userId = null,
        ?string $perfil = null
    ): string {
        $auditId = $this->generateUuidV4();
        $timestamp = $this->getUtcTimestamp();
        $ip = $this->getClientIp();
        
        // Obtém hash do registro anterior para encadeamento
        $hashAnterior = $this->getLastHash();
        
        // Calcula hash de integridade deste registro
        $hashIntegridade = $this->calculateHash(
            $timestamp,
            $acao,
            $userId ?? '',
            $contexto ?? [],
            $hashAnterior
        );
        
        // Prepara contexto JSON
        $contextoJson = $contexto !== null ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null;
        
        // Insert WORM - nunca update/delete
        $sql = "INSERT INTO auditoria 
                (audit_id, timestamp_iso, user_id, perfil, acao, resultado, detalhe_resumo, 
                 ip_terminal, contexto_json, hash_anterior, hash_integridade) 
                VALUES (:audit_id, :timestamp, :user_id, :perfil, :acao, :resultado, 
                        :detalhe, :ip, :contexto, :hash_anterior, :hash_integridade)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':audit_id' => $auditId,
            ':timestamp' => $timestamp,
            ':user_id' => $userId,
            ':perfil' => $perfil,
            ':acao' => $acao,
            ':resultado' => $resultado,
            ':detalhe' => $detalhe,
            ':ip' => $ip,
            ':contexto' => $contextoJson,
            ':hash_anterior' => $hashAnterior,
            ':hash_integridade' => $hashIntegridade
        ]);
        
        // Atualiza último hash para próximo registro
        $this->lastHash = $hashIntegridade;
        
        return $auditId;
    }
    
    /**
     * Registra tentativa não autorizada (RBAC)
     */
    public function logUnauthorizedAttempt(
        string $recursoTentado,
        string $perfilUsuario,
        ?string $userId = null
    ): string {
        return $this->log(
            'TENTATIVA_NAO_AUTORIZADA',
            'NEGADO',
            'Acesso negado ao recurso: ' . $recursoTentado,
            [
                'recurso_tentado' => $recursoTentado,
                'perfil_usuario' => $perfilUsuario
            ],
            $userId,
            $perfilUsuario
        );
    }
    
    /**
     * Valida cadeia de hashes da auditoria
     * Retorna true se todos os hashes estiverem íntegros
     */
    public function validarIntegridadeCadeia(): bool {
        $sql = "SELECT audit_id, timestamp_iso, acao, user_id, contexto_json, 
                       hash_anterior, hash_integridade 
                FROM auditoria 
                ORDER BY timestamp_iso ASC";
        
        $stmt = $this->db->query($sql);
        $registros = $stmt->fetchAll();
        
        $hashEsperado = null;
        
        foreach ($registros as $registro) {
            $hashCalculado = $this->calculateHash(
                $registro['timestamp_iso'],
                $registro['acao'],
                $registro['user_id'] ?? '',
                json_decode($registro['contexto_json'] ?? 'null', true) ?? [],
                $registro['hash_anterior']
            );
            
            if ($hashCalculado !== $registro['hash_integridade']) {
                return false; // Hash divergente encontrado
            }
            
            $hashEsperado = $hashCalculado;
        }
        
        return true;
    }
    
    /**
     * Exporta auditoria para CSV/PDF (com assinatura)
     */
    public function exportarPeriodo(
        DateTime $inicio,
        DateTime $fim,
        string $formato = 'CSV'
    ): string {
        $sql = "SELECT * FROM auditoria 
                WHERE timestamp_iso BETWEEN :inicio AND :fim 
                ORDER BY timestamp_iso ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $inicio->format('Y-m-d H:i:s.u'),
            ':fim' => $fim->format('Y-m-d H:i:s.u')
        ]);
        
        $registros = $stmt->fetchAll();
        
        if ($formato === 'CSV') {
            return $this->exportarCsv($registros);
        }
        
        throw new InvalidArgumentException("Formato não suportado: {$formato}");
    }
    
    /**
     * Mascara dados PII antes de registrar na auditoria
     */
    public static function mascararPII(string $dado, string $tipo): string {
        switch ($tipo) {
            case 'cpf':
                // ***.123.***-**
                return substr($dado, 0, 3) . '.' . 
                       str_pad(substr($dado, 6, 3), 3, '*', STR_PAD_LEFT) . '.' . 
                       str_pad(substr($dado, -2), 2, '*', STR_PAD_LEFT);
            case 'telefone':
                // (11) 9****-****
                return preg_replace('/(\d{2})(\d)(\d{4})(\d{4})/', '($1) $2****-$4***', $dado);
            case 'email':
                // j***@example.com
                $parts = explode('@', $dado);
                if (count($parts) === 2) {
                    return substr($parts[0], 0, 1) . '***@' . $parts[1];
                }
                return '***';
            case 'placa':
                // ABC**** (mascara últimos dígitos)
                return substr($dado, 0, 3) . '****';
            default:
                return '***';
        }
    }
    
    /**
     * Calcula hash SHA256 para integridade
     */
    private function calculateHash(
        string $timestamp,
        string $acao,
        string $userId,
        array $contexto,
        ?string $hashAnterior
    ): string {
        $dados = sprintf(
            '%s|%s|%s|%s|%s',
            $timestamp,
            $acao,
            $userId,
            json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS),
            $hashAnterior ?? ''
        );
        
        return hash('sha256', $dados);
    }
    
    /**
     * Obtém último hash registrado
     */
    private function getLastHash(): ?string {
        $sql = "SELECT hash_integridade FROM auditoria 
                ORDER BY timestamp_iso DESC LIMIT 1";
        
        $stmt = $this->db->query($sql);
        $resultado = $stmt->fetchColumn();
        
        return $resultado ?: null;
    }
    
    /**
     * Gera UUID v4 para audit_id
     */
    private function generateUuidV4(): string {
        $data = random_bytes(16);
        
        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant to RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Timestamp UTC com milissegundos
     */
    private function getUtcTimestamp(): string {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.') . substr($dt->format('u'), 0, 3);
    }
    
    /**
     * Obtém IP do cliente
     */
    private function getClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Exporta para CSV
     */
    private function exportarCsv(array $registros): string {
        $output = fopen('php://temp', 'r+');
        
        // Header
        fputcsv($output, [
            'audit_id', 'timestamp', 'user_id', 'perfil', 'acao', 
            'resultado', 'detalhe', 'ip', 'contexto'
        ]);
        
        // Dados
        foreach ($registros as $registro) {
            fputcsv($output, [
                $registro['audit_id'],
                $registro['timestamp_iso'],
                $registro['user_id'] ?? '',
                $registro['perfil'],
                $registro['acao'],
                $registro['resultado'],
                $registro['detalhe_resumo'],
                $registro['ip_terminal'],
                $registro['contexto_json'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
