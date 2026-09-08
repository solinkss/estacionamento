<?php
/**
 * API REST - Administração de Catálogos (Marca/Modelo/Cor)
 * Sistema Estacionamento v3.0
 * Aprovação e Mesclagem de Cadastros Pendentes
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth/AuthDomain.php';
require_once __DIR__ . '/../../src/Audit/AuditGuard.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

if (!$user || !in_array($user['perfil'], ['admin', 'gestor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Operação não autorizada']);
    exit;
}

$db = Database::getInstance()->getConnection();
$audit = new AuditGuard();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        listarCadastros();
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? null;
        
        switch ($action) {
            case 'aprovar':
                aprovarCadastro($input, $db, $audit, $user);
                break;
                
            case 'mesclar':
                mesclarCadastro($input, $db, $audit, $user);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Ação inválida']);
        }
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
 * Lista cadastros com filtros
 */
function listarCadastros(): void {
    global $db;
    
    $tabela = $_GET['tabela'] ?? null;
    $status = $_GET['status'] ?? 'PENDENTE';
    $incluirAtivos = $_GET['incluir_ativos'] ?? false;
    
    if (!$tabela) {
        http_response_code(400);
        echo json_encode(['error' => 'Tabela não especificada']);
        return;
    }
    
    $tabelasPermitidas = ['marcas_veiculo', 'modelos_veiculo', 'cores_veiculo'];
    if (!in_array($tabela, $tabelasPermitidas)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tabela inválida']);
        return;
    }
    
    // Campos extras para modelos
    $camposExtra = '';
    $joinExtra = '';
    
    if ($tabela === 'modelos_veiculo') {
        $camposExtra = ', m.nome as marca_nome';
        $joinExtra = 'LEFT JOIN marcas_veiculo m ON modelos_veiculo.marca_id = m.id';
    }
    
    // Query
    $whereStatus = $incluirAtivos ? "WHERE status IN ('PENDENTE', 'ATIVO')" : "WHERE status = :status";
    
    $sql = "SELECT id, nome, status, criado_por_ticket_id, created_at {$camposExtra}
            FROM {$tabela}
            {$joinExtra}
            {$whereStatus}
            ORDER BY 
                CASE WHEN status = 'PENDENTE' THEN 0 ELSE 1 END,
                created_at DESC";
    
    $stmt = $db->prepare($sql);
    
    if (!$incluirAtivos) {
        $stmt->execute([':status' => $status]);
    } else {
        $stmt->execute();
    }
    
    $resultados = $stmt->fetchAll();
    
    echo json_encode($resultados);
}

/**
 * Aprova cadastro PENDENTE → ATIVO
 */
function aprovarCadastro(array $dados, PDO $db, AuditGuard $audit, array $user): void {
    $tabela = $dados['tabela'] ?? null;
    $id = $dados['id'] ?? null;
    
    if (!$tabela || !$id) {
        throw new Exception('Dados incompletos');
    }
    
    $tabelasPermitidas = ['marcas_veiculo', 'modelos_veiculo', 'cores_veiculo'];
    if (!in_array($tabela, $tabelasPermitidas)) {
        throw new Exception('Tabela inválida');
    }
    
    // Atualiza status
    $update = "UPDATE {$tabela} SET status = 'ATIVO' WHERE id = :id AND status = 'PENDENTE'";
    $stmt = $db->prepare($update);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Cadastro não encontrado ou já aprovado');
    }
    
    // Auditoria
    $nomeTabela = ucfirst(str_replace('_veiculo', '', $tabela));
    $audit->log(
        'CADASTRO_APROVADO',
        'SUCESSO',
        "{$nomeTabela} aprovada",
        ['tabela' => $tabela, 'id' => $id],
        $user['id'],
        $user['perfil']
    );
    
    echo json_encode(['success' => true, 'message' => 'Cadastro aprovado']);
}

/**
 * Mescla cadastro PENDENTE em ATIVO existente
 * Atualiza todos os tickets vinculados
 */
function mesclarCadastro(array $dados, PDO $db, AuditGuard $audit, array $user): void {
    $tabela = $dados['tabela'] ?? null;
    $idPendente = $dados['id_pendente'] ?? null;
    $idAtivo = $dados['id_ativo'] ?? null;
    
    if (!$tabela || !$idPendente || !$idAtivo) {
        throw new Exception('Dados incompletos');
    }
    
    $tabelasPermitidas = ['marcas_veiculo', 'modelos_veiculo', 'cores_veiculo'];
    if (!in_array($tabela, $tabelasPermitidas)) {
        throw new Exception('Tabela inválida');
    }
    
    // Nome da FK na tabela tickets
    $fkName = str_replace('_veiculo', '_id', $tabela);
    
    // Inicia transação
    $db->beginTransaction();
    
    try {
        // 1. Atualiza tickets vinculados ao pendente → aponta para ativo
        $updateTickets = "UPDATE tickets SET {$fkName} = :id_ativo WHERE {$fkName} = :id_pendente";
        $stmt = $db->prepare($updateTickets);
        $stmt->execute([
            ':id_ativo' => $idAtivo,
            ':id_pendente' => $idPendente
        ]);
        
        $ticketsAtualizados = $stmt->rowCount();
        
        // 2. Marca pendente como INATIVO
        $updateStatus = "UPDATE {$tabela} SET status = 'INATIVO' WHERE id = :id";
        $stmt = $db->prepare($updateStatus);
        $stmt->execute([':id' => $idPendente]);
        
        // 3. Busca nomes para auditoria
        $selectNomes = "SELECT nome FROM {$tabela} WHERE id IN (:id_pendente, :id_ativo)";
        $stmt = $db->prepare($selectNomes);
        $stmt->execute([
            ':id_pendente' => $idPendente,
            ':id_ativo' => $idAtivo
        ]);
        $nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Commit
        $db->commit();
        
        // 4. Auditoria
        $nomeTabela = ucfirst(str_replace('_veiculo', '', $tabela));
        $audit->log(
            'CADASTRO_MESCLADO',
            'SUCESSO',
            "{$nomeTabela} mesclada - {$ticketsAtualizados} tickets atualizados",
            [
                'tabela' => $tabela,
                'de' => ['id' => $idPendente, 'nome' => $nomes[0] ?? '?'],
                'para' => ['id' => $idAtivo, 'nome' => $nomes[1] ?? '?'],
                'tickets_atualizados' => $ticketsAtualizados
            ],
            $user['id'],
            $user['perfil']
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Mesclagem realizada',
            'tickets_atualizados' => $ticketsAtualizados
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
