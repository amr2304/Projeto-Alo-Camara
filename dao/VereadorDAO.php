<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Vereador.php';

/**
 * DAO de Vereador — acesso à tabela `vereadores`.
 * Nesta primeira etapa cobre apenas os dados básicos (perfil, listagem,
 * filtro por região) — avaliações detalhadas podem virar tabela própria depois.
 */
class VereadorDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function create(Vereador $vereador): int
    {
        $sql = 'INSERT INTO vereadores (nome, partido, regiao, bio, email, telefone)
                VALUES (:nome, :partido, :regiao, :bio, :email, :telefone)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome'     => $vereador->getNome(),
            ':partido'  => $vereador->getPartido(),
            ':regiao'   => $vereador->getRegiao(),
            ':bio'      => $vereador->getBio(),
            ':email'    => $vereador->getEmail(),
            ':telefone' => $vereador->getTelefone(),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function findById(int $id): ?Vereador
    {
        $stmt = $this->conn->prepare('SELECT * FROM vereadores WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? Vereador::fromArray($row) : null;
    }

    /** @return Vereador[] */
    public function findAll(): array
    {
        $stmt = $this->conn->query('SELECT * FROM vereadores ORDER BY nome ASC');

        return array_map(fn (array $row) => Vereador::fromArray($row), $stmt->fetchAll());
    }

    /** @return Vereador[] */
    public function findByRegiao(string $regiao): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM vereadores WHERE regiao = :regiao ORDER BY nome ASC');
        $stmt->execute([':regiao' => $regiao]);

        return array_map(fn (array $row) => Vereador::fromArray($row), $stmt->fetchAll());
    }

    public function update(Vereador $vereador): bool
    {
        $sql = 'UPDATE vereadores
                SET nome = :nome, partido = :partido, regiao = :regiao,
                    bio = :bio, email = :email, telefone = :telefone
                WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':nome'     => $vereador->getNome(),
            ':partido'  => $vereador->getPartido(),
            ':regiao'   => $vereador->getRegiao(),
            ':bio'      => $vereador->getBio(),
            ':email'    => $vereador->getEmail(),
            ':telefone' => $vereador->getTelefone(),
            ':id'       => $vereador->getId(),
        ]);
    }

    /** Recalcula nota média/taxa de resposta — chamado pelo módulo de avaliações/protocolos (Parte 2/3). */
    public function atualizarIndicadores(int $id, float $notaMedia, int $totalAvaliacoes, float $taxaResposta, int $totalDemandas): bool
    {
        $sql = 'UPDATE vereadores
                SET nota_media = :nota, total_avaliacoes = :total_aval,
                    taxa_resposta = :taxa, total_demandas = :total_dem
                WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':nota'       => $notaMedia,
            ':total_aval' => $totalAvaliacoes,
            ':taxa'       => $taxaResposta,
            ':total_dem'  => $totalDemandas,
            ':id'         => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM vereadores WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }
}
