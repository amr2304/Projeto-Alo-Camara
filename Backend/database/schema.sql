-- =====================================================================
-- Schema base (Parte 1): tabelas `usuarios` e `vereadores`.
-- Ordem de execução:
--   1) schema.sql
--   2) migration_preferencias_conta.sql
--   3) migration_protocolos.sql
--   4) migration_partes_2_3.sql
-- =====================================================================
CREATE DATABASE IF NOT EXISTS alo_camara CHARACTER SET utf8mb4;
USE alo_camara;

CREATE TABLE IF NOT EXISTS usuarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    senha_hash  VARCHAR(255) NULL,                 -- NULL para contas criadas via OAuth
    telefone    VARCHAR(30) NULL,
    bairro      VARCHAR(100) NULL,
    notif_push  TINYINT(1) NOT NULL DEFAULT 1,
    notif_email TINYINT(1) NOT NULL DEFAULT 0,
    criado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vereadores (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(150) NOT NULL,
    partido          VARCHAR(50) NULL,
    regiao           VARCHAR(100) NULL,
    bio              TEXT NULL,
    email            VARCHAR(150) NULL,
    telefone         VARCHAR(30) NULL,
    nota_media       DECIMAL(3,2) NOT NULL DEFAULT 0,
    total_avaliacoes INT NOT NULL DEFAULT 0,
    taxa_resposta    DECIMAL(5,2) NOT NULL DEFAULT 0,
    total_demandas   INT NOT NULL DEFAULT 0,
    INDEX(regiao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
