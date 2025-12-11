<?php

namespace Config;

$routes = Services::routes();

if (file_exists(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);

// API Routes with authentication
$routes->group('api', ['filter' => 'apiAuth'], function($routes) {
    // Print endpoints
    $routes->post('print/upload', 'PrintController::upload');
    $routes->get('print/pending', 'PrintController::pending');
    $routes->put('print/update/(:alphanum)', 'PrintController::update/$1');
    $routes->get('print/status/(:alphanum)', 'PrintController::status/$1');
    $routes->get('print/history', 'PrintController::history');
    $routes->delete('print/delete/(:alphanum)', 'PrintController::delete/$1');
    
    // Printer endpoints
    $routes->get('printers/list', 'PrinterController::list');
});

// Web routes
$routes->get('print/upload', 'PrintController::uploadForm');
$routes->get('print/history', 'HistoryController::index');
$routes->get('print/status/(:alphanum)', 'StatusController::check/$1');

// Cleanup route (can be called via cron)
$routes->get('cleanup', 'CleanupController::index');

if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
