<?php
/**
 * ApiController — rotas consumidas pelo Frontend (docs/BACKEND.md) e proteção das
 * rotas "legadas" dos módulos (UsuarioController, ProtocoloController, ModuloController).
 *
 * tratar() devolve true quando respondeu (na prática Response::json encerra a execução)
 * e false quando a rota deve seguir para os controllers legados — já com a autenticação
 * exigida quando necessário.
 *
 * Rotas novas:
 *   POST  /auth/login | /auth/cadastro | /auth/google | /auth/govbr
 *   GET|PUT /usuarios/me, PUT /usuarios/me/senha, GET|PUT /usuarios/me/preferencias
 *   POST  /protocolos, GET /protocolos/meus, GET /protocolos/{numero}
 *   POST  /vereadores/{id}/avaliacao
 *   GET   /historia, POST /upload
 *   GET|PATCH /notificacoes, /notificacoes/lidas, /notificacoes/{id}/lida
 *   /admin/dashboard | protocolos | vereadores | eventos | comunicados | usuarios
 */
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../Services/UsuarioService.php';
require_once __DIR__ . '/../Services/ProtocoloService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';
require_once __DIR__ . '/../Services/PreferenciaNotificacaoService.php';
require_once __DIR__ . '/../Services/VereadorService.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';
require_once __DIR__ . '/ModuloController.php';

class ApiController
{
    private const TAMANHO_MAX_UPLOAD = 10 * 1024 * 1024;

