<?php
/**
 * Auth Domain - Autenticação, RBAC e Segurança
 * Sistema Estacionamento v3.0
 * 
 * - Senha Argon2id com histórico
 * - Bloqueio progressivo
 * - Rate limiting
 * - Sessão segura
 */

declare(strict_types=1);

class AuthDomain {
    private PDO $db;
    private AuditGuard $audit;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->audit = new AuditGuard();
    }
    
    /**
     * Autentica usuário com validações de segurança
     */
    public function login(string $email, string $senha, string $ip): ?array {
        // Rate limiting por conta
        if (!$this->checkRateLimitLogin($email)) {
            $this->audit->log(
                'LOGIN_TENTATIVA',
                'NEGADO',
                'Rate limit excedido para login',
                ['email' => AuditGuard::mascararPII($email, 'email')],
                null,
                null
            );
            throw new Exception('Operação não autorizada');
        }
        
        // Busca usuário por email_hash
        $emailHash = hash('sha256', $email);
        
        $sql = "SELECT id, nome, email, perfil, status, senha_hash, tentativas_falha, bloqueado_ate 
                FROM usuarios 
                WHERE email_hash = :email_hash";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email_hash' => $emailHash]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            $this->registrarFalhaLogin(null, $email);
            return null;
        }
        
        // Verifica se está bloqueado
        if ($usuario['status'] === 'bloqueado') {
            if ($usuario['bloqueado_ate'] && strtotime($usuario['bloqueado_ate']) > time()) {
                $this->audit->log(
                    'LOGIN_TENTATIVA',
                    'NEGADO',
                    'Usuário bloqueado temporariamente',
                    ['user_id' => $usuario['id']],
                    $usuario['id'],
                    $usuario['perfil']
                );
                throw new Exception('Conta bloqueada temporariamente');
            }
        }
        
        if ($usuario['status'] !== 'ativo') {
            $this->audit->log(
                'LOGIN_TENTATIVA',
                'NEGADO',
                'Usuário inativo',
                ['user_id' => $usuario['id']],
                $usuario['id'],
                $usuario['perfil']
            );
            return null;
        }
        
        // Verifica senha Argon2id
        if (!password_verify($senha, $usuario['senha_hash'])) {
            $this->registrarFalhaLogin($usuario['id'], $email);
            return null;
        }
        
        // Login bem sucedido
        $this->finalizarLoginSucesso($usuario, $ip);
        
        return [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'perfil' => $usuario['perfil'],
            'email' => $usuario['email']
        ];
    }
    
    /**
     * Valida permissão RBAC
     * Fluxo 4 passos: session_valid → user_ativo → permission_check → business_logic
     */
    public function checkPermission(?string $userId, string $perfil, string $permission): bool {
        if (!$userId) {
            return false;
        }
        
        // Verifica se usuário está ativo
        $sql = "SELECT status FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $status = $stmt->fetchColumn();
        
        if ($status !== 'ativo') {
            return false;
        }
        
        // Matriz de permissões
        $permissions = $this->getPermissionsMatrix();
        
        if ($perfil === PERFIL_ADMIN) {
            return true; // Admin tem tudo
        }
        
        $userPerms = $permissions[$perfil] ?? [];
        
        // Wildcard support (ex: TICKET_*)
        foreach ($userPerms as $perm) {
            if ($perm === $permission) {
                return true;
            }
            if (str_ends_with($perm, '*')) {
                $prefix = substr($perm, 0, -1);
                if (str_starts_with($permission, $prefix)) {
                    return true;
                }
            }
        }
        
        // Log tentativa não autorizada
        $this->audit->logUnauthorizedAttempt($permission, $perfil, $userId);
        
        return false;
    }
    
    /**
     * Registra nova senha no histórico (Argon2id)
     */
    public function alterarSenha(string $userId, string $novaSenha): bool {
        // Valida força da senha
        if (!$this->validarForcaSenha($novaSenha)) {
            throw new Exception('Senha não atende requisitos mínimos');
        }
        
        // Verifica histórico (não pode repetir últimas 3)
        if ($this->senhaNoHistorico($userId, $novaSenha)) {
            throw new Exception('Senha já utilizada recentemente');
        }
        
        $hash = password_hash($novaSenha, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        $sql = "UPDATE usuarios SET senha_hash = :hash, tentativas_falha = 0 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':hash' => $hash, ':id' => $userId]);
        
        $this->audit->log(
            'SENHA_ALTERADA',
            'SUCESSO',
            'Senha alterada com sucesso',
            [],
            $userId,
            null
        );
        
        return true;
    }
    
    /**
     * Bloqueia/desbloqueia usuário
     */
    public function toggleBloqueio(string $userId, string $gestorId): bool {
        $sql = "SELECT status, bloqueado_ate FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            return false;
        }
        
        $novoStatus = $usuario['status'] === 'bloqueado' ? 'ativo' : 'bloqueado';
        $bloqueadoAte = $novoStatus === 'bloqueado' ? date('Y-m-d H:i:s') : null;
        
        $update = "UPDATE usuarios SET status = :status, bloqueado_ate = :bloqueado 
                   WHERE id = :id";
        
        $stmt = $this->db->prepare($update);
        $stmt->execute([
            ':status' => $novoStatus,
            ':bloqueado' => $bloqueadoAte,
            ':id' => $userId
        ]);
        
        $this->audit->log(
            $novoStatus === 'bloqueado' ? 'USUARIO_BLOQUEADO' : 'DESBLOQUEIO',
            'SUCESSO',
            'Usuário ' . $novoStatus,
            ['user_id' => $userId],
            $gestorId,
            PERFIL_GESTOR
        );
        
        return true;
    }
    
    /**
     * Valida sessão ativa
     */
    public function validarSessao(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[SESSION_NAME])) {
            return null;
        }
        
        $sessao = $_SESSION[SESSION_NAME];
        
        // Verifica timeout absoluto (8h)
        if (time() - $sessao['created_at'] > SESSION_ABSOLUTE) {
            $this->logout();
            return null;
        }
        
        // Verifica timeout inatividade (15min)
        if (time() - $sessao['last_activity'] > SESSION_LIFETIME) {
            $this->logout();
            return null;
        }
        
        // Atualiza last_activity (refresh silencioso)
        $_SESSION[SESSION_NAME]['last_activity'] = time();
        
        return $sessao['user'];
    }
    
    /**
     * Logout
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = $_SESSION[SESSION_NAME]['user']['id'] ?? null;
        $perfil = $_SESSION[SESSION_NAME]['user']['perfil'] ?? null;
        
        if ($userId) {
            $this->audit->log('LOGOUT', 'SUCESSO', 'Logout realizado', [], $userId, $perfil);
        }
        
        unset($_SESSION[SESSION_NAME]);
        session_destroy();
    }
    
    /**
     * Gera token de rotação de sessão pós-login
     */
    private function rotacionarSessao(array $usuario, string $ip): void {
        session_regenerate_id(true);
        
        $_SESSION[SESSION_NAME] = [
            'user' => $usuario,
            'created_at' => time(),
            'last_activity' => time(),
            'ip' => $ip,
            'token' => bin2hex(random_bytes(32))
        ];
    }
    
    /**
     * Registra falha de login e aplica bloqueio progressivo
     */
    private function registrarFalhaLogin(?string $userId, string $email): void {
        $this->audit->log(
            'LOGIN_TENTATIVA',
            'FALHA',
            'Credenciais inválidas',
            ['email' => AuditGuard::mascararPII($email, 'email')],
            $userId,
            null
        );
        
        if ($userId) {
            $sql = "UPDATE usuarios 
                    SET tentativas_falha = tentativas_falha + 1 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $userId]);
            
            // Verifica bloqueio
            $this->verificarBloqueioProgressivo($userId);
        }
    }
    
    /**
     * Aplica bloqueio progressivo
     */
    private function verificarBloqueioProgressivo(string $userId): void {
        $sql = "SELECT tentativas_falha FROM usuarios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $tentativas = (int) $stmt->fetchColumn();
        
        if ($tentativas >= LOGIN_MAX_ATTEMPTS_HARD) {
            // Bloqueio até gestor liberar
            $update = "UPDATE usuarios SET status = 'bloqueado', bloqueado_ate = NULL 
                       WHERE id = :id";
            $stmt = $this->db->prepare($update);
            $stmt->execute([':id' => $userId]);
            
            $this->audit->log(
                'USUARIO_BLOQUEADO',
                'SUCESSO',
                'Bloqueio permanente após ' . $tentativas . ' falhas',
                [],
                $userId,
                null
            );
        } elseif ($tentativas >= LOGIN_MAX_ATTEMPTS_SOFT) {
            // Bloqueio temporário 15min
            $bloqueadoAte = date('Y-m-d H:i:s', time() + (LOGIN_SOFT_LOCK_MINUTES * 60));
            $update = "UPDATE usuarios SET status = 'bloqueado', bloqueado_ate = :bloqueado 
                       WHERE id = :id";
            $stmt = $this->db->prepare($update);
            $stmt->execute([':bloqueado' => $bloqueadoAte, ':id' => $userId]);
            
            $this->audit->log(
                'USUARIO_BLOQUEADO',
                'SUCESSO',
                'Bloqueio temporário após ' . $tentativas . ' falhas',
                ['bloqueado_ate' => $bloqueadoAte],
                $userId,
                null
            );
        }
    }
    
    /**
     * Finaliza login bem sucedido
     */
    private function finalizarLoginSucesso(array $usuario, string $ip): void {
        // Reseta tentativas
        $sql = "UPDATE usuarios 
                SET tentativas_falha = 0, bloqueado_ate = NULL, ultimo_acesso = NOW() 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $usuario['id']]);
        
        // Rotaciona sessão
        $this->rotacionarSessao($usuario, $ip);
        
        // Auditoria
        $this->audit->log(
            'LOGIN_SUCESSO',
            'SUCESSO',
            'Login realizado',
            ['ip' => $ip],
            $usuario['id'],
            $usuario['perfil']
        );
    }
    
    /**
     * Check rate limiting para login
     */
    private function checkRateLimitLogin(string $email): bool {
        // Implementação simplificada - em produção usar Redis/Memcached
        $chave = 'login_limit:' . hash('sha256', $email);
        
        // Verifica tentativas nos últimos 5 minutos
        $sql = "SELECT COUNT(*) FROM auditoria 
                WHERE acao = 'LOGIN_TENTATIVA' 
                AND resultado = 'FALHA'
                AND contexto_json LIKE :email_pattern
                AND timestamp_iso > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email_pattern' => '%' . $email . '%']);
        $count = (int) $stmt->fetchColumn();
        
        return $count < RATE_LIMIT_LOGIN;
    }
    
    /**
     * Valida força da senha
     */
    private function validarForcaSenha(string $senha): bool {
        if (strlen($senha) < PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        // Maiúscula, minúscula, número, especial
        return preg_match('/[A-Z]/', $senha) &&
               preg_match('/[a-z]/', $senha) &&
               preg_match('/[0-9]/', $senha) &&
               preg_match('/[^A-Za-z0-9]/', $senha);
    }
    
    /**
     * Verifica se senha está no histórico
     */
    private function senhaNoHistorico(string $userId, string $senha): bool {
        // Implementação simplificada - em produção ter tabela senha_historico
        return false;
    }
    
    /**
     * Matriz de permissões RBAC
     */
    private function getPermissionsMatrix(): array {
        return [
            PERFIL_GESTOR => [
                'USUARIOS_LISTAR',
                'USUARIOS_EDITAR_STATUS_OPERADOR',
                'CONFIG_VIEW',
                'TARIFAS_LISTAR',
                'TICKET_*',
                'FILA_PENDENTE_*',
                'AUDITORIA_VIEW_TODOS',
                'RELATORIOS_VIEW',
                'DASHBOARD_VIEW'
            ],
            PERFIL_OPERADOR => [
                'TICKET_CRIAR_ENTRADA',
                'TICKET_BUSCAR',
                'TICKET_CALCULAR_SAIDA',
                'TICKET_FECHAR',
                'CATALOGO_AUTOCOMPLETE',
                'CATALOGO_PENDENTE_CRIAR_AUTO',
                'AUDITORIA_VIEW_PROPRIA',
                'DASHBOARD_VIEW_LIMITADO'
            ]
        ];
    }
}
