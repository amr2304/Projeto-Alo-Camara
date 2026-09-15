<?php

header('Content-Type: application/json; charset=utf-8');

function corpoJson(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $dados = json_decode($raw, true);
    return is_array($dados) ? $dados : [];
}

function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(string $mensagem, int $status = 400): void
{
    responder(['erro' => $mensagem], $status);
}

/**
 * TODO(integração): trocar por leitura do usuário autenticado (sessão/JWT)
 * assim que a parte de login estiver pronta. Por enquanto, para testar
 * isoladamente via Postman/Insomnia, o usuario_id vem explícito.
 */
function usuarioIdAtual(): int
{
    $id = $_GET['usuario_id'] ?? corpoJson()['usuario_id'] ?? null;

    if ($id === null || !ctype_digit((string) $id)) {
        erro('Parâmetro usuario_id é obrigatório (temporário até integrar login)', 400);
    }

    return (int) $id;
}
