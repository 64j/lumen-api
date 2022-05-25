<?php 

$router->get('home', [
	'uses' => 'App\Http\Controllers\HomeController@index',
	'as' => 'home',
	'middleware' => [],
	'where' => [],
	'domain' => NULL,
]);

$router->post('home', [
	'uses' => 'App\Http\Controllers\HomeController@index',
	'as' => NULL,
	'middleware' => ['App\Middleware\RequestBodyMiddleware'],
	'where' => [],
	'domain' => NULL,
]);

$router->post('home2', [
	'uses' => 'App\Http\Controllers\HomeController@index2',
	'as' => 'home2',
	'middleware' => ['App\Middleware\RequestBodyMiddleware'],
	'where' => [],
	'domain' => NULL,
]);

$router->get('home2', [
	'uses' => 'App\Http\Controllers\HomeController@index2',
	'as' => 'home2',
	'middleware' => ['App\Middleware\RequestBodyMiddleware'],
	'where' => [],
	'domain' => NULL,
]);

$router->get('home3', [
	'uses' => 'App\Http\Controllers\HomeController@index3',
	'as' => NULL,
	'middleware' => [],
	'where' => [],
	'domain' => NULL,
]);
