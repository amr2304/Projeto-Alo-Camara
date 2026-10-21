<?php

require_once __DIR__ . '/../dao/NotificacaoDAO.php';
require_once __DIR__ . '/../dao/PreferenciaNotificacaoDAO.php';
require_once __DIR__ . '/../models/Protocolo.php';

class NotificacaoService
{
    private NotificacaoDAO $dao;

    public function __construct(?NotificacaoDAO $dao = null)
    {
        $this->dao = $dao ?? new NotificacaoDAO();
    }

    public function criar(int $usuarioId, string $titulo, string $mensagem): Notificacao
    {
        $titulo = trim($titulo);
        $mensagem = trim($mensagem);

        if ($usuarioId < 1 || $titulo === '' || $mensagem === '') {
            throw new InvalidArgumentException('Usuário, título e mensagem são obrigatórios.');
        }

        $notificacao = new Notificacao(null, $usuarioId, null, $titulo, $mensagem);
        return $this->dao->create($notificacao);
    }

    /**
     * Gera a notificação de mudança de status, respeitando a preferência
     * "notificar_mudanca_status" do cidadão. Retorna null quando ele desativou.
     */
    public function gerarPorMudancaProtocolo(Protocolo $protocolo): ?Notificacao
    {
        $preferencias = (new PreferenciaNotificacaoDAO())->buscarPorUsuario($protocolo->getUsuarioId());
        if (!$preferencias->notificarMudancaStatus) {
            return null;
        }

        $titulo = 'Atualização do protocolo ' . $protocolo->getNumero();
        $rotulos = [
            'recebido'   => 'Recebido',
            'em_analise' => 'Em Análise',
            'respondido' => 'Respondido',
            'finalizado' => 'Finalizado',
        ];
        $mensagem = '"' . $protocolo->getTitulo() . '" está agora com status: '
            . ($rotulos[$protocolo->getStatus()] ?? $protocolo->getStatus()) . '.';
        if ($protocolo->getStatus() === 'respondido' && $protocolo->getResposta()) {
            $mensagem .= ' Resposta: ' . $protocolo->getResposta();
        }
        $notificacao = new Notificacao(
            null,
            $protocolo->getUsuarioId(),
            $protocolo->getId(),
            $titulo,
            $mensagem
        );

        return $this->dao->create($notificacao);
    }

    public function listar(int $usuarioId): array
    {
        if ($usuarioId < 1) {
            throw new InvalidArgumentException('usuario_id inválido.');
        }

        return $this->dao->all($usuarioId);
    }

    public function marcarTodasLidas(int $usuarioId): int
    {
        return $this->dao->readAll($usuarioId);
    }

    public function comunicar(string $titulo, string $mensagem, ?string $bairro = null): int
    {
        $titulo = trim($titulo);
        $mensagem = trim($mensagem);

        if ($titulo === '' || $mensagem === '') {
            throw new InvalidArgumentException('Título e mensagem são obrigatórios.');
        }

        return $this->dao->broadcast($titulo, $mensagem, $bairro);
    }

    public function marcarLida(int $id, int $usuarioId): bool
    {
        return $this->dao->read($id, $usuarioId);
    }
}
