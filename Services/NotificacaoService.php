<?php

require_once __DIR__ . '/../dao/NotificacaoDAO.php';
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

    public function gerarPorMudancaProtocolo(Protocolo $protocolo): Notificacao
    {
        $titulo = 'Atualização do protocolo ' . $protocolo->getNumero();
        $mensagem = 'O protocolo está agora com status: ' . $protocolo->getStatus() . '.';
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

    public function marcarLida(int $id, int $usuarioId): bool
    {
        return $this->dao->read($id, $usuarioId);
    }
}
