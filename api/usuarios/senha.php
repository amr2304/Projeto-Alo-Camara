<?php

/**
 * PUT /usuarios/senha
 *     body: {
 *       "usuario_id": 1,
 *       "senha_atual": "minhaSenha123",
 *       "nova_senha": "novaSenhaForte456"
 *     }
 */

require_once __DIR__ . '/../_helpers.php';
require_once __DIR__ . '/../../services/ContaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    erro('Método não suportado nesta rota', 405);
}

$dados = corpoJson();
$usuarioId = usuarioIdAtual();

if (empty($dados['senha_atual']) || empty($dados['nova_senha'])) {
    erro('Os campos senha_atual e nova_senha são obrigatórios', 400);
}

$service = new ContaService();

try {
    $service->alterarSenha($usuarioId, $dados['senha_atual'], $dados['nova_senha']);
    responder(['mensagem' => 'Senha alterada com sucesso']);
} catch (InvalidArgumentException $e) {
    $status = $e->getCode() ?: 400;
    erro($e->getMessage(), $status);
} catch (Throwable $e) {
    erro('Erro interno ao alterar senha', 500);
}
