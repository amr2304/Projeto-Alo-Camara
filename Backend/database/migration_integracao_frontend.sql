-- =====================================================================
-- Migration: integração com o Frontend (Alô Câmara)
-- Executar por último (depois de migration_partes_2_3.sql).
--   - usuarios.perfil (cidadao | admin)
--   - protocolos.endereco / protocolos.categoria
--   - avaliacoes (nota 1-5 por cidadão/vereador)
--   - protocolo_historico (linha do tempo + resposta + encaminhamento)
--   - historia_camara (linha do tempo da tela "História da Câmara")
-- Idempotente: pode ser executada mais de uma vez.
-- =====================================================================
SET NAMES utf8mb4; -- evita acentos gravados com codificação errada, qualquer que seja o cliente
USE alo_camara;

-- usuarios.perfil
SET @ddl = (
    SELECT IF(COUNT(*) = 0,
        "ALTER TABLE usuarios ADD COLUMN perfil ENUM('cidadao','admin') NOT NULL DEFAULT 'cidadao' AFTER senha_hash",
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'perfil'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- protocolos.endereco
SET @ddl = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE protocolos ADD COLUMN endereco VARCHAR(255) NULL AFTER bairro',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'protocolos' AND COLUMN_NAME = 'endereco'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- protocolos.categoria
SET @ddl = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE protocolos ADD COLUMN categoria VARCHAR(100) NULL AFTER endereco',
        'DO 0')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'protocolos' AND COLUMN_NAME = 'categoria'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS avaliacoes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    vereador_id  INT NOT NULL,
    usuario_id   INT NOT NULL,
    nota         TINYINT NOT NULL,
    criada_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_avaliacao (vereador_id, usuario_id),
    CONSTRAINT fk_avaliacao_vereador FOREIGN KEY (vereador_id) REFERENCES vereadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacao_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS protocolo_historico (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    protocolo_id      INT NOT NULL,
    status            ENUM('recebido','em_analise','respondido','finalizado') NOT NULL,
    resposta          TEXT NULL,
    encaminhado_para  VARCHAR(150) NULL,
    admin_id          INT NULL,
    criado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (protocolo_id),
    CONSTRAINT fk_hist_protocolo FOREIGN KEY (protocolo_id) REFERENCES protocolos(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_admin     FOREIGN KEY (admin_id)     REFERENCES usuarios(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historia_camara (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ano        SMALLINT NOT NULL,
    nome       VARCHAR(150) NOT NULL,
    descricao  TEXT NULL,
    INDEX (ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO historia_camara (ano, nome, descricao)
SELECT * FROM (
    SELECT 2024 AS ano, 'João Silva' AS nome, 'Eleito com foco em infraestrutura urbana.' AS descricao
    UNION ALL SELECT 2020, 'Maria Souza', 'Atuação voltada à saúde e educação.'
    UNION ALL SELECT 2016, 'Carlos Lima', 'Projetos de mobilidade urbana e segurança.'
    UNION ALL SELECT 2012, 'Ana Ribeiro', 'Fortalecimento da cultura local.'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM historia_camara);
