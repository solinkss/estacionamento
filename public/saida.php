<?php
/**
 * Saída e Cobrança - Sistema Estacionamento v3.0
 * Mobile-first Bootstrap 5.3
 */
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['status'] !== 'ativo') {
    header('Location: login.php');
    exit;
}

$usuario = $_SESSION['nome'];
$perfil = $_SESSION['perfil'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saída | Estacionamento v3.0</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary-color: #2563eb; --success-color: #16a34a; }
        body { background: #f8fafc; min-height: 100vh; }
        .header-app { background: linear-gradient(135deg, var(--primary-color), #1e40af); color: white; padding: 1rem; border-radius: 0 0 1rem 1rem; margin-bottom: 1.5rem; }
        .card-ticket { border: none; border-radius: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .valor-total { font-size: 2.5rem; font-weight: 700; color: var(--success-color); }
        .btn-pagamento { padding: 1rem; font-size: 1.1rem; border-radius: 0.75rem; }
        .status-badge { font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: 2rem; }
        .tempo-decorrido { background: #fef3c7; color: #92400e; padding: 0.75rem; border-radius: 0.5rem; text-align: center; font-weight: 600; }
        @media (max-width: 576px) {
            .valor-total { font-size: 2rem; }
            .btn-pagamento { padding: 0.75rem; font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-app">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="bi bi-car-front-fill me-2"></i>Estacionamento</h4>
                    <small class="opacity-75">Saída e Cobrança</small>
                </div>
                <div class="text-end">
                    <div class="fw-bold"><?= htmlspecialchars($usuario) ?></div>
                    <small class="opacity-75"><?= ucfirst($perfil) ?></small>
                </div>
            </div>
        </div>

        <!-- Busca por QR ou Placa -->
        <div class="card card-ticket mb-3">
            <div class="card-body">
                <h6 class="card-title mb-3"><i class="bi bi-search me-2"></i>Localizar Ticket</h6>
                <div class="row g-2">
                    <div class="col-12 col-md-8">
                        <input type="text" id="buscaInput" class="form-control form-control-lg" placeholder="QR Code ou Placa (AAA1A23)">
                    </div>
                    <div class="col-12 col-md-4">
                        <button id="btnBuscar" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-search me-2"></i>Buscar
                        </button>
                    </div>
                </div>
                <div class="mt-2">
                    <button class="btn btn-outline-secondary btn-sm" id="btnLerQR">
                        <i class="bi bi-qr-code-scan me-1"></i>Ler QR Code
                    </button>
                </div>
            </div>
        </div>

        <!-- Resultado do Ticket -->
        <div id="ticketInfo" class="d-none">
            <div class="card card-ticket mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1"><span id="ticketPlaca"></span></h5>
                            <small class="text-muted" id="ticketModelo"></small>
                        </div>
                        <span id="ticketStatus" class="badge status-badge bg-success">ABERTO</span>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <small class="text-muted">Entrada</small>
                            <div class="fw-bold" id="ticketEntrada"></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Tipo</small>
                            <div class="fw-bold" id="ticketTipo"></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Marca</small>
                            <div class="fw-bold" id="ticketMarca"></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Cor</small>
                            <div class="fw-bold" id="ticketCor"></div>
                        </div>
                    </div>

                    <div class="tempo-decorrido mb-3">
                        <i class="bi bi-clock me-2"></i>
                        <span id="tempoDecorrido">--:--</span>
                    </div>

                    <hr>

                    <div class="text-center mb-4">
                        <small class="text-muted">Valor a Pagar</small>
                        <div class="valor-total" id="valorTotal">R$ 0,00</div>
                        <small class="text-muted" id="detalhesCalculo"></small>
                    </div>

                    <!-- Botões de Pagamento -->
                    <div class="d-grid gap-2">
                        <button id="btnConfirmarPagamento" class="btn btn-success btn-pagamento">
                            <i class="bi bi-check-circle me-2"></i>Confirmar Pagamento (MANUAL)
                        </button>
                        <button id="btnCortesia" class="btn btn-outline-warning btn-pagamento">
                            <i class="bi bi-gift me-2"></i>Aplicar Cortesia
                        </button>
                        <button id="btnCancelar" class="btn btn-outline-danger btn-pagamento">
                            <i class="bi bi-x-circle me-2"></i>Cancelar Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Cortesia -->
        <div class="modal fade" id="modalCortesia" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Aplicar Cortesia</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Motivo da Cortesia</label>
                        <select id="motivoCortesia" class="form-select">
                            <option value="">Selecione...</option>
                            <option value="CLIENTE_FIEL">Cliente Fiel</option>
                            <option value="PROBLEMA_TECNICO">Problema Técnico</option>
                            <option value="TEMPO_CURTO">Tempo Muito Curto</option>
                            <option value="CORTESIA_COMERCIAL">Cortesia Comercial</option>
                            <option value="OUTRO">Outro</option>
                        </select>
                        <textarea id="obsCortesia" class="form-control mt-2" rows="3" placeholder="Observações (opcional)"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" id="btnAplicarCortesia" class="btn btn-warning">Aplicar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Cancelamento -->
        <div class="modal fade" id="modalCancelamento" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancelar Ticket</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Justificativa (obrigatório)</label>
                        <textarea id="justificativaCancelamento" class="form-control" rows="4" placeholder="Descreva o motivo do cancelamento..."></textarea>
                        <small class="text-muted">Mínimo 20 caracteres</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
                        <button type="button" id="btnConfirmarCancelamento" class="btn btn-danger">Confirmar Cancelamento</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar fixed-bottom navbar-light bg-white border-top">
            <div class="container-fluid justify-content-around">
                <a href="index.php" class="nav-link text-center">
                    <i class="bi bi-house d-block fs-4"></i>
                    <small>Início</small>
                </a>
                <a href="entrada.php" class="nav-link text-center">
                    <i class="bi bi-plus-circle d-block fs-4"></i>
                    <small>Entrada</small>
                </a>
                <a href="saida.php" class="nav-link text-center active">
                    <i class="bi bi-minus-circle d-block fs-4"></i>
                    <small>Saída</small>
                </a>
                <?php if ($perfil === 'admin' || $perfil === 'gestor'): ?>
                <a href="fila-validacao.php" class="nav-link text-center">
                    <i class="bi bi-list-check d-block fs-4"></i>
                    <small>Fila</small>
                </a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link text-center">
                    <i class="bi bi-box-arrow-right d-block fs-4"></i>
                    <small>Sair</small>
                </a>
            </div>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/saida.js"></script>
</body>
</html>
