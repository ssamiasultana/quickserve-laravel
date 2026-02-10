<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// SSL Commerz callbacks (no auth required - accepts both GET and POST)
// These routes need to be accessible without /api prefix for SSL Commerz redirects
Route::match(['get', 'post'], '/api/success', [PaymentController::class, 'sslCommerzSuccess']);
Route::match(['get', 'post'], '/api/fail', [PaymentController::class, 'sslCommerzFail']);
Route::match(['get', 'post'], '/api/cancel', [PaymentController::class, 'sslCommerzCancel']);
Route::post('/api/ipn', [PaymentController::class, 'sslCommerzIpn']);
