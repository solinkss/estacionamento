<?php
/**
 * Tela de Login
 * Sistema Estacionamento v3.0
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth/AuthDomain.php';

$auth = new AuthDomain();
$error = null;

// Se já estiver logado, redireciona
if ($auth->validarSessao()) {
    header('Location: index.php');
    exit;
}

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $error = 'E-mail e senha são obrigatórios';
    } else {
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            $user = $auth->login($email, $senha, $ip);
            
            if ($user) {
                header('Location: index.php');
                exit;
            } else {
                $error = 'Credenciais inválidas';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --success-color: #27ae60;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a252f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        .btn-login {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .alert-error {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="bi bi-p-circle-fill"></i>
                        <h4 class="mb-0"><?= APP_NAME ?></h4>
                        <small>v<?= APP_VERSION ?></small>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-error" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" autocomplete="off">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> E-mail
                                </label>
                                <input type="email" 
                                       class="form-control form-control-lg" 
                                       id="email" 
                                       name="email" 
                                       placeholder="seu@email.com"
                                       required
                                       autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="senha" class="form-label">
                                    <i class="bi bi-lock"></i> Senha
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="senha" 
                                       name="senha" 
                                       placeholder="••••••••••••"
                                       required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Entrar
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center text-muted small">
                            <p class="mb-0">
                                <i class="bi bi-shield-check"></i> 
                                Ambiente seguro com autenticação Argon2id
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white-50 small">
                    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?> - Todos os direitos reservados</p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
