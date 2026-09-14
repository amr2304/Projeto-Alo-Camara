<?php
require_once __DIR__ . '/../dao/VereadorDAO.php';
require_once __DIR__ . '/../models/Vereador.php';

/**
 * Service de Vereador — regras básicas para a Parte 1/3.
 * O detalhamento de avaliações, agenda e notificações fica a cargo
 * de quem pegar a Parte 3, chamando atualizarIndicadores() no DAO.
 */
class VereadorService
{
    private VereadorDAO $dao;

    public function __construct(?VereadorDAO $dao = null)
    {
        $this->dao = $dao ?? new VereadorDAO();
    }

    public function cadastrar(string $nome, ?string $partido, ?string $regiao, ?string $bio, ?string $email, ?string $telefone): Vereador
    {
        $nome = trim($nome);
        if ($nome === '' || strlen($nome) < 3) {
            throw new InvalidArgumentException('Nome do vereador deve ter ao menos 3 caracteres.');
        }

        if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail de contato inválido.');
        }

        $vereador = new Vereador(null, $nome, $partido, $regiao, $bio, $email, $telefone);
        $id = $this->dao->create($vereador);
        $vereador->setId($id);

        return $vereador;
    }

    public function buscarPorId(int $id): ?Vereador
    {
        return $this->dao->findById($id);
    }

    /** @return Vereador[] */
    public function listarTodos(): array
    {
        return $this->dao->findAll();
    }

    /** Usado pelo filtro de região na tela "Vereadores" (chips Centro/Norte/Sul/...). */
    public function listarPorRegiao(?string $regiao): array
    {
        if ($regiao === null || $regiao === '' || strtolower($regiao) === 'todas') {
            return $this->dao->findAll();
        }

        return $this->dao->findByRegiao($regiao);
    }

    public function editar(int $id, string $nome, ?string $partido, ?string $regiao, ?string $bio, ?string $email, ?string $telefone): Vereador
    {
        $vereador = $this->dao->findById($id);

        if (!$vereador) {
            throw new InvalidArgumentException('Vereador não encontrado.');
        }

        $vereador->setNome(trim($nome));
        $vereador->setPartido($partido);
        $vereador->setRegiao($regiao);
        $vereador->setBio($bio);
        $vereador->setEmail($email);
        $vereador->setTelefone($telefone);

        $this->dao->update($vereador);

        return $vereador;
    }

    /**
     * Recalcula nota média/taxa de resposta a partir de dados vindos de
     * avaliações e do módulo de Protocolos (Parte 2/3).
     */
    public function atualizarIndicadores(int $id, float $notaMedia, int $totalAvaliacoes, float $taxaResposta, int $totalDemandas): bool
    {
        return $this->dao->atualizarIndicadores($id, $notaMedia, $totalAvaliacoes, $taxaResposta, $totalDemandas);
    }
}
