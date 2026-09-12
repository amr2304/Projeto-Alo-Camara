<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Usuario.php';

/**
 * DAO de Usuario — único ponto que fala SQL sobre a tabela `usuarios`.
 * O Service nunca deve montar SQL diretamente, só chamar métodos daqui.
 */
class UsuarioDAO
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function create(Usuario $usuario): int
    {
        $sql = 'INSERT INTO usuarios (nome, email, senha_hash, telefone, bairro, notif_push, notif_email)
                VALUES (:nome, :email, :senha_hash, :telefone, :bairro, :notif_push, :notif_email)';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':nome'        => $usuario->getNome(),
            ':email'       => $usuario->getEmail(),
            ':senha_hash'  => $usuario->getSenhaHash(),
            ':telefone'    => $usuario->getTelefone(),
            ':bairro'      => $usuario->getBairro(),
            ':notif_push'  => (int) $usuario->isNotifPush(),
            ':notif_email' => (int) $usuario->isNotifEmail(),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function findById(int $id): ?Usuario
    {
        $stmt = $this->conn->prepare('SELECT * FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? Usuario::fromArray($row) : null;
    }

    public function findByEmail(string $email): ?Usuario
    {
        $stmt = $this->conn->prepare('SELECT * FROM usuarios WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row ? Usuario::fromArray($row) : null;
    }

    public function emailExiste(string $email): bool
    {
        $stmt = $this->conn->prepare('SELECT 1 FROM usuarios WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    public function update(Usuario $usuario): bool
    {
        $sql = 'UPDATE usuarios
                SET nome = :nome, telefone = :telefone, bairro = :bairro
                WHERE id = :id';

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':nome'     => $usuario->getNome(),
            ':telefone' => $usuario->getTelefone(),
            ':bairro'   => $usuario->getBairro(),
            ':id'       => $usuario->getId(),
        ]);
    }

    public function updateSenha(int $id, string $novaSenhaHash): bool
    {
        $stmt = $this->conn->prepare('UPDATE usuarios SET senha_hash = :senha WHERE id = :id');

        return $stmt->execute([
            ':senha' => $novaSenhaHash,
            ':id'    => $id,
        ]);
    }

    public function updatePreferenciasNotificacao(int $id, bool $push, bool $email): bool
    {
        $sql = 'UPDATE usuarios SET notif_push = :push, notif_email = :email WHERE id = :id';
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':push'  => (int) $push,
            ':email' => (int) $email,
            ':id'    => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM usuarios WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }
}
