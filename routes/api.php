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
use App\Http\Controllers\BookingController;



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
    // User profile routes (for Admin, Moderator, Customer)
    Route::get('/user/profile', [AuthController::class, 'getProfile']);
    Route::patch('/user/profile', [AuthController::class, 'updateProfile']);
    
    // Worker profile routes
    Route::get('/worker/check-profile', [WorkerController::class, 'checkProfile']);
    Route::get('/worker/profile', [WorkerController::class, 'getProfile']);
    Route::patch('/worker/profile', [WorkerController::class, 'updateProfile']);
    
    // Worker CRUD routes
    Route::get('/getWorkers', [WorkerController::class, 'getAllWorkers']);
    Route::post('/workers', [WorkerController::class, 'createWorker']);
    Route::get('/workers/paginated', [WorkerController::class, 'getPaginated']);
    Route::get('/workers/search', [WorkerController::class, 'searchWorkers']);
    Route::patch('/workers/{id}', [WorkerController::class, 'updateWorker']);
    Route::delete('/workers/{id}', [WorkerController::class, 'deleteWorker']);
    Route::get('/workers/{id}', [WorkerController::class, 'getSingleWorker']);
    Route::get('/workers/{serviceId}', [WorkerController::class, 'getWorkersByService']);

    Route::post('/workers/{id}/verify-nid', [WorkerController::class, 'verifyNID']);
    
    // Check NID availability
    Route::post('/workers/check-nid', [WorkerController::class, 'checkNIDAvailability']);

    // Service routes
    Route::post('/services', [ServiceController::class, 'createServices']);
    Route::get('/getServices', [ServiceController::class, 'getServices']);
    Route::put('/services/{id}', [ServiceController::class, 'updateServices']);
    Route::delete('/services/{id}', [ServiceController::class, 'deleteServices']);
    
    // Bulk workers
    Route::post('/workers/bulk', [WorkerController::class, 'createBulkWorkers']);

    // Get bookings for the authenticated worker
    Route::get('/booking/worker/jobs', [BookingController::class, 'getBookingsByWorker']);
    
    // Update booking status (confirm/cancel) - only for workers
    Route::patch('/booking/{booking}/status', [BookingController::class, 'updateBookingStatus']);
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

Route::prefix('/booking')->group(function () {
    Route::post('/', [BookingController::class, 'createBooking']);
    // Single booking by ID (model binding)
    Route::get('/{booking}', [BookingController::class, 'getBooking']);
    // All bookings for a customer by customer_id
    Route::get('/customer/{customerId}', [BookingController::class, 'getBookingsByCustomer']);
    Route::post('/batch', [BookingController::class, 'batchStore']);
    Route::get('/',[BookingController::class,'getAllBookings']);
});

Route::middleware(['jwt.auth'])->group(function () {
    // Get bookings for the authenticated worker
    Route::get('/booking/worker/jobs', [BookingController::class, 'getBookingsByWorker']);
});

Route::post('/workers/bulk', [WorkerController::class, 'createBulkWorkers']);