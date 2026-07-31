<?php
/**
 * API REST - Gerenciamento de Tickets v3.0
 * Sistema Estacionamento - Saída, Cobrança, Cortesia, Cancelamento
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Headers de segurança
foreach (SECURITY_HEADERS as $header => $value) {
    header("$header: $value");
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth/AuthDomain.php';
require_once __DIR__ . '/../../src/Core/ParkingCore.php';
require_once __DIR__ . '/../../src/Billing/BillingEngine.php';
require_once __DIR__ . '/../../src/Audit/AuditGuard.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

// Método HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Lê JSON do body
        $input = json_decode(file_get_contents('php://input'), true);
        $acao = $input['acao'] ?? null;
        
        switch ($acao) {
            case 'criar_entrada':
                if (!$user || !$auth->checkPermission($user['id'], $user['perfil'], 'TICKET_CRIAR_ENTRADA')) {
                    http_response_code(403);
                    echo json_encode(['sucesso' => false, 'erro' => 'Operação não autorizada']);
                    exit;
                }
                criarEntrada($input);
                break;
                
            case 'buscar':
                buscarTicket($input);
                break;
                
            case 'fechar':
                if (!$user || !$auth->checkPermission($user['id'], $user['perfil'], 'TICKET_FECHAR')) {
                    http_response_code(403);
                    echo json_encode(['sucesso' => false, 'erro' => 'Operação não autorizada']);
                    exit;
                }
                fecharTicket($input);
                break;
                
            case 'cortesia':
                if (!$user || !$auth->checkPermission($user['id'], $user['perfil'], 'TICKET_FECHAR')) {
                    http_response_code(403);
                    echo json_encode(['sucesso' => false, 'erro' => 'Operação não autorizada']);
                    exit;
                }
                aplicarCortesia($input);
                break;
                
            case 'cancelar':
                if (!$user || !$auth->checkPermission($user['id'], $user['perfil'], 'TICKET_CANCELAR')) {
                    http_response_code(403);
                    echo json_encode(['sucesso' => false, 'erro' => 'Operação não autorizada']);
                    exit;
                }
                cancelarTicket($input);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida']);
        }
    } elseif ($method === 'GET') {
        buscarTicketGet();
    } else {
        http_response_code(405);
        echo json_encode(['erro' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'erro' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Erro interno'
    ]);
}

/**
 * Cria nova entrada
 */
function criarEntrada(array $dados): void {
    $parking = new ParkingCore();
    
    if (empty($dados['placa'])) {
        throw new Exception('Placa é obrigatória');
    }
    
    if (empty($dados['tipo_veiculo_id'])) {
        throw new Exception('Tipo de veículo é obrigatório');
    }
    
    $ticketDados = [
        'placa' => $dados['placa'],
        'tipo_veiculo_id' => $dados['tipo_veiculo_id'],
        'marca' => $dados['marca'] ?? null,
        'modelo' => $dados['modelo'] ?? null,
        'cor' => $dados['cor'] ?? null,
        'marca_id' => $dados['marca_id'] ?? null,
        'modelo_id' => $dados['modelo_id'] ?? null,
        'cor_id' => $dados['cor_id'] ?? null,
        'operador_id' => $_SESSION[SESSION_NAME]['user']['id']
    ];
    
    $ticket = $parking->criarEntrada($ticketDados);
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $ticket
    ]);
}

/**
 * Busca ticket por QR Code ou placa (POST)
 */
function buscarTicket(array $dados): void {
    $valor = $dados['valor'] ?? null;
    
    if (!$valor) {
        throw new Exception('Informe QR Code ou Placa');
    }
    
    $parking = new ParkingCore();
    
    // Tenta buscar por QR code primeiro (ULID tem 26 chars)
    if (strlen($valor) === 26) {
        $ticket = $parking->buscarTicketPorQR($valor);
    } else {
        // Busca por placa normalizada
        $placa = preg_replace('/[^A-Z0-9]/', '', strtoupper($valor));
        $ticket = $parking->buscarTicketPorPlaca($placa);
    }
    
    if (!$ticket) {
        throw new Exception('Ticket não encontrado');
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $ticket
    ]);
}

/**
 * Busca ticket por QR Code ou placa (GET - legado)
 */
function buscarTicketGet(): void {
    $qrCode = $_GET['qr'] ?? null;
    $placa = $_GET['placa'] ?? null;
    
    if (!$qrCode && !$placa) {
        http_response_code(400);
        echo json_encode(['erro' => 'Informe QR Code ou placa']);
        return;
    }
    
    $parking = new ParkingCore();
    $ticket = $parking->buscarTicket($qrCode, $placa);
    
    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['erro' => 'Ticket não encontrado']);
        return;
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $ticket
    ]);
}

