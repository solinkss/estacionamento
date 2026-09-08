<?php
/**
 * API REST - Health Check e Endpoints Básicos
 * Sistema Estacionamento v3.0
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Headers de segurança
foreach (SECURITY_HEADERS as $header => $value) {
    header("$header: $value");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';

// Roteamento básico
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('/\/api\/v1\//', '', $path);

try {
    switch ($path) {
        case 'health':
            healthCheck();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint não encontrado']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Operação não autorizada'
    ]);
}

/**
 * Health check do sistema
 */
function healthCheck(): void {
    $db = Database::getInstance()->getConnection();
    
    // Verifica conexão DB
    try {
        $stmt = $db->query('SELECT 1');
        $dbStatus = 'connected';
    } catch (PDOException $e) {
        $dbStatus = 'disconnected';
    }
    
    $response = [
        'status' => 'healthy',
        'version' => APP_VERSION,
        'timestamp' => date('c'),
        'database' => $dbStatus,
        'environment' => ENVIRONMENT
    ];
    
    if ($dbStatus !== 'connected') {
        $response['status'] = 'unhealthy';
        http_response_code(503);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
