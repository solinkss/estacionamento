# Sistema de Estacionamento v3.0

Sistema completo de controle de estacionamento por minutagem, mobile-first, com conformidade LGPD e arquitetura pronta para pagamentos digitais.

## 📋 Visão Geral

- **Versão:** 3.0.0
- **Stack:** PHP 8.1.25 + MariaDB 10.4.32 + Bootstrap 5.3
- **Ambiente:** localhost Windows (Apache 2.4.58)
- **Status:** Pronto para Kick-off

## ✨ Funcionalidades Principais

### Controle de Acesso
- ✅ Entrada/saída por QR Code (ULID não previsível) ou placa
- ✅ Validação de placa Mercosul (ABC1234 ou ABC1D23)
- ✅ Constraint de concorrência impede tickets duplicados
- ✅ Cadastro auxiliar não-bloqueante (marca/modelo/cor)

### Cobrança Determinística
- ✅ 5 modos: Por minuto, Hora fracionada, Hora cheia, Tarifa fixa, Diária
- ✅ Granularidade configurável (5, 10, 15, 30 min)
- ✅ Tolerância/cortesia configurável
- ✅ Regras de arredondamento (Teto, Piso, Matemático)
- ✅ Idempotency key para integração gateway futuro

### Segurança & RBAC
- ✅ Senha Argon2id com requisitos fortes (12+ chars)
- ✅ Bloqueio progressivo (5 falhas → 15min, 10 → permanente)
- ✅ Rate limiting multi-nível
- ✅ Perfis: Admin, Gestor, Operador
- ✅ Matriz de permissões explícita

### Auditoria & LGPD
- ✅ WORM (sem UPDATE/DELETE na auditoria)
- ✅ Hash encadeado SHA256 para integridade
- ✅ Mascaramento PII automático
- ✅ Retenção: 5 anos auditoria, 12 meses clientes
- ✅ Anonimização LGPD configurada

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────────────────────┐
│                   Frontend Bootstrap 5.3                │
│              (Mobile-first, Vanilla JS)                 │
└─────────────────────┬───────────────────────────────────┘
                      │ API REST /api/v1/
┌─────────────────────▼───────────────────────────────────┐
│                  Backend PHP 8.1                        │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│  │   Auth   │ │  Core    │ │ Billing  │ │  Audit   │  │
│  │  Domain  │ │ Parking  │ │  Engine  │ │  Guard   │  │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘  │
└─────────────────────┬───────────────────────────────────┘
                      │ PDO
┌─────────────────────▼───────────────────────────────────┐
│               MariaDB 10.4.32 InnoDB                    │
│  • constraint UNIQUE(placa_aberta)                      │
│  • cadastros auxiliares com status PENDENTE/ATIVO       │
│  • auditoria WORM hash encadeado                        │
└─────────────────────────────────────────────────────────┘
```

## 📁 Estrutura de Diretórios

```
/workspace
├── config/
│   └── config.php           # Configurações do sistema
├── src/
│   ├── Database.php         # Conexão PDO singleton
│   ├── Auth/
│   │   └── AuthDomain.php   # Autenticação, RBAC, sessão
│   ├── Core/
│   │   └── ParkingCore.php  # Tickets, entradas, saídas
│   ├── Billing/
│   │   └── BillingEngine.php # Motor cálculo determinístico
│   └── Audit/
│       └── AuditGuard.php   # Auditoria WORM
├── api/v1/
│   └── index.php            # API REST endpoints
├── public/
│   ├── index.php            # Dashboard operador
│   ├── css/                 # Estilos customizados
│   └── js/                  # Scripts frontend
├── database/
│   └── schema.sql           # Schema completo + seeds
└── tests/                   # Testes PHPUnit
```

## 🚀 Instalação

### Pré-requisitos
- Apache 2.4.58 (Windows)
- PHP 8.1.25 com extensões: pdo_mysql, json, mbstring
- MariaDB 10.4.32

### Passo a Passo

1. **Configurar Banco de Dados**
```sql
CREATE DATABASE estacionamento_v3 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estacionamento_v3;
SOURCE database/schema.sql;
```

2. **Configurar Aplicação**
Editar `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'estacionamento_v3');
define('DB_USER', 'root');
define('DB_PASS', '');
define('QR_SECRET_KEY', 'SUA_CHAVE_SECRETA_AQUI_32CHARS');
```

3. **Criar Usuário Admin Inicial**
```sql
INSERT INTO usuarios (id, nome, email, email_hash, perfil, status, senha_hash)
VALUES (
    UUID(),
    'Administrador',
    'admin@estacionamento.com',
    SHA2('admin@estacionamento.com', 256),
    'admin',
    'ativo',
    '$argon2id$v=19$m=65536,t=4,p=3$...' -- Gerar com password_hash()
);
```

4. **Acessar Sistema**
- URL: `http://localhost/public/`
- API Health: `http://localhost/api/v1/health`

