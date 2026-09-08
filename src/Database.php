<?php
/**
 * Database Connection - PDO Singleton
 * Sistema Estacionamento v3.0
 */

declare(strict_types=1);

class Database {
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect(): void {
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=%s",
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', NAMES utf8mb4"
        ];
        
        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            throw new Exception("Erro de conexão com banco de dados");
        }
    }
    
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->connection->commit();
    }
    
    public function rollback(): bool {
        return $this->connection->rollBack();
    }
    
    // Previne clone
    private function __clone() {}
    
    // Previne unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
