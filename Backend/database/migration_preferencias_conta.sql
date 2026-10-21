-- =====================================================================
-- Migration: Preferências de Notificação & Configurações de Conta
-- Responsável: Parte 1 (Usuários & Autenticação) - sub-etapa
-- =====================================================================
-- Pré-requisito: tabela `usuarios` já criada pelo restante da Parte 1
-- (cadastro/login). Se ainda não existir no schema principal do grupo,
-- descomente o CREATE TABLE abaixo para testar este módulo isoladamente.
-- =====================================================================

-- CREATE TABLE IF NOT EXISTS usuarios (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     nome VARCHAR(150) NOT NULL,
--     email VARCHAR(150) NOT NULL UNIQUE,
--     senha_hash VARCHAR(255) NOT NULL,
--     tipo ENUM('cidadao', 'admin') NOT NULL DEFAULT 'cidadao',
--     cidade VARCHAR(100),
--     bairro VARCHAR(100),
--     criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1) Coluna de status de conta (ativar/desativar), usada nas
--    "Configurações de conta". Não mexe em nenhuma coluna existente.
--    (compatível com MySQL e MariaDB: `ADD COLUMN IF NOT EXISTS` só existe no MariaDB)
SET @ddl = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE usuarios ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER senha_hash',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'ativo'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Tabela de preferências de notificação (1 para 1 com usuario)
CREATE TABLE IF NOT EXISTS preferencias_notificacao (
    usuario_id                 INT NOT NULL PRIMARY KEY,
    notificar_email            TINYINT(1) NOT NULL DEFAULT 1,
    notificar_push             TINYINT(1) NOT NULL DEFAULT 1,
    notificar_mudanca_status   TINYINT(1) NOT NULL DEFAULT 1, -- protocolo mudou de status (Parte 2/3)
    notificar_avisos_gerais    TINYINT(1) NOT NULL DEFAULT 1, -- avisos/agenda (Parte 3)
    atualizado_em              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pref_notificacao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
