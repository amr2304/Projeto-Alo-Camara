<?php
/**
 * Front controller — roteia /usuarios/* para o UsuarioController único.
 * Rodar localmente com: php -S localhost:8000 index.php
 */
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/UsuarioController.php';

$uri = trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$metodo = $_SERVER['REQUEST_METHOD'];

$partes = explode('/', $uri);

if (count($partes) !== 2 || $partes[0] !== 'usuarios') {
    Response::json(404, ['erro' => "Rota não encontrada: {$metodo} /{$uri}"]);
}

$acao = $partes[1]; // login | login-oauth | cadastrar | perfil | alterar-senha

(new UsuarioController())->despachar($acao, $metodo);
