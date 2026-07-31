<?php
/**
 * Configuração do Sistema de Estacionamento v3.0
 * Ambiente: localhost Windows Apache 2.4.58 + PHP 8.1.25 + MariaDB 10.4.32
 */

declare(strict_types=1);

// Constantes de ambiente
define('APP_VERSION', '3.0.0');
define('APP_NAME', 'Estacionamento v3.0');
define('ENVIRONMENT', 'development'); // development | production

// Database
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'estacionamento_v3');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Timezone
define('TIMEZONE_APP', 'America/Sao_Paulo');
define('TIMEZONE_DB', 'UTC');

// Segurança
define('SESSION_NAME', 'EST_SESSION');
define('SESSION_LIFETIME', 900); // 15 minutos inatividade
define('SESSION_ABSOLUTE', 28800); // 8 horas absoluto
define('PASSWORD_MIN_LENGTH', 12);
define('PASSWORD_HISTORY_SIZE', 3);
define('LOGIN_MAX_ATTEMPTS_SOFT', 5); // 5 falhas → bloqueio 15min
define('LOGIN_MAX_ATTEMPTS_HARD', 10); // 10 falhas → bloqueio até gestor liberar
define('LOGIN_SOFT_LOCK_MINUTES', 15);

// Rate Limiting
define('RATE_LIMIT_SEARCHES', 10); // buscas placa por 2min
define('RATE_LIMIT_API', 20); // req API por minuto por IP
define('RATE_LIMIT_LOGIN', 5); // logins por 5min por conta

// QR Code
define('QR_SECRET_KEY', 'CHANGE_ME_IN_PRODUCTION_32CHARS!!'); // HMAC secret
define('QR_CODE_TYPE', 'ULID'); // ULID ou UUIDv7

// LGPD e Retenção
define('AUDIT_RETENTION_YEARS', 5); // 5 anos auditoria (fiscal)
define('CLIENT_RETENTION_MONTHS', 12); // 12 meses após última interação

// Paths
define('BASE_PATH', dirname(__DIR__));
define('SRC_PATH', BASE_PATH . '/src');
define('API_PATH', BASE_PATH . '/api');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('LOGS_PATH', BASE_PATH . '/logs');
define('TEMP_PATH', BASE_PATH . '/temp');

// Logs
define('LOG_SECURITY', LOGS_PATH . '/security.log');
define('LOG_AUDIT', LOGS_PATH . '/audit.log');
define('LOG_ERROR', LOGS_PATH . '/error.log');

// Headers de segurança HTTP
define('SECURITY_HEADERS', [
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'",
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
]);

// Perfis RBAC
define('PERFIL_ADMIN', 'admin');
define('PERFIL_GESTOR', 'gestor');
define('PERFIL_OPERADOR', 'operador');

// Status
define('STATUS_ATIVO', 'ATIVO');
define('STATUS_PENDENTE', 'PENDENTE');
define('STATUS_INATIVO', 'INATIVO');

define('TICKET_ABERTO', 'ABERTO');
define('TICKET_FECHADO', 'FECHADO');
define('TICKET_CANCELADO', 'CANCELADO');

// Modos de cobrança
define('MODO_POR_MINUTO', 'POR_MINUTO');
define('MODO_HORA_FRACIONADA', 'HORA_FRACIONADA');
define('MODO_HORA_CHEIA', 'HORA_CHEIA');
define('MODO_TARIFA_FIXA', 'TARIFA_FIXA');
define('MODO_DIARIA', 'DIARIA');

// Regras de arredondamento
define('REGRA_TETO', 'TETO');
define('REGRA_PISO', 'PISO');
define('REGRA_MATEMATICO', 'MATEMATICO');

date_default_timezone_set(TIMEZONE_APP);

// Criar diretórios se não existirem
$dirs = [LOGS_PATH, TEMP_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
