# Sistema de Estacionamento v3.0 - Guia de Implantação

## Visão Geral

Sistema completo de controle de estacionamento por minutagem, mobile-first, operando em localhost.

**Stack:**
- Backend: PHP 8.1.25 (tipagem estrita, PDO)
- Banco: MariaDB 10.4.32 InnoDB
- Frontend: Bootstrap 5.3 + Vanilla JS
- Servidor: Apache 2.4.58 (Windows)

---

## Pré-requisitos

### Software Necessário

1. **Apache 2.4.58+** (Windows)
2. **PHP 8.1.25+** com extensões:
   - `pdo_mysql`
   - `json`
   - `mbstring`
   - `openssl`
3. **MariaDB 10.4.32+** ou MySQL 8.0+

### Configuração do PHP (php.ini)

```ini
extension=pdo_mysql
extension=json
extension=mbstring
extension=openssl

memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 2M
post_max_size = 8M

; Timezone
date.timezone = "America/Sao_Paulo"
```

---

## Instalação Passo a Passo

### 1. Configurar Banco de Dados

```sql
-- Criar banco
CREATE DATABASE estacionamento_v3 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Criar usuário (opcional, pode usar root)
CREATE USER 'estacionamento'@'localhost' IDENTIFIED BY 'sua_senha_forte';
GRANT ALL PRIVILEGES ON estacionamento_v3.* TO 'estacionamento'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Executar Schema

```bash
# Via linha de comando
mysql -u root -p estacionamento_v3 < database/schema.sql

# Ou via phpMyAdmin/MySQL Workbench
# Importar arquivo database/schema.sql
```

### 3. Configurar Aplicação

Editar `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'estacionamento_v3');
define('DB_USER', 'root');        // Alterar se necessário
define('DB_PASS', '');            // Colocar senha se existir
define('DB_CHARSET', 'utf8mb4');

// Segurança - ALTERAR EM PRODUÇÃO!
define('QR_SECRET_KEY', 'NOVA_CHAVE_SECRETA_32_CARACTERES_AQUI');

// Ambiente
define('ENVIRONMENT', 'development'); // production em prod
```

### 4. Configurar Apache (httpd-vhosts.conf)

```apache
<VirtualHost *:80>
    ServerName estacionamento.local
    DocumentRoot "C:/caminho/para/workspace/public"
    
    <Directory "C:/caminho/para/workspace/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "logs/estacionamento-error.log"
    CustomLog "logs/estacionamento-access.log" common
</VirtualHost>
```

### 5. Habilitar mod_rewrite

No httpd.conf:
```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

### 6. .htaccess (public/.htaccess)

Criar arquivo `public/.htaccess`:

```apache
RewriteEngine On

# Redirecionar tudo para index.php (exceto arquivos existentes)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Headers de segurança
<IfModule mod_headers.c>
    Header set X-Frame-Options "DENY"
    Header set X-Content-Type-Options "nosniff"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Prevenir listagem de diretórios
Options -Indexes
```

### 7. Criar Diretórios de Log

```bash
# No Windows Explorer ou via comando
mkdir C:\caminho\para\workspace\logs
mkdir C:\caminho\para\workspace\temp

# Permissões (leitura/escrita para Apache)
icacls logs /grant IIS_IUSRS:(OI)(CI)F
icacls temp /grant IIS_IUSRS:(OI)(CI)F
```

---

## Usuário Admin Padrão

Após instalar o schema, criar usuário admin manualmente:

```sql
-- Senha padrão: Admin@12345678 (ALTERAR no primeiro acesso!)
INSERT INTO usuarios (id, nome, email, email_hash, perfil, status, senha_hash) VALUES
(
    '550e8400-e29b-41d4-a716-446655440000',
    'Administrador',
    'admin@estacionamento.local',
    SHA2('admin@estacionamento.local', 256),
    'admin',
    'ativo',
    '$argon2id$v=19$m=65536,t=4,p=3$...' -- Gerar hash Argon2id
);
```

### Gerar Hash Argon2id (PHP)

```php
<?php
// script-gerar-hash.php
$senha = 'Admin@12345678';
$hash = password_hash($senha, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 3
]);
echo $hash;
?>
```

---

## Acessando o Sistema

1. **URL:** http://estacionamento.local (ou http://localhost/workspace/public)
2. **Login:** admin@estacionamento.local
3. **Senha:** Admin@12345678 (alterar imediatamente!)

---

## Endpoints API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/health` | Health check |
| POST | `/api/v1/tickets.php` | Criar entrada/registrar saída |
| GET | `/api/v1/tickets.php` | Buscar ticket |
| GET | `/api/v1/catalogo.php` | Autocomplete marca/modelo/cor |
| GET/POST | `/api/v1/admin-catalogo.php` | Gestão cadastros pendentes |

---

## Testes Básicos

### 1. Health Check

```bash
curl http://estacionamento.local/api/v1/health
```

Resposta esperada:
```json
{
  "status": "healthy",
  "version": "3.0.0",
  "database": "connected"
}
```

### 2. Criar Entrada

```bash
curl -X POST http://estacionamento.local/api/v1/tickets.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "criar_entrada",
    "placa": "ABC1D23",
    "tipo_veiculo_id": "uuid-do-tipo",
    "marca": "Chevrolet",
    "modelo": "Onix",
    "cor": "Prata"
  }'
```

---

## Troubleshooting

### Erro: Conexão com banco falhou

- Verificar se MariaDB está rodando
- Confirmar credenciais em config.php
- Testar conexão: `mysql -u root -p`

### Erro: Session não persiste

- Verificar permissões da pasta de sessão do PHP
- Confirmar `session.save_path` no php.ini

### Erro: 403 Forbidden

- Verificar permissões do Apache
- Checar .htaccess
- Confirmar `AllowOverride All`

### Erro: Hash Argon2id não funciona

- Verificar extensão argon2 instalada
- PHP 8.1+ requer libargon2

---

## Segurança em Produção

### Checklist Obrigatório

- [ ] Alterar `QR_SECRET_KEY` no config.php
- [ ] Mudar senha do admin padrão
- [ ] Setar `ENVIRONMENT = 'production'`
- [ ] Habilitar HTTPS/TLS
- [ ] Configurar HSTS headers
- [ ] Restringir acesso por IP se possível
- [ ] Backup automático do banco
- [ ] Logs em servidor externo

### Hardening PHP

```ini
; php.ini production
expose_php = Off
display_errors = Off
log_errors = On
error_log = /caminho/para/logs/php-error.log

disable_functions = exec,passthru,shell_exec,system
allow_url_fopen = Off
```

---

## Backup e Restauração

### Backup Diário

```bash
# Script backup.sh
mysqldump -u root -p --single-transaction \
  estacionamento_v3 > backup_$(date +%Y%m%d).sql

# Manter últimos 7 dias
find /backups -name "backup_*.sql" -mtime +7 -delete
```

### Restauração

```bash
mysql -u root -p estacionamento_v3 < backup_YYYYMMDD.sql
```

---

## Suporte e Documentação

- **Documentação Técnica:** `/docs/`
- **Matriz RBAC:** Ver proposta técnica v3.0
- **LGPD:** Procedimentos em `/src/Audit/AuditGuard.php`

---

**Versão:** 3.0.0  
**Última Atualização:** Julho 2026  
**Status:** PRONTO PARA PRODUÇÃO
