# 🅿️ Sistema de Estacionamento v3.0 - Guia Completo de Implantação e Uso

**Versão:** 3.0 Executiva  
**Data:** Julho 2026  
**Status:** Pronto para Operação (HTTP Local)  
**Última Atualização:** Sprint 5 Completa

---

## 📋 Índice

1. [Visão Geral](#1-visão-geral)
2. [Requisitos do Sistema](#2-requisitos-do-sistema)
3. [Instalação Passo a Passo](#3-instalação-passo-a-passo)
4. [Configuração Inicial](#4-configuração-inicial)
5. [Guia do Operador](#5-guia-do-operador)
6. [Guia do Gestor](#6-guia-do-gestor)
7. [Guia do Administrador](#7-guia-do-administrador)
8. [Segurança e Boas Práticas](#8-segurança-e-boas-práticas)
9. [Solução de Problemas](#9-solução-de-problemas)
10. [Manutenção e Backup](#10-manutenção-e-backup)
11. [LGPD e Conformidade](#11-lgpd-e-conformidade)
12. [Próximos Passos e Melhorias Futuras](#12-próximos-passos-e-melhorias-futuras)

---

## 1. Visão Geral

### 1.1 O Que É Este Sistema?

Sistema completo de controle de estacionamento por minutagem, desenvolvido com arquitetura **mobile-first** para operação em ambiente local (localhost). Automatiza entradas, saídas, cálculos tarifários e fornece relatórios gerenciais com conformidade LGPD.

### 1.2 Principais Funcionalidades

✅ **Controle de Acesso**
- Entrada/saída por QR Code (ULID não previsível) ou placa
- Validação de placa Mercosul
- Prevenção de duplicidade (constraint de concorrência)

✅ **Cobrança Automatizada**
- 5 modos de cobrança: Por minuto, Hora fracionada, Hora cheia, Tarifa fixa, Diária
- Configuração por tipo de veículo
- Tolerância/grace period configurável
- Cortesia com justificativa auditada

✅ **Cadastro Inteligente**
- Marcas, modelos e cores com validação assíncrona
- Operador nunca trava: cadastros novos vão para fila "PENDENTE"
- Admin/gestor aprova ou mescla depois

✅ **Segurança e Conformidade**
- Senhas Argon2id com requisitos fortes
- RBAC (Admin/Gestor/Operador)
- Auditoria WORM com hash encadeado
- LGPD: mascaramento PII, retenção 5 anos/12 meses

✅ **Relatórios e Dashboard**
- Ocupação em tempo real
- Ticket médio, receita do dia
- Ranking de marcas
- Exportação de auditoria

### 1.3 Perfis de Usuário

| Perfil | Responsabilidades | Acessos Principais |
|--------|-------------------|-------------------|
| **Operador** | Entrada/saída de veículos, busca de tickets | Dashboard simplificado, entrada, saída, busca |
| **Gestor** | Operação gerencial, fila de validação, relatórios | Tudo do operador + fila PENDENTE, relatórios, desbloqueio |
| **Admin** | Configuração total, usuários, tarifas, anonimização | Acesso completo ao sistema |

---

## 2. Requisitos do Sistema

### 2.1 Requisitos Mínimos

**Servidor:**
- CPU: Dual Core 2.0 GHz
- RAM: 4 GB
- Disco: 10 GB livres
- SO: Windows 10/11, Linux Ubuntu 20.04+, macOS 11+

**Software:**
- Apache 2.4.x (ou XAMPP 8.0+, WAMP, Laragon)
- PHP 8.1.x (extensões: pdo, pdo_mysql, json, mbstring, openssl)
- MariaDB 10.4.x ou MySQL 5.7+

**Cliente (Navegador):**
- Chrome 90+, Firefox 88+, Edge 90+
- Resolução mínima: 1024x768
- JavaScript habilitado

### 2.2 Ambiente Recomendado (Produção Local)

```
Apache 2.4.58
PHP 8.1.25
MariaDB 10.4.32
Windows 10/11 Pro
Rede local isolada (sem acesso externo)
```

### 2.3 Verificação de Extensões PHP

Crie um arquivo `info.php` na pasta pública:
```php
<?php phpinfo(); ?>
```

Acesse `http://localhost/info.php` e verifique:
- ✅ PDO
- ✅ pdo_mysql
- ✅ json
- ✅ mbstring
- ✅ openssl
- ✅ hash

---

## 3. Instalação Passo a Passo

### 3.1 Download e Estrutura de Arquivos

1. Extraia os arquivos do sistema em uma pasta, ex: `C:\xampp\htdocs\estacionamento`

2. Estrutura esperada:
```
estacionamento/
├── api/
│   └── v1/
│       ├── admin-catalogo.php
│       ├── catalogo.php
│       ├── dashboard.php
│       └── tickets.php
├── config/
│   └── config.php
├── database/
│   └── schema.sql
├── public/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   ├── buscar.js
│   │   ├── dashboard.js
│   │   ├── entrada.js
│   │   ├── fila-validacao.js
│   │   ├── index-dashboard.js
│   │   ├── saida.js
│   │   └── utils.js
│   ├── buscar.php
│   ├── dashboard.php
│   ├── entrada.php
│   ├── fila-validacao.php
│   ├── index.php
│   ├── login.php
│   └── saida.php
├── src/
│   ├── Audit/
│   │   └── AuditGuard.php
│   ├── Auth/
│   │   └── AuthDomain.php
│   ├── Billing/
│   │   └── BillingEngine.php
│   ├── Core/
│   │   └── ParkingCore.php
│   └── Security/
│       └── RateLimiter.php
├── tests/
│   └── BillingEngineTest.php
├── create_admin.php
├── anonymize_clients.php
├── INSTALL.md
└── MANUAL_USUARIO.md (este arquivo)
```

### 3.2 Criação do Banco de Dados

**Via MySQL/MariaDB Command Line:**
```bash
mysql -u root -p
```

```sql
CREATE DATABASE estacionamento_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

EXIT;
```

**Via phpMyAdmin:**
1. Acesse `http://localhost/phpmyadmin`
2. Clique em "Novo" no menu lateral
3. Nome: `estacionamento_db`
4. Collation: `utf8mb4_unicode_ci`
5. Clique em "Criar"

### 3.3 Importação do Schema

**Via Linha de Comando:**
```bash
mysql -u root -p estacionamento_db < database/schema.sql
```

**Via phpMyAdmin:**
1. Selecione o banco `estacionamento_db`
2. Clique na aba "Importar"
3. Escolha o arquivo `database/schema.sql`
4. Clique em "Executar"

**Verificação:**
```sql
USE estacionamento_db;
SHOW TABLES;
-- Deve listar 11 tabelas
SELECT COUNT(*) FROM cores_veiculo WHERE status='ATIVO';
-- Deve retornar 15
SELECT COUNT(*) FROM marcas_veiculo WHERE status='ATIVO';
-- Deve retornar ~31 (marcas seed)
```

### 3.4 Configuração do Arquivo config/config.php

Edite o arquivo `config/config.php`:

```php
<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'estacionamento_db');
define('DB_USER', 'root');
define('DB_PASS', ''); // Em produção, use senha forte!
define('DB_CHARSET', 'utf8mb4');

// Configurações da Aplicação
define('APP_NAME', 'Estacionamento v3.0');
define('APP_URL', 'http://localhost/estacionamento/public');
define('TIMEZONE', 'America/Sao_Paulo');

// Sessão e Segurança
define('SESSION_LIFETIME', 900); // 15 minutos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// Rate Limiting
define('RATE_LIMIT_SEARCH', 10); // buscas por 2 min
define('RATE_LIMIT_API', 20); // req por minuto
define('RATE_LIMIT_LOGIN', 5); // logins por 5 min

// Auditoria
define('AUDIT_RETENTION_YEARS', 5);
define('CLIENT_RETENTION_MONTHS', 12);
```

**⚠️ IMPORTANTE:** Em produção, altere `DB_PASS` para uma senha forte!

### 3.5 Configuração do Apache (Opcional)

Se estiver usando Apache direto (não XAMPP/WAMP), crie `.htaccess` na pasta `public/`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]

# Headers de segurança
Header set X-Frame-Options "SAMEORIGIN"
Header set X-Content-Type-Options "nosniff"
Header set X-XSS-Protection "1; mode=block"
```

### 3.6 Permissões de Arquivos (Linux)

```bash
cd /var/www/html/estacionamento
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 config/
```

---

## 4. Configuração Inicial

### 4.1 Criação do Primeiro Administrador

**Método 1: Via Linha de Comando (Recomendado)**

```bash
cd /caminho/para/estacionamento
php create_admin.php
```

O script solicitará:
```
=== CRIAÇÃO DE ADMINISTRADOR ===
Nome completo: João Silva
Email: admin@estacionamento.com.br
Senha: SuaSenhaForte123!
Confirme a senha: SuaSenhaForte123!

✅ Administrador criado com sucesso!
ID: 019a3b4c-5d6e-7f8g-9h0i-1j2k3l4m5n6o
Email: admin@estacionamento.com.br
Perfil: admin

⚠️ IMPORTANTE: Delete este arquivo após o uso!
rm create_admin.php
```

**Método 2: Via Navegador**

1. Acesse: `http://localhost/estacionamento/create_admin.php`
2. Preencha o formulário:
   - Nome completo
   - Email válido
   - Senha (mín. 12 caracteres, com maiúscula, minúscula, número e caractere especial)
   - Confirme a senha
3. Clique em "Criar Administrador"
4. Anote as credenciais em local seguro

**⚠️ CRÍTICO:** Após criar o admin, DELETE o arquivo:
```bash
# Linux/Mac
rm create_admin.php

# Windows (CMD)
del create_admin.php

# Windows (PowerShell)
Remove-Item create_admin.php
```

### 4.2 Primeiro Login

1. Acesse: `http://localhost/estacionamento/public/login.php`
2. Credenciais:
   - Email: o email cadastrado
   - Senha: a senha definida
3. Marque "Lembrar-me" se estiver em dispositivo seguro
4. Clique em "Entrar"

### 4.3 Configuração de Tarifas (Primeiro Acesso)

Como **Admin**:

1. Menu → Configurações → Tarifas
2. Clique em "Nova Tarifa"
3. Preencha:
   - Tipo de Veículo: Carro de passeio
   - Modo de Cobrança: POR_MINUTO (ou outro)
   - Valor: R$ 0,50 (exemplo)
   - Vigência Início: data atual
   - Motivo: "Tarifa inicial de implantação"
4. Salve

**Tipos de Veículo já cadastrados:**
- Carro de passeio
- Moto
- Caminhonete / SUV
- Van
- Caminhão

### 4.4 Configuração de Tolerância e Granularidade

Como **Admin**:

1. Menu → Configurações → Sistema
2. Ajuste:
   - Tolerância (minutos): 15 (cortesia)
   - Granularidade (minutos): 15
   - Regra de Arredondamento: TETO
   - Diária (virada horas): 24
3. Salve

---

## 5. Guia do Operador

### 5.1 Acesso ao Sistema

**URL:** `http://localhost/estacionamento/public/index.php`

**Perfil:** Operador

**Telas Disponíveis:**
- Dashboard (início)
- Nova Entrada
- Registrar Saída
- Buscar Ticket

### 5.2 Dashboard do Operador

Ao logar, você verá:

📊 **Cards de Status:**
- Vagas Ocupadas (em tempo real)
- Tickets Abertos Hoje
- Receita do Dia (R$)
- Ticket Médio (R$)

🕐 **Últimos Tickets:**
- Lista dos 10 últimos tickets criados
- Status colorido (Aberto=Verde, Fechado=Azul, Cancelado=Vermelho)

⚡ **Acesso Rápido:**
- Botões grandes para "Nova Entrada" e "Registrar Saída"

### 5.3 Registrar Nova Entrada

**Passo a Passo:**

1. Clique em **"Nova Entrada"** no menu ou dashboard
2. Preencha os campos obrigatórios:

   **Placa (Obrigatório)**
   - Formato: AAA1A23 ou AAA-1A23 (Mercosul)
   - O sistema normaliza automaticamente
   - Exemplos válidos: ABC1D23, XYZ9B87

   **Tipo de Veículo (Obrigatório)**
   - Selecione: Carro, Moto, SUV, Van, Caminhão

   **Marca (Opcional mas recomendado)**
   - Digite e selecione no autocomplete
   - Se não existir, será criada como "PENDENTE" automaticamente
   - Ex: "Toyota", "Chevrolet", "Honda"

   **Modelo (Opcional mas recomendado)**
   - Disponível após selecionar marca
   - Se não existir, será criada como "PENDENTE"
   - Ex: "Corolla", "Onix", "Civic"

   **Cor (Opcional mas recomendado)**
   - Digite e selecione
   - Cores comuns já cadastradas (Branco, Preto, Prata, etc.)
   - Nova cor vai para "PENDENTE"

3. Clique em **"Registrar Entrada"**

**Resultado:**
- Ticket criado com status "ABERTO"
- QR Code único (ULID 26 caracteres) gerado
- Comprovante pode ser impresso ou enviado
- Tempo de entrada registrado em UTC

**⚠️ Atenção:**
- Se a placa já tiver ticket aberto, o sistema bloqueará com erro: "Já existe ticket aberto para esta placa"
- Isso previne duplicidade mesmo com dois operadores tentando simultaneamente

### 5.4 Registrar Saída e Cobrança

**Passo a Passo:**

1. Clique em **"Registrar Saída"** no menu
2. Identifique o ticket:
   
   **Opção A: Ler QR Code**
   - Use leitor USB ou digite o código ULID (26 chars)
   
   **Opção B: Buscar por Placa**
   - Digite a placa normalizada
   - Sistema busca último ticket ABERTO

3. O sistema exibirá:
   - Dados do veículo (placa, marca, modelo, cor)
   - Hora de entrada
   - Tempo total (minutos)
   - Valor calculado (conforme tarifa vigente)
   - Forma de pagamento (padrão: MANUAL)

4. **Conferir valor:**
   - Verifique se o cálculo está correto
   - O sistema usa modo determinístico (sempre mesmo resultado)

5. **Confirmar Pagamento:**
   - Selecione método: MANUAL, PIX (futuro), CARTÃO (futuro)
   - Clique em **"Fechar Ticket"**

**Cortesia (Valor Zerado):**
- Marque "Aplicar Cortesia"
- Selecione motivo: "Cortesia", "Funcionário", "Deficiência", "Outro"
- Digite observação (opcional)
- Valor será zerado e auditado como "CORTESIA"

**Resultado:**
- Ticket muda para status "FECHADO"
- Hora de saída registrada
- Valor e método de pagamento salvos
- Auditoria gera evento "SAIDA_FECHADA"

### 5.5 Buscar Ticket

**Quando usar:**
- Cliente perdeu comprovante
- Conferência de status
- Impressão de segunda via

**Passo a Passo:**

1. Clique em **"Buscar Ticket"** no menu
2. Digite:
   - Placa (último ticket aberto)
   - OU Código QR/ULID (ticket específico)
3. Clique em **"Buscar"**

**Resultado:**
- Dados completos do ticket
- Status atual (ABERTO/FECHADO/CANCELADO)
- Valores e tempos
- Botão "Imprimir Comprovante"

### 5.6 Impressão de Comprovante

**Na Entrada:**
- Após registrar entrada, clique em "Imprimir"
- Comprovante contém:
  - QR Code (ULID)
  - Placa
  - Marca/Modelo/Cor
  - Hora de entrada
  - Instruções de pagamento

**Na Saída:**
- Após fechar ticket, clique em "Imprimir Recibo"
- Recibo contém:
  - QR Code
  - Placa
  - Tempo total
  - Valor pago
  - Método de pagamento
  - Data/hora de saída

### 5.7 Erros Comuns e Soluções

| Erro | Causa | Solução |
|------|-------|---------|
| "Placa inválida" | Formato incorreto | Use formato Mercosul: 3 letras + 1 número + 1 letra + 2 números |
| "Já existe ticket aberto" | Duplicidade | Verifique se veículo já entrou; use busca por placa |
| "Marca não encontrada" | Cadastro pendente | Digite novamente; sistema criará como PENDENTE automaticamente |
| "Valor não calculado" | Sem tarifa vigente | Contate gestor para configurar tarifa |
| "Sessão expirada" | 15min inatividade | Faça login novamente |

---

## 6. Guia do Gestor

### 6.1 Acesso ao Sistema

**Perfil:** Gestor

**Telas Disponíveis:**
- Tudo que operador tem +
- Fila de Validação (Pendentes)
- Relatórios Gerenciais
- Dashboard Completo
- Desbloqueio de Usuários

### 6.2 Fila de Validação de Cadastros

**Objetivo:** Aprovar ou corrigir marcas/modelos/cores criados como PENDENTE pelos operadores.

**Acesso:** Menu → Administração → Fila de Validação

**Funcionamento:**

1. **Visualizar Pendências:**
   - Abas: "Marcas" | "Modelos" | "Cores"
   - Filtros: Data, Criado por ticket, Status
   - Ordenação: Mais recentes primeiro

2. **Aprovar Cadastro:**
   - Selecione item PENDENTE
   - Clique em "Aprovar"
   - Status muda para ATIVO
   - Auditoria: "CADASTRO_APROVADO"

3. **Mesclar Cadastro (Corrigir):**
   - Situação: Operador digitou "Toytota" (errado) e "Toyota" (certo) já existe
   - Selecione "Toytota" (PENDENTE)
   - Clique em "Mesclar"
   - Selecione "Toyota" (ATIVO) como destino
   - Sistema:
     - Atualiza todos tickets com marca_id correto
     - Marca "Toytota" como INATIVO
     - Auditoria: "CADASTRO_MESCLADO" com de/para

4. **Inativar Cadastro:**
   - Use apenas para cadastros obsoletos ou errados sem alternativa
   - Não mescla tickets (eles permanecem com referência antiga)

**Expectativa de Uso:**
- **Cores:** Fila seca em semanas (15-20 cores padrão)
- **Marcas:** Estabiliza em 1-2 meses (70 marcas principais seed)
- **Modelos:** Fluxo contínuo baixo (lançamentos novos constantes)

### 6.3 Relatórios Gerenciais

**Acesso:** Menu → Relatórios

**Tipos de Relatório:**

1. **Receita por Período**
   - Filtros: Data início/fim, Tipo veículo, Forma pagamento
   - Exibe: Total, Média diária, Comparativo período anterior
   - Export: CSV, PDF

2. **Ticket Médio**
   - Por tipo de veículo
   - Por hora do dia (pico x fora pico)
   - Evolução mensal

3. **Ranking de Marcas**
   - Top 10 marcas mais frequentes
   - Percentual sobre total
   - Comparativo mensal

4. **Ocupação por Horário**
   - Heatmap de ocupação (hora x dia da semana)
   - Identifica picos e ociosidade

5. **Tickets Cancelados**
   - Lista com justificativas
   - Operador que cancelou
   - Motivo mais comum

**Exportação:**
- CSV: Abre no Excel/Planilhas
- PDF: Formatado para impressão
- Ambos assinados digitalmente (hash de integridade)

### 6.4 Dashboard Gerencial

**Acesso:** Menu → Dashboard

**Diferenças vs Dashboard Operador:**

| Operador | Gestor |
|----------|--------|
| Vagas ocupadas | + Receita acumulada mês |
| Tickets hoje | + Ticket médio histórico |
| Receita dia | + Gráfico de ocupação |
| Últimos tickets | + Ranking marcas (gráfico) |
| - | + Comparativo semana/mês |

**Gráficos:**
- Occupação última semana (barras)
- Receita por dia (linha)
- Top 5 marcas (pizza)

### 6.5 Gestão de Usuários (Operadores)

**Acesso:** Menu → Administração → Usuários

**Ações Permitidas:**

1. **Listar Operadores:**
   - Nome, email, status, último acesso
   - Filtro por status (Ativo/Bloqueado/Inativo)

2. **Bloquear/Desbloquear:**
   - Bloqueio automático: 5 falhas → 15min lock, 10 falhas → bloqueio até liberação
   - Gestor pode desbloquear operadores
   - Auditoria: "DESBLOQUEIO"

3. **Reset de Senha:**
   - Gere token temporário
   - Operador deve trocar no próximo login
   - Auditoria: "SENHA_RESET"

4. **Inativar Operador:**
   - Para desligamentos
   - Impede novo login
   - Tickets históricos mantêm referência

**⚠️ Restrições:**
- Gestor NÃO pode criar outros gestores
- Gestor NÃO pode criar admins
- Gestor só edita operadores

### 6.6 Reabertura de Tickets (Contestação)

**Quando usar:**
- Cliente contestou valor
- Erro de lançamento na saída
- Pagamento não confirmado

**Passo a Passo:**

1. Busque ticket FECHADO
2. Clique em "Solicitar Reabertura"
3. Justificativa obrigatória (mín. 20 caracteres):
   - Ex: "Cliente alegou que permaneceu apenas 30min, sistema registrou 1h. Verificar câmeras."
4. Submeta para aprovação (auto-aprovado para gestor)
5. Ticket volta para status ABERTO
6. Refaça saída com valores corretos

**Auditoria:**
- Evento: "TICKET_REABERTO"
- Justificativa salva em contexto_json
- Perfil que reabriu registrado

### 6.7 Erros Comuns e Soluções

| Erro | Causa | Solução |
|------|-------|---------|
| "Sem permissão" | Tentativa de ação de admin | Solicite ao administrador |
| "Nenhum pendente encontrado" | Fila vazia | Bom sinal! Cadastros estão consistentes |
| "Não é possível mesclar" | Nenhum registro ATIVO similar | Crie novo registro ATIVO manualmente |
| "Reabertura negada" | Já reaberto anteriormente | Apenas admin pode reabrir múltiplas vezes |

---

## 7. Guia do Administrador

### 7.1 Acesso ao Sistema

**Perfil:** Admin

**Telas Disponíveis:**
- Acesso completo a todas as funcionalidades
- Configurações do sistema
- Gestão de tarifas
- Gestão de usuários (todos perfis)
- Auditoria completa
- LGPD e anonimização

### 7.2 Configurações do Sistema

**Acesso:** Menu → Configurações → Sistema

**Parâmetros Configuráveis:**

1. **Modo de Cobrança Padrão:**
   - POR_MINUTO: Cobra cada minuto exato
   - HORA_FRACIONADA: Divide hora em frações (ex: 15min = 1/4 do valor hora)
   - HORA_CHEIA: Arredonda para hora seguinte (teto)
   - TARIFA_FIXA: Valor único independente do tempo
   - DIARIA: Valor por dia (virada configurável)

2. **Granularidade (minutos):**
   - Opções: 5, 10, 15, 30
   - Define blocos mínimos de cobrança
   - Padrão: 15 minutos

3. **Tolerância (minutos):**
   - Período de cortesia automática
   - Ex: 15min → até 15min grátis
   - Padrão: 15 minutos

4. **Regra de Arredondamento:**
   - TETO: Sempre arredonda para cima (1.1 → 2)
   - PISO: Sempre arredonda para baixo (1.9 → 1)
   - MATEMATICO: Arredondamento tradicional (1.5 → 2, 1.4 → 1)

5. **Diária (Virada em Horas):**
   - Após quantas horas conta como novo dia
   - Padrão: 24 horas
   - Ex: 20 horas → entrada 10h, saída 12h dia seguinte = 1.5 dias

**Auditoria:**
- Toda alteração gera evento "CONFIG_ALTERADA"
- Diff JSON com valores antigos/novos
- Motivo obrigatório

### 7.3 Gestão de Tarifas

**Acesso:** Menu → Configurações → Tarifas

**Criar Nova Tarifa:**

1. Clique em "Nova Tarifa"
2. Preencha:
   - **Tipo de Veículo:** Carro, Moto, SUV, etc.
   - **Modo de Cobrança:** Um dos 5 modos
   - **Valor:** R$ (ex: 0.50 por minuto)
   - **Granularidade:** Opcional (sobrescreve global)
   - **Tolerância:** Opcional (sobrescreve global)
   - **Vigência Início:** Data/hora inicial
   - **Vigência Fim:** Opcional (deixa em branco para indefinido)
   - **Motivo:** Obrigatório (ex: "Reajuste anual 2026")

3. Salve

**Regras de Conflito:**
- Apenas UMA tarifa ativa por tipo_veiculo + modo_cobranca + vigência
- Se houver sobreposição, sistema usa vigência mais recente
- Tarifas antigas são mantidas para histórico

**Histórico de Tarifas:**
- Visualize todas tarifas (ativas e inativas)
- Compare de/para entre períodos
- Exporte histórico para auditoria

**Editar/Inativar Tarifa:**
- Clique na tarifa existente
- Altere valor ou vigência_fim
- Motivo obrigatório para alterações
- Sistema versiona automaticamente

### 7.4 Gestão de Tipos de Veículo

**Acesso:** Menu → Configurações → Tipos de Veículo

**Padrão (já cadastrados):**
- Carro de passeio
- Moto
- Caminhonete / SUV
- Van
- Caminhão

**Adicionar Novo Tipo:**
1. Clique em "Novo Tipo"
2. Nome (único)
3. Descrição (opcional)
4. Ativo (sim/não)
5. Salve

**Usos:**
- Cada tipo pode ter tarifas independentes
- Relatórios filtram por tipo
- Estatísticas separam por tipo

### 7.5 Gestão de Usuários

**Acesso:** Menu → Administração → Usuários

**Ações Completas:**

1. **Criar Usuário:**
   - Admin: Pode criar admin, gestor, operador
   - Campos: Nome, email, perfil, senha
   - Validação de senha forte (12+ chars, complexidade)

2. **Editar Usuário:**
   - Alterar nome, email, perfil
   - Ativar/inativar/bloquear
   - Reset de senha

3. **Excluir Usuário:**
   - Na verdade inativa (não deleta por auditoria)
   - Tickets históricos mantêm referência

4. **Logs de Acesso:**
   - Último login
   - Tentativas falhas
   - Status de bloqueio

**Matriz de Permissões:**
- Veja seção "Matriz RBAC" neste manual
- Admin tem acesso total (*)
- Todas ações auditadas

### 7.6 Auditoria Completa

**Acesso:** Menu → Administração → Auditoria

**Filtros Disponíveis:**
- Período (data início/fim)
- Usuário
- Ação (tipo de evento)
- Resultado (Sucesso/Falha/Negado)
- IP terminal
- Placa (busca no contexto JSON)

**Colunas Exibidas:**
- Timestamp (UTC convertido para local)
- Usuário
- Perfil
- Ação
- Resultado
- Resumo
- IP

**Visualizar Detalhe:**
- Clique no registro
- Modal com contexto_json completo
- Hash de integridade
- Hash anterior (encadeamento)

**Exportar:**
- CSV: Todos campos, abre no Excel
- PDF: Formatado, com hash de assinatura
- Ambos incluem cabeçalho com período e filtros

**Eventos de Auditoria:**
```
LOGIN_TENTATIVA, LOGIN_SUCESSO, LOGOUT
USUARIO_CRIADO, USUARIO_ADMIN_CRIADO, USUARIO_STATUS_ALTERADO
SENHA_RESET, SENHA_ALTERADA, DESBLOQUEIO
CONFIG_VIEW, CONFIG_ALTERADA
TARIFA_LISTADA, TARIFA_ALTERADA, TARIFA_HISTORICO_VIEW
ENTRADA_CRIADA, TICKET_BUSCADO, SAIDA_CALCULADA, SAIDA_FECHADA
TICKET_CANCELADO, TICKET_REABERTO, RECALCULO
MARCA_PENDENTE_CRIADA, MODELO_PENDENTE_CRIADA, COR_PENDENTE_CRIADA
CADASTRO_APROVADO, CADASTRO_MESCLADO, CADASTRO_INATIVADO
AUDITORIA_CONSULTADA, AUDITORIA_EXPORTADA
CLIENTE_ANONIMIZADO, DIREITO_TITULAR_ATENDIDO
TENTATIVA_NAO_AUTORIZADA
```

### 7.7 LGPD e Anonimização

**Acesso:** Menu → Administração → LGPD

**Funcionalidades:**

1. **Listar Clientes:**
   - Nome, documento mascarado, última interação
   - Filtro: Ativos, Anonimizados, Por período

2. **Anonimização Manual:**
   - Selecione cliente
   - Confirme ação (irreversível)
   - Sistema:
     - Limpa nome, telefone, email
     - Mantém documento_hash (para estatística)
     - Define anonimizado_at
     - Auditoria: "CLIENTE_ANONIMIZADO"

3. **Anonimização Automática:**
   - Script `anonymize_clients.php` roda via cron
   - Critério: ultima_interacao_at > 12 meses atrás
   - Executa diariamente às 02:00

4. **Direitos do Titular:**
   - Acesso: Exporta todos dados do titular em JSON
   - Correção: Edita dados cadastrais
   - Exclusão: Anonimiza (não deleta tickets por obrigação fiscal)
   - Auditoria: "DIREITO_TITULAR_ATENDIDO"

**Retenção de Dados:**
- Auditoria: 5 anos (obrigação fiscal)
- Clientes ativos: Indefinido (enquanto houver interação)
- Clientes inativos: 12 meses após última interação → anonimização
- Tickets anonimizados: Mantêm placa para estatística, desvinculam cliente

### 7.8 Execução de Scripts de Manutenção

**Script: anonymize_clients.php**

Executa anonimização automática de clientes inativos há 12+ meses.

**Via Linha de Comando:**
```bash
php anonymize_clients.php
```

**Via Cron (Linux):**
```bash
# Edite crontab
crontab -e

# Adicione linha (diário às 02:00)
0 2 * * * /usr/bin/php /caminho/para/anonymize_clients.php >> /var/log/estacionamento_anon.log 2>&1
```

**Via Agendador Tarefas (Windows):**
1. Abra "Agendador de Tarefas"
2. Crie tarefa básica
3. Programa/script: `C:\php\php.exe`
4. Argumentos: `C:\caminho\estacionamento\anonymize_clients.php`
5. Agendar: Diariamente, 02:00

### 7.9 Erros Comuns e Soluções

| Erro | Causa | Solução |
|------|-------|---------|
| "Conflito de vigência" | Duas tarifas ativas mesmo período | Inative uma ou ajuste vigência_fim |
| "Não é possível excluir admin" | Último admin ativo | Crie outro admin antes |
| "Hash de auditoria inválido" | Tentativa de manipulação | Investigue; tabela é WORM |
| "Anonimização falhou" | Cliente com tickets abertos | Feche tickets primeiro |

---

## 8. Segurança e Boas Práticas

### 8.1 Segurança em Ambiente HTTP (Local)

**⚠️ AVISO:** Este sistema opera em HTTP por padrão para ambientes locais. Para produção com acesso externo, HTTPS é OBRIGATÓRIO.

**Medidas de Mitigação (HTTP Local):**

1. **Rede Isolada:**
   - Servidor em rede local sem acesso à internet
   - Firewall bloqueia portas 80/443 para externa
   - Apenas IPs internos acessam

2. **Autenticação Forte:**
   - Senhas Argon2id com parâmetros altos
   - Requisitos: 12+ chars, maiúscula, minúscula, número, especial
   - Histórico últimas 3 senhas (não repetir)

3. **Bloqueio Progressivo:**
   - 5 falhas → bloqueio 15 minutos
   - 10 falhas → bloqueio até admin liberar
   - CAPTCHA lógico após 3 falhas

4. **Sessão Segura:**
   - 15 minutos inatividade → expira
   - 8 horas absoluto → expira
   - Rotação de ID após login
   - Cookie HttpOnly, SameSite=Strict

5. **Rate Limiting:**
   - 10 buscas de placa / 2 minutos / terminal
   - 20 requisições API / minuto / IP
   - 5 logins / 5 minutos / conta

6. **Mascaramento de Dados:**
   - PII mascarado antes de persistir
   - Logs nunca mostram dados completos
   - Erros genéricos ("Operação não autorizada")

### 8.2 Política de Senhas

**Requisitos:**
- Mínimo 12 caracteres
- Pelo menos 1 maiúscula (A-Z)
- Pelo menos 1 minúscula (a-z)
- Pelo menos 1 número (0-9)
- Pelo menos 1 caractere especial (!@#$%^&*()_+-=)

**Exemplos Válidos:**
- `Estac2026!Seguro`
- `Parking@123Safe`
- `M0to#Resistente`

**Exemplos Inválidos:**
- `senha123` (curta, sem maiúscula/especial)
- `Senhaforte` (sem número)
- `123456789012` (sem letras/especial)

**Troca Periódica:**
- Recomenda-se troca a cada 90 dias
- Admin pode forçar reset
- Histórico impede repetição das últimas 3

### 8.3 Backup e Recuperação

**Backup Diário (Recomendado):**

**Via mysqldump (Linha de Comando):**
```bash
# Script backup.sh (Linux)
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u root -p'Senha' estacionamento_db > /backups/estacionamento_$DATE.sql
gzip /backups/estacionamento_$DATE.sql
find /backups -name "estacionamento_*.sql.gz" -mtime +30 -delete
```

**Via Agendador (Windows):**
```batch
:: backup.bat
@echo off
set DATE=%date:~-4%%date:~3,2%%date:~0,2%_%time:~0,2%%time:~3,2%
mysqldump -u root -pSenha estacionamento_db > C:\backups\estacionamento_%DATE%.sql
```

**Cron (Linux - diário 03:00):**
```bash
0 3 * * * /caminho/para/backup.sh
```

**Agendador (Windows - diário 03:00):**
1. Crie tarefa no Agendador
2. Ação: `C:\caminho\backup.bat`
3. Repetir: Diariamente, 03:00

**Política de Retenção:**
- Diários: 30 dias
- Semanais: 12 semanas
- Mensais: 12 meses

**Teste de Restauração:**
- Mensalmente, restaure backup em ambiente de teste
- Valide integridade dos dados
- Documente tempo de recuperação (RTO)

### 8.4 Monitoramento

**Logs a Monitorar:**

1. **Apache Access Log:**
   - Localização: `/var/log/apache2/access.log` ou `C:\xampp\apache\logs\access.log`
   - O que buscar: IPs desconhecidos, muitos 403/404

2. **Apache Error Log:**
   - Localização: `/var/log/apache2/error.log`
   - O que buscar: Erros PHP, tentativas de SQL injection

3. **Auditoria do Sistema:**
   - Menu → Auditoria
   - Filtre por "TENTATIVA_NAO_AUTORIZADA"
   - Investigue múltiplas falhas

4. **Performance do Banco:**
   - Queries lentas (>1s)
   - Locks prolongados
   - Espaço em disco

**Alertas Recomendados:**
- 10+ falhas de login em 5 minutos → Email para admin
- Backup falhou → Notificação imediata
- Disco >80% cheio → Alerta
- 5+ tickets cancelados em 1 hora → Revisão

### 8.5 Hardening Adicional (Opcional)

**Para Ambientes Críticos:**

1. **HTTPS (Recomendado para Produção):**
   ```bash
   # Let's Encrypt (Linux)
   certbot --apache -d estacionamento.empresa.com.br
   ```

2. **2FA para Admins:**
   - Implementar Google Authenticator
   - Obrigatório para perfil admin
   - Opcional para gestor

3. **Whitelist de IPs:**
   - Restrinja acesso a IPs conhecidos
   - Configure no Apache ou firewall

4. **Logs de Segurança Separados:**
   - Envie logs para SIEM
   - Correlação de eventos

5. **Varredura de Vulnerabilidades:**
   - OWASP ZAP trimestral
   - Dependências PHP atualizadas

---

## 9. Solução de Problemas

### 9.1 Problemas de Instalação

**Erro: "PDO extension not found"**

**Causa:** Extensão PDO não habilitada no PHP

**Solução:**
1. Edite `php.ini` (localize com `php --ini`)
2. Descomente linhas:
   ```ini
   extension=pdo
   extension=pdo_mysql
   ```
3. Reinicie Apache:
   ```bash
   sudo systemctl restart apache2  # Linux
   net stop Apache2.4 && net start Apache2.4  # Windows
   ```

**Erro: "Access denied for user 'root'@'localhost'"**

**Causa:** Senha do banco incorreta em `config/config.php`

**Solução:**
1. Verifique credenciais no `config/config.php`
2. Teste conexão:
   ```bash
   mysql -u root -p
   ```
3. Se necessário, reset senha root:
   ```sql
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'nova_senha';
   FLUSH PRIVILEGES;
   ```

**Erro: "Table 'estacionamento_db.usuarios' doesn't exist"**

**Causa:** Schema não importado

**Solução:**
```bash
mysql -u root -p estacionamento_db < database/schema.sql
```

### 9.2 Problemas de Login

**Erro: "Credenciais inválidas"**

**Causas Possíveis:**
- Email ou senha incorretos
- Usuário inativo/bloqueado
- Caps Lock ativado

**Solução:**
1. Verifique caps lock
2. Confirme email exato (case-sensitive)
3. Admin: Verifique status do usuário
4. Se bloqueado: Aguarde 15min ou peça desbloqueio

**Erro: "Sessão expirada"**

**Causa:** 15 minutos de inatividade ou 8 horas absolutas

**Solução:**
- Faça login novamente
- Para evitar: Interaja com sistema a cada 10min

### 9.3 Problemas de Entrada/Saída

**Erro: "Placa já possui ticket aberto"**

**Causa:** Veículo já registrado e não saiu

**Solução:**
1. Busque ticket por placa
2. Verifique status (deve estar ABERTO)
3. Se encontrou: Registre saída normalmente
4. Se não encontrou: Possível inconsistência; contate admin

**Erro: "QR Code inválido"**

**Causas:**
- Código digitado errado
- QR Code danificado
- Ticket já fechado/cancelado

**Solução:**
1. Verifique digitação (26 caracteres, sem espaços)
2. Use busca por placa como alternativa
3. Confira se ticket não foi cancelado

**Erro: "Valor não calculado"**

**Causa:** Sem tarifa vigente para tipo de veículo

**Solução:**
1. Admin: Menu → Tarifas
2. Crie tarifa para tipo_veiculo específico
3. Defina vigência_inicio <= data atual
4. Ative tarifa

### 9.4 Problemas de Performance

**Sistema Lento**

**Causas Possíveis:**
- Banco de dados sem índices
- Muitas conexões simultâneas
- Hardware insuficiente

**Solução:**
1. Verifique índices:
   ```sql
   SHOW INDEX FROM tickets;
   ```
2. Otimize tabelas:
   ```sql
   OPTIMIZE TABLE tickets;
   OPTIMIZE TABLE auditoria;
   ```
3. Aumente recursos (RAM, CPU)
4. Considere SSD se usar HDD

**Timeout em Operações**

**Causa:** Query demorada ou lock prolongado

**Solução:**
1. Verifique locks:
   ```sql
   SHOW OPEN TABLES WHERE In_use > 0;
   ```
2. Mate processos travados:
   ```sql
   SHOW PROCESSLIST;
   KILL <process_id>;
   ```
3. Reduza carga simultânea

### 9.5 Problemas de Auditoria

**Erro: "Hash de integridade inválido"**

**Causa:** Tentativa de alteração manual na tabela auditoria

**Solução:**
1. NÃO tente corrigir manualmente
2. Investigue origem (possível ataque interno)
3. Restore de backup se crítico
4. Tabela é WORM por design

### 9.6 Contato Suporte

Para problemas não listados:
1. Verifique logs (`/var/log/` ou `C:\xampp\logs\`)
2. Consulte documentação (este manual)
3. Capture screenshot do erro
4. Contate equipe técnica com:
   - Versão do sistema
   - SO e versões (Apache, PHP, MariaDB)
   - Passos para reproduzir
   - Logs relevantes

---

## 10. Manutenção e Backup

### 10.1 Rotina de Manutenção Diária

**Operador:**
- [ ] Verificar se todos tickets do dia foram fechados
- [ ] Conferir valor de cortesia (se aplicável)
- [ ] Reportar anomalias ao gestor

**Gestor:**
- [ ] Validar fila de pendentes (marcas/modelos/cores)
- [ ] Aprovar/meclar cadastros PENDENTE
- [ ] Revisar tickets cancelados
- [ ] Conferir relatório de receita do dia

**Admin:**
- [ ] Verificar logs de segurança
- [ ] Monitorar espaço em disco
- [ ] Validar backup noturno

### 10.2 Rotina de Manutenção Semanal

**Gestor:**
- [ ] Gerar relatório semanal de receita
- [ ] Analisar ticket médio
- [ ] Revisar ranking de marcas
- [ ] Planejar ajustes de tarifa (se necessário)

**Admin:**
- [ ] Auditar logs de tentativas não autorizadas
- [ ] Revisar usuários bloqueados
- [ ] Testar restore de backup (amostra)
- [ ] Atualizar sistema (se patches disponíveis)

### 10.3 Rotina de Manutenção Mensal

**Admin:**
- [ ] Executar `anonymize_clients.php` manual (se não automatizado)
- [ ] Revisar política de senhas (forçar troca se 90 dias)
- [ ] Exportar auditoria do mês (backup adicional)
- [ ] Reunião de revisão com gestores

**Todos:**
- [ ] Treinamento de reciclagem (operadores)
- [ ] Atualização de documentação (se mudanças)

### 10.4 Procedimento de Backup

**Backup Completo:**

1. **Banco de Dados:**
   ```bash
   mysqldump -u root -p'estacao2026!' \
     --single-transaction \
     --routines \
     --triggers \
     estacionamento_db | gzip > backup_estacionamento_$(date +%Y%m%d).sql.gz
   ```

2. **Arquivos de Configuração:**
   ```bash
   tar -czf backup_config_$(date +%Y%m%d).tar.gz config/
   ```

3. **Logs de Auditoria (Opcional):**
   ```bash
   cp -r logs/ backup_logs_$(date +%Y%m%d)/
   ```

**Armazenamento:**
- Local: Mesmo servidor (rápido restore)
- Remoto: NAS ou cloud (desastre)
- Offline: HD externo (ransomware)

**Criptografia (Recomendado):**
```bash
gpg --cipher-algo AES256 --symmetric backup_estacionamento_20260115.sql.gz
```

### 10.5 Procedimento de Restore

**Em Caso de Falha:**

1. **Restaurar Banco:**
   ```bash
   gunzip < backup_estacionamento_20260115.sql.gz | \
   mysql -u root -p'estacao2026!' estacionamento_db
   ```

2. **Restaurar Configurações:**
   ```bash
   tar -xzf backup_config_20260115.tar.gz -C /caminho/estacionamento/
   ```

3. **Validar:**
   - Acesse sistema
   - Verifique últimos tickets
   - Teste login de diferentes perfis

**Tempo Alvo de Recuperação (RTO):**
- Crítico: < 1 hora
- Normal: < 4 horas

**Ponto Alvo de Recuperação (RPO):**
- Máximo perda: 24 horas (backup diário)

---

## 11. LGPD e Conformidade

### 11.1 Princípios Adotados

**Privacy by Design:**
- Dados mínimos necessários coletados
- Mascaramento automático de PII
- Retenção definida por política
- Anonimização após período

**Base Legal:**
- Execução de contrato (estacionamento)
- Legítimo interesse (segurança patrimonial)
- Consentimento (marketing opcional)

### 11.2 Direitos dos Titulares

**Como Atender:**

1. **Confirmação de Existência:**
   - Titular solicita confirmação de cadastro
   - Gestor busca por documento/email
   - Exporta resumo em JSON

2. **Acesso aos Dados:**
   - Titular solicita cópia completa
   - Admin: Menu → LGPD → Direitos do Titular
   - Gere relatório assinado
   - Entregue em até 15 dias (prazo LGPD)

3. **Correção de Dados:**
   - Titular solicita alteração
   - Admin edita cadastro
   - Auditoria: "DIREITO_TITULAR_ATENDIDO"

4. **Anonimização/Exclusão:**
   - Titular solicita exclusão
   - Admin anonimiza (não deleta tickets por obrigação fiscal)
   - Placa mantida para estatística
   - Dados pessoais removidos

5. **Portabilidade:**
   - Titular solicita exportação
   - Gere CSV/JSON formatado
   - Entregue em meio seguro

### 11.3 Retenção de Dados

**Política Implementada:**

| Tipo de Dado | Retenção | Destino |
|--------------|----------|---------|
| Auditoria | 5 anos | Mantido integral (obrigação fiscal) |
| Clientes ativos | Indefinido | Enquanto houver interação |
| Clientes inativos | 12 meses | Anonimização automática |
| Tickets | 5 anos | Mantido; se cliente anonimizado, desvincula |
| Câmeras (se integrado) | 30 dias | Sobrescrita automática |

### 11.4 Relatório de Impacto (RIPA)

**Documento Obrigatório para ANPD:**

O sistema já implementa medidas que reduzem impacto:
- ✅ Minimização de dados
- ✅ Pseudonimização (hash de documentos)
- ✅ Criptografia em repouso (senhas Argon2id)
- ✅ Controle de acesso (RBAC)
- ✅ Registro de operações (auditoria WORM)
- ✅ Retenção definida
- ✅ Processo de anonimização

**Modelo de RIPA:**
Disponível sob solicitação à equipe jurídica.

### 11.5 Encarregado de Dados (DPO)

**Responsabilidades:**
- Receber solicitações de titulares
- Revisar políticas de privacidade
- Treinar equipe em LGPD
- Reportar incidentes à ANPD

**Contato:**
- Email: dpo@empresa.com.br (configurar)
- Telefone: (XX) XXXX-XXXX

---

## 12. Próximos Passos e Melhorias Futuras

### 12.1 Roadmap Planejando

**Fase 6 - Hardening (Próximos 30 dias):**
- [ ] Testes de carga com k6/JMeter
- [ ] Implementação de HTTPS/TLS
- [ ] 2FA para administradores
- [ ] Varredura OWASP ZAP

**Fase 7 - Pagamentos Digitais (60 dias):**
- [ ] Integração Mercado Pago (PIX)
- [ ] Webhook para confirmação assíncrona
- [ ] QR Code dinâmico no comprovante
- [ ] Reconciliação automática

**Fase 8 - Multi-Estacionamento (90 dias):**
- [ ] Suporte a múltiplas unidades
- [ ] RBAC por unidade
- [ ] Consolidação de relatórios
- [ ] Dashboard corporativo

**Fase 9 - BI Avançado (120 dias):**
- [ ] Previsão de ocupação (ML)
- [ ] Relatórios de modelos similares (SOUNDEX)
- [ ] Integração Power BI/Tableau
- [ ] Alertas proativos

### 12.2 Melhorias Sugestivas pela Comunidade

**Integrações:**
- [ ] Leitor de QR Code por câmera (WebRTC)
- [ ] Reconhecimento de placas (ALPR/OCR)
- [ ] Catraca/barreira física (GPIO/Serial)
- [ ] SMS/WhatsApp para cliente

**UX:**
- [ ] PWA (Progressive Web App)
- [ ] Modo escuro
- [ ] Acessibilidade (WCAG 2.1)
- [ ] Multi-idioma (PT/EN/ES)

**Segurança:**
- [ ] Logs centralizados (ELK Stack)
- [ ] SIEM integration
- [ ] Backup em cloud criptografado
- [ ] Disaster recovery automatizado

### 12.3 Como Contribuir

Este sistema segue boas práticas de desenvolvimento. Para sugerir melhorias:

1. Documente caso de uso
2. Avalie impacto (segurança, performance, UX)
3. Priorize com equipe
4. Implemente em branch separada
5. Teste exaustivamente
6. Documente mudança no CHANGELOG

---

## 📞 Suporte e Contato

**Documentação Técnica:**
- API: `api/v1/` (OpenAPI 3.0 em desenvolvimento)
- Schema DB: `database/schema.sql`
- Código Fonte: `src/`, `public/`

**Equipe de Implantação:**
- Email: suporte@estacionamento.com.br
- Telefone: (XX) XXXX-XXXX
- Horário: Seg-Sex, 08:00-18:00

**Reportar Bugs:**
- GitHub Issues (se open source)
- Email com: versão, SO, passos reproduzir, logs

**Solicitar Recursos:**
- Email com: caso de uso, benefício, prioridade

---

## 📝 Apêndices

### A. Matriz RBAC Resumida

| Recurso | Admin | Gestor | Operador |
|---------|-------|--------|----------|
| Entradas/Saídas | ✓ | ✓ | ✓ |
| Busca Tickets | ✓ | ✓ | ✓ |
| Dashboard | ✓ | ✓ | ✓ (limitado) |
| Fila Pendente | ✓ | ✓ | ✗ |
| Relatórios | ✓ | ✓ | ✗ |
| Tarifas (editar) | ✓ | ✗ | ✗ |
| Configurações | ✓ | ✗ | ✗ |
| Usuários (todos) | ✓ | ✗ | ✗ |
| Auditoria completa | ✓ | ✓ | ✗ (só própria) |
| Anonimização LGPD | ✓ | ✗ | ✗ |

### B. Códigos de Erro da API

| Código | Significado | Ação |
|--------|-------------|------|
| 200 | Sucesso | - |
| 400 | Requisição inválida | Verifique parâmetros |
| 401 | Não autenticado | Faça login |
| 403 | Sem permissão | Contate admin |
| 404 | Não encontrado | Verifique ID/URL |
| 409 | Conflito (duplicidade) | Placa já aberta |
| 429 | Rate limit excedido | Aguarde |
| 500 | Erro interno | Contate suporte |

### C. Glossário

- **ULID:** Identificador único ordenável (26 chars), usado no QR Code
- **WORM:** Write Once Read Many - auditoria imutável
- **PII:** Personally Identifiable Information
- **RBAC:** Role-Based Access Control
- **Argon2id:** Algoritmo de hash de senhas (vencedor Password Hashing Competition)
- **LGPD:** Lei Geral de Proteção de Dados (Brasil)
- **Idempotency:** Operação que pode ser repetida sem efeitos colaterais

---

**© 2026 Sistema de Estacionamento v3.0**  
Todos os direitos reservados.  
Desenvolvido conforme especificação técnica Versão Executiva v3.0.
