<?php

/**
 * Model Protocolo — representa uma solicitação, reclamação, sugestão,
 * denúncia ou elogio aberto por um cidadão (usuarios) para a prefeitura,
 * opcionalmente vinculado a um vereador (vereadores).
 *
 * Objeto simples (POJO-like), sem regra de negócio — a máquina de estados
 * do status e as regras por tipo (denúncia sigilosa, elogio com
 * imagem/geolocalização) ficam no ProtocoloService.
 */
class Protocolo
{
    public const TIPOS = ['solicitacao', 'reclamacao', 'sugestao', 'denuncia', 'elogio'];

    public const STATUS = ['recebido', 'em_analise', 'respondido', 'finalizado'];

    private ?int $id;
    private string $numero;
    private int $usuarioId;
    private ?int $vereadorId;
    private string $tipo;
    private string $titulo;
    private string $descricao;
    private ?string $bairro;
    private string $status;
    private bool $sigiloso;
    private ?string $fotoUrl;
    private ?string $localizacao;
    private ?string $resposta;
    private ?int $respondidoPor;
    private ?string $criadoEm;
    private ?string $atualizadoEm;

    public function __construct(
        ?int $id,
        string $numero,
        int $usuarioId,
        ?int $vereadorId,
        string $tipo,
        string $titulo,
        string $descricao,
        ?string $bairro = null,
        string $status = 'recebido',
        bool $sigiloso = false,
        ?string $fotoUrl = null,
        ?string $localizacao = null,
        ?string $resposta = null,
        ?int $respondidoPor = null,
        ?string $criadoEm = null,
        ?string $atualizadoEm = null
    ) {
        $this->id = $id;
        $this->numero = $numero;
        $this->usuarioId = $usuarioId;
        $this->vereadorId = $vereadorId;
        $this->tipo = $tipo;
        $this->titulo = $titulo;
        $this->descricao = $descricao;
        $this->bairro = $bairro;
        $this->status = $status;
        // Denúncia é sigilosa por definição, independente do que for passado.
        $this->sigiloso = $tipo === 'denuncia' ? true : $sigiloso;
        $this->fotoUrl = $fotoUrl;
        $this->localizacao = $localizacao;
        $this->resposta = $resposta;
        $this->respondidoPor = $respondidoPor;
        $this->criadoEm = $criadoEm;
        $this->atualizadoEm = $atualizadoEm;
    }

    /** Cria um Protocolo a partir de uma linha do banco (array associativo). */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['numero'],
            (int) $row['usuario_id'],
            isset($row['vereador_id']) ? (int) $row['vereador_id'] : null,
            $row['tipo'],
            $row['titulo'],
            $row['descricao'],
            $row['bairro'] ?? null,
            $row['status'],
            (bool) ($row['sigiloso'] ?? false),
            $row['foto_url'] ?? null,
            $row['localizacao'] ?? null,
            $row['resposta'] ?? null,
            isset($row['respondido_por']) ? (int) $row['respondido_por'] : null,
            $row['criado_em'] ?? null,
            $row['atualizado_em'] ?? null
        );
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNumero(): string { return $this->numero; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getVereadorId(): ?int { return $this->vereadorId; }
    public function getTipo(): string { return $this->tipo; }
    public function getTitulo(): string { return $this->titulo; }
    public function getDescricao(): string { return $this->descricao; }
    public function getBairro(): ?string { return $this->bairro; }
    public function getStatus(): string { return $this->status; }
    public function isSigiloso(): bool { return $this->sigiloso; }
    public function getFotoUrl(): ?string { return $this->fotoUrl; }
    public function getLocalizacao(): ?string { return $this->localizacao; }
    public function getResposta(): ?string { return $this->resposta; }
    public function getRespondidoPor(): ?int { return $this->respondidoPor; }
    public function getCriadoEm(): ?string { return $this->criadoEm; }
    public function getAtualizadoEm(): ?string { return $this->atualizadoEm; }

    // Setters usados pelo Service (máquina de estados, resposta, anexos)
    public function setId(int $id): void { $this->id = $id; }
    /** Usado internamente pelo ProtocoloDAO ao gerar o número (ex: AL20240001). */
    public function setNumero(string $numero): void { $this->numero = $numero; }
    public function setVereadorId(?int $vereadorId): void { $this->vereadorId = $vereadorId; }
    public function setStatus(string $status): void { $this->status = $status; }
    public function setFotoUrl(?string $fotoUrl): void { $this->fotoUrl = $fotoUrl; }
    public function setLocalizacao(?string $localizacao): void { $this->localizacao = $localizacao; }
    public function setResposta(?string $resposta): void { $this->resposta = $resposta; }
    public function setRespondidoPor(?int $respondidoPor): void { $this->respondidoPor = $respondidoPor; }

    /**
     * Representação para respostas da API. Quando $ocultarIdentidade for
     * true (denúncia, em listagens públicas), o usuario_id não é exposto.
     */
    public function toArray(bool $ocultarIdentidade = false): array
    {
        return [
            'id'             => $this->id,
            'numero'         => $this->numero,
            'usuario_id'     => $ocultarIdentidade ? null : $this->usuarioId,
            'vereador_id'    => $this->vereadorId,
            'tipo'           => $this->tipo,
            'titulo'         => $this->titulo,
            'descricao'      => $this->descricao,
            'bairro'         => $this->bairro,
            'status'         => $this->status,
            'sigiloso'       => $this->sigiloso,
            'foto_url'       => $this->fotoUrl,
            'localizacao'    => $this->localizacao,
            'resposta'       => $this->resposta,
            'respondido_por' => $this->respondidoPor,
            'criado_em'      => $this->criadoEm,
            'atualizado_em'  => $this->atualizadoEm,
        ];
    }
}
