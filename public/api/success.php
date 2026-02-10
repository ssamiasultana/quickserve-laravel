<?php
/**
 * Temporary redirect file for Apache compatibility
 * Routes requests from /api/success.php to Laravel's /api/success route
 */

// Bootstrap Laravel
require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create request to Laravel route (without .php extension)
$request = Illuminate\Http\Request::create('/api/success', $_SERVER['REQUEST_METHOD'], $_POST);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
