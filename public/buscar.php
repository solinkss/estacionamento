<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Ticket - Estacionamento v3.0</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #2563eb; --success: #16a34a; --warning: #ca8a04; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .card-custom { border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: none; }
        .btn-primary { background: var(--primary); border: none; padding: 12px 24px; font-weight: 600; }
        .status-aberto { color: #16a34a; font-weight: bold; }
        .status-fechado { color: #dc2626; font-weight: bold; }
        .status-cancelado { color: #6b7280; font-weight: bold; }
        .ticket-info { background: #f8fafc; border-radius: 8px; padding: 16px; margin-top: 16px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #64748b; font-size: 0.9rem; }
        .info-value { font-weight: 600; color: #1e293b; }
        .qr-display { font-family: monospace; font-size: 1.1rem; background: #fff; padding: 8px 16px; border-radius: 6px; border: 2px dashed #cbd5e1; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-white/10 backdrop-blur">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1"><i class="bi bi-p-circle-fill me-2"></i>Estacionamento v3.0</span>
            <div>
                <span class="text-white me-3" id="user-info"></span>
                <a href="index.php" class="btn btn-sm btn-outline-light"><i class="bi bi-house me-1"></i>Início</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card card-custom bg-white">
                    <div class="card-body p-4">
                        <h3 class="mb-4 text-center"><i class="bi bi-search me-2"></i>Buscar Ticket</h3>
                        
                        <!-- Form de busca -->
                        <div class="mb-4">
                            <ul class="nav nav-pills mb-3" id="busca-tab" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#placa-tab">Placa</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#qr-tab">QR Code</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="placa-tab">
                                    <label class="form-label">Placa do Veículo</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-lg" id="placa-busca" 
                                               placeholder="ABC1D23" maxlength="8" style="text-transform: uppercase;">
                                        <button class="btn btn-primary" onclick="buscarPorPlaca()">
                                            <i class="bi bi-search me-1"></i>Buscar
                                        </button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="qr-tab">
                                    <label class="form-label">Código QR (ULID)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-lg" id="qr-busca" 
                                               placeholder="01HXYZ... (26 caracteres)">
                                        <button class="btn btn-primary" onclick="buscarPorQR()">
                                            <i class="bi bi-qr-code-scan me-1"></i>Buscar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resultado -->
                        <div id="resultado" class="d-none">
                            <div class="alert alert-info d-flex align-items-center" id="alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <span id="alert-msg"></span>
                            </div>
                            
                            <div class="ticket-info" id="ticket-detalhes">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Dados do Ticket</h5>
                                    <span class="badge" id="status-badge"></span>
                                </div>
                                
                                <div class="info-row">
                                    <span class="info-label">QR Code</span>
                                    <span class="info-value qr-display" id="det-codigo"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Placa</span>
                                    <span class="info-value" id="det-placa"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Tipo</span>
                                    <span class="info-value" id="det-tipo"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Marca</span>
                                    <span class="info-value" id="det-marca"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Modelo</span>
                                    <span class="info-value" id="det-modelo"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Cor</span>
                                    <span class="info-value" id="det-cor"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Entrada</span>
                                    <span class="info-value" id="det-entrada"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Saída</span>
                                    <span class="info-value" id="det-saida"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Tempo</span>
                                    <span class="info-value" id="det-tempo"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Valor</span>
                                    <span class="info-value" id="det-valor"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Operador Entrada</span>
                                    <span class="info-value" id="det-op-entrada"></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Operador Saída</span>
                                    <span class="info-value" id="det-op-saida"></span>
                                </div>
                            </div>
                            
                            <div class="mt-4 d-flex gap-2" id="acoes-ticket">
                                <!-- Botões dinâmicos -->
                            </div>
                        </div>

                        <!-- Loading -->
                        <div id="loading" class="text-center d-none py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="mt-2 text-muted">Buscando ticket...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/buscar.js"></script>
</body>
</html>
