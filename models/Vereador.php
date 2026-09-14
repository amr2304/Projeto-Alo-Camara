<?php
/**
 * Model Vereador — dados básicos para exibição de perfil público,
 * card de listagem e vínculo com Protocolo/Agenda (Partes 2 e 3).
 */
class Vereador
{
    private ?int $id;
    private string $nome;
    private ?string $partido;
    private ?string $regiao;
    private ?string $bio;
    private ?string $email;
    private ?string $telefone;
    private float $notaMedia;
    private int $totalAvaliacoes;
    private float $taxaResposta;
    private int $totalDemandas;

    public function __construct(
        ?int $id,
        string $nome,
        ?string $partido = null,
        ?string $regiao = null,
        ?string $bio = null,
        ?string $email = null,
        ?string $telefone = null,
        float $notaMedia = 0.0,
        int $totalAvaliacoes = 0,
        float $taxaResposta = 0.0,
        int $totalDemandas = 0
    ) {
        $this->id = $id;
        $this->nome = $nome;
        $this->partido = $partido;
        $this->regiao = $regiao;
        $this->bio = $bio;
        $this->email = $email;
        $this->telefone = $telefone;
        $this->notaMedia = $notaMedia;
        $this->totalAvaliacoes = $totalAvaliacoes;
        $this->taxaResposta = $taxaResposta;
        $this->totalDemandas = $totalDemandas;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['nome'],
            $row['partido'] ?? null,
            $row['regiao'] ?? null,
            $row['bio'] ?? null,
            $row['email'] ?? null,
            $row['telefone'] ?? null,
            (float) ($row['nota_media'] ?? 0),
            (int) ($row['total_avaliacoes'] ?? 0),
            (float) ($row['taxa_resposta'] ?? 0),
            (int) ($row['total_demandas'] ?? 0)
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getPartido(): ?string { return $this->partido; }
    public function getRegiao(): ?string { return $this->regiao; }
    public function getBio(): ?string { return $this->bio; }
    public function getEmail(): ?string { return $this->email; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function getNotaMedia(): float { return $this->notaMedia; }
    public function getTotalAvaliacoes(): int { return $this->totalAvaliacoes; }
    public function getTaxaResposta(): float { return $this->taxaResposta; }
    public function getTotalDemandas(): int { return $this->totalDemandas; }

    // Setters
    public function setId(int $id): void { $this->id = $id; }
    public function setNome(string $nome): void { $this->nome = $nome; }
    public function setPartido(?string $partido): void { $this->partido = $partido; }
    public function setRegiao(?string $regiao): void { $this->regiao = $regiao; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
    public function setEmail(?string $email): void { $this->email = $email; }
    public function setTelefone(?string $telefone): void { $this->telefone = $telefone; }
    public function setNotaMedia(float $notaMedia): void { $this->notaMedia = $notaMedia; }
    public function setTotalAvaliacoes(int $totalAvaliacoes): void { $this->totalAvaliacoes = $totalAvaliacoes; }
    public function setTaxaResposta(float $taxaResposta): void { $this->taxaResposta = $taxaResposta; }
    public function setTotalDemandas(int $totalDemandas): void { $this->totalDemandas = $totalDemandas; }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'nome'              => $this->nome,
            'partido'           => $this->partido,
            'regiao'            => $this->regiao,
            'bio'               => $this->bio,
            'email'             => $this->email,
            'telefone'          => $this->telefone,
            'nota_media'        => $this->notaMedia,
            'total_avaliacoes'  => $this->totalAvaliacoes,
            'taxa_resposta'     => $this->taxaResposta,
            'total_demandas'    => $this->totalDemandas,
        ];
    }
}
