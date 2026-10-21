<?php

class Notificacao
{
    private ?int $id;
    private int $usuarioId;
    private ?int $protocoloId;
    private string $titulo;
    private string $mensagem;
    private bool $lida;
    private ?string $criadaEm;

    public function __construct(
        ?int $id,
        int $usuarioId,
        ?int $protocoloId,
        string $titulo,
        string $mensagem,
        bool $lida = false,
        ?string $criadaEm = null
    ) {
        $this->id = $id;
        $this->usuarioId = $usuarioId;
        $this->protocoloId = $protocoloId;
        $this->titulo = $titulo;
        $this->mensagem = $mensagem;
        $this->lida = $lida;
        $this->criadaEm = $criadaEm;
    }

    public static function fromArray(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            (int) $linha['usuario_id'],
            isset($linha['protocolo_id']) ? (int) $linha['protocolo_id'] : null,
            $linha['titulo'],
            $linha['mensagem'],
            (bool) $linha['lida'],
            $linha['criada_em'] ?? null
        );
    }

    public function getId(): ?int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getProtocoloId(): ?int { return $this->protocoloId; }
    public function getTitulo(): string { return $this->titulo; }
    public function getMensagem(): string { return $this->mensagem; }
    public function isLida(): bool { return $this->lida; }
    public function setId(int $id): void { $this->id = $id; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuarioId,
            'protocolo_id' => $this->protocoloId,
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'tipo' => $this->protocoloId !== null ? 'Protocolos' : 'Avisos',
            'lida' => $this->lida,
            'criada_em' => $this->criadaEm,
        ];
    }
}
