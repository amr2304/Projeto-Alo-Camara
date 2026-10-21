<?php
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/controllers/UsuarioController.php';
require_once __DIR__ . '/controllers/ProtocoloController.php';
require_once __DIR__ . '/controllers/ModuloController.php';
$uri=trim((string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
$metodo=$_SERVER['REQUEST_METHOD']; $partes=explode('/',$uri);
if($partes[0]==='usuarios'&&count($partes)===2){(new UsuarioController())->despachar($partes[1],$metodo);}
elseif($partes[0]==='protocolos'&&count($partes)<=2){(new ProtocoloController())->despachar($partes[1]??'',$metodo);}
elseif(in_array($partes[0],['vereadores','agenda','notificacoes'],true)&&count($partes)<=2){(new ModuloController())->despachar($partes[0],$partes[1]??'',$metodo);}
else{Response::json(404,['erro'=>"Rota não encontrada: {$metodo} /{$uri}"]);}
