<?php

class Evento
{
    private ?int $id;
    private string $titulo;
    private string $tipo;
    private string $data;
    private ?string $local;
    private ?string $descricao;
    private array $participantes;

    public function __construct(
        ?int $id,
        string $titulo,
        string $tipo,
        string $data,
        ?string $local = null,
        ?string $descricao = null,
        array $participantes = []
    ) {
        $this->id = $id;
        $this->titulo = $titulo;
        $this->tipo = $tipo;
        $this->data = $data;
        $this->local = $local;
        $this->descricao = $descricao;
        $this->participantes = $participantes;
    }

    public static function fromArray(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            $linha['titulo'],
            $linha['tipo'],
            $linha['data_evento'],
            $linha['local'] ?? null,
            $linha['descricao'] ?? null,
            json_decode($linha['participantes'] ?? '[]', true) ?: []
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getTitulo(): string { return $this->titulo; }
    public function getTipo(): string { return $this->tipo; }
    public function getData(): string { return $this->data; }
    public function getLocal(): ?string { return $this->local; }
    public function getDescricao(): ?string { return $this->descricao; }
    public function getParticipantes(): array { return $this->participantes; }
    public function setId(int $id): void { $this->id = $id; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'data' => $this->data,
            'local' => $this->local,
            'descricao' => $this->descricao,
            'participantes' => $this->participantes,
        ];
    }
}
