<?php
/**
 * Anonimização LGPD - Rotina automatizada para anonimizar dados de clientes
 * Conforme política: 12 meses após última interação + anonimização
 * 
 * Execução: php scripts/anonimizar-lgpd.php [--dry-run]
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use PDO;
use DateTimeImmutable;
use DateTimeZone;

class AnonimizadorLGPD
{
    private PDO $db;
    private bool $dryRun;
    private array $estatisticas = [
        'clientes_anonimizados' => 0,
        'tickets_mantidos' => 0,
        'erros' => 0
    ];
    
    public function __construct(PDO $db, bool $dryRun = false)
    {
        $this->db = $db;
        $this->dryRun = $dryRun;
    }
    
    /**
     * Executa rotina de anonimização
     * Clientes sem interação há 12+ meses são anonimizados
     */
    public function executar(): array
    {
        echo "=== ROTINA DE ANONIMIZAÇÃO LGPD ===\n";
        echo "Data execução: " . date('Y-m-d H:i:s') . "\n";
        echo "Modo: " . ($this->dryRun ? 'DRY RUN (simulação)' : 'PRODUÇÃO') . "\n\n";
        
        // Calcula data corte (12 meses atrás)
        $dataCorte = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-12 months');
        
        echo "Data de corte: " . $dataCorte->format('Y-m-d H:i:s') . " UTC\n\n";
        
        // Busca clientes elegíveis para anonimização
        $clientes = $this->buscarClientesElegiveis($dataCorte);
        
        if (empty($clientes)) {
            echo "Nenhum cliente encontrado para anonimização.\n";
            return $this->estatisticas;
        }
        
        echo "Clientes encontrados: " . count($clientes) . "\n\n";
        
        // Processa cada cliente
        foreach ($clientes as $cliente) {
            $this->anonimizarCliente($cliente);
        }
        
        // Resumo
        $this->exibirResumo();
        
        return $this->estatisticas;
    }
    
    /**
     * Busca clientes sem interação há 12+ meses e não anonimizados
     */
    private function buscarClientesElegiveis(DateTimeImmutable $dataCorte): array
    {
        $sql = "SELECT id, nome, ultima_interacao_at 
                FROM clientes 
                WHERE anonimizado_at IS NULL 
                AND (ultima_interacao_at IS NULL OR ultima_interacao_at < :corte)
                ORDER BY ultima_interacao_at ASC
                LIMIT 1000";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':corte' => $dataCorte->format('Y-m-d H:i:s.v')
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Anonimiza dados pessoais do cliente mantendo integridade referencial
     */
    private function anonimizarCliente(array $cliente): void
    {
        $clienteId = $cliente['id'];
        
        try {
            // Inicia transação
            $this->db->beginTransaction();
            
            // Gera dados anonimizados
            $nomeAnonimo = 'CLIENTE #' . substr(md5($clienteId), 0, 8);
            $documentoHash = hash('sha256', $clienteId . '_ANONIMIZADO_' . time());
            
            if ($this->dryRun) {
                echo "[DRY RUN] Cliente {$cliente['nome']} → {$nomeAnonimo}\n";
                $this->db->rollBack();
                $this->estatisticas['clientes_anonimizados']++;
                return;
            }
            
            // Atualiza cliente com dados anonimizados
            $sqlUpdate = "UPDATE clientes SET
                          nome = :nome,
                          documento_hash = :doc_hash,
                          documento_mascarado = NULL,
                          telefone_mascarado = NULL,
                          email_cript = NULL,
                          consentimento_lgpd_at = NULL,
                          anonimizado_at = NOW(3)
                          WHERE id = :id";
            
            $stmt = $this->db->prepare($sqlUpdate);
            $stmt->execute([
                ':nome' => $nomeAnonimo,
                ':doc_hash' => $documentoHash,
                ':id' => $clienteId
            ]);
            
            // Auditoria do evento
            $this->registrarAuditoria($clienteId, $cliente['nome'], $nomeAnonimo);
            
            $this->db->commit();
            
            echo "✓ Cliente '{$cliente['nome']}' anonimizedo como '{$nomeAnonimo}'\n";
            $this->estatisticas['clientes_anonimizados']++;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            echo "✗ Erro ao anonimizar cliente {$clienteId}: " . $e->getMessage() . "\n";
            $this->estatisticas['erros']++;
        }
    }
    
    /**
     * Registra evento de anonimização na auditoria WORM
     */
    private function registrarAuditoria(string $clienteId, string $nomeOriginal, string $nomeAnonimo): void
    {
        $auditId = $this->generateUuid();
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s.v');
        
        $contexto = json_encode([
            'acao' => 'ANONIMIZACAO_LGPD',
            'cliente_id' => $clienteId,
            'nome_original' => $nomeOriginal,
            'nome_anonimo' => $nomeAnonimo,
            'motivo' => '12 meses sem interação',
            'base_legal' => 'LGPD Art. 16'
        ], JSON_UNESCAPED_UNICODE);
        
        // Busca último hash para encadeamento
        $ultimoHash = $this->getUltimoHashAuditoria();
        
        $conteudoHash = $timestamp . 'CLIENTE_ANONIMIZADO' . 'sistema' . $contexto . $ultimoHash;
        $hashIntegridade = hash('sha256', $conteudoHash);
        
        $sql = "INSERT INTO auditoria (
                    audit_id, timestamp_iso, user_id, perfil, acao, 
                    resultado, detalhe_resumo, contexto_json, 
                    hash_anterior, hash_integridade
                ) VALUES (
                    :audit_id, :timestamp, NULL, 'sistema', 'CLIENTE_ANONIMIZADO',
                    'SUCESSO', :detalhe, :contexto, :hash_anterior, :hash_integridade
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':audit_id' => $auditId,
            ':timestamp' => $timestamp,
            ':detalhe' => "Anonimização automática: {$nomeOriginal} → {$nomeAnonimo}",
            ':contexto' => $contexto,
            ':hash_anterior' => $ultimoHash,
            ':hash_integridade' => $hashIntegridade
        ]);
    }
    
    /**
     * Obtém último hash da auditoria para encadeamento
     */
    private function getUltimoHashAuditoria(): ?string
    {
        $sql = "SELECT hash_integridade FROM auditoria 
                ORDER BY timestamp_iso DESC LIMIT 1";
        
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['hash_integridade'] : null;
    }
    
    /**
     * Exibe resumo da execução
     */
    private function exibirResumo(): void
    {
        echo "\n=== RESUMO ===\n";
        echo "Clientes anonimizados: {$this->estatisticas['clientes_anonimizados']}\n";
        echo "Erros: {$this->estatisticas['erros']}\n";
        
        if ($this->estatisticas['erros'] > 0) {
            echo "\n⚠️  Atenção: Houve erros durante o processo. Verifique os logs.\n";
        } else {
            echo "\n✓ Processo concluído com sucesso!\n";
        }
    }
    
    /**
     * Gera UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Método estático para execução via CLI
     */
    public static function main(): void
    {
        global $pdo;
        
        $dryRun = in_array('--dry-run', $argv, true);
        
        $anonimizador = new self($pdo, $dryRun);
        $anonimizador->executar();
    }
}

// Executa se chamado diretamente da CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0])) {
    AnonimizadorLGPD::main();
}
