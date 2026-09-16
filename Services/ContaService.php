<?php
require_once __DIR__ . '/../dao/UsuarioContaDAO.php';

/**
 * Service de Conta — cobre "Configurações de conta": ativar/desativar
 * e alterar senha, usando o UsuarioContaDAO (enxuto, focado só nesses campos).
 *
 * ATENÇÃO (débito técnico conhecido): já existe UsuarioService::alterarSenha()
 * usando UsuarioDAO para o mesmo fim. Este método foi mantido aqui só para
 * não quebrar o endpoint /usuarios/senha que já estava implementado — o ideal
 * é o grupo escolher UM dos dois caminhos (UsuarioService ou ContaService)
 * e remover o outro antes da entrega final.
 */
class ContaService
{
    private UsuarioContaDAO $dao;

    public function __construct(?UsuarioContaDAO $dao = null)
    {
        $this->dao = $dao ?? new UsuarioContaDAO();
    }

    /**
     * @throws InvalidArgumentException código 404 se usuário não existir
     */
    public function definirStatus(int $usuarioId, bool $ativo): array
    {
        $usuario = $this->dao->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new InvalidArgumentException('Usuário não encontrado.', 404);
        }

        $this->dao->atualizarStatus($usuarioId, $ativo);

        return [
            'usuario_id' => $usuarioId,
            'ativo'      => $ativo,
        ];
    }

    /**
     * @throws InvalidArgumentException 404 usuário não encontrado,
     *         401 senha atual incorreta, 422 nova senha inválida
     */
    public function alterarSenha(int $usuarioId, string $senhaAtual, string $novaSenha): bool
    {
        $usuario = $this->dao->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new InvalidArgumentException('Usuário não encontrado.', 404);
        }

        if (!password_verify($senhaAtual, (string) $usuario['senha_hash'])) {
            throw new InvalidArgumentException('Senha atual incorreta.', 401);
        }

        if (strlen($novaSenha) < 6) {
            throw new InvalidArgumentException('Nova senha deve ter ao menos 6 caracteres.', 422);
        }

        return $this->dao->atualizarSenha($usuarioId, password_hash($novaSenha, PASSWORD_BCRYPT));
    }
}
