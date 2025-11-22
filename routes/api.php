<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\ServiceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



// Route::post('/workers', [App\Http\Controllers\WorkerController::class, 'createWorker']);

Route::post('/signup', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);



Route::controller(WorkerController::class)->group(function () {
    Route::get('/getWorkers', 'getAllWorkers');
    Route::post('/workers', 'createWorker');
    Route::put('/workers/{id}','updateWorker');
    Route::delete('/workers/{id}',  'deleteWorker');
});

Route::controller(ServiceController::class)->group(function () {
    Route::post('/services', 'createServices');
    Route::get('/getServices','getServices');
    Route::put('/services/{id}',  'updateServices');
    Route::delete('/services/{id}',  'deleteServices');
});

Route::post('/workers/bulk', [WorkerController::class, 'createBulkWorkers']);