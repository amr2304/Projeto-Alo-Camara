<?php

require_once __DIR__ . '/../config/Database.php';

/**
 * DAO enxuto usado apenas pelas rotinas de "Configurações de conta"
 * (alterar senha / ativar-desativar). Cadastro, login e edição de perfil
 * completos ficam no UsuarioDAO principal, feito pelo resto da Parte 1.
 */
class UsuarioContaDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function buscarPorId(int $usuarioId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT id, nome, email, senha_hash, ativo FROM usuarios WHERE id = :id'
        );
        $stmt->execute(['id' => $usuarioId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function atualizarSenha(int $usuarioId, string $novaSenhaHash): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id'
        );

        return $stmt->execute([
            'senha_hash' => $novaSenhaHash,
            'id' => $usuarioId,
        ]);
    }

    public function atualizarStatus(int $usuarioId, bool $ativo): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE usuarios SET ativo = :ativo WHERE id = :id'
        );

        return $stmt->execute([
            'ativo' => (int) $ativo,
            'id' => $usuarioId,
        ]);
    }
}
