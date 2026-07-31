-- ==========================================================
-- SISTEMA ESTACIONAMENTO v3.0 - SCHEMA COMPLETO
-- MariaDB 10.4.32 | InnoDB | utf8mb4_unicode_ci | UTC
-- Inclui: padrão Cadastro Auxiliar PENDENTE, constraint concorrência,
-- idempotency_key, auditoria WORM hash encadeado
-- ==========================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------------------------------------
-- 1. CONFIGURAÇÕES SISTEMA
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
  id CHAR(36) PRIMARY KEY,
  modo_cobranca_default ENUM('POR_MINUTO','HORA_FRACIONADA','HORA_CHEIA','TARIFA_FIXA','DIARIA') NOT NULL DEFAULT 'POR_MINUTO',
  granularidade_min INT NOT NULL DEFAULT 15 COMMENT '5,10,15,30',
  tolerancia_min INT NOT NULL DEFAULT 15 COMMENT 'cortesia',
  regra_arredondamento ENUM('TETO','PISO','MATEMATICO') NOT NULL DEFAULT 'TETO',
  diaria_virada_horas INT NOT NULL DEFAULT 24,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. USUÁRIOS - Argon2id, RBAC
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id CHAR(36) PRIMARY KEY COMMENT 'UUIDv7',
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL COMMENT 'criptografado at-rest na app',
  email_hash CHAR(64) NOT NULL COMMENT 'SHA256 para busca sem descriptografar',
  perfil ENUM('admin','gestor','operador') NOT NULL,
  status ENUM('ativo','inativo','bloqueado') NOT NULL DEFAULT 'ativo',
  senha_hash VARCHAR(255) NOT NULL COMMENT 'Argon2id',
  tentativas_falha INT NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME(3) NULL,
  ultimo_acesso DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  UNIQUE KEY ux_email_hash (email_hash),
  KEY ix_perfil_status (perfil, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. TIPOS VEÍCULO (quem define tarifa)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_veiculo (
  id CHAR(36) PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  descricao VARCHAR(255) NULL,
  ativo BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY ux_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. CADASTROS AUXILIARES - PADRÃO PENDENTE UNIFICADO
-- ----------------------------------------------------------

-- 4.1 CORES
CREATE TABLE IF NOT EXISTS cores_veiculo (
  id CHAR(36) PRIMARY KEY COMMENT 'UUIDv4',
  nome VARCHAR(30) NOT NULL COMMENT 'normalizado Title Case',
  nome_normalizado VARCHAR(30) GENERATED ALWAYS AS (LOWER(TRIM(nome))) STORED,
  status ENUM('ATIVO','PENDENTE','INATIVO') NOT NULL DEFAULT 'PENDENTE',
  criado_por_ticket_id CHAR(36) NULL COMMENT 'origem auto',
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY ux_nome_norm_ativo (nome_normalizado, status) COMMENT 'evita duplicidade ATIVO mas permite PENDENTE similar',
  KEY ix_status (status),
  KEY ix_criado_por_ticket (criado_por_ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 MARCAS
CREATE TABLE IF NOT EXISTS marcas_veiculo (
  id CHAR(36) PRIMARY KEY,
  nome VARCHAR(50) NOT NULL,
  nome_normalizado VARCHAR(50) GENERATED ALWAYS AS (LOWER(TRIM(nome))) STORED,
  status ENUM('ATIVO','PENDENTE','INATIVO') NOT NULL DEFAULT 'PENDENTE',
  criado_por_ticket_id CHAR(36) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY ux_nome_norm (nome_normalizado),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.3 MODELOS - depende de marca
CREATE TABLE IF NOT EXISTS modelos_veiculo (
  id CHAR(36) PRIMARY KEY,
  marca_id CHAR(36) NOT NULL,
  nome VARCHAR(80) NOT NULL,
  nome_normalizado VARCHAR(80) GENERATED ALWAYS AS (LOWER(TRIM(nome))) STORED,
  status ENUM('ATIVO','PENDENTE','INATIVO') NOT NULL DEFAULT 'PENDENTE',
  criado_por_ticket_id CHAR(36) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_modelo_marca FOREIGN KEY (marca_id) REFERENCES marcas_veiculo(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY ux_marca_nome_norm (marca_id, nome_normalizado),
  KEY ix_status (status),
  KEY ix_marca (marca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. CLIENTES (LGPD)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS clientes (
  id CHAR(36) PRIMARY KEY,
  nome VARCHAR(120) NULL,
  documento_hash CHAR(64) NULL COMMENT 'SHA256 para busca',
  documento_mascarado VARCHAR(30) NULL COMMENT '***.123.***-**',
  telefone_mascarado VARCHAR(30) NULL,
  email_cript VARCHAR(500) NULL,
  consentimento_lgpd_at DATETIME(3) NULL,
  ultima_interacao_at DATETIME(3) NULL,
  anonimizado_at DATETIME(3) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  KEY ix_documento_hash (documento_hash),
  KEY ix_ultima_interacao (ultima_interacao_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. TARIFAS - versionada com vigência
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tarifas (
  id CHAR(36) PRIMARY KEY,
  tipo_veiculo_id CHAR(36) NOT NULL,
  modo_cobranca ENUM('POR_MINUTO','HORA_FRACIONADA','HORA_CHEIA','TARIFA_FIXA','DIARIA') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  granularidade_min INT NULL COMMENT 'sobrescreve config global se definido',
  tolerancia_min INT NULL,
  vigencia_inicio DATETIME(3) NOT NULL,
  vigencia_fim DATETIME(3) NULL,
  ativa BOOLEAN NOT NULL DEFAULT TRUE,
  created_by CHAR(36) NOT NULL,
  motivo TEXT NOT NULL COMMENT 'obrigatório para auditoria',
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_tarifa_tipo FOREIGN KEY (tipo_veiculo_id) REFERENCES tipos_veiculo(id),
  CONSTRAINT fk_tarifa_user FOREIGN KEY (created_by) REFERENCES usuarios(id),
  INDEX ix_tarifa_busca (tipo_veiculo_id, modo_cobranca, vigencia_inicio, ativa),
  INDEX ix_vigencia (vigencia_inicio, vigencia_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. TICKETS - CORE COM CONSTRAINT CONCORRÊNCIA + IDEMPOTENCY
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
  id CHAR(36) PRIMARY KEY COMMENT 'UUIDv7 ordenável',
  codigo_unico VARCHAR(26) NOT NULL COMMENT 'ULID - QR Code não previsível',
  placa_normalizada VARCHAR(8) NOT NULL COMMENT 'AAA1A23 uppercase sem traço',
  -- CONSTRAINT CONCORRÊNCIA: coluna virtual que só tem valor quando ABERTO
  placa_aberta VARCHAR(8) GENERATED ALWAYS AS (IF(status='ABERTO', placa_normalizada, NULL)) STORED COMMENT 'usado para UNIQUE impedir duplicidade',
  tipo_veiculo_id CHAR(36) NOT NULL,
  marca_id CHAR(36) NULL COMMENT 'FK pode ser PENDENTE - não bloqueia',
  modelo_id CHAR(36) NULL,
  cor_id CHAR(36) NULL,
  cliente_id CHAR(36) NULL,
  modelo_texto_livre VARCHAR(80) NULL COMMENT 'backup se modelo_id ainda PENDENTE - opcional',
  entrada_utc DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  saida_utc DATETIME(3) NULL,
  tempo_minutos INT NULL COMMENT 'CEIL((saida-entrada)/60)',
  valor_calculado DECIMAL(10,2) NULL,
  status ENUM('ABERTO','FECHADO','CANCELADO') NOT NULL DEFAULT 'ABERTO',
  metodo_pagamento ENUM('MANUAL','PIX','CARTAO') NULL DEFAULT 'MANUAL',
  status_pagamento ENUM('PENDENTE','PAGO','FALHOU','CORTESIA') NULL DEFAULT 'PENDENTE',
  idempotency_key CHAR(36) NOT NULL COMMENT 'UUIDv7 único para gateway futuro',
  gateway_ref VARCHAR(120) NULL,
  hash_qr VARCHAR(128) NULL COMMENT 'HMAC do codigo_unico opcional',
  operador_entrada_id CHAR(36) NOT NULL,
  operador_saida_id CHAR(36) NULL,
  observacao TEXT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_ticket_tipo FOREIGN KEY (tipo_veiculo_id) REFERENCES tipos_veiculo(id),
  CONSTRAINT fk_ticket_marca FOREIGN KEY (marca_id) REFERENCES marcas_veiculo(id),
  CONSTRAINT fk_ticket_modelo FOREIGN KEY (modelo_id) REFERENCES modelos_veiculo(id),
  CONSTRAINT fk_ticket_cor FOREIGN KEY (cor_id) REFERENCES cores_veiculo(id),
  CONSTRAINT fk_ticket_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_ticket_op_entrada FOREIGN KEY (operador_entrada_id) REFERENCES usuarios(id),
  CONSTRAINT fk_ticket_op_saida FOREIGN KEY (operador_saida_id) REFERENCES usuarios(id),
  UNIQUE KEY ux_codigo_unico (codigo_unico),
  UNIQUE KEY ux_idempotency (idempotency_key),
  UNIQUE KEY ux_placa_aberta (placa_aberta) COMMENT 'CRÍTICO: impede 2 tickets ABERTO mesma placa - resolve race condition',
  KEY ix_placa_normalizada (placa_normalizada),
  KEY ix_status_entrada (status, entrada_utc),
  KEY ix_entrada (entrada_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. AUDITORIA WORM - hash encadeado
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
  audit_id CHAR(36) PRIMARY KEY COMMENT 'UUIDv4',
  timestamp_iso DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'UTC',
  user_id CHAR(36) NULL,
  perfil ENUM('admin','gestor','operador','sistema') NULL,
  acao ENUM(
    'LOGIN_TENTATIVA','LOGIN_SUCESSO','LOGOUT',
    'USUARIO_CRIADO','USUARIO_ADMIN_CRIADO','USUARIO_STATUS_ALTERADO','SENHA_RESET','SENHA_ALTERADA','DESBLOQUEIO',
    'CONFIG_VIEW','CONFIG_ALTERADA',
    'TARIFA_LISTADA','TARIFA_ALTERADA','TARIFA_HISTORICO_VIEW',
    'TIPO_VEICULO_ALTERADO',
    'ENTRADA_CRIADA','TICKET_BUSCADO','SAIDA_CALCULADA','SAIDA_FECHADA','TICKET_CANCELADO','TICKET_REABERTO','RECALCULO',
    'MARCA_PENDENTE_CRIADA','MODELO_PENDENTE_CRIADA','COR_PENDENTE_CRIADA',
    'CADASTRO_APROVADO','CADASTRO_MESCLADO','CADASTRO_INATIVADO',
    'FILA_PENDENTE_LISTADA','TICKETS_LISTADOS',
    'AUDITORIA_CONSULTADA','AUDITORIA_EXPORTADA',
    'CLIENTE_ANONIMIZADO','DIREITO_TITULAR_ATENDIDO',
    'TENTATIVA_NAO_AUTORIZADA','PAGAMENTO_WEBHOOK'
  ) NOT NULL,
  resultado ENUM('SUCESSO','FALHA','NEGADO') NOT NULL,
  detalhe_resumo VARCHAR(500) NOT NULL,
  ip_terminal VARCHAR(45) NULL,
  contexto_json JSON NULL COMMENT 'de/para, diff, etc - PII já mascarado',
  hash_anterior CHAR(64) NULL COMMENT 'SHA256 do registro anterior para encadeamento',
  hash_integridade CHAR(64) NOT NULL COMMENT 'SHA256(timestamp+acao+user+contexto+hash_anterior)',
  INDEX ix_timestamp (timestamp_iso),
  INDEX ix_user (user_id),
  INDEX ix_acao (acao),
  INDEX ix_placa_busca ((CAST(contexto_json->>'$.placa' AS CHAR(8)))) COMMENT 'busca por placa no JSON'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WORM - sem UPDATE/DELETE via app';

-- ==========================================================
-- SEEDS - CORES (15 padrão) + MARCAS (70) + TIPOS
-- ==========================================================

INSERT INTO tipos_veiculo (id, nome, descricao, ativo) VALUES
(UUID(), 'Carro de passeio', 'Veículos leves', TRUE),
(UUID(), 'Moto', 'Motocicletas', TRUE),
(UUID(), 'Caminhonete / SUV', 'SUV, pickup', TRUE),
(UUID(), 'Van', 'Vans e utilitários médios', TRUE),
(UUID(), 'Caminhão', 'Caminhões', TRUE)
ON DUPLICATE KEY UPDATE nome=nome;

-- Cores padrão ATIVO
INSERT INTO cores_veiculo (id, nome, status) VALUES
(UUID(), 'Branco', 'ATIVO'),
(UUID(), 'Preto', 'ATIVO'),
(UUID(), 'Prata', 'ATIVO'),
(UUID(), 'Cinza', 'ATIVO'),
(UUID(), 'Vermelho', 'ATIVO'),
(UUID(), 'Azul', 'ATIVO'),
(UUID(), 'Verde', 'ATIVO'),
(UUID(), 'Amarelo', 'ATIVO'),
(UUID(), 'Bege', 'ATIVO'),
(UUID(), 'Marrom', 'ATIVO'),
(UUID(), 'Dourado', 'ATIVO'),
(UUID(), 'Laranja', 'ATIVO'),
(UUID(), 'Roxo', 'ATIVO'),
(UUID(), 'Vinho', 'ATIVO'),
(UUID(), 'Grafite', 'ATIVO')
ON DUPLICATE KEY UPDATE nome=nome;

-- Marcas principais ATIVO - 70 mais comuns Brasil
INSERT INTO marcas_veiculo (id, nome, status) VALUES
(UUID(), 'Chevrolet', 'ATIVO'), (UUID(), 'Volkswagen', 'ATIVO'), (UUID(), 'Fiat', 'ATIVO'),
(UUID(), 'Toyota', 'ATIVO'), (UUID(), 'Hyundai', 'ATIVO'), (UUID(), 'Honda', 'ATIVO'),
(UUID(), 'Ford', 'ATIVO'), (UUID(), 'Renault', 'ATIVO'), (UUID(), 'Nissan', 'ATIVO'),
(UUID(), 'Jeep', 'ATIVO'), (UUID(), 'Peugeot', 'ATIVO'), (UUID(), 'Citroën', 'ATIVO'),
(UUID(), 'Mitsubishi', 'ATIVO'), (UUID(), 'Kia', 'ATIVO'), (UUID(), 'Suzuki', 'ATIVO'),
(UUID(), 'BMW', 'ATIVO'), (UUID(), 'Mercedes-Benz', 'ATIVO'), (UUID(), 'Audi', 'ATIVO'),
(UUID(), 'Volvo', 'ATIVO'), (UUID(), 'Yamaha', 'ATIVO'), (UUID(), 'Suzuki Motos', 'ATIVO'),
(UUID(), 'Honda Motos', 'ATIVO'), (UUID(), 'Iveco', 'ATIVO'), (UUID(), 'Scania', 'ATIVO'),
(UUID(), 'MAN', 'ATIVO'), (UUID(), 'Volvo Caminhões', 'ATIVO'), (UUID(), 'Chery', 'ATIVO'),
(UUID(), 'BYD', 'ATIVO'), (UUID(), 'GWM', 'ATIVO'), (UUID(), 'JAC', 'ATIVO'), (UUID(), 'RAM', 'ATIVO')
ON DUPLICATE KEY UPDATE nome=nome;

-- Config padrão
INSERT INTO configuracoes_sistema (id, modo_cobranca_default, granularidade_min, tolerancia_min, regra_arredondamento, diaria_virada_horas)
VALUES (UUID(), 'POR_MINUTO', 15, 15, 'TETO', 24)
ON DUPLICATE KEY UPDATE id=id;

SET FOREIGN_KEY_CHECKS=1;
