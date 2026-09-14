<?php
require_once __DIR__ . '/../dao/UsuarioDAO.php';
require_once __DIR__ . '/../models/Usuario.php';

/**
 * Service de Usuario — concentra as regras de negócio da Parte 1
 * (Usuários & Autenticação). Endpoints/controllers devem falar só com esta
 * classe, nunca direto com o DAO.
 */
class UsuarioService
{
    private UsuarioDAO $dao;

    public function __construct(?UsuarioDAO $dao = null)
    {
        $this->dao = $dao ?? new UsuarioDAO();
    }

    /**
     * Cadastro de um novo cidadão.
     * @throws InvalidArgumentException se dados forem inválidos ou e-mail já existir
     */
    public function cadastrar(string $nome, string $email, string $senha, ?string $telefone = null, ?string $bairro = null): Usuario
    {
        $nome = trim($nome);
        $email = strtolower(trim($email));

        if ($nome === '' || strlen($nome) < 3) {
            throw new InvalidArgumentException('Nome deve ter ao menos 3 caracteres.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido.');
        }

        if (strlen($senha) < 6) {
            throw new InvalidArgumentException('Senha deve ter ao menos 6 caracteres.');
        }

        if ($this->dao->emailExiste($email)) {
            throw new InvalidArgumentException('Já existe uma conta com este e-mail.');
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

        $usuario = new Usuario(null, $nome, $email, $senhaHash, $telefone, $bairro);
        $id = $this->dao->create($usuario);
        $usuario->setId($id);

        return $usuario;
    }

    /**
     * Autentica por e-mail/senha (fluxo local — Google/GOV.BR entram como
     * stub separado, ex: autenticarViaOAuth()).
     * @throws InvalidArgumentException se credenciais forem inválidas
     */
    public function login(string $email, string $senha): Usuario
    {
        $usuario = $this->dao->findByEmail(strtolower(trim($email)));

        if (!$usuario || !password_verify($senha, (string) $usuario->getSenhaHash())) {
            throw new InvalidArgumentException('E-mail ou senha inválidos.');
        }

        return $usuario;
    }

    /**
     * Stub para login social (Google / GOV.BR).
     * Cada provedor deve preencher $dadosProvedor com pelo menos email e nome;
     * se o usuário ainda não existir, cria a conta automaticamente.
     */
    public function autenticarViaOAuth(string $provedor, array $dadosProvedor): Usuario
    {
        $email = strtolower(trim($dadosProvedor['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Provedor {$provedor} não retornou um e-mail válido.");
        }

        $usuario = $this->dao->findByEmail($email);

        if ($usuario) {
            return $usuario;
        }

        // Conta criada sem senha local (senha_hash nula) — login futuro só via OAuth.
        $novo = new Usuario(null, $dadosProvedor['nome'] ?? $email, $email);
        $id = $this->dao->create($novo);
        $novo->setId($id);

        return $novo;
    }

    public function buscarPorId(int $id): ?Usuario
    {
        return $this->dao->findById($id);
    }

    public function editarPerfil(int $id, string $nome, ?string $telefone, ?string $bairro): Usuario
    {
        $usuario = $this->dao->findById($id);

        if (!$usuario) {
            throw new InvalidArgumentException('Usuário não encontrado.');
        }

        $nome = trim($nome);
        if ($nome === '' || strlen($nome) < 3) {
            throw new InvalidArgumentException('Nome deve ter ao menos 3 caracteres.');
        }

        $usuario->setNome($nome);
        $usuario->setTelefone($telefone);
        $usuario->setBairro($bairro);

        $this->dao->update($usuario);

        return $usuario;
    }

    public function alterarSenha(int $id, string $senhaAtual, string $novaSenha): bool
    {
        $usuario = $this->dao->findById($id);

        if (!$usuario || !password_verify($senhaAtual, (string) $usuario->getSenhaHash())) {
            throw new InvalidArgumentException('Senha atual incorreta.');
        }

        if (strlen($novaSenha) < 6) {
            throw new InvalidArgumentException('Nova senha deve ter ao menos 6 caracteres.');
        }

        return $this->dao->updateSenha($id, password_hash($novaSenha, PASSWORD_BCRYPT));
    }

    public function atualizarPreferenciasNotificacao(int $id, bool $push, bool $email): bool
    {
        return $this->dao->updatePreferenciasNotificacao($id, $push, $email);
    }
}
