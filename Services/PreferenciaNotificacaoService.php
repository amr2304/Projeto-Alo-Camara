<?php

require_once __DIR__ . '/../dao/PreferenciaNotificacaoDAO.php';
require_once __DIR__ . '/../models/PreferenciaNotificacao.php';

class PreferenciaNotificacaoService
{
    private PreferenciaNotificacaoDAO $dao;

    public function __construct()
    {
        $this->dao = new PreferenciaNotificacaoDAO();
    }

    public function obter(int $usuarioId): array
    {
        return $this->dao->buscarPorUsuario($usuarioId)->toArray();
    }

    /**
     * Atualiza apenas os campos enviados; os não enviados mantêm o valor atual.
     *
     * @param array $dados ex: ['notificar_email' => false, 'notificar_push' => true]
     * @throws InvalidArgumentException se nenhum campo válido for enviado
     */
    public function atualizar(int $usuarioId, array $dados): array
    {
        $camposValidos = [
            'notificar_email',
            'notificar_push',
            'notificar_mudanca_status',
            'notificar_avisos_gerais',
        ];

        $enviados = array_intersect_key($dados, array_flip($camposValidos));

        if (empty($enviados)) {
            throw new InvalidArgumentException(
                'Envie ao menos um campo válido: ' . implode(', ', $camposValidos)
            );
        }

        $atual = $this->dao->buscarPorUsuario($usuarioId);

        $atual->notificarEmail = isset($enviados['notificar_email'])
            ? (bool) $enviados['notificar_email'] : $atual->notificarEmail;
        $atual->notificarPush = isset($enviados['notificar_push'])
            ? (bool) $enviados['notificar_push'] : $atual->notificarPush;
        $atual->notificarMudancaStatus = isset($enviados['notificar_mudanca_status'])
            ? (bool) $enviados['notificar_mudanca_status'] : $atual->notificarMudancaStatus;
        $atual->notificarAvisosGerais = isset($enviados['notificar_avisos_gerais'])
            ? (bool) $enviados['notificar_avisos_gerais'] : $atual->notificarAvisosGerais;

        return $this->dao->atualizar($atual)->toArray();
    }
}