    public function tratar(array $partes, string $metodo): bool
    {
        $r0 = $partes[0] ?? '';
        $r1 = $partes[1] ?? '';
        $r2 = $partes[2] ?? '';

        try {
            switch ($r0) {
                case 'auth':
                    return $this->auth($r1, $metodo);
                case 'usuarios':
                    return $this->usuarios($r1, $r2, $metodo);
                case 'protocolos':
                    return $this->protocolos($r1, $metodo);
                case 'vereadores':
                    return $this->vereadores($r1, $r2, $metodo);
                case 'agenda':
                    if ($metodo !== 'GET' && $metodo !== 'OPTIONS') {
                        Auth::exigirAdmin();
                    }
                    return $this->agendaComFiltro($metodo, $r1);
                case 'historia':
                    return $this->historia($metodo);
                case 'upload':
                    return $this->upload($metodo);
                case 'notificacoes':
                    return $this->notificacoes($r1, $r2, $metodo);
                case 'admin':
                    return $this->admin($partes, $metodo);
            }
        } catch (InvalidArgumentException $erro) {
            Response::json(400, ['erro' => $erro->getMessage()]);
        } catch (OutOfBoundsException $erro) {
            Response::json(404, ['erro' => $erro->getMessage()]);
        } catch (Throwable $erro) {
            error_log("[api/{$r0}] " . $erro->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar a solicitação.']);
        }

        return false;
    }

    // ------------------------------------------------------------------ auth

    private function auth(string $acao, string $metodo): bool
    {
        if ($metodo !== 'POST') {
            Response::metodoNaoPermitido('POST');
        }

        $service = new UsuarioService();

        if ($acao === 'login') {
            $corpo = Response::lerCorpo();
            $email = trim((string) ($corpo['email'] ?? ''));
            $senha = (string) ($corpo['senha'] ?? '');
            if ($email === '' || $senha === '') {
                Response::json(400, ['erro' => 'Informe e-mail e senha.']);
            }

            try {
                $usuario = $service->login($email, $senha);
            } catch (InvalidArgumentException $erro) {
                Response::json(401, ['erro' => $erro->getMessage()]);
            }

            Response::json(200, $this->sessao($usuario));
        }

        if ($acao === 'cadastro') {
            $corpo = Response::lerCorpo();
            $usuario = $service->cadastrar(
                (string) ($corpo['nome'] ?? ''),
                (string) ($corpo['email'] ?? ''),
                (string) ($corpo['senha'] ?? ''),
                isset($corpo['telefone']) ? (string) $corpo['telefone'] : null,
                isset($corpo['bairro']) ? (string) $corpo['bairro'] : null
            );
            Response::json(201, $this->sessao($usuario));
        }

        if ($acao === 'google' || $acao === 'govbr') {
            // Sem validar o token do provedor, aceitar o e-mail enviado pelo cliente
            // permitiria entrar na conta de qualquer pessoa. Fica desligado até haver OAuth real.
            Response::json(501, ['erro' => 'Login social ainda não está disponível. Use e-mail e senha.']);
        }

        Response::json(404, ['erro' => 'Rota de autenticação não encontrada.']);
    }

    private function sessao(Usuario $usuario): array
    {
        return [
            'token'   => Auth::emitir((int) $usuario->getId(), $usuario->getPerfil()),
            'usuario' => $usuario->toArray(),
        ];
    }

    // -------------------------------------------------------------- usuarios

    private function usuarios(string $r1, string $r2, string $metodo): bool
    {
        $service = new UsuarioService();

        if ($r1 === 'me') {
            $usuario = Auth::exigir();
            $id = $usuario['id'];

            if ($r2 === '' && $metodo === 'GET') {
                Response::json(200, ['usuario' => $service->buscarPorId($id)->toArray()]);
            }

            if ($r2 === '' && $metodo === 'PUT') {
                $corpo = Response::lerCorpo();
                $atualizado = $service->editarPerfil(
                    $id,
                    (string) ($corpo['nome'] ?? ''),
                    isset($corpo['telefone']) ? (string) $corpo['telefone'] : null,
                    isset($corpo['bairro']) ? (string) $corpo['bairro'] : null
                );
                Response::json(200, ['usuario' => $atualizado->toArray()]);
            }

            if ($r2 === 'senha' && $metodo === 'PUT') {
                $corpo = Response::lerCorpo();
                $service->alterarSenha($id, (string) ($corpo['senha_atual'] ?? ''), (string) ($corpo['nova_senha'] ?? ''));
                Response::json(200, ['mensagem' => 'Senha alterada com sucesso.']);
            }

            if ($r2 === 'preferencias' && in_array($metodo, ['GET', 'PUT', 'PATCH'], true)) {
                $prefs = new PreferenciaNotificacaoService();
                if ($metodo === 'GET') {
                    Response::json(200, ['preferencias' => $prefs->obter($id)]);
                }
                Response::json(200, ['preferencias' => $prefs->atualizar($id, Response::lerCorpo())]);
            }

            Response::json(404, ['erro' => 'Rota de usuário não encontrada.']);
        }

        // Rotas legadas que recebem "id" no corpo/URL: só valem para o próprio usuário logado.
        if (in_array($r1, ['perfil', 'alterar-senha', 'status', 'preferencias'], true)) {
            $usuario = Auth::exigir();
            $corpo = json_decode((string) file_get_contents('php://input'), true);
            $idInformado = (int) ($_GET['id'] ?? (is_array($corpo) ? ($corpo['id'] ?? 0) : 0));
            if ($idInformado !== $usuario['id']) {
                Response::json(403, ['erro' => 'Você só pode alterar a sua própria conta.']);
            }
        }

        return false;
    }

    // ------------------------------------------------------------- protocolos

    private function protocolos(string $r1, string $metodo): bool
    {
        $service = new ProtocoloService();

        if ($r1 === '' && $metodo === 'POST') {
            $usuario = Auth::exigir();
            $corpo = Response::lerCorpo();
            $corpo['usuario_id'] = $usuario['id']; // nunca confia no id enviado pelo cliente
            $protocolo = $service->criar($corpo);
            Response::json(201, ['protocolo' => $protocolo->toArray()]);
        }

        if ($r1 === 'meus' && $metodo === 'GET') {
            $usuario = Auth::exigir();
            $filtros = array_intersect_key($_GET, array_flip(['status', 'tipo', 'bairro']));
            $filtros['usuario_id'] = $usuario['id'];
            $lista = array_map(fn (Protocolo $p) => $p->toArray(), $service->listar($filtros));
            Response::json(200, ['protocolos' => $lista]);
        }

        if (preg_match('/^AL\d+$/i', $r1) && $metodo === 'GET') {
            $usuario = Auth::exigir();
            $protocolo = $service->obterPorNumero(strtoupper($r1));
            $ehDono = $protocolo !== null && $protocolo->getUsuarioId() === $usuario['id'];
            if ($protocolo === null || (!$ehDono && $usuario['perfil'] !== 'admin')) {
                Response::json(404, ['erro' => 'Protocolo não encontrado.']);
            }

            Response::json(200, [
                'protocolo' => $protocolo->toArray(!$ehDono && $protocolo->isSigiloso()),
                'historico' => $service->historico((int) $protocolo->getId()),
            ]);
        }

        // Demais rotas (listagem geral, GET/PATCH/DELETE por id) são administrativas.
        Auth::exigirAdmin();

        return false;
    }

    // ------------------------------------------------------------- vereadores

    private function vereadores(string $r1, string $r2, string $metodo): bool
    {
        if (ctype_digit($r1) && $r2 === 'avaliacao') {
            if ($metodo !== 'POST') {
                Response::metodoNaoPermitido('POST');
            }
            $usuario = Auth::exigir();
            $corpo = Response::lerCorpo();
            $vereador = (new VereadorService())->avaliar((int) $r1, $usuario['id'], (int) ($corpo['nota'] ?? 0));
            Response::json(200, ['vereador' => $vereador->toArray()]);
        }

        if ($metodo !== 'GET') {
            Auth::exigirAdmin();
        }

        return false;
    }

    // ----------------------------------------------------------------- agenda

    /** GET /agenda?tipo=sessao|comissao|audiencia — o controller legado não filtra por tipo. */
    private function agendaComFiltro(string $metodo, string $acao): bool
    {
        $tipo = strtolower(trim((string) ($_GET['tipo'] ?? '')));
        if ($metodo === 'GET' && $acao === '' && $tipo !== '') {
            $eventos = array_values(array_filter(
                (new AgendaService())->listar(),
                fn (Evento $e) => $e->getTipo() === $tipo
            ));
            Response::json(200, ['eventos' => array_map(fn (Evento $e) => $e->toArray(), $eventos)]);
        }

        return false;
    }

    // ---------------------------------------------------------------- historia

    private function historia(string $metodo): bool
    {
        if ($metodo !== 'GET') {
            Response::metodoNaoPermitido('GET');
        }

        $linhas = Database::getConnection()
            ->query('SELECT id, ano, nome, descricao FROM historia_camara ORDER BY ano DESC')
            ->fetchAll();
        Response::json(200, ['historia' => $linhas]);

        return true;
    }

    // ------------------------------------------------------------------ upload

    private function upload(string $metodo): bool
    {
        if ($metodo !== 'POST') {
            Response::metodoNaoPermitido('POST');
        }
        Auth::exigir();

        $arquivo = $_FILES['arquivo'] ?? null;
        if (!$arquivo || $arquivo['error'] !== UPLOAD_ERR_OK) {
            Response::json(400, ['erro' => 'Envie uma imagem no campo "arquivo".']);
        }

        if ($arquivo['size'] > self::TAMANHO_MAX_UPLOAD) {
            Response::json(400, ['erro' => 'A imagem deve ter no máximo 10MB.']);
        }

        $extensoes = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        if (!isset($extensoes[$mime])) {
            Response::json(400, ['erro' => 'Apenas imagens PNG ou JPG são aceitas.']);
        }

        $pasta = __DIR__ . '/../uploads';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $nome = bin2hex(random_bytes(16)) . '.' . $extensoes[$mime];
        if (!move_uploaded_file($arquivo['tmp_name'], "{$pasta}/{$nome}")) {
            throw new RuntimeException('Falha ao salvar o arquivo enviado.');
        }

        Response::json(201, ['url' => "/uploads/{$nome}"]);

        return true;
    }

    // ------------------------------------------------------------ notificacoes

    private function notificacoes(string $r1, string $r2, string $metodo): bool
    {
        $usuario = Auth::exigir();
        $service = new NotificacaoService();

        if ($r1 === '' && $metodo === 'GET') {
            $lista = array_map(fn (Notificacao $n) => $n->toArray(), $service->listar($usuario['id']));
            Response::json(200, ['notificacoes' => $lista]);
        }

        if ($r1 === 'lidas' && $metodo === 'PATCH') {
            Response::json(200, ['atualizadas' => $service->marcarTodasLidas($usuario['id'])]);
        }

        if (ctype_digit($r1) && $r2 === 'lida' && $metodo === 'PATCH') {
            if (!$service->marcarLida((int) $r1, $usuario['id'])) {
                Response::json(404, ['erro' => 'Notificação não encontrada.']);
            }
            Response::json(200, ['mensagem' => 'Notificação marcada como lida.']);
        }

        Response::json(404, ['erro' => 'Rota de notificação não encontrada.']);

        return true;
    }

    // ------------------------------------------------------------------- admin

    private function admin(array $partes, string $metodo): bool
    {
        $adm = Auth::exigirAdmin();
        $recurso = $partes[1] ?? '';
        $id = $partes[2] ?? '';
        $acao = $partes[3] ?? '';

        switch ($recurso) {
            case 'dashboard':
                if ($metodo !== 'GET') {
                    Response::metodoNaoPermitido('GET');
                }
                Response::json(200, ['dashboard' => (new ProtocoloService())->estatisticas()]);
                break;

            case 'protocolos':
                $this->adminProtocolos($id, $metodo, $adm['id']);
                break;

            case 'vereadores':
            case 'eventos':
                if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
                    Response::metodoNaoPermitido('POST, PUT ou DELETE');
                }
                $modulo = $recurso === 'vereadores' ? 'vereadores' : 'agenda';
                (new ModuloController())->despachar($modulo, $id, $metodo);
                break;

            case 'comunicados':
                if ($metodo !== 'POST') {
                    Response::metodoNaoPermitido('POST');
                }
                $corpo = Response::lerCorpo();
                $enviados = (new NotificacaoService())->comunicar(
                    (string) ($corpo['titulo'] ?? ''),
                    (string) ($corpo['mensagem'] ?? ''),
                    isset($corpo['bairro']) ? (string) $corpo['bairro'] : null
                );
                Response::json(201, ['enviados' => $enviados]);
                break;

            case 'usuarios':
                $this->adminUsuarios($id, $acao, $metodo);
                break;
        }

        Response::json(404, ['erro' => 'Rota administrativa não encontrada.']);

        return true;
    }

