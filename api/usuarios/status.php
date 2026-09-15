<?php

/**
 * PUT /usuarios/status
 *     body: { "usuario_id": 1, "ativo": false }
 *
 * Usado nas Configurações de conta para o cidadão desativar
 * (ou reativar) a própria conta.
 */

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../services/ContaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    erro('Método não suportado nesta rota', 405);
}

$dados = corpoJson();
$usuarioId = usuarioIdAtual();

if (!array_key_exists('ativo', $dados)) {
    erro('O campo ativo (true/false) é obrigatório', 400);
}

$service = new ContaService();

try {
    $resultado = $service->definirStatus($usuarioId, (bool) $dados['ativo']);
    responder($resultado);
} catch (InvalidArgumentException $e) {
    $status = $e->getCode() ?: 400;
    erro($e->getMessage(), $status);
} catch (Throwable $e) {
    erro('Erro interno ao atualizar status da conta', 500);
}
