<?php
/**
 * Rate Limiter - Controle de requisições por IP/Usuário
 * Implementa rate limiting multi-nível conforme especificação v3.0
 * 
 * Limites configurados:
 * - 10 buscas de placa por 2 minutos por terminal
 * - 20 requisições API por minuto por IP
 * - 5 tentativas de login por 5 minutos por conta
 */

declare(strict_types=1);

namespace App\Security;

use PDO;
use InvalidArgumentException;

class RateLimiter
{
    private PDO $db;
    
    // Limites configuráveis
    private const LIMIT_BUSCAS_PLACA = 10;
    private const WINDOW_BUSCAS_PLACA = 120; // segundos (2 minutos)
    
    private const LIMIT_REQ_API = 20;
    private const WINDOW_REQ_API = 60; // segundos (1 minuto)
    
    private const LIMIT_LOGIN = 5;
    private const WINDOW_LOGIN = 300; // segundos (5 minutos)
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->initializeTable();
    }
    
    /**
     * Inicializa tabela de rate limiting se não existir
     */
    private function initializeTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id CHAR(36) PRIMARY KEY,
            identifier VARCHAR(128) NOT NULL COMMENT 'IP ou user_id ou placa',
            action_type ENUM('BUSCA_PLACA', 'REQ_API', 'LOGIN') NOT NULL,
            request_count INT NOT NULL DEFAULT 1,
            first_request_at DATETIME(3) NOT NULL,
            last_request_at DATETIME(3) NOT NULL,
            blocked_until DATETIME(3) NULL,
            created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            UNIQUE KEY ux_identifier_action (identifier, action_type),
            INDEX ix_blocked_until (blocked_until),
            INDEX ix_last_request (last_request_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
    
    /**
     * Verifica se a ação está permitida dentro dos limites
     * 
     * @param string $identifier Identificador único (IP, user_id, placa)
     * @param string $actionType Tipo de ação (BUSCA_PLACA, REQ_API, LOGIN)
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int|null]
     * @throws InvalidArgumentException Se tipo de ação inválido
     */
    public function checkLimit(string $identifier, string $actionType): array
    {
        $this->validateActionType($actionType);
        
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $window = $this->getWindowSeconds($actionType);
        $limit = $this->getLimit($actionType);
        
        // Verifica se está bloqueado
        $blockStatus = $this->checkBlockStatus($identifier, $actionType);
        if ($blockStatus['blocked']) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $blockStatus['seconds_remaining'],
                'reason' => 'BLOCKED'
            ];
        }
        
        // Limpa registros antigos fora da janela
        $this->cleanupOldRecords($identifier, $actionType, $window);
        
        // Busca registro atual
        $record = $this->getRecord($identifier, $actionType);
        
        if (!$record) {
            // Primeiro registro
            $this->createRecord($identifier, $actionType, $now);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'retry_after' => null
            ];
        }
        
        // Verifica se ainda está dentro da janela
        $firstRequest = new \DateTimeImmutable($record['first_request_at']);
        $elapsed = $now->getTimestamp() - $firstRequest->getTimestamp();
        
        if ($elapsed > $window) {
            // Janela expirada, reseta contagem
            $this->resetRecord($identifier, $actionType, $now);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'retry_after' => null
            ];
        }
        
        // Ainda dentro da janela
        $currentCount = (int)$record['request_count'];
        
        if ($currentCount >= $limit) {
            // Limite excedido - aplica bloqueio
            $this->applyBlock($identifier, $actionType, $now, $window);
            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => $window - $elapsed,
                'reason' => 'LIMIT_EXCEEDED'
            ];
        }
        
        // Incrementa contador
        $this->incrementRequest($identifier, $actionType, $now);
        
        return [
            'allowed' => true,
            'remaining' => $limit - $currentCount - 1,
            'retry_after' => null
        ];
    }
    
    /**
     * Aplica bloqueio temporário após exceder limite
     */
    private function applyBlock(string $identifier, string $actionType, \DateTimeImmutable $now, int $window): void
    {
        $blockedUntil = $now->modify("+{$window} seconds");
        
        $sql = "UPDATE rate_limits 
                SET blocked_until = :blocked_until, 
                    request_count = request_count + 1,
                    last_request_at = :last_request
                WHERE identifier = :identifier AND action_type = :action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':blocked_until' => $blockedUntil->format('Y-m-d H:i:s.v'),
            ':last_request' => $now->format('Y-m-d H:i:s.v'),
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
    }
    
    /**
     * Remove bloqueio manualmente (ex: após desbloqueio por admin)
     */
    public function clearBlock(string $identifier, string $actionType): bool
    {
        $sql = "UPDATE rate_limits SET blocked_until = NULL WHERE identifier = :identifier AND action_type = :action_type";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
    }
    
    /**
     * Verifica status de bloqueio
     */
    private function checkBlockStatus(string $identifier, string $actionType): array
    {
        $sql = "SELECT blocked_until FROM rate_limits 
                WHERE identifier = :identifier AND action_type = :action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result || !$result['blocked_until']) {
            return ['blocked' => false, 'seconds_remaining' => 0];
        }
        
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $blockedUntil = new \DateTimeImmutable($result['blocked_until']);
        
        if ($now >= $blockedUntil) {
            // Bloqueio expirado, limpa
            $this->clearBlock($identifier, $actionType);
            return ['blocked' => false, 'seconds_remaining' => 0];
        }
        
        return [
            'blocked' => true,
            'seconds_remaining' => $blockedUntil->getTimestamp() - $now->getTimestamp()
        ];
    }
    
    /**
     * Limpa registros antigos fora da janela de tempo
     */
    private function cleanupOldRecords(string $identifier, string $actionType, int $windowSeconds): void
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$windowSeconds} seconds");
        
        $sql = "DELETE FROM rate_limits 
                WHERE identifier = :identifier 
                AND action_type = :action_type 
                AND last_request_at < :cutoff";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType,
            ':cutoff' => $cutoff->format('Y-m-d H:i:s.v')
        ]);
    }
    
    /**
     * Busca registro existente
     */
    private function getRecord(string $identifier, string $actionType): ?array
    {
        $sql = "SELECT * FROM rate_limits 
                WHERE identifier = :identifier AND action_type = :action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Cria novo registro de rate limit
     */
    private function createRecord(string $identifier, string $actionType, \DateTimeImmutable $now): void
    {
        $sql = "INSERT INTO rate_limits (id, identifier, action_type, request_count, first_request_at, last_request_at)
                VALUES (:id, :identifier, :action_type, 1, :first, :last)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $this->generateUuid(),
            ':identifier' => $identifier,
            ':action_type' => $actionType,
            ':first' => $now->format('Y-m-d H:i:s.v'),
            ':last' => $now->format('Y-m-d H:i:s.v')
        ]);
    }
    
    /**
     * Reseta registro para nova janela
     */
    private function resetRecord(string $identifier, string $actionType, \DateTimeImmutable $now): void
    {
        $sql = "UPDATE rate_limits 
                SET request_count = 1, 
                    first_request_at = :first, 
                    last_request_at = :last,
                    blocked_until = NULL
                WHERE identifier = :identifier AND action_type = :action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':first' => $now->format('Y-m-d H:i:s.v'),
            ':last' => $now->format('Y-m-d H:i:s.v'),
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
    }
    
    /**
     * Incrementa contador de requisições
     */
    private function incrementRequest(string $identifier, string $actionType, \DateTimeImmutable $now): void
    {
        $sql = "UPDATE rate_limits 
                SET request_count = request_count + 1, 
                    last_request_at = :last
                WHERE identifier = :identifier AND action_type = :action_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':last' => $now->format('Y-m-d H:i:s.v'),
            ':identifier' => $identifier,
            ':action_type' => $actionType
        ]);
    }
    
    /**
     * Valida tipo de ação
     */
    private function validateActionType(string $actionType): void
    {
        $validTypes = ['BUSCA_PLACA', 'REQ_API', 'LOGIN'];
        if (!in_array($actionType, $validTypes, true)) {
            throw new InvalidArgumentException("Tipo de ação inválido: {$actionType}");
        }
    }
    
    /**
     * Retorna janela em segundos por tipo de ação
     */
    private function getWindowSeconds(string $actionType): int
    {
        return match($actionType) {
            'BUSCA_PLACA' => self::WINDOW_BUSCAS_PLACA,
            'REQ_API' => self::WINDOW_REQ_API,
            'LOGIN' => self::WINDOW_LOGIN,
            default => throw new InvalidArgumentException("Tipo de ação inválido")
        };
    }
    
    /**
     * Retorna limite por tipo de ação
     */
    private function getLimit(string $actionType): int
    {
        return match($actionType) {
            'BUSCA_PLACA' => self::LIMIT_BUSCAS_PLACA,
            'REQ_API' => self::LIMIT_REQ_API,
            'LOGIN' => self::LIMIT_LOGIN,
            default => throw new InvalidArgumentException("Tipo de ação inválido")
        };
    }
    
    /**
     * Gera UUID v4 para IDs
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Retorna estatísticas de rate limiting para dashboard
     */
    public function getStatistics(string $actionType, int $hours = 24): array
    {
        $cutoff = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$hours} hours");
        
        $sql = "SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN blocked_until IS NOT NULL THEN 1 END) as blocked_count,
                    AVG(request_count) as avg_requests_per_identifier,
                    MAX(request_count) as max_requests
                FROM rate_limits 
                WHERE action_type = :action_type 
                AND created_at >= :cutoff";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':action_type' => $actionType,
            ':cutoff' => $cutoff->format('Y-m-d H:i:s.v')
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_requests' => 0,
            'blocked_count' => 0,
            'avg_requests_per_identifier' => 0,
            'max_requests' => 0
        ];
    }
}