    private function adminProtocolos(string $id, string $metodo, int $adminId): void
    {
        $service = new ProtocoloService();

        if ($id === '' && $metodo === 'GET') {
            $filtros = array_intersect_key($_GET, array_flip(['status', 'tipo', 'busca']));
            $linhas = array_map(function (array $linha): array {
                $sigiloso = (bool) $linha['sigiloso'];
                $linha['cidadao'] = $sigiloso ? 'Anônimo' : $linha['usuario_nome'];
                unset($linha['usuario_nome']);
                if ($sigiloso) {
                    $linha['usuario_id'] = null;
                }

                return $linha;
            }, $service->listarParaAdmin($filtros));
            Response::json(200, ['protocolos' => $linhas]);
        }

        if (ctype_digit($id) && $metodo === 'GET') {
            $protocolo = $service->obter((int) $id);
            if ($protocolo === null) {
                Response::json(404, ['erro' => 'Protocolo não encontrado.']);
            }
            Response::json(200, [
                'protocolo' => $protocolo->toArray($protocolo->isSigiloso()),
                'historico' => $service->historico((int) $id),
            ]);
        }

        if (ctype_digit($id) && $metodo === 'PATCH') {
            $corpo = Response::lerCorpo();
            $protocolo = $service->atualizarPeloAdmin(
                (int) $id,
                (string) ($corpo['status'] ?? ''),
                isset($corpo['resposta']) ? (string) $corpo['resposta'] : null,
                isset($corpo['encaminhar_para']) ? (string) $corpo['encaminhar_para'] : null,
                $adminId
            );
            Response::json(200, ['protocolo' => $protocolo->toArray($protocolo->isSigiloso())]);
        }
    }

    private function adminUsuarios(string $id, string $acao, string $metodo): void
    {
        $dao = new UsuarioDAO();

        if ($id === '' && $metodo === 'GET') {
            $linhas = array_map(function (array $linha): array {
                $linha['ativo'] = (bool) $linha['ativo'];
                $linha['total_protocolos'] = (int) $linha['total_protocolos'];

                return $linha;
            }, $dao->listarCidadaos());
            Response::json(200, ['usuarios' => $linhas]);
        }

        if (ctype_digit($id) && $acao === 'bloquear' && $metodo === 'PATCH') {
            $usuario = $dao->findById((int) $id);
            if ($usuario === null || $usuario->getPerfil() !== 'cidadao') {
                Response::json(404, ['erro' => 'Cidadão não encontrado.']);
            }

            $corpo = json_decode((string) file_get_contents('php://input'), true);
            $bloquear = is_array($corpo) && array_key_exists('bloqueado', $corpo)
                ? (bool) $corpo['bloqueado']
                : $usuario->isAtivo(); // sem corpo: alterna o estado atual

            $dao->definirAtivo((int) $id, !$bloquear);
            Response::json(200, ['id' => (int) $id, 'ativo' => !$bloquear]);
        }
    }
}