/**
 * Fecha ticket com pagamento MANUAL
 */
function fecharTicket(array $dados): void {
    $db = Database::getInstance()->getConnection();
    $parking = new ParkingCore();
    $billing = new BillingEngine();
    $audit = new AuditGuard();
    
    if (empty($dados['ticket_id'])) {
        throw new Exception('ID do ticket é obrigatório');
    }
    
    $db->beginTransaction();
    
    try {
        // Busca ticket com lock
        $sql = "SELECT t.*, tv.id as tipo_veiculo_id, tv.nome as tipo_veiculo_nome
                FROM tickets t
                LEFT JOIN tipos_veiculo tv ON t.tipo_veiculo_id = tv.id
                WHERE t.id = :id
                FOR UPDATE";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $dados['ticket_id']]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            throw new Exception('Ticket não encontrado');
        }
        
        if ($ticket['status'] !== 'ABERTO') {
            throw new Exception('Ticket já está fechado');
        }
        
        // Calcula tempo em minutos
        $entrada = new DateTime($ticket['entrada_utc'], new DateTimeZone('UTC'));
        $saida = new DateTime('now', new DateTimeZone('UTC'));
        $diff = $entrada->diff($saida);
        $tempoMinutos = (int) ceil(($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s > 0 ? 1 : 0));
        
        // Busca tarifa vigente
        $sql = "SELECT * FROM tarifas 
                WHERE tipo_veiculo_id = :tipo_id 
                AND ativa = TRUE 
                AND (vigencia_fim IS NULL OR vigencia_fim >= NOW())
                ORDER BY vigencia_inicio DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':tipo_id' => $ticket['tipo_veiculo_id']]);
        $tarifa = $stmt->fetch();
        
        // Configurações padrão
        $configSql = "SELECT * FROM configuracoes_sistema LIMIT 1";
        $config = $db->query($configSql)->fetch();
        
        if (!$tarifa) {
            $modoCobranca = $config['modo_cobranca_default'];
            $valorTarifa = 0.50;
            $granularidade = $config['granularidade_min'] ?? 15;
            $tolerancia = $config['tolerancia_min'] ?? 15;
        } else {
            $modoCobranca = $tarifa['modo_cobranca'];
            $valorTarifa = (float) $tarifa['valor'];
            $granularidade = $tarifa['granularidade_min'] ?? $config['granularidade_min'] ?? 15;
            $tolerancia = $tarifa['tolerancia_min'] ?? $config['tolerancia_min'] ?? 15;
        }
        
        $regraArredondamento = $config['regra_arredondamento'] ?? 'TETO';
        
        // Calcula cobrança
        $calculo = $billing->calcular(
            $tempoMinutos,
            $modoCobranca,
            $valorTarifa,
            $granularidade,
            $tolerancia,
            $regraArredondamento
        );
        
        // Atualiza ticket
        $updateSql = "UPDATE tickets SET 
                      saida_utc = :saida,
                      tempo_minutos = :tempo,
                      valor_calculado = :valor,
                      status = 'FECHADO',
                      metodo_pagamento = 'MANUAL',
                      status_pagamento = 'PAGO',
                      operador_saida_id = :op_id,
                      updated_at = NOW()
                      WHERE id = :id";
        
        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':saida' => $saida->format('Y-m-d H:i:s'),
            ':tempo' => $tempoMinutos,
            ':valor' => $calculo['valor'],
            ':op_id' => $_SESSION[SESSION_NAME]['user']['id'],
            ':id' => $dados['ticket_id']
        ]);
        
        // Auditoria
        $audit->registrar(
            'SAIDA_FECHADA',
            'SUCESSO',
            "Ticket {$ticket['codigo_unico']} fechado - Valor: R$ " . number_format($calculo['valor'], 2, ',', '.'),
            [
                'ticket_id' => $ticket['id'],
                'placa' => $ticket['placa_normalizada'],
                'tempo_minutos' => $tempoMinutos,
                'valor' => $calculo['valor'],
                'metodo' => 'MANUAL'
            ]
        );
        
        $db->commit();
        
        echo json_encode([
            'sucesso' => true,
            'dados' => [
                'ticket_id' => $ticket['id'],
                'placa' => $ticket['placa_normalizada'],
                'tempo_minutos' => $tempoMinutos,
                'valor_pago' => $calculo['valor'],
                'metodo_pagamento' => 'MANUAL'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Aplica cortesia (valor zero)
 */
function aplicarCortesia(array $dados): void {
    $db = Database::getInstance()->getConnection();
    $audit = new AuditGuard();
    
    if (empty($dados['ticket_id'])) {
        throw new Exception('ID do ticket é obrigatório');
    }
    
    if (empty($dados['motivo'])) {
        throw new Exception('Motivo da cortesia é obrigatório');
    }
    
    $db->beginTransaction();
    
    try {
        // Busca ticket
        $sql = "SELECT * FROM tickets WHERE id = :id FOR UPDATE";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $dados['ticket_id']]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            throw new Exception('Ticket não encontrado');
        }
        
        if ($ticket['status'] !== 'ABERTO') {
            throw new Exception('Ticket já está fechado');
        }
        
        // Calcula tempo
        $entrada = new DateTime($ticket['entrada_utc'], new DateTimeZone('UTC'));
        $saida = new DateTime('now', new DateTimeZone('UTC'));
        $diff = $entrada->diff($saida);
        $tempoMinutos = (int) ceil(($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s > 0 ? 1 : 0));
        
        // Atualiza como cortesia
        $updateSql = "UPDATE tickets SET 
                      saida_utc = :saida,
                      tempo_minutos = :tempo,
                      valor_calculado = 0.00,
                      status = 'FECHADO',
                      metodo_pagamento = 'MANUAL',
                      status_pagamento = 'CORTESIA',
                      observacao = :obs,
                      operador_saida_id = :op_id,
                      updated_at = NOW()
                      WHERE id = :id";
        
        $observacao = "CORTESIA - Motivo: {$dados['motivo']}" . (isset($dados['observacao']) ? " - {$dados['observacao']}" : '');
        
        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':saida' => $saida->format('Y-m-d H:i:s'),
            ':tempo' => $tempoMinutos,
            ':obs' => $observacao,
            ':op_id' => $_SESSION[SESSION_NAME]['user']['id'],
            ':id' => $dados['ticket_id']
        ]);
        
        // Auditoria
        $audit->registrar(
            'SAIDA_FECHADA',
            'SUCESSO',
            "Cortesia aplicada - Ticket {$ticket['codigo_unico']}",
            [
                'ticket_id' => $ticket['id'],
                'placa' => $ticket['placa_normalizada'],
                'motivo' => $dados['motivo'],
                'observacao' => $dados['observacao'] ?? null
            ]
        );
        
        $db->commit();
        
        echo json_encode([
            'sucesso' => true,
            'dados' => [
                'ticket_id' => $ticket['id'],
                'placa' => $ticket['placa_normalizada'],
                'status' => 'CORTESIA_APLICADA'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Cancela ticket aberto
 */
function cancelarTicket(array $dados): void {
    $db = Database::getInstance()->getConnection();
    $audit = new AuditGuard();
    
    if (empty($dados['ticket_id'])) {
        throw new Exception('ID do ticket é obrigatório');
    }
    
    if (empty($dados['justificativa']) || strlen($dados['justificativa']) < 20) {
        throw new Exception('Justificativa deve ter pelo menos 20 caracteres');
    }
    
    $db->beginTransaction();
    
    try {
        // Busca ticket
        $sql = "SELECT * FROM tickets WHERE id = :id FOR UPDATE";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $dados['ticket_id']]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            throw new Exception('Ticket não encontrado');
        }
        
        if ($ticket['status'] !== 'ABERTO') {
            throw new Exception('Ticket já está fechado');
        }
        
        // Atualiza como cancelado
        $updateSql = "UPDATE tickets SET 
                      status = 'CANCELADO',
                      observacao = :obs,
                      operador_saida_id = :op_id,
                      updated_at = NOW()
                      WHERE id = :id";
        
        $stmt = $db->prepare($updateSql);
        $stmt->execute([
            ':obs' => "CANCELAMENTO: {$dados['justificativa']}",
            ':op_id' => $_SESSION[SESSION_NAME]['user']['id'],
            ':id' => $dados['ticket_id']
        ]);
        
        // Auditoria
        $audit->registrar(
            'TICKET_CANCELADO',
            'SUCESSO',
            "Ticket {$ticket['codigo_unico']} cancelado",
            [
                'ticket_id' => $ticket['id'],
                'placa' => $ticket['placa_normalizada'],
                'justificativa' => $dados['justificativa']
            ]
        );
        
        $db->commit();
        
        echo json_encode([
            'sucesso' => true,
            'dados' => [
                'ticket_id' => $ticket['id'],
                'status' => 'CANCELADO'
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
