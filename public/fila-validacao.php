<?php
/**
 * Tela de Fila de Validação - Aprovação de Cadastros Pendentes
 * Sistema Estacionamento v3.0
 * Padrão Cadastro Auxiliar Assíncrono
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth/AuthDomain.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

if (!$user) {
    header('Location: login.php');
    exit;
}

// Verifica permissão (apenas admin e gestor)
if (!in_array($user['perfil'], ['admin', 'gestor'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Operação não autorizada</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Filtros
$filtroTipo = $_GET['tipo'] ?? 'todos'; // marcas, modelos, cores, todos
$filtroStatus = $_GET['status'] ?? 'PENDENTE';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fila de Validação - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --warning-color: #f39c12;
        }
        
        body {
            background-color: #f5f6fa;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .badge-pendente {
            background-color: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .card-cadastro {
            border-left: 4px solid var(--warning-color);
        }
        
        .btn-action {
            min-width: 100px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark" style="background-color: var(--primary-color);">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-list-check"></i> Fila de Validação
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['nome']) ?>
                    <small class="ms-2">(<?= ucfirst($user['perfil']) ?>)</small>
                </span>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container-fluid py-4">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="filtro-tipo" class="form-label">Tipo de Cadastro</label>
                                <select class="form-select" id="filtro-tipo" name="tipo">
                                    <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                                    <option value="marcas" <?= $filtroTipo === 'marcas' ? 'selected' : '' ?>>Marcas</option>
                                    <option value="modelos" <?= $filtroTipo === 'modelos' ? 'selected' : '' ?>>Modelos</option>
                                    <option value="cores" <?= $filtroTipo === 'cores' ? 'selected' : '' ?>>Cores</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filtro-status" class="form-label">Status</label>
                                <select class="form-select" id="filtro-status" name="status">
                                    <option value="PENDENTE" <?= $filtroStatus === 'PENDENTE' ? 'selected' : '' ?>>Pendente</option>
                                    <option value="ATIVO" <?= $filtroStatus === 'ATIVO' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="INATIVO" <?= $filtroStatus === 'INATIVO' ? 'selected' : '' ?>>Inativo</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtrar
                                </button>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="fila-validacao.php" class="btn btn-secondary w-100">
                                    <i class="bi bi-arrow-clockwise"></i> Limpar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abas por Tipo -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs mb-3" id="tabs-cadastros" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-marcas" type="button">
                            Marcas <span id="count-marcas" class="badge bg-warning">-</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-modelos" type="button">
                            Modelos <span id="count-modelos" class="badge bg-warning">-</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cores" type="button">
                            Cores <span id="count-cores" class="badge bg-warning">-</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="tabs-content">
                    <!-- Tab Marcas -->
                    <div class="tab-pane fade show active" id="tab-marcas">
                        <div id="lista-marcas" class="row g-3">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>

                    <!-- Tab Modelos -->
                    <div class="tab-pane fade" id="tab-modelos">
                        <div id="lista-modelos" class="row g-3">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>

                    <!-- Tab Cores -->
                    <div class="tab-pane fade" id="tab-cores">
                        <div id="lista-cores" class="row g-3">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Mesclagem -->
    <div class="modal fade" id="modal-mesclar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-diagram-3-fill"></i> Mesclar Cadastro
                    </h5>
                </div>
                <div class="modal-body">
                    <p>O cadastro pendente <strong id="mesclar-nome-pendente"></strong> será substituído por:</p>
                    
                    <div class="mb-3">
                        <label for="mesclar-selecionar" class="form-label">Selecione o cadastro ATIVO correto:</label>
                        <select class="form-select" id="mesclar-selecionar">
                            <!-- Preenchido via JS -->
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Todos os tickets vinculados ao cadastro pendente serão atualizados automaticamente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" id="btn-confirmar-mesclar">
                        <i class="bi bi-check-lg"></i> Confirmar Mesclagem
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/fila-validacao.js"></script>
</body>
</html>
