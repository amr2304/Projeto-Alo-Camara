<?php
/**
 * UsuarioController — concentra todas as rotas de /usuarios (Parte 1:
 * Autenticação e Gestão de Conta).
 *
 * Rotas cobertas:
 *   POST /usuarios/login
 *   POST /usuarios/login-oauth
 *   POST /usuarios/cadastrar
 *   PUT  /usuarios/perfil
 *   PUT  /usuarios/alterar-senha
 *   PUT  /usuarios/status
 *   GET|PUT|PATCH /usuarios/preferencias
 *
 * NOTA DE INTEGRAÇÃO: "status" e "preferencias" foram originalmente enviados
 * como arquivos de rota separados (status.php, senha.php, preferencias.php)
 * usando um helper próprio (_helpers.php). Foram migrados para cá para manter
 * um único padrão de resposta (Response::json) e um único ponto de entrada
 * (index.php + despachar()). Os arquivos antigos e o _helpers.php podem ser
 * removidos do repositório.
 */
require_once __DIR__ . '/../services/UsuarioService.php';
require_once __DIR__ . '/../services/ContaService.php';
require_once __DIR__ . '/../services/PreferenciaNotificacaoService.php';
require_once __DIR__ . '/../utils/Response.php';

class UsuarioController
{
    private UsuarioService $service;
    private ?ContaService $contaServiceInstance = null;
    private ?PreferenciaNotificacaoService $prefServiceInstance = null;

    public function __construct(?UsuarioService $service = null)
    {
        $this->service = $service ?? new UsuarioService();
    }

    private function contaService(): ContaService
    {
        return $this->contaServiceInstance ??= new ContaService();
    }

    private function prefService(): PreferenciaNotificacaoService
    {
        return $this->prefServiceInstance ??= new PreferenciaNotificacaoService();
    }

    /**
     * Ponto de entrada único: recebe a ação (nome da rota) e o método HTTP,
     * valida o método e despacha para o handler correto.
     */
    public function despachar(string $acao, string $metodo): void
    {
        $mapa = [
            'login'          => ['POST', 'login'],
            'login-oauth'    => ['POST', 'loginOauth'],
            'cadastrar'      => ['POST', 'cadastrar'],
            'perfil'         => ['PUT', 'perfil'],
            'alterar-senha'  => ['PUT', 'alterarSenha'],
            'status'         => ['PUT', 'status'],
            'preferencias'   => [['GET', 'PUT', 'PATCH'], 'preferencias'],
        ];

        if (!isset($mapa[$acao])) {
            Response::json(404, ['erro' => "Rota /usuarios/{$acao} não existe."]);
        }

        [$metodoEsperado, $handler] = $mapa[$acao];
        $metodosPermitidos = is_array($metodoEsperado) ? $metodoEsperado : [$metodoEsperado];

        if (!in_array($metodo, $metodosPermitidos, true)) {
            Response::metodoNaoPermitido(implode(' ou ', $metodosPermitidos));
        }

        $this->$handler();
    }

