<?php

/**
 * GET  /usuarios/preferencias?usuario_id=1
 * PUT  /usuarios/preferencias?usuario_id=1
 *      body: { "notificar_email": false, "notificar_push": true }
 */

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../services/PreferenciaNotificacaoService.php';

$service = new PreferenciaNotificacaoService();
$usuarioId = usuarioIdAtual();
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        responder($service->obter($usuarioId));
    }

    if ($metodo === 'PUT' || $metodo === 'PATCH') {
        $dados = corpoJson();
        $atualizado = $service->atualizar($usuarioId, $dados);
        responder($atualizado);
    }

    erro('Método não suportado nesta rota', 405);
} catch (InvalidArgumentException $e) {
    erro($e->getMessage(), 422);
} catch (Throwable $e) {
    erro('Erro interno ao processar preferências', 500);
}
