<?php
/**
 * API REST - Catálogos Auxiliares (Marca/Modelo/Cor)
 * Sistema Estacionamento v3.0
 * Suporte a autocomplete com status ATIVO/PENDENTE
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth/AuthDomain.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $tabela = $_GET['tabela'] ?? null;
        $query = $_GET['q'] ?? '';
        
        if (!$tabela) {
            http_response_code(400);
            echo json_encode(['error' => 'Tabela não especificada']);
            exit;
        }
        
        // Valida tabela permitida
        $tabelasPermitidas = ['marcas_veiculo', 'modelos_veiculo', 'cores_veiculo'];
        if (!in_array($tabela, $tabelasPermitidas)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tabela inválida']);
            exit;
        }
        
        buscarCatalogo($db, $tabela, $query);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Erro interno'
    ]);
}

/**
 * Busca itens do catálogo com prioridade para ATIVO
 */
function buscarCatalogo(PDO $db, string $tabela, string $query): void {
    $queryNormalizada = strtolower(trim($query));
    
    // Campos específicos por tabela
    $camposExtra = '';
    $joinExtra = '';
    
    if ($tabela === 'modelos_veiculo') {
        $camposExtra = ', m.id as marca_id';
        $joinExtra = 'LEFT JOIN marcas_veiculo m ON modelos_veiculo.marca_id = m.id';
    }
    
    // Query principal: busca ATIVO primeiro, depois PENDENTE recentes
    // Prioriza ATIVO e ordena por nome dentro de cada grupo
    $sql = "SELECT id, nome, status {$camposExtra}
            FROM {$tabela}
            {$joinExtra}
            WHERE nome_normalizado LIKE :query
            ORDER BY 
                CASE WHEN status = 'ATIVO' THEN 0 ELSE 1 END,
                created_at DESC,
                nome
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':query' => "%{$queryNormalizada}%"]);
    
    $resultados = $stmt->fetchAll();
    
    echo json_encode($resultados);
}
