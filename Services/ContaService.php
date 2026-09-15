<?php

require_once __DIR__ . '/../dao/UsuarioContaDAO.php';

class ContaService
{
    private UsuarioContaDAO $dao;

    public function __construct()
    {
        $this->dao = new UsuarioContaDAO();
    }

    /**
     * @throws InvalidArgumentException se o usuário não existir, a senha
     *         atual estiver errada, ou a nova senha for fraca
     */
    public function alterarSenha(int $usuarioId, string $senhaAtual, string $novaSenha): void
    {
        $usuario = $this->dao->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new InvalidArgumentException('Usuário não encontrado', 404);
        }

        if (!password_verify($senhaAtual, $usuario['senha_hash'])) {
            throw new InvalidArgumentException('Senha atual incorreta', 401);
        }

        if (strlen($novaSenha) < 8) {
            throw new InvalidArgumentException('A nova senha deve ter ao menos 8 caracteres', 422);
        }

        $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $this->dao->atualizarSenha($usuarioId, $novoHash);
    }

    /**
     * @throws InvalidArgumentException se o usuário não existir
     */
    public function definirStatus(int $usuarioId, bool $ativo): array
    {
        $usuario = $this->dao->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new InvalidArgumentException('Usuário não encontrado', 404);
        }

        $this->dao->atualizarStatus($usuarioId, $ativo);

        return [
            'usuario_id' => $usuarioId,
            'ativo' => $ativo,
        ];
    }
}
