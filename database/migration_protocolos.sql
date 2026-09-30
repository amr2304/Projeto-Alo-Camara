-- =====================================================================
-- Migration: Protocolos (Parte 2 — núcleo do sistema)
-- =====================================================================
-- Pré-requisitos: tabelas `usuarios` e `vereadores` já criadas
-- (Parte 1). `vereador_id` é opcional — só é preenchido quando o
-- protocolo é vinculado a um vereador específico.
-- =====================================================================

CREATE TABLE IF NOT EXISTS protocolos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,          -- ex: AL20240001
    usuario_id      INT NOT NULL,
    vereador_id     INT NULL,
    tipo            ENUM('solicitacao', 'reclamacao', 'sugestao', 'denuncia', 'elogio') NOT NULL,
    titulo          VARCHAR(150) NOT NULL,
    descricao       TEXT NOT NULL,
    bairro          VARCHAR(100) NULL,
    status          ENUM('recebido', 'em_analise', 'respondido', 'finalizado')
                        NOT NULL DEFAULT 'recebido',
    sigiloso        TINYINT(1) NOT NULL DEFAULT 0,         -- sempre 1 quando tipo = denuncia
    foto_url        VARCHAR(255) NULL,                     -- usado por elogio (e outros, se quiserem)
    localizacao     VARCHAR(255) NULL,                     -- usado por elogio (texto ou "lat,lng")
    resposta        TEXT NULL,
    respondido_por  INT NULL,                              -- FK -> usuarios.id (admin que respondeu)
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_protocolo_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_protocolo_vereador
        FOREIGN KEY (vereador_id) REFERENCES vereadores(id)
        ON DELETE SET NULL,

    CONSTRAINT fk_protocolo_respondido_por
        FOREIGN KEY (respondido_por) REFERENCES usuarios(id)
        ON DELETE SET NULL,

    INDEX idx_protocolo_status (status),
    INDEX idx_protocolo_bairro (bairro),
    INDEX idx_protocolo_tipo (tipo),
    INDEX idx_protocolo_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
