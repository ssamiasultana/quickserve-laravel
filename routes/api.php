<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\PasswordResetController; 
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ServiceCategoryController;


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


Route::middleware(['jwt.auth'])->group(function () {

    Route::get('/users', [AuthController::class, 'getAllUsers']);
    // Worker profile routes
    Route::get('/worker/check-profile', [WorkerController::class, 'checkProfile']);
    
    // Worker CRUD routes
    Route::get('/getWorkers', [WorkerController::class, 'getAllWorkers']);
    Route::post('/workers', [WorkerController::class, 'createWorker']);
    Route::get('/workers/paginated', [WorkerController::class, 'getPaginated']);
    Route::get('/workers/search', [WorkerController::class, 'searchWorkers']);
    Route::put('/workers/{id}', [WorkerController::class, 'updateWorker']);
    Route::delete('/workers/{id}', [WorkerController::class, 'deleteWorker']);
    Route::get('/workers/{id}', [WorkerController::class, 'getSingleWorker']);
    Route::get('/workers/{serviceId}', [WorkerController::class, 'getWorkersByService']);

    // Service routes
    Route::post('/services', [ServiceController::class, 'createServices']);
    Route::get('/getServices', [ServiceController::class, 'getServices']);
    Route::put('/services/{id}', [ServiceController::class, 'updateServices']);
    Route::delete('/services/{id}', [ServiceController::class, 'deleteServices']);
    
    // Bulk workers
    Route::post('/workers/bulk', [WorkerController::class, 'createBulkWorkers']);
});

// Customer routes
Route::get('/customers', [CustomerController::class, 'getAllCustomers']);
Route::get('/customers/paginated', [CustomerController::class, 'getPaginated']);

// Password reset routes
Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword']);
Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);
Route::get('/password/reset/{token}', [PasswordResetController::class, 'verifyToken']);


Route::prefix('service-subcategories')->group(function () {
    Route::get('/', [ServiceCategoryController::class, 'getServicecategory']);
    Route::post('/', [ServiceCategoryController::class, 'createServiceCategory']);
    Route::get('/{id}',[ServiceCategoryController::class,'getServicecategoryById']);
   
});

Route::post('/workers/bulk', [WorkerController::class, 'createBulkWorkers']);