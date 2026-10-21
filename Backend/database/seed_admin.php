<?php
/**
 * Cria (ou promove) o usuário administrador do painel.
 *
 * Uso (na pasta Backend):
 *   php database/seed_admin.php [email] [senha] [nome]
 *
 * Padrão: admin@camara.gov.br / 123456 / "Administrador"
 * Troque a senha padrão antes de qualquer uso fora do ambiente de desenvolvimento.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute apenas pela linha de comando.');
}

require_once __DIR__ . '/../config/Database.php';

$email = strtolower($argv[1] ?? 'admin@camara.gov.br');
$senha = $argv[2] ?? '123456';
$nome  = $argv[3] ?? 'Administrador';

$conn = Database::getConnection();
$hash = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = :email');
$stmt->execute(['email' => $email]);
$id = $stmt->fetchColumn();

if ($id) {
    $conn->prepare("UPDATE usuarios SET perfil = 'admin', ativo = 1, senha_hash = :hash WHERE id = :id")
        ->execute(['hash' => $hash, 'id' => $id]);
    echo "Usuário {$email} promovido a admin.\n";
} else {
    $conn->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (:nome, :email, :hash, 'admin')")
        ->execute(['nome' => $nome, 'email' => $email, 'hash' => $hash]);
    echo "Admin {$email} criado.\n";
}
