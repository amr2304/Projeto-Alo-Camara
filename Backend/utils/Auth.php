<?php
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../config/Database.php';

/**
 * Autenticação por token assinado (HS256, formato JWT) sem dependências externas.
 *
 * O segredo vem da variável de ambiente ALO_JWT_SECRET. O valor padrão serve
 * apenas para desenvolvimento local — defina a variável em qualquer outro ambiente.
 */
class Auth
{
    private const DURACAO = 60 * 60 * 12; // 12 horas

    private static ?array $usuarioAtual = null;

    private static function segredo(): string
    {
        return getenv('ALO_JWT_SECRET') ?: 'alo-camara-segredo-de-desenvolvimento';
    }

    private static function b64(string $dados): string
    {
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    private static function b64Decode(string $dados): string
    {
        return (string) base64_decode(strtr($dados, '-_', '+/'));
    }

    public static function emitir(int $usuarioId, string $perfil): string
    {
        $cabecalho = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $corpo = self::b64(json_encode([
            'sub'    => $usuarioId,
            'perfil' => $perfil,
            'exp'    => time() + self::DURACAO,
        ]));
        $assinatura = self::b64(hash_hmac('sha256', "{$cabecalho}.{$corpo}", self::segredo(), true));

        return "{$cabecalho}.{$corpo}.{$assinatura}";
    }

    /** Lê o token do header Authorization: Bearer ... e devolve o payload válido (ou null). */
    private static function payload(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $nome => $valor) {
                if (strtolower($nome) === 'authorization') {
                    $header = $valor;
                }
            }
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }

        $partes = explode('.', $m[1]);
        if (count($partes) !== 3) {
            return null;
        }

        [$cabecalho, $corpo, $assinatura] = $partes;
        $esperada = self::b64(hash_hmac('sha256', "{$cabecalho}.{$corpo}", self::segredo(), true));
        if (!hash_equals($esperada, $assinatura)) {
            return null;
        }

        $dados = json_decode(self::b64Decode($corpo), true);
        if (!is_array($dados) || ($dados['exp'] ?? 0) < time()) {
            return null;
        }

        return $dados;
    }

    /**
     * Exige usuário autenticado e ativo. Devolve ['id' => int, 'perfil' => string].
     * O perfil é sempre lido do banco, nunca confiado ao token.
     */
    public static function exigir(): array
    {
        if (self::$usuarioAtual !== null) {
            return self::$usuarioAtual;
        }

        $payload = self::payload();
        if ($payload === null) {
            Response::json(401, ['erro' => 'Autenticação necessária. Faça login novamente.']);
        }

        $stmt = Database::getConnection()->prepare('SELECT id, perfil, ativo FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => (int) $payload['sub']]);
        $linha = $stmt->fetch();

        if (!$linha || !(int) $linha['ativo']) {
            Response::json(401, ['erro' => 'Sessão inválida ou conta desativada.']);
        }

        return self::$usuarioAtual = ['id' => (int) $linha['id'], 'perfil' => $linha['perfil']];
    }

    public static function exigirAdmin(): array
    {
        $usuario = self::exigir();
        if ($usuario['perfil'] !== 'admin') {
            Response::json(403, ['erro' => 'Acesso restrito à equipe da Câmara.']);
        }

        return $usuario;
    }
}
