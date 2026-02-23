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
use App\Http\Controllers\ModeratorController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SslCommerzPaymentController;
use App\Http\Controllers\ReviewController;



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
   
    Route::post('/workers', [WorkerController::class, 'createWorker']);
    Route::get('/workers/paginated', [WorkerController::class, 'getPaginated']);
    
    Route::patch('/workers/{id}', [WorkerController::class, 'updateWorker']);
    Route::delete('/workers/{id}', [WorkerController::class, 'deleteWorker']);
    
    

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
    
    // Update booking status (confirm/cancel) - for workers and moderators
    Route::patch('/booking/{booking}/status', [BookingController::class, 'updateBookingStatus']);

    // Review routes (requires authentication)
    Route::post('/reviews', [ReviewController::class, 'createReview']);
   
});



Route::get('/getWorkers', [WorkerController::class, 'getAllWorkers']);

// Place the search route BEFORE parameterised worker routes to avoid conflicts
Route::get('/workers/search', [WorkerController::class, 'searchWorkers']);

// Get workers by service – use a distinct URI segment to prevent route collisions
Route::get('/workers/service/{serviceId}', [WorkerController::class, 'getWorkersByService']);

// Get single worker by ID
Route::get('/workers/{id}', [WorkerController::class, 'getSingleWorker'])->whereNumber('id');

// Review routes (public get)
Route::get('/workers/{workerId}/reviews', [ReviewController::class, 'getWorkerReviews']);
Route::get('/bookings/{bookingId}/review', [ReviewController::class, 'getBookingReview']);

// Customer routes
Route::get('/customers', [CustomerController::class, 'getAllCustomers']);
Route::get('/customers/paginated', [CustomerController::class, 'getPaginated']);

Route::middleware(['jwt.auth'])->group(function () {
    Route::patch('/customers/{id}', [CustomerController::class, 'updateCustomer']);
    Route::delete('/customers/{id}', [CustomerController::class, 'deleteCustomer']);
    
    // Moderator routes
    Route::patch('/moderators/{id}', [ModeratorController::class, 'updateModerator']);
    Route::delete('/moderators/{id}', [ModeratorController::class, 'deleteModerator']);
});

// Moderator routes (public get)
Route::get('/moderators', [ModeratorController::class, 'getAllModerators']);
Route::get('/moderators/paginated', [ModeratorController::class, 'getPaginated']);
Route::get('/moderators/{id}', [ModeratorController::class, 'getSingleModerator'])->whereNumber('id');

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

// Removed duplicate route - already defined above in jwt.auth middleware group

// Payment routes for workers (requires authentication)
Route::middleware(['jwt.auth'])->group(function () {
    Route::post('/payments/submit-commission', [PaymentController::class, 'submitCommissionPayment']);
    Route::post('/payments/sslcommerz/initiate', [PaymentController::class, 'initiateSslCommerzPayment']);
    Route::get('/payments/worker/transactions', [PaymentController::class, 'getWorkerTransactions']);
});

// Payment routes for customers (requires authentication)
Route::middleware(['jwt.auth'])->group(function () {
    Route::post('/payments/sslcommerz/customer/initiate', [PaymentController::class, 'initiateCustomerSslCommerzPayment']);
});

// Payment routes for admin (requires authentication)
Route::middleware(['jwt.auth'])->group(function () {
    Route::get('/payments/pending-commission-payments', [PaymentController::class, 'getPendingCommissionPayments']);
    Route::post('/payments/commission-payment/{transaction}/process', [PaymentController::class, 'processCommissionPayment']);
    Route::get('/payments/pending-online-payments', [PaymentController::class, 'getPendingOnlinePayments']);
    Route::post('/payments/send-online-payment', [PaymentController::class, 'sendOnlinePayment']);
    Route::get('/payments/all-transactions', [PaymentController::class, 'getAllTransactions']);
});

// SSL Commerz callbacks are handled in web.php for better compatibility with external POST requests
// These routes need to be accessible without API middleware and CSRF protection

// Example routes (for testing - can be removed in production)
Route::get('/example1', [SslCommerzPaymentController::class, 'exampleEasyCheckout']);
Route::get('/example2', [SslCommerzPaymentController::class, 'exampleHostedCheckout']);
Route::post('/pay', [SslCommerzPaymentController::class, 'index']);
Route::post('/pay-via-ajax', [SslCommerzPaymentController::class, 'payViaAjax']);

// Note: SSL Commerz callback routes are also defined in web.php for better compatibility
// Routes in api.php are automatically prefixed with /api, so /success becomes /api/success
