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

    /** Marca todas as notificações do cidadão como lidas. */
    public function readAll(int $usuarioId): int
    {
        $stmt = $this->conn->prepare('UPDATE notificacoes SET lida = 1 WHERE usuario_id = :usuario_id AND lida = 0');
        $stmt->execute(['usuario_id' => $usuarioId]);

        return $stmt->rowCount();
    }

    /**
     * Comunicado geral: cria uma notificação para cada cidadão ativo (opcionalmente
     * só de um bairro), respeitando a preferência "notificar_avisos_gerais".
     * Devolve quantos cidadãos foram notificados.
     */
    public function broadcast(string $titulo, string $mensagem, ?string $bairro = null): int
    {
        $sql = "INSERT INTO notificacoes (usuario_id, protocolo_id, titulo, mensagem)
                SELECT u.id, NULL, :titulo, :mensagem
                FROM usuarios u
                LEFT JOIN preferencias_notificacao p ON p.usuario_id = u.id
                WHERE u.perfil = 'cidadao' AND u.ativo = 1
                  AND (p.usuario_id IS NULL OR p.notificar_avisos_gerais = 1)";
        $parametros = ['titulo' => $titulo, 'mensagem' => $mensagem];

        if ($bairro !== null && $bairro !== '') {
            $sql .= ' AND u.bairro = :bairro';
            $parametros['bairro'] = $bairro;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->rowCount();
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
