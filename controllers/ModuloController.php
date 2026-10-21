<?php

require_once __DIR__ . '/../Services/AgendaService.php';
require_once __DIR__ . '/../Services/NotificacaoService.php';
require_once __DIR__ . '/../Services/VereadorService.php';
require_once __DIR__ . '/../utils/Response.php';

class ModuloController
{
    public function despachar(string $modulo, string $acao, string $metodo): void
    {
        try {
            switch ($modulo) {
                case 'vereadores':
                    $this->despacharVereadores($acao, $metodo);
                    break;
                case 'agenda':
                    $this->despacharAgenda($acao, $metodo);
                    break;
                case 'notificacoes':
                    $this->despacharNotificacoes($acao, $metodo);
                    break;
                default:
                    Response::json(404, ['erro' => 'Módulo não encontrado.']);
            }
        } catch (InvalidArgumentException $erro) {
            Response::json(400, ['erro' => $erro->getMessage()]);
        } catch (Throwable $erro) {
            error_log("[{$modulo}] " . $erro->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar a solicitação.']);
        }
    }

    private function despacharVereadores(string $acao, string $metodo): void
    {
        $service = new VereadorService();

        if ($acao === '' && $metodo === 'GET') {
            $vereadores = $service->listarPorRegiao($_GET['regiao'] ?? null);
            Response::json(200, ['vereadores' => array_map(fn ($v) => $v->toArray(), $vereadores)]);
        }

        if ($acao === '' && $metodo === 'POST') {
            $dados = Response::lerCorpo();
            $vereador = $service->cadastrar(
                (string) ($dados['nome'] ?? ''),
                $dados['partido'] ?? null,
                $dados['regiao'] ?? null,
                $dados['bio'] ?? null,
                $dados['email'] ?? null,
                $dados['telefone'] ?? null
            );
            Response::json(201, ['vereador' => $vereador->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'GET') {
            $vereador = $service->buscarPorId((int) $acao);
            if ($vereador === null) {
                Response::json(404, ['erro' => 'Vereador não encontrado.']);
            }
            Response::json(200, ['vereador' => $vereador->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'PUT') {
            $dados = Response::lerCorpo();
            $vereador = $service->editar(
                (int) $acao,
                (string) ($dados['nome'] ?? ''),
                $dados['partido'] ?? null,
                $dados['regiao'] ?? null,
                $dados['bio'] ?? null,
                $dados['email'] ?? null,
                $dados['telefone'] ?? null
            );
            Response::json(200, ['vereador' => $vereador->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'DELETE') {
            $service->excluir((int) $acao);
            Response::json(200, ['mensagem' => 'Vereador excluído.']);
        }

        Response::json(404, ['erro' => 'Rota de vereador não encontrada.']);
    }

    private function despacharAgenda(string $acao, string $metodo): void
    {
        $service = new AgendaService();

        if ($acao === '' && $metodo === 'GET') {
            Response::json(200, ['eventos' => array_map(fn ($e) => $e->toArray(), $service->listar())]);
        }

        if ($acao === '' && $metodo === 'POST') {
            Response::json(201, ['evento' => $service->salvar(Response::lerCorpo())->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'GET') {
            $evento = $service->obter((int) $acao);
            if ($evento === null) {
                Response::json(404, ['erro' => 'Evento não encontrado.']);
            }
            Response::json(200, ['evento' => $evento->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'PUT') {
            $evento = $service->salvar(Response::lerCorpo(), (int) $acao);
            Response::json(200, ['evento' => $evento->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'DELETE') {
            if (!$service->excluir((int) $acao)) {
                Response::json(404, ['erro' => 'Evento não encontrado.']);
            }
            Response::json(200, ['mensagem' => 'Evento excluído.']);
        }

        Response::json(404, ['erro' => 'Rota de agenda não encontrada.']);
    }

    private function despacharNotificacoes(string $acao, string $metodo): void
    {
        $service = new NotificacaoService();

        if ($acao === '' && $metodo === 'GET') {
            $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
            $notificacoes = $service->listar($usuarioId);
            Response::json(200, ['notificacoes' => array_map(fn ($n) => $n->toArray(), $notificacoes)]);
        }

        if ($acao === '' && $metodo === 'POST') {
            $dados = Response::lerCorpo();
            $notificacao = $service->criar(
                (int) ($dados['usuario_id'] ?? 0),
                (string) ($dados['titulo'] ?? ''),
                (string) ($dados['mensagem'] ?? '')
            );
            Response::json(201, ['notificacao' => $notificacao->toArray()]);
        }

        if (ctype_digit($acao) && $metodo === 'PATCH') {
            $usuarioId = (int) ($_GET['usuario_id'] ?? 0);
            if (!$service->marcarLida((int) $acao, $usuarioId)) {
                Response::json(404, ['erro' => 'Notificação não encontrada.']);
            }
            Response::json(200, ['mensagem' => 'Notificação marcada como lida.']);
        }

        Response::json(404, ['erro' => 'Rota de notificação não encontrada.']);
    }
}
