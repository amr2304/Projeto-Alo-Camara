<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Notificacao.php';

class NotificacaoDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function create(Notificacao $notificacao): Notificacao
    {
        $sql = 'INSERT INTO notificacoes (usuario_id, protocolo_id, titulo, mensagem)
                VALUES (:usuario_id, :protocolo_id, :titulo, :mensagem)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'usuario_id' => $notificacao->getUsuarioId(),
            'protocolo_id' => $notificacao->getProtocoloId(),
            'titulo' => $notificacao->getTitulo(),
            'mensagem' => $notificacao->getMensagem(),
        ]);

        $notificacao->setId((int) $this->conn->lastInsertId());
        return $notificacao;
    }

    public function all(int $usuarioId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM notificacoes WHERE usuario_id = :usuario_id ORDER BY criada_em DESC'
        );
        $stmt->execute(['usuario_id' => $usuarioId]);

        return array_map(
            fn (array $linha): Notificacao => Notificacao::fromArray($linha),
            $stmt->fetchAll()
        );
    }

    public function read(int $id, int $usuarioId): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE notificacoes SET lida = 1 WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);

        return $stmt->rowCount() > 0;
    }
}
