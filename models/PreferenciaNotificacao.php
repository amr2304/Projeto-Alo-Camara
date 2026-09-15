<?php

class PreferenciaNotificacao
{
    public int $usuarioId;
    public bool $notificarEmail;
    public bool $notificarPush;
    public bool $notificarMudancaStatus;
    public bool $notificarAvisosGerais;
    public ?string $atualizadoEm;

    public function __construct(
        int $usuarioId,
        bool $notificarEmail = true,
        bool $notificarPush = true,
        bool $notificarMudancaStatus = true,
        bool $notificarAvisosGerais = true,
        ?string $atualizadoEm = null
    ) {
        $this->usuarioId = $usuarioId;
        $this->notificarEmail = $notificarEmail;
        $this->notificarPush = $notificarPush;
        $this->notificarMudancaStatus = $notificarMudancaStatus;
        $this->notificarAvisosGerais = $notificarAvisosGerais;
        $this->atualizadoEm = $atualizadoEm;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['usuario_id'],
            (bool) $row['notificar_email'],
            (bool) $row['notificar_push'],
            (bool) $row['notificar_mudanca_status'],
            (bool) $row['notificar_avisos_gerais'],
            $row['atualizado_em'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'usuario_id' => $this->usuarioId,
            'notificar_email' => $this->notificarEmail,
            'notificar_push' => $this->notificarPush,
            'notificar_mudanca_status' => $this->notificarMudancaStatus,
            'notificar_avisos_gerais' => $this->notificarAvisosGerais,
            'atualizado_em' => $this->atualizadoEm,
        ];
    }
}
