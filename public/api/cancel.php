<?php
/**
 * Temporary redirect file for Apache compatibility
 */
require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/cancel', $_SERVER['REQUEST_METHOD'], $_POST);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
