<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout');
    $routes->get('me', 'AuthController::me', ['filter' => 'auth']);
});
