<?php
require 'core/bootstrap.php';

$routes = [
	'' => 'ChatController@login',
	'/chat/login' => 'ChatController@login',
	'/chat/logout' => 'ChatController@logout',

	'/chat/register' => 'ChatController@register',
	'/chat/entry' => 'ChatController@entry',

	// To add a chat
	'/chat/addChat' => 'ChatController@addChat',

	'/chat/home' => 'ChatController@home',
];

$db = [
	'name'     => 'websocket',
	'username' => 'root',
	'password' => '',
];

$router = new Router($routes);
$router->run($_GET['url'] ?? '');