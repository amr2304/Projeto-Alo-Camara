<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/PreferenciaNotificacao.php';

class PreferenciaNotificacaoDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    /**
     * Busca as preferências do usuário. Se ele ainda não tiver uma linha
     * (primeiro acesso), cria uma com os valores padrão (tudo ativado)
     * e retorna já criada.
     */
    public function buscarPorUsuario(int $usuarioId): PreferenciaNotificacao
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM preferencias_notificacao WHERE usuario_id = :usuario_id'
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return $this->criarPadrao($usuarioId);
        }

        return PreferenciaNotificacao::fromArray($row);
    }

    private function criarPadrao(int $usuarioId): PreferenciaNotificacao
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO preferencias_notificacao (usuario_id) VALUES (:usuario_id)'
        );
        $stmt->execute(['usuario_id' => $usuarioId]);

        return new PreferenciaNotificacao($usuarioId);
    }

    public function atualizar(PreferenciaNotificacao $pref): PreferenciaNotificacao
    {
        // Garante que a linha existe antes de atualizar (upsert simples)
        $this->buscarPorUsuario($pref->usuarioId);

        $stmt = $this->conn->prepare(
            'UPDATE preferencias_notificacao SET
                notificar_email = :notificar_email,
                notificar_push = :notificar_push,
                notificar_mudanca_status = :notificar_mudanca_status,
                notificar_avisos_gerais = :notificar_avisos_gerais
             WHERE usuario_id = :usuario_id'
        );

        $stmt->execute([
            'notificar_email' => (int) $pref->notificarEmail,
            'notificar_push' => (int) $pref->notificarPush,
            'notificar_mudanca_status' => (int) $pref->notificarMudancaStatus,
            'notificar_avisos_gerais' => (int) $pref->notificarAvisosGerais,
            'usuario_id' => $pref->usuarioId,
        ]);

        return $this->buscarPorUsuario($pref->usuarioId);
    }
}
