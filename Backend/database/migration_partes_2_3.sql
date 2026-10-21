-- Execute após a criação das tabelas usuarios e vereadores.
CREATE TABLE IF NOT EXISTS protocolos (
 id INT AUTO_INCREMENT PRIMARY KEY, numero VARCHAR(20) NOT NULL UNIQUE, usuario_id INT NOT NULL, vereador_id INT NULL,
 tipo ENUM('solicitacao','reclamacao','sugestao','denuncia','elogio') NOT NULL, titulo VARCHAR(150) NOT NULL, descricao TEXT NOT NULL,
 bairro VARCHAR(100) NULL, status ENUM('recebido','em_analise','respondido','finalizado') NOT NULL DEFAULT 'recebido', sigiloso TINYINT(1) NOT NULL DEFAULT 0,
 foto_url VARCHAR(255) NULL, localizacao VARCHAR(255) NULL, resposta TEXT NULL, respondido_por INT NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY(vereador_id) REFERENCES vereadores(id) ON DELETE SET NULL,
 FOREIGN KEY(respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL, INDEX(status), INDEX(bairro), INDEX(tipo), INDEX(usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS eventos (
 id INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(180) NOT NULL, tipo ENUM('sessao','comissao','audiencia') NOT NULL,
 data_evento DATETIME NOT NULL, local VARCHAR(255) NULL, descricao TEXT NULL, participantes JSON NOT NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(data_evento), INDEX(tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notificacoes (
 id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, protocolo_id INT NULL, titulo VARCHAR(180) NOT NULL,
 mensagem TEXT NOT NULL, lida TINYINT(1) NOT NULL DEFAULT 0, criada_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE, FOREIGN KEY(protocolo_id) REFERENCES protocolos(id) ON DELETE CASCADE,
 INDEX(usuario_id, criada_em), INDEX(protocolo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
