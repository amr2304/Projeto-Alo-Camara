<?php
require_once __DIR__ . '/../dao/PreferenciaNotificacaoDAO.php';
require_once __DIR__ . '/../models/PreferenciaNotificacao.php';

/**
 * Service de Preferências de Notificação — usado nas "Configurações de conta"
 * para o cidadão ligar/desligar push, e-mail, avisos de mudança de status
 * de protocolo (integração Parte 2) e avisos gerais (Parte 3).
 */
class PreferenciaNotificacaoService
{
    private PreferenciaNotificacaoDAO $dao;

    public function __construct(?PreferenciaNotificacaoDAO $dao = null)
    {
        $this->dao = $dao ?? new PreferenciaNotificacaoDAO();
    }

    public function obter(int $usuarioId): array
    {
        return $this->dao->buscarPorUsuario($usuarioId)->toArray();
    }

    /**
     * Atualização parcial: só troca os campos enviados em $dados,
     * preservando os demais como já estavam salvos.
     */
    public function atualizar(int $usuarioId, array $dados): array
    {
        $atual = $this->dao->buscarPorUsuario($usuarioId);

        $pref = new PreferenciaNotificacao(
            $usuarioId,
            (bool) ($dados['notificar_email'] ?? $atual->notificarEmail),
            (bool) ($dados['notificar_push'] ?? $atual->notificarPush),
            (bool) ($dados['notificar_mudanca_status'] ?? $atual->notificarMudancaStatus),
            (bool) ($dados['notificar_avisos_gerais'] ?? $atual->notificarAvisosGerais)
        );

        return $this->dao->atualizar($pref)->toArray();
    }
}
