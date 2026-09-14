<?php
/**
 * Helper simples para padronizar respostas JSON dos endpoints.
 * Sempre define o header correto, o status HTTP e encerra a execução.
 */
class Response
{
    public static function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function metodoNaoPermitido(string $esperado): void
    {
        self::json(405, [
            'erro' => "Método não permitido. Use {$esperado}.",
        ]);
    }

    public static function corpoInvalido(): void
    {
        self::json(400, [
            'erro' => 'Corpo da requisição inválido ou ausente. Esperado JSON.',
        ]);
    }

    /** Lê e decodifica o corpo JSON da requisição. */
    public static function lerCorpo(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            self::corpoInvalido();
        }

        return $data;
    }
}
