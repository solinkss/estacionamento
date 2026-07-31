<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Estacionamento v3.0</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root { --primary: #2563eb; --success: #16a34a; --warning: #ca8a04; --danger: #dc2626; }
        body { background: #f1f5f9; min-height: 100vh; }
        .card-stat { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-4px); }
        .stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .chart-container { position: relative; height: 300px; }
        .table-custom { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .navbar-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark navbar-custom mb-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</span>
            <div>
                <span class="text-white me-3" id="user-info"></span>
                <a href="index.php" class="btn btn-sm btn-outline-light"><i class="bi bi-house me-1"></i>Início</a>
                <a href="logout.php" class="btn btn-sm btn-outline-light ms-2"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <!-- Cards de Estatísticas -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat bg-white p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-gradient-primary text-white me-3">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Veículos no Pátio</h6>
                            <h3 class="mb-0" id="stat-ocupados">-</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat bg-white p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-gradient-success text-white me-3">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Receita Hoje</h6>
                            <h3 class="mb-0" id="stat-receita">-</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat bg-white p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-gradient-warning text-white me-3">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Tickets Fechados</h6>
                            <h3 class="mb-0" id="stat-tickets">-</h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-stat bg-white p-3">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-gradient-info text-white me-3">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Ticket Médio</h6>
                            <h3 class="mb-0" id="stat-medio">-</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card card-stat bg-white p-4">
                    <h5 class="mb-4"><i class="bi bi-bar-chart-line me-2"></i>Movimento por Hora</h5>
                    <div class="chart-container">
                        <canvas id="chartHora"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-stat bg-white p-4">
                    <h5 class="mb-4"><i class="bi bi-pie-chart me-2"></i>Tipo de Veículos</h5>
                    <div class="chart-container">
                        <canvas id="chartTipo"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ranking Marcas -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card card-stat bg-white p-4">
                    <h5 class="mb-4"><i class="bi bi-trophy me-2"></i>Ranking de Marcas (Top 10)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Marca</th>
                                    <th class="text-end">Qtd. Veículos</th>
                                    <th class="text-end">% do Total</th>
                                </tr>
                            </thead>
                            <tbody id="tabela-marcas">
                                <tr><td colspan="4" class="text-center py-4">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimos Tickets -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card card-stat bg-white p-4">
                    <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>Últimos Tickets</h5>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Placa</th>
                                    <th>QR Code</th>
                                    <th>Tipo</th>
                                    <th>Marca/Modelo</th>
                                    <th>Entrada</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabela-tickets">
                                <tr><td colspan="7" class="text-center py-4">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>
