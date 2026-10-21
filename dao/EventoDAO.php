<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Evento.php';

class EventoDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function save(Evento $evento): Evento
    {
        $novoEvento = $evento->getId() === null;
        $sql = $novoEvento
            ? 'INSERT INTO eventos (titulo, tipo, data_evento, local, descricao, participantes)
               VALUES (:titulo, :tipo, :data, :local, :descricao, :participantes)'
            : 'UPDATE eventos
               SET titulo = :titulo, tipo = :tipo, data_evento = :data,
                   local = :local, descricao = :descricao, participantes = :participantes
               WHERE id = :id';

        $stmt = $this->conn->prepare($sql);
        $parametros = [
            'titulo' => $evento->getTitulo(),
            'tipo' => $evento->getTipo(),
            'data' => $evento->getData(),
            'local' => $evento->getLocal(),
            'descricao' => $evento->getDescricao(),
            'participantes' => json_encode($evento->getParticipantes(), JSON_UNESCAPED_UNICODE),
        ];

        if (!$novoEvento) {
            $parametros['id'] = $evento->getId();
        }

        $stmt->execute($parametros);
        if ($novoEvento) {
            $evento->setId((int) $this->conn->lastInsertId());
        }

        return $evento;
    }

    public function find(int $id): ?Evento
    {
        $stmt = $this->conn->prepare('SELECT * FROM eventos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        return $linha ? Evento::fromArray($linha) : null;
    }

    public function all(): array
    {
        $stmt = $this->conn->query('SELECT * FROM eventos ORDER BY data_evento');

        return array_map(
            fn (array $linha): Evento => Evento::fromArray($linha),
            $stmt->fetchAll()
        );
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM eventos WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
