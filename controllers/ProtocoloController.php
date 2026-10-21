<?php

require_once __DIR__ . '/../Services/ProtocoloService.php';
require_once __DIR__ . '/../utils/Response.php';

class ProtocoloController
{
    private ProtocoloService $service;

    public function __construct(?ProtocoloService $service = null)
    {
        $this->service = $service ?? new ProtocoloService();
    }

    public function despachar(string $acao, string $metodo): void
    {
        try {
            if ($acao === '') {
                $this->despacharColecao($metodo);
            }

            if (!ctype_digit($acao)) {
                Response::json(404, ['erro' => 'Rota não encontrada.']);
            }

            $this->despacharItem((int) $acao, $metodo);
        } catch (InvalidArgumentException $erro) {
            Response::json(400, ['erro' => $erro->getMessage()]);
        } catch (OutOfBoundsException $erro) {
            Response::json(404, ['erro' => $erro->getMessage()]);
        } catch (Throwable $erro) {
            error_log('[protocolos] ' . $erro->getMessage());
            Response::json(500, ['erro' => 'Erro interno ao processar protocolo.']);
        }
    }

    private function despacharColecao(string $metodo): void
    {
        if ($metodo === 'GET') {
            $filtros = $_GET;
            $consultaDoUsuario = isset($filtros['usuario_id']);
            $protocolos = $this->service->listar($filtros);

            Response::json(200, [
                'protocolos' => array_map(
                    fn (Protocolo $protocolo): array => $protocolo->toArray(
                        $protocolo->isSigiloso() && !$consultaDoUsuario
                    ),
                    $protocolos
                ),
            ]);
        }

        if ($metodo === 'POST') {
            $protocolo = $this->service->criar(Response::lerCorpo());
            Response::json(201, ['protocolo' => $protocolo->toArray()]);
        }

        Response::metodoNaoPermitido('GET ou POST');
    }

    private function despacharItem(int $id, string $metodo): void
    {
        if ($metodo === 'GET') {
            $protocolo = $this->service->obter($id);
            if ($protocolo === null) {
                Response::json(404, ['erro' => 'Protocolo não encontrado.']);
            }

            Response::json(200, [
                'protocolo' => $protocolo->toArray($protocolo->isSigiloso()),
            ]);
        }

        if ($metodo === 'PATCH') {
            $dados = Response::lerCorpo();
            $protocolo = $this->service->mudarStatus(
                $id,
                (string) ($dados['status'] ?? ''),
                $dados['resposta'] ?? null,
                isset($dados['respondido_por']) ? (int) $dados['respondido_por'] : null
            );

            Response::json(200, ['protocolo' => $protocolo->toArray()]);
        }

        if ($metodo === 'DELETE') {
            $this->service->excluir($id);
            Response::json(200, ['mensagem' => 'Protocolo excluído.']);
        }

        Response::metodoNaoPermitido('GET, PATCH ou DELETE');
    }
}