## 🔐 Matrizes RBAC

| Recurso | Admin | Gestor | Operador |
|---------|-------|--------|----------|
| Criar entrada | ✓ | ✓ | ✓ |
| Registrar saída | ✓ | ✓ | ✓ |
| Cancelar ticket | ✓ | ✓ | ✗ |
| Reabrir ticket | ✓ | ✓ | ✗ |
| Criar/editar tarifa | ✓ | ✗ | ✗ |
| Fila validação | ✓ | ✓ | ✗ |
| Auditoria completa | ✓ | ✓ | ✗ |
| Configurações sistema | ✓ | ✗ | ✗ |
| Criar admin | ✓ | ✗ | ✗ |

## 📊 Modos de Cobrança

| Modo | Descrição | Exemplo |
|------|-----------|---------|
| POR_MINUTO | Cobra por minuto | R$ 0,10/min × 45min = R$ 4,50 |
| HORA_FRACIONADA | Proporcional à hora | 45min de R$ 10/h = R$ 7,50 |
| HORA_CHEIA | Cada fração = hora completa | 61min = 2 horas × R$ 10 = R$ 20 |
| TARIFA_FIXA | Valor único | R$ 15,00 independente tempo |
| DIARIA | Por dia (24h) | 48h = 2 diárias × R$ 50 = R$ 100 |

## 🔒 Recursos de Segurança

### Validação Placa Mercosul
```php
// Aceita ambos formatos
ParkingCore::validarPlaca('ABC1234');  // true (antigo)
ParkingCore::validarPlaca('ABC1D23');  // true (Mercosul)
ParkingCore::validarPlaca('XXX0000');  // false (inválido)
```

### Constraint Concorrência
```sql
-- Impede 2 tickets ABERTOS mesma placa
ALTER TABLE tickets 
ADD COLUMN placa_aberta VARCHAR(8) 
GENERATED ALWAYS AS (IF(status='ABERTO', placa_normalizada, NULL)) STORED,
ADD UNIQUE INDEX ux_placa_aberta (placa_aberta);
```

### Cadastro Auxiliar Não-Bloqueante
```php
// Operador digita "Toyota Corolla" (não existe)
// Sistema cria automaticamente como PENDENTE
// Ticket é criado SEM BLOQUEAR
// Admin aprova/mescla depois via fila validação
```

## 📝 Auditoria WORM

Todos os eventos são registrados com hash encadeado:

```json
{
  "audit_id": "uuid-v4",
  "timestamp_iso": "2026-07-15 14:30:45.123",
  "user_id": "uuid-operador",
  "perfil": "operador",
  "acao": "ENTRADA_CRIADA",
  "resultado": "SUCESSO",
  "detalhe_resumo": "Ticket criado para placa ABC****",
  "hash_anterior": "sha256-do-registro-anterior",
  "hash_integridade": "sha256(timestamp+acao+user+contexto+hash_anterior)"
}
```

## 🧪 Testes

### Critérios de Aceite (Definition of Done)

- [ ] Placa duplicada bloqueada por constraint DB (testar race condition)
- [ ] Cálculo por 5 modos validado com 20 casos de teste
- [ ] Motor 100% determinístico (mesmo input = mesmo output)
- [ ] QR Code ULID não previsível e único
- [ ] Auditoria WORM testada (UPDATE/DELETE falha)
- [ ] Rate limiting ativo (10 buscas/2min, 20 req/min)
- [ ] Fila PENDENTE funciona (operador não trava)
- [ ] LGPD: mascaramento PII, retenção configurada
- [ ] Idempotency_key gerado e único por ticket

### Executar Testes
```bash
cd /workspace
phpunit tests/
```

## 📄 Licença

Proprietário - Uso interno

## 📞 Suporte

Para dúvidas ou suporte técnico, contate a equipe de desenvolvimento.

---

**Documento Técnico Completo:** Ver especificação detalhada no início deste arquivo.

**Última Atualização:** Julho 2026