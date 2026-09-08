<?php
/**
 * Frontend - Dashboard Operador
 * Sistema Estacionamento v3.0
 * Bootstrap 5.3 Mobile-First
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
        }
        
        body {
            background-color: #f5f6fa;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .card-stat {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .card-stat:hover {
            transform: translateY(-5px);
        }
        
        .btn-grande {
            padding: 1.5rem;
            font-size: 1.2rem;
            border-radius: 15px;
            margin: 0.5rem;
        }
        
        .status-aberto {
            background-color: #27ae60;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .status-fechado {
            background-color: #95a5a6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark" style="background-color: var(--primary-color);">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-p-circle-fill"></i> <?= APP_NAME ?>
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3 d-none d-md-block">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['nome']) ?>
                    <small class="ms-2">(<?= ucfirst($user['perfil']) ?>)</small>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container-fluid py-4">
        <!-- Cards de Estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card card-stat bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Tickets Abertos</h6>
                                <h2 class="mb-0 mt-2" id="stat-abertos">-</h2>
                            </div>
                            <i class="bi bi-car-front-fill display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card card-stat bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Receita Hoje</h6>
                                <h2 class="mb-0 mt-2" id="stat-receita">R$ -</h2>
                            </div>
                            <i class="bi bi-cash-stack display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card card-stat bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Média Permanência</h6>
                                <h2 class="mb-0 mt-2" id="stat-media">- min</h2>
                            </div>
                            <i class="bi bi-clock-history display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card card-stat bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Ocupação</h6>
                                <h2 class="mb-0 mt-2" id="stat-ocupacao">-%</h2>
                            </div>
                            <i class="bi bi-speedometer2 display-4 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <h5 class="text-secondary"><i class="bi bi-lightning-charge-fill"></i> Ações Rápidas</h5>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="entrada.php" class="btn btn-success btn-grande w-100">
                    <i class="bi bi-plus-circle d-block mb-2"></i>
                    <span>Nova Entrada</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="saida.php" class="btn btn-primary btn-grande w-100">
                    <i class="bi bi-qrcode-scan d-block mb-2"></i>
                    <span>Registrar Saída</span>
                </a>
            </div>
            
            <div class="col-6 col-md-3">
                <a href="buscar.php" class="btn btn-info btn-grande w-100 text-white">
                    <i class="bi bi-search d-block mb-2"></i>
                    <span>Buscar Ticket</span>
                </a>
            </div>
            
            <?php if ($user['perfil'] === 'admin' || $user['perfil'] === 'gestor'): ?>
            <div class="col-6 col-md-3">
                <a href="fila-validacao.php" class="btn btn-warning btn-grande w-100">
                    <i class="bi bi-list-check d-block mb-2"></i>
                    <span>Fila Validação</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Últimos Tickets -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock"></i> Últimas Movimentações</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Placa</th>
                                        <th>Tipo</th>
                                        <th>Entrada</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-tickets">
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Carregando...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/index-dashboard.js"></script>
</body>
</html>
