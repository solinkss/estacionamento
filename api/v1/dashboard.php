<?php
/**
 * API REST - Dashboard e Estatísticas v3.0
 * Sistema Estacionamento - Métricas, Gráficos, Ranking
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

foreach (SECURITY_HEADERS as $header => $value) {
    header("$header: $value");
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth/AuthDomain.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

if (!$user) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$tipo = $_GET['tipo'] ?? null;

try {
    switch ($tipo) {
        case 'estatisticas':
            echo json_encode(getEstatisticas());
            break;
        case 'movimento-hora':
            echo json_encode(getMovimentoHora());
            break;
        case 'tipo-veiculo':
            echo json_encode(getTipoVeiculo());
            break;
        case 'marcas-ranking':
            echo json_encode(getMarcasRanking());
            break;
        case 'ultimos-tickets':
            echo json_encode(getUltimosTickets());
            break;
        default:
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Tipo inválido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Erro interno'
    ]);
}

/**
 * Estatísticas gerais do dia
 */
function getEstatisticas(): array {
    $pdo = Database::getInstance()->getConnection();
    $hoje = date('Y-m-d') . ' 00:00:00';
    $amanha = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
    
    // Veículos no pátio (ABERTOS)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tickets WHERE status = 'ABERTO'");
    $stmt->execute();
    $veiculosPatio = (int)$stmt->fetch()['total'];
    
    // Receita hoje (FECHADOS hoje)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(valor_calculado), 0) as total 
        FROM tickets 
        WHERE status = 'FECHADO' 
        AND saida_utc >= ? 
        AND saida_utc < ?
    ");
    $stmt->execute([$hoje, $amanha]);
    $receitaHoje = (float)$stmt->fetch()['total'];
    
    // Tickets fechados hoje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM tickets 
        WHERE status = 'FECHADO' 
        AND saida_utc >= ? 
        AND saida_utc < ?
    ");
    $stmt->execute([$hoje, $amanha]);
    $ticketsFechados = (int)$stmt->fetch()['total'];
    
    // Ticket médio
    $ticketMedio = $ticketsFechados > 0 ? $receitaHoje / $ticketsFechados : 0;
    
    return [
        'sucesso' => true,
        'dados' => [
            'veiculos_patio' => $veiculosPatio,
            'receita_hoje' => $receitaHoje,
            'tickets_fechados_hoje' => $ticketsFechados,
            'ticket_medio' => $ticketMedio
        ]
    ];
}

/**
 * Movimento por hora (últimas 24h)
 */
function getMovimentoHora(): array {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(entrada_utc) as hora,
            COUNT(*) as entradas
        FROM tickets
        WHERE entrada_utc >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY HOUR(entrada_utc)
        ORDER BY hora
    ");
    $stmt->execute();
    $entradas = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(saida_utc) as hora,
            COUNT(*) as saidas
        FROM tickets
        WHERE saida_utc >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND saida_utc IS NOT NULL
        GROUP BY HOUR(saida_utc)
        ORDER BY hora
    ");
    $stmt->execute();
    $saidas = $stmt->fetchAll();
    
    // Consolidar horas (0-23)
    $resultado = [];
    for ($i = 0; $i < 24; $i++) {
        $resultado[$i] = ['hora' => $i, 'entradas' => 0, 'saidas' => 0];
    }
    
    foreach ($entradas as $row) {
        $hora = (int)$row['hora'];
        if (isset($resultado[$hora])) {
            $resultado[$hora]['entradas'] = (int)$row['entradas'];
        }
    }
    
    foreach ($saidas as $row) {
        $hora = (int)$row['hora'];
        if (isset($resultado[$hora])) {
            $resultado[$hora]['saidas'] = (int)$row['saidas'];
        }
    }
    
    return [
        'sucesso' => true,
        'dados' => array_values($resultado)
    ];
}

/**
 * Quantidade por tipo de veículo
 */
function getTipoVeiculo(): array {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            tv.nome as tipo,
            COUNT(t.id) as quantidade
        FROM tickets t
        JOIN tipos_veiculo tv ON t.tipo_veiculo_id = tv.id
        WHERE t.entrada_utc >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY tv.id, tv.nome
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    
    return [
        'sucesso' => true,
        'dados' => $stmt->fetchAll()
    ];
}

/**
 * Ranking de marcas (top 10)
 */
function getMarcasRanking(): array {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            mv.nome as marca,
            COUNT(t.id) as quantidade
        FROM tickets t
        JOIN marcas_veiculo mv ON t.marca_id = mv.id
        WHERE t.entrada_utc >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY mv.id, mv.nome
        ORDER BY quantidade DESC
        LIMIT 10
    ");
    $stmt->execute();
    
    return [
        'sucesso' => true,
        'dados' => $stmt->fetchAll()
    ];
}

/**
 * Últimos tickets (20 mais recentes)
 */
function getUltimosTickets(): array {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            t.codigo_unico,
            t.placa_normalizada,
            t.status,
            t.entrada_utc,
            tv.nome as tipo_veiculo,
            mv.nome as marca,
            modv.nome as modelo
        FROM tickets t
        LEFT JOIN tipos_veiculo tv ON t.tipo_veiculo_id = tv.id
        LEFT JOIN marcas_veiculo mv ON t.marca_id = mv.id
        LEFT JOIN modelos_veiculo modv ON t.modelo_id = modv.id
        ORDER BY t.entrada_utc DESC
        LIMIT 20
    ");
    $stmt->execute();
    
    return [
        'sucesso' => true,
        'dados' => $stmt->fetchAll()
    ];
}