    /**
     * POST /usuarios/login
     * Body: { "email": "...", "senha": "..." }
     * 200 sucesso | 400 corpo inválido | 401 credenciais inválidas | 500 erro interno
     */
    private function login(): void
    {
        $body = Response::lerCorpo();
        $email = trim((string) ($body['email'] ?? ''));
        $senha = (string) ($body['senha'] ?? '');

        if ($email === '' || $senha === '') {
            Response::json(400, ['erro' => 'Os campos "email" e "senha" são obrigatórios.']);
        }

        try {
            $usuario = $this->service->login($email, $senha);
            Response::json(200, [
                'mensagem' => 'Login realizado com sucesso.',
                'usuario'  => $usuario->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            Response::json(401, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/login] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar o login.']);
        }
    }

    /**
     * POST /usuarios/login-oauth
     * STUB — estrutura preparada para login social (Google / GOV.BR).
     * Body: { "provedor": "google", "dados": { "email": "...", "nome": "..." } }
     * 200 sucesso | 400 dados inválidos | 500 erro interno
     */
    private function loginOauth(): void
    {
        $body = Response::lerCorpo();
        $provedor = trim((string) ($body['provedor'] ?? ''));
        $dadosProvedor = is_array($body['dados'] ?? null) ? $body['dados'] : [];

        if ($provedor === '') {
            Response::json(400, ['erro' => 'O campo "provedor" é obrigatório (ex: "google", "govbr").']);
        }

        try {
            $usuario = $this->service->autenticarViaOAuth($provedor, $dadosProvedor);
            Response::json(200, [
                'mensagem' => "Login via {$provedor} realizado com sucesso.",
                'usuario'  => $usuario->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            Response::json(400, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/login-oauth] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar o login social.']);
        }
    }

    /**
     * POST /usuarios/cadastrar
     * Body: { "nome", "email", "senha", "telefone"?, "bairro"? }
     * 201 sucesso | 400 dados inválidos/e-mail já existe | 500 erro interno
     */
    private function cadastrar(): void
    {
        $body = Response::lerCorpo();
        $nome = (string) ($body['nome'] ?? '');
        $email = (string) ($body['email'] ?? '');
        $senha = (string) ($body['senha'] ?? '');
        $telefone = isset($body['telefone']) ? (string) $body['telefone'] : null;
        $bairro = isset($body['bairro']) ? (string) $body['bairro'] : null;

        try {
            $usuario = $this->service->cadastrar($nome, $email, $senha, $telefone, $bairro);
            Response::json(201, [
                'mensagem' => 'Cadastro realizado com sucesso.',
                'usuario'  => $usuario->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            Response::json(400, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/cadastrar] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar o cadastro.']);
        }
    }

    /**
     * PUT /usuarios/perfil
     * Body: { "id", "nome", "telefone"?, "bairro"? }
     *
     * ATENÇÃO (débito técnico conhecido): "id" vem do corpo porque ainda não
     * há sessão/token. Trocar pelo ID do usuário autenticado assim que
     * houver sessão/JWT — hoje qualquer um pode editar perfil alheio.
     * O mesmo vale para alterarSenha(), status() e preferencias() abaixo.
     *
     * 200 sucesso | 400 dados inválidos/usuário não encontrado | 500 erro interno
     */
    private function perfil(): void
    {
        $body = Response::lerCorpo();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $nome = (string) ($body['nome'] ?? '');
        $telefone = isset($body['telefone']) ? (string) $body['telefone'] : null;
        $bairro = isset($body['bairro']) ? (string) $body['bairro'] : null;

        if ($id <= 0) {
            Response::json(400, ['erro' => 'O campo "id" é obrigatório e deve ser um inteiro válido.']);
        }

        try {
            $usuario = $this->service->editarPerfil($id, $nome, $telefone, $bairro);
            Response::json(200, [
                'mensagem' => 'Perfil atualizado com sucesso.',
                'usuario'  => $usuario->toArray(),
            ]);
        } catch (InvalidArgumentException $e) {
            Response::json(400, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/perfil] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao atualizar o perfil.']);
        }
    }

    /**
     * PUT /usuarios/alterar-senha
     * Body: { "id", "senha_atual", "nova_senha" }
     * 200 sucesso | 400 senha atual incorreta/nova senha inválida | 500 erro interno
     */
    private function alterarSenha(): void
    {
        $body = Response::lerCorpo();
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        $senhaAtual = (string) ($body['senha_atual'] ?? '');
        $novaSenha = (string) ($body['nova_senha'] ?? '');

        if ($id <= 0) {
            Response::json(400, ['erro' => 'O campo "id" é obrigatório e deve ser um inteiro válido.']);
        }

        if ($senhaAtual === '' || $novaSenha === '') {
            Response::json(400, ['erro' => 'Os campos "senha_atual" e "nova_senha" são obrigatórios.']);
        }

        try {
            $this->service->alterarSenha($id, $senhaAtual, $novaSenha);
            Response::json(200, ['mensagem' => 'Senha alterada com sucesso.']);
        } catch (InvalidArgumentException $e) {
            Response::json(400, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/alterar-senha] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao alterar a senha.']);
        }
    }

    /**
     * PUT /usuarios/status
     * Body: { "id", "ativo": true|false }
     * Usado nas Configurações de conta para o cidadão desativar/reativar a própria conta.
     * 200 sucesso | 400 dados inválidos | 404 usuário não encontrado | 500 erro interno
     */
    private function status(): void
    {
        $body = Response::lerCorpo();
        $id = isset($body['id']) ? (int) $body['id'] : 0;

        if ($id <= 0) {
            Response::json(400, ['erro' => 'O campo "id" é obrigatório e deve ser um inteiro válido.']);
        }

        if (!array_key_exists('ativo', $body)) {
            Response::json(400, ['erro' => 'O campo "ativo" (true/false) é obrigatório.']);
        }

        try {
            $resultado = $this->contaService()->definirStatus($id, (bool) $body['ativo']);
            Response::json(200, [
                'mensagem' => $resultado['ativo'] ? 'Conta reativada com sucesso.' : 'Conta desativada com sucesso.',
                'status'   => $resultado,
            ]);
        } catch (InvalidArgumentException $e) {
            Response::json($e->getCode() ?: 400, ['erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('[usuarios/status] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao atualizar status da conta.']);
        }
    }

    /**
     * GET  /usuarios/preferencias?id=1
     * PUT|PATCH /usuarios/preferencias
     *     Body: { "id", "notificar_email"?, "notificar_push"?,
     *              "notificar_mudanca_status"?, "notificar_avisos_gerais"? }
     *
     * GET retorna as preferências atuais (cria com padrão "tudo ativado" no
     * primeiro acesso). PUT/PATCH atualiza só os campos enviados.
     *
     * 200 sucesso | 400 "id" ausente/inválido | 500 erro interno
     */
    private function preferencias(): void
    {
        $metodo = $_SERVER['REQUEST_METHOD'];
        $body = $metodo === 'GET' ? [] : Response::lerCorpo();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            Response::json(400, [
                'erro' => 'O campo "id" é obrigatório e deve ser um inteiro válido (via ?id= na URL ou no corpo).',
            ]);
        }

        try {
            if ($metodo === 'GET') {
                Response::json(200, ['preferencias' => $this->prefService()->obter($id)]);
            }

            $atualizado = $this->prefService()->atualizar($id, $body);
            Response::json(200, [
                'mensagem'     => 'Preferências atualizadas com sucesso.',
                'preferencias' => $atualizado,
            ]);
        } catch (Throwable $e) {
            error_log('[usuarios/preferencias] ' . $e->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar preferências.']);
        }
    }
}
