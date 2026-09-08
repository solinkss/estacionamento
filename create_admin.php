<?php
/**
 * Script de Criação do Primeiro Administrador
 * Sistema de Estacionamento v3.0
 * 
 * USO: php create_admin.php
 * Ou acessar via navegador: http://localhost/estacionamento/create_admin.php
 * 
 * Este script cria o usuário admin inicial com senha segura.
 * Após criar, DELETE este arquivo por segurança.
 */

// Configurações iniciais
require_once __DIR__ . '/config/config.php';

// Verifica se já existe admin
$checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM usuarios WHERE perfil = 'admin' AND status = 'ativo'");
$checkStmt->execute();
$result = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] > 0) {
    echo "❌ ERRO: Já existe pelo menos um usuário administrador ativo no sistema.\n";
    echo "Se necessário, crie outro via painel administrativo ou reative um inativo.\n";
    exit(1);
}

echo "=================================================\n";
echo "  CRIAÇÃO DE USUÁRIO ADMINISTRADOR - v3.0\n";
echo "=================================================\n\n";

// Coleta dados via CLI ou usa padrões
if (php_sapi_name() === 'cli') {
    echo "Nome completo: ";
    $nome = trim(fgets(STDIN));
    
    echo "Email: ";
    $email = trim(fgets(STDIN));
    
    echo "Senha (mínimo 12 caracteres): ";
    $senha = trim(fgets(STDIN));
    
    echo "\nConfirme a senha: ";
    $senhaConfirm = trim(fgets(STDIN));
} else {
    // Modo web - formulário simples
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $senhaConfirm = $_POST['senha_confirm'] ?? '';
    } else {
        // Mostra formulário HTML
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Criar Admin - Estacionamento v3.0</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .card { max-width: 500px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
                .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="card-body p-4">
                    <h3 class="text-center mb-4">🔐 Criar Administrador</h3>
                    <p class="text-muted text-center small">Primeiro acesso ao sistema</p>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha (mín. 12 caracteres)</label>
                            <input type="password" name="senha" class="form-control" required minlength="12">
                            <div class="form-text">Mínimo 12 chars, com maiúscula, minúscula, número e especial</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirme a Senha</label>
                            <input type="password" name="senha_confirm" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Criar Administrador</button>
                    </form>
                    <hr class="my-3">
                    <p class="text-center small text-muted">⚠️ Delete este arquivo após criar o admin</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Validações
$erros = [];

if (empty($nome) || strlen($nome) < 3) {
    $erros[] = "Nome deve ter pelo menos 3 caracteres";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = "Email inválido";
}

if (strlen($senha) < 12) {
    $erros[] = "Senha deve ter pelo menos 12 caracteres";
}

if ($senha !== $senhaConfirm) {
    $erros[] = "Senhas não conferem";
}

// Validação de força da senha
if (!preg_match('/[A-Z]/', $senha)) {
    $erros[] = "Senha deve conter pelo menos uma letra maiúscula";
}
if (!preg_match('/[a-z]/', $senha)) {
    $erros[] = "Senha deve conter pelo menos uma letra minúscula";
}
if (!preg_match('/[0-9]/', $senha)) {
    $erros[] = "Senha deve conter pelo menos um número";
}
if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $senha)) {
    $erros[] = "Senha deve conter pelo menos um caractere especial (!@#$%^&*...)";
}

if (!empty($erros)) {
    echo "❌ ERROS DE VALIDAÇÃO:\n";
    foreach ($erros as $erro) {
        echo "   - $erro\n";
    }
    echo "\nTente novamente.\n";
    exit(1);
}

// Gera UUIDv7 para o ID
function generateUUIDv7(): string {
    $time = microtime(true) * 1000;
    $timestamp = pack('H*', sprintf('%012x', (int)$time));
    
    $random = random_bytes(10);
    
    $uuid = bin2hex($timestamp . $random);
    
    $uuid[12] = '7';
    $uuid[16] = chr((ord($uuid[16]) & 0x3f) | 0x80);
    
    return sprintf(
        '%08s-%04s-%04s-%04s-%012s',
        substr($uuid, 0, 8),
        substr($uuid, 8, 4),
        substr($uuid, 12, 4),
        substr($uuid, 16, 4),
        substr($uuid, 20, 12)
    );
}

// Hash da senha com Argon2id
$senhaHash = password_hash($senha, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 3
]);

if (!$senhaHash) {
    echo "❌ ERRO: Falha ao gerar hash da senha. Verifique se PHP está com Argon2id habilitado.\n";
    exit(1);
}

// Prepara dados
$id = generateUUIDv7();
$emailHash = hash('sha256', strtolower(trim($email)));

try {
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (
            id, nome, email, email_hash, perfil, status, 
            senha_hash, tentativas_falha, bloqueado_ate, created_at
        ) VALUES (
            :id, :nome, :email, :email_hash, 'admin', 'ativo',
            :senha_hash, 0, NULL, NOW(3)
        )
    ");
    
    $stmt->execute([
        ':id' => $id,
        ':nome' => $nome,
        ':email' => $email,
        ':email_hash' => $emailHash,
        ':senha_hash' => $senhaHash
    ]);
    
    echo "✅ SUCESSO!\n\n";
    echo "Administrador criado com sucesso:\n";
    echo "-----------------------------------\n";
    echo "ID:        $id\n";
    echo "Nome:      $nome\n";
    echo "Email:     $email\n";
    echo "Perfil:    admin\n";
    echo "Status:    ativo\n";
    echo "Criado em: " . date('d/m/Y H:i:s') . "\n";
    echo "\n";
    echo "📝 PRÓXIMOS PASSOS:\n";
    echo "   1. Acesse: http://localhost/estacionamento/public/login.php\n";
    echo "   2. Login com email: $email\n";
    echo "   3. Use a senha informada\n";
    echo "   4. ⚠️  DELETE ESTE ARQUIVO APÓS O PRIMEIRO ACESSO!\n";
    echo "      rm create_admin.php (Linux/Mac) ou del create_admin.php (Windows)\n";
    echo "\n";
    echo "=================================================\n";
    
    // Se foi via web, mostra mensagem HTML
    if (php_sapi_name() !== 'cli') {
        echo '<br><div class="alert alert-success mt-3">';
        echo '<strong>Sucesso!</strong> Administrador criado.<br>';
        echo 'Agora você pode fazer login e <strong>deletar este arquivo</strong>.';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo "❌ ERRO AO INSERIR NO BANCO: " . $e->getMessage() . "\n";
    if ($e->getCode() == 23000) {
        echo "   Possível duplicidade de email. Verifique se já existe usuário com este email.\n";
    }
    exit(1);
}
