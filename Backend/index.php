<?php
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/UsuarioController.php';
require_once __DIR__ . '/controllers/ProtocoloController.php';
require_once __DIR__ . '/controllers/ModuloController.php';
require_once __DIR__ . '/controllers/ApiController.php';

// CORS: o frontend é servido de outra origem (ou de file://) e envia o header Authorization.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$partes = explode('/', $uri);

// Com `php -S localhost:8000 index.php`, devolver false serve o arquivo estático (imagens enviadas).
if ($partes[0] === 'uploads' && is_file(__DIR__ . '/' . $uri)) {
    return false;
}

// Rotas do frontend e proteção (autenticação/perfil) das rotas dos módulos.
(new ApiController())->tratar($partes, $metodo);

if ($partes[0] === 'usuarios' && count($partes) === 2) {
    (new UsuarioController())->despachar($partes[1], $metodo);
} elseif ($partes[0] === 'protocolos' && count($partes) <= 2) {
    (new ProtocoloController())->despachar($partes[1] ?? '', $metodo);
} elseif (in_array($partes[0], ['vereadores', 'agenda'], true) && count($partes) <= 2) {
    (new ModuloController())->despachar($partes[0], $partes[1] ?? '', $metodo);
} else {
    Response::json(404, ['erro' => "Rota não encontrada: {$metodo} /{$uri}"]);
}
