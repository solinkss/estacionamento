<?php
/**
 * Tela de Entrada - Registro de Novo Ticket
 * Sistema Estacionamento v3.0
 * Bootstrap 5.3 Mobile-First
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth/AuthDomain.php';
require_once __DIR__ . '/../src/Core/ParkingCore.php';

$auth = new AuthDomain();
$user = $auth->validarSessao();

if (!$user) {
    header('Location: login.php');
    exit;
}

// Verifica permissão
if (!$auth->checkPermission($user['id'], $user['perfil'], 'TICKET_CRIAR_ENTRADA')) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Operação não autorizada</div>';
    exit;
}

$db = Database::getInstance()->getConnection();

// Carrega tipos de veículo
$stmt = $db->query("SELECT id, nome FROM tipos_veiculo WHERE ativo = TRUE ORDER BY nome");
$tiposVeiculo = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Entrada - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --success-color: #27ae60;
        }
        
        body {
            background-color: #f5f6fa;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .btn-grande {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 10px;
        }
        
        .autocomplete-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
        }
        
        .autocomplete-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .autocomplete-item:hover {
            background-color: #f8f9fa;
        }
        
        .autocomplete-item .status-badge {
            font-size: 0.75rem;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            margin-left: 0.5rem;
        }
        
        .status-ativo {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .placa-input {
            font-family: monospace;
            font-size: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark" style="background-color: var(--primary-color);">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-plus-circle-fill"></i> Nova Entrada
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['nome']) ?>
                </span>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-car-front-fill"></i> Dados do Veículo</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-entrada" autocomplete="off">
                            <!-- Placa -->
                            <div class="mb-3">
                                <label for="placa" class="form-label">Placa *</label>
                                <input type="text" 
                                       class="form-control placa-input" 
                                       id="placa" 
                                       name="placa" 
                                       maxlength="8"
                                       placeholder="ABC1D23"
                                       required
                                       autofocus>
                                <div class="form-text">Formato Mercosul (7 caracteres)</div>
                            </div>

                            <!-- Tipo de Veículo -->
                            <div class="mb-3">
                                <label for="tipo_veiculo_id" class="form-label">Tipo de Veículo *</label>
                                <select class="form-select" id="tipo_veiculo_id" name="tipo_veiculo_id" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($tiposVeiculo as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo['id']) ?>">
                                        <?= htmlspecialchars($tipo['nome']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Marca -->
                            <div class="mb-3 position-relative">
                                <label for="marca" class="form-label">Marca</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="marca" 
                                       name="marca" 
                                       placeholder="Digite ou selecione a marca"
                                       data-type="marca">
                                <div id="marca-suggestions" class="autocomplete-list"></div>
                                <input type="hidden" id="marca_id" name="marca_id">
                                <div class="form-text">Se não existir, será cadastrada automaticamente</div>
                            </div>

                            <!-- Modelo -->
                            <div class="mb-3 position-relative">
                                <label for="modelo" class="form-label">Modelo</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="modelo" 
                                       name="modelo" 
                                       placeholder="Digite ou selecione o modelo"
                                       data-type="modelo">
                                <div id="modelo-suggestions" class="autocomplete-list"></div>
                                <input type="hidden" id="modelo_id" name="modelo_id">
                            </div>

                            <!-- Cor -->
                            <div class="mb-3 position-relative">
                                <label for="cor" class="form-label">Cor</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="cor" 
                                       name="cor" 
                                       placeholder="Digite ou selecione a cor"
                                       data-type="cor">
                                <div id="cor-suggestions" class="autocomplete-list"></div>
                                <input type="hidden" id="cor_id" name="cor_id">
                            </div>

                            <!-- Observações -->
                            <div class="mb-3">
                                <label for="observacao" class="form-label">Observações</label>
                                <textarea class="form-control" 
                                          id="observacao" 
                                          name="observacao" 
                                          rows="2"
                                          placeholder="Opcionais"></textarea>
                            </div>

                            <!-- Botões -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="index.php" class="btn btn-secondary btn-grande">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-grande">
                                    <i class="bi bi-check-circle"></i> Registrar Entrada
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Mensagem de Sucesso -->
                <div id="modal-sucesso" class="modal fade" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-check-circle-fill"></i> Entrada Registrada!
                                </h5>
                            </div>
                            <div class="modal-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-qrcode display-1 text-success"></i>
                                </div>
                                <p class="mb-2"><strong>Placa:</strong> <span id="sucesso-placa"></span></p>
                                <p class="mb-2"><strong>Código Único:</strong> <span id="sucesso-codigo"></span></p>
                                <p class="mb-2"><strong>Entrada:</strong> <span id="sucesso-entrada"></span></p>
                                <div id="qr-code-container" class="mt-3"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal">
                                    <i class="bi bi-check-lg"></i> OK
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/entrada.js"></script>
</body>
</html>
