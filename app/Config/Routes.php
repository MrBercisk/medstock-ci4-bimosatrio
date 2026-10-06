<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function ($routes) {
    // auth
    $routes->post('login', 'AuthController::login');
    $routes->post('logout', 'AuthController::logout');
    $routes->get('me', 'AuthController::me', ['filter' => 'auth']);

    // stock
    $routes->get('stocks', 'StockController::index', ['filter' => 'auth']);


    // receipt
    $routes->get('receipts', 'ReceiptController::index', ['filter' => 'auth']);
    $routes->get('receipts/(:num)', 'ReceiptController::show/$1', ['filter' => 'auth']);
    $routes->post('receipts', 'ReceiptController::store', ['filter' => 'auth']);
    $routes->put('receipts/(:num)', 'ReceiptController::update/$1', ['filter' => 'auth']);
});
