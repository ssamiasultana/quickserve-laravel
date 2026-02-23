<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Worker;
use App\Library\SslCommerz\SslCommerzNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Worker submits 30% commission payment to admin through online payment
     */
    public function submitCommissionPayment(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:booking,id',
                'payment_proof' => 'nullable|string|max:1000', // URL or reference to payment proof
                'transaction_id' => 'nullable|string|max:255', // Payment gateway transaction ID
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get worker - try by user_id first, then by email
            $worker = Worker::where('user_id', $user->id)->first();
            
            if (!$worker) {
                $worker = Worker::where('email', $user->email)->first();
                if ($worker && !$worker->user_id) {
                    $worker->user_id = $user->id;
                    $worker->save();
                }
            }
            
            if (!$worker) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker profile not found. Please complete your worker profile first.'
                ], 404);
            }

            // Get booking
            $booking = Booking::where('id', $request->booking_id)
                ->where('worker_id', $worker->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or not assigned to you'
                ], 404);
            }

            // Check if booking is paid
            if ($booking->status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking must be paid before submitting commission'
                ], 400);
            }

            // Calculate 30% commission
            $commissionAmount = $booking->total_amount * 0.30;

            // Check if commission already submitted
            $existingTransaction = PaymentTransaction::where('booking_id', $booking->id)
                ->where('transaction_type', 'commission_payment')
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commission payment already submitted for this booking'
                ], 400);
            }

            // Create payment transaction
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $worker->id,
                'transaction_type' => 'commission_payment',
                'amount' => $commissionAmount,
                'status' => 'pending',
                'notes' => $request->notes ? ($request->notes . ($request->transaction_id ? "\nTransaction ID: " . $request->transaction_id : '') . ($request->payment_proof ? "\nPayment Proof: " . $request->payment_proof : '')) : ($request->transaction_id ? "Transaction ID: " . $request->transaction_id : ($request->payment_proof ? "Payment Proof: " . $request->payment_proof : null)),
            ]);

            $transaction->load(['booking', 'worker']);

            return response()->json([
                'success' => true,
                'message' => 'Commission payment submitted successfully',
                'data' => $transaction
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Commission payment submission failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit commission payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Initiate SSL Commerz payment for commission
     */
    public function initiateSslCommerzPayment(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:booking,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get worker - try by user_id first, then by email
            $worker = Worker::where('user_id', $user->id)->first();
            
            if (!$worker) {
                $worker = Worker::where('email', $user->email)->first();
                if ($worker && !$worker->user_id) {
                    $worker->user_id = $user->id;
                    $worker->save();
                }
            }
            
            if (!$worker) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker profile not found. Please complete your worker profile first.'
                ], 404);
            }

            // Get booking
            $booking = Booking::where('id', $request->booking_id)
                ->where('worker_id', $worker->id)
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or not assigned to you'
                ], 404);
            }

            // Check if booking is paid
            if ($booking->status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking must be paid before submitting commission'
                ], 400);
            }

            // Calculate 30% commission
            $commissionAmount = $booking->total_amount * 0.30;

            // Check if commission already submitted
            $existingTransaction = PaymentTransaction::where('booking_id', $booking->id)
                ->where('transaction_type', 'commission_payment')
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Commission payment already submitted for this booking'
                ], 400);
            }

            // Generate unique transaction ID
            $tranId = 'TXN' . date('YmdHis') . $booking->id . $worker->id . rand(1000, 9999);

            // Create pending transaction first
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $worker->id,
                'transaction_type' => 'commission_payment',
                'amount' => $commissionAmount,
                'status' => 'pending',
                'notes' => 'SSL Commerz Payment - Transaction ID: ' . $tranId,
            ]);

            // Prepare SSL Commerz payment data using official library format
            $post_data = [];
            $post_data['total_amount'] = number_format($commissionAmount, 2, '.', '');
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $tranId;

            // CUSTOMER INFORMATION
            $post_data['cus_name'] = $user->name ?? $worker->name ?? 'Worker';
            $post_data['cus_email'] = $user->email ?? '';
            $post_data['cus_add1'] = $worker->address ?? 'N/A';
            $post_data['cus_add2'] = "";
            $post_data['cus_city'] = "Dhaka";
            $post_data['cus_state'] = "";
            $post_data['cus_postcode'] = "";
            $post_data['cus_country'] = "Bangladesh";
            $post_data['cus_phone'] = $worker->phone ?? '';
            $post_data['cus_fax'] = "";

            // SHIPMENT INFORMATION
            $post_data['shipping_method'] = "NO";
            $post_data['product_name'] = "Commission Payment - Booking #" . $booking->id;
            $post_data['product_category'] = "Service Commission";
            $post_data['product_profile'] = "general";

            // OPTIONAL PARAMETERS - Store booking and transaction IDs
            $post_data['value_a'] = $booking->id; // Booking ID
            $post_data['value_b'] = $worker->id; // Worker ID
            $post_data['value_c'] = $transaction->id; // Payment Transaction ID
            $post_data['value_d'] = "";

            // Verify SSL Commerz config before proceeding
            $sslConfig = config('sslcommerz');
            if (empty($sslConfig['apiCredentials']['store_id']) || empty($sslConfig['apiCredentials']['store_password'])) {
                Log::error('SSL Commerz credentials missing', [
                    'store_id' => $sslConfig['apiCredentials']['store_id'] ?? 'NOT SET',
                    'store_password' => isset($sslConfig['apiCredentials']['store_password']) ? 'SET' : 'NOT SET',
                    'env_store_id' => env('SSLCZ_STORE_ID'),
                    'env_store_password' => env('SSLCZ_STORE_PASSWORD') ? 'SET' : 'NOT SET',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'SSL Commerz configuration error. Please check your .env file and ensure SSLCZ_STORE_ID and SSLCZ_STORE_PASSWORD are set correctly.',
                ], 500);
            }

            // Use official SSL Commerz library
            $sslc = new SslCommerzNotification();
            
            // Override callback URLs to use full backend URL (must be accessible from browser)
            // IMPORTANT: Use Laravel's built-in server (port 8000) or configure Apache properly
            $appUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));
            $post_data['success_url'] = rtrim($appUrl, '/') . '/api/success';
            $post_data['fail_url'] = rtrim($appUrl, '/') . '/api/fail';
            $post_data['cancel_url'] = rtrim($appUrl, '/') . '/api/cancel';
            $post_data['ipn_url'] = rtrim($appUrl, '/') . '/api/ipn';
            
            // For API response (not redirect), use 'checkout' type with 'json' pattern
            $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');

            // The library returns a JSON string, so we need to decode it
            $payment_data = json_decode($payment_options, true);

            if (is_array($payment_data) && isset($payment_data['status']) && $payment_data['status'] === 'success' && isset($payment_data['data'])) {
                // Update transaction with session info
                $transaction->update([
                    'notes' => 'SSL Commerz Payment - Transaction ID: ' . $tranId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment session created successfully',
                    'payment_url' => $payment_data['data'],
                    'transaction_id' => $transaction->id,
                    'tran_id' => $tranId,
                ]);
            } else {
                // Delete the transaction if payment initiation failed
                $transaction->delete();

                $errorMessage = 'Failed to initiate payment';
                if (is_array($payment_data) && isset($payment_data['message'])) {
                    $errorMessage = $payment_data['message'];
                } elseif (is_string($payment_options)) {
                    $errorMessage = $payment_options;
                }

                Log::error('SSL Commerz payment initiation failed', [
                    'payment_response' => $payment_options,
                    'decoded_response' => $payment_data,
                    'booking_id' => $booking->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

        } catch (\Throwable $e) {
            Log::error('SSL Commerz payment initiation failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Initiate SSL Commerz payment for customer booking
     */
    public function initiateCustomerSslCommerzPayment(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:booking,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get booking
            $booking = Booking::where('id', $request->booking_id)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // Check if booking already has online payment transaction
            $existingTransaction = PaymentTransaction::where('booking_id', $booking->id)
                ->where('transaction_type', 'online_payment')
                ->whereIn('status', ['pending', 'completed'])
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already initiated for this booking'
                ], 400);
            }

            // Generate unique transaction ID
            $tranId = 'CUST' . date('YmdHis') . $booking->id . rand(1000, 9999);

            // Create pending transaction
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $booking->worker_id,
                'transaction_type' => 'online_payment',
                'amount' => $booking->total_amount,
                'status' => 'pending',
                'notes' => 'SSL Commerz Payment - Customer Booking - Transaction ID: ' . $tranId,
            ]);

            // Prepare SSL Commerz payment data
            $post_data = [];
            $post_data['total_amount'] = number_format($booking->total_amount, 2, '.', '');
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $tranId;

            // CUSTOMER INFORMATION
            $post_data['cus_name'] = $booking->customer_name ?? $user->name ?? 'Customer';
            $post_data['cus_email'] = $booking->customer_email ?? $user->email ?? '';
            $post_data['cus_add1'] = $booking->service_address ?? 'N/A';
            $post_data['cus_add2'] = "";
            $post_data['cus_city'] = "Dhaka";
            $post_data['cus_state'] = "";
            $post_data['cus_postcode'] = "";
            $post_data['cus_country'] = "Bangladesh";
            $post_data['cus_phone'] = $booking->customer_phone ?? '';
            $post_data['cus_fax'] = "";

            // SHIPMENT INFORMATION
            $post_data['shipping_method'] = "NO";
            $post_data['product_name'] = "Service Booking - Booking #" . $booking->id;
            $post_data['product_category'] = "Service Booking";
            $post_data['product_profile'] = "general";

            // OPTIONAL PARAMETERS
            $post_data['value_a'] = $booking->id; // Booking ID
            $post_data['value_b'] = $user->id; // Customer User ID
            $post_data['value_c'] = $transaction->id; // Payment Transaction ID
            $post_data['value_d'] = "customer_payment"; // Payment type identifier

            // Verify SSL Commerz config
            $sslConfig = config('sslcommerz');
            if (empty($sslConfig['apiCredentials']['store_id']) || empty($sslConfig['apiCredentials']['store_password'])) {
                Log::error('SSL Commerz credentials missing', [
                    'store_id' => $sslConfig['apiCredentials']['store_id'] ?? 'NOT SET',
                    'store_password' => isset($sslConfig['apiCredentials']['store_password']) ? 'SET' : 'NOT SET',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'SSL Commerz configuration error. Please check your .env file.',
                ], 500);
            }

            // Use official SSL Commerz library
            $sslc = new SslCommerzNotification();
            
            $appUrl = config('app.url', env('APP_URL', 'http://localhost:8000'));
            $post_data['success_url'] = rtrim($appUrl, '/') . '/api/success';
            $post_data['fail_url'] = rtrim($appUrl, '/') . '/api/fail';
            $post_data['cancel_url'] = rtrim($appUrl, '/') . '/api/cancel';
            $post_data['ipn_url'] = rtrim($appUrl, '/') . '/api/ipn';
            
            $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');
            $payment_data = json_decode($payment_options, true);

            if (is_array($payment_data) && isset($payment_data['status']) && $payment_data['status'] === 'success' && isset($payment_data['data'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment session created successfully',
                    'payment_url' => $payment_data['data'],
                    'transaction_id' => $transaction->id,
                    'tran_id' => $tranId,
                ]);
            } else {
                $transaction->delete();

                $errorMessage = 'Failed to initiate payment';
                if (is_array($payment_data) && isset($payment_data['message'])) {
                    $errorMessage = $payment_data['message'];
                } elseif (is_string($payment_options)) {
                    $errorMessage = $payment_options;
                }

                Log::error('SSL Commerz customer payment initiation failed', [
                    'payment_response' => $payment_options,
                    'decoded_response' => $payment_data,
                    'booking_id' => $booking->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

        } catch (\Throwable $e) {
            Log::error('SSL Commerz customer payment initiation failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * SSL Commerz success callback
     */
    public function sslCommerzSuccess(Request $request)
    {
        try {
            $tranId = $request->input('tran_id');
            $amount = $request->input('amount');
            $currency = $request->input('currency', 'BDT');

            Log::info('SSL Commerz success callback received', [
                'tran_id' => $tranId,
                'amount' => $amount,
                'all_params' => $request->all(),
            ]);

            // Find transaction by tran_id in notes
            $transaction = PaymentTransaction::where('notes', 'like', '%' . $tranId . '%')
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                Log::error('SSL Commerz success: Transaction not found', ['tran_id' => $tranId]);
                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                // Try to determine redirect based on value_d parameter
                $isCustomerPayment = $request->input('value_d') === 'customer_payment';
                $redirectUrl = $isCustomerPayment 
                    ? $frontendUrl . '/customer/my-booking?status=error&message=' . urlencode('Transaction not found')
                    : $frontendUrl . '/worker/submit-payment?status=error&message=' . urlencode('Transaction not found');
                Log::info('Redirecting to frontend', ['url' => $redirectUrl]);
                return $this->htmlRedirect($redirectUrl);
            }

            // Use official SSL Commerz library for validation
            $sslc = new SslCommerzNotification();
            $validation = $sslc->orderValidate($request->all(), $tranId, $amount, $currency);

            if ($validation) {
                DB::beginTransaction();

                // Check if this is a customer payment or worker commission payment
                $isCustomerPayment = $request->input('value_d') === 'customer_payment' 
                    || $transaction->transaction_type === 'online_payment';

                // Update transaction status
                $transaction->update([
                    'status' => 'completed',
                    'notes' => $transaction->notes . "\nSSL Commerz Val ID: " . ($request->input('val_id') ?? 'N/A') . "\nVerified: " . now(),
                ]);

                // Update booking status to 'paid' if it's a customer payment
                if ($isCustomerPayment && $transaction->booking_id) {
                    // Use direct database update to avoid any datetime casting issues with scheduled_at
                    // This ensures scheduled_at is not re-processed during the update
                    // Only update status and payment_method, explicitly exclude scheduled_at
                    DB::table('booking')
                        ->where('id', $transaction->booking_id)
                        ->where('status', '!=', 'paid')
                        ->update([
                            'status' => 'paid',
                            'payment_method' => 'online',
                            'updated_at' => now(),
                        ]);
                    
                    // Log for debugging
                    Log::info('Booking updated after payment', [
                        'booking_id' => $transaction->booking_id,
                        'scheduled_at_before' => DB::table('booking')->where('id', $transaction->booking_id)->value('scheduled_at'),
                    ]);
                }

                DB::commit();

                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                
                // Redirect based on payment type
                if ($isCustomerPayment) {
                    $redirectUrl = $frontendUrl . '/customer/my-booking?status=success&transaction_id=' . $transaction->id;
                } else {
                    $redirectUrl = $frontendUrl . '/worker/submit-payment?status=success&transaction_id=' . $transaction->id;
                }
                
                Log::info('Payment validated successfully, redirecting to frontend', [
                    'transaction_id' => $transaction->id,
                    'payment_type' => $isCustomerPayment ? 'customer' : 'worker',
                    'redirect_url' => $redirectUrl,
                ]);
                return $this->htmlRedirect($redirectUrl);
            } else {
                // Validation failed
                $isCustomerPayment = $request->input('value_d') === 'customer_payment' 
                    || ($transaction && $transaction->transaction_type === 'online_payment');
                    
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nValidation Failed at: " . now(),
                ]);

                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                $redirectUrl = $isCustomerPayment
                    ? $frontendUrl . '/customer/my-booking?status=error&message=' . urlencode('Payment verification failed')
                    : $frontendUrl . '/worker/submit-payment?status=error&message=' . urlencode('Payment verification failed');
                Log::warning('Payment validation failed, redirecting to frontend', ['redirect_url' => $redirectUrl]);
                return $this->htmlRedirect($redirectUrl);
            }

        } catch (\Throwable $e) {
            Log::error('SSL Commerz success callback error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            $isCustomerPayment = $request->input('value_d') === 'customer_payment';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $redirectUrl = $isCustomerPayment
                ? $frontendUrl . '/customer/my-booking?status=error&message=' . urlencode('Payment processing error')
                : $frontendUrl . '/worker/submit-payment?status=error&message=' . urlencode('Payment processing error');
            Log::error('Exception occurred, redirecting to frontend', ['redirect_url' => $redirectUrl]);
            return $this->htmlRedirect($redirectUrl);
        }
    }

    /**
     * SSL Commerz fail callback
     */
    public function sslCommerzFail(Request $request)
    {
        try {
            $tranId = $request->input('tran_id');
            
            // Find transaction
            $transaction = PaymentTransaction::where('notes', 'like', '%' . $tranId . '%')
                ->where('status', 'pending')
                ->first();

            $isCustomerPayment = $request->input('value_d') === 'customer_payment' 
                || ($transaction && $transaction->transaction_type === 'online_payment');

            if ($transaction) {
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nPayment Failed: " . ($request->input('error') ?? 'Unknown error') . "\nFailed at: " . now(),
                ]);
            }

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $redirectUrl = $isCustomerPayment
                ? $frontendUrl . '/customer/my-booking?status=failed&message=' . urlencode('Payment failed')
                : $frontendUrl . '/worker/submit-payment?status=failed&message=' . urlencode('Payment failed');
            return $this->htmlRedirect($redirectUrl);

        } catch (\Throwable $e) {
            Log::error('SSL Commerz fail callback error: ' . $e->getMessage());
            $isCustomerPayment = $request->input('value_d') === 'customer_payment';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $redirectUrl = $isCustomerPayment
                ? $frontendUrl . '/customer/my-booking?status=error'
                : $frontendUrl . '/worker/submit-payment?status=error';
            return $this->htmlRedirect($redirectUrl);
        }
    }

    /**
     * SSL Commerz cancel callback
     */
    public function sslCommerzCancel(Request $request)
    {
        try {
            $tranId = $request->input('tran_id');
            
            // Find transaction
            $transaction = PaymentTransaction::where('notes', 'like', '%' . $tranId . '%')
                ->where('status', 'pending')
                ->first();

            $isCustomerPayment = $request->input('value_d') === 'customer_payment' 
                || ($transaction && $transaction->transaction_type === 'online_payment');

            if ($transaction) {
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nPayment Cancelled by user\nCancelled at: " . now(),
                ]);
            }

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $redirectUrl = $isCustomerPayment
                ? $frontendUrl . '/customer/my-booking?status=cancelled&message=' . urlencode('Payment cancelled')
                : $frontendUrl . '/worker/submit-payment?status=cancelled&message=' . urlencode('Payment cancelled');
            return $this->htmlRedirect($redirectUrl);

        } catch (\Throwable $e) {
            Log::error('SSL Commerz cancel callback error: ' . $e->getMessage());
            $isCustomerPayment = $request->input('value_d') === 'customer_payment';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            $redirectUrl = $isCustomerPayment
                ? $frontendUrl . '/customer/my-booking?status=error'
                : $frontendUrl . '/worker/submit-payment?status=error';
            return $this->htmlRedirect($redirectUrl);
        }
    }

    /**
     * SSL Commerz IPN (Instant Payment Notification)
     */
    public function sslCommerzIpn(Request $request)
    {
        try {
            $tranId = $request->input('tran_id');

            if (!$tranId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Data'
                ], 400);
            }

            // Find transaction
            $transaction = PaymentTransaction::where('notes', 'like', '%' . $tranId . '%')
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                Log::warning('SSL Commerz IPN: Transaction not found', ['tran_id' => $tranId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Use official SSL Commerz library for validation
            $sslc = new SslCommerzNotification();
            $validation = $sslc->orderValidate($request->all(), $tranId, $transaction->amount, 'BDT');

            if ($validation == TRUE) {
                DB::beginTransaction();

                $transaction->update([
                    'status' => 'completed',
                    'notes' => $transaction->notes . "\nIPN Verified - Val ID: " . ($request->input('val_id') ?? 'N/A') . "\nVerified at: " . now(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction is successfully Completed'
                ]);
            } else {
                Log::error('SSL Commerz IPN validation failed', [
                    'tran_id' => $tranId,
                    'error' => $sslc->error ?? 'Unknown error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Transaction'
                ], 400);
            }

        } catch (\Throwable $e) {
            Log::error('SSL Commerz IPN error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'IPN processing error'
            ], 500);
        }
    }

    /**
     * Get worker's payment transactions
     */
    public function getWorkerTransactions(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get worker - try by user_id first, then by email
            $worker = Worker::where('user_id', $user->id)->first();
            
            if (!$worker) {
                $worker = Worker::where('email', $user->email)->first();
                if ($worker && !$worker->user_id) {
                    $worker->user_id = $user->id;
                    $worker->save();
                }
            }
            
            if (!$worker) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker profile not found. Please complete your worker profile first.'
                ], 404);
            }

            $transactions = PaymentTransaction::where('worker_id', $worker->id)
                ->with(['booking.serviceSubcategory', 'booking.customer', 'processedBy'])
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Throwable $e) {
            Log::error('Get worker transactions failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get worker's pending commission payments (for admin)
     */
    public function getPendingCommissionPayments(): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $transactions = PaymentTransaction::where('transaction_type', 'commission_payment')
                ->where('status', 'pending')
                ->with(['booking.serviceSubcategory', 'booking.customer', 'worker'])
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Throwable $e) {
            Log::error('Get pending commission payments failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending commission payments',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Admin approves/rejects commission payment
     */
    public function processCommissionPayment(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($transaction->transaction_type !== 'commission_payment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid transaction type'
                ], 400);
            }

            if ($transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction already processed'
                ], 400);
            }

            DB::beginTransaction();

            if ($request->action === 'approve') {
                $transaction->status = 'completed';
                $transaction->processed_by = $user->id;
                $transaction->processed_at = now();
                if ($request->notes) {
                    $transaction->notes = ($transaction->notes ? $transaction->notes . "\n" : '') . 'Admin: ' . $request->notes;
                }
                $transaction->save();
            } else {
                $transaction->status = 'rejected';
                $transaction->processed_by = $user->id;
                $transaction->processed_at = now();
                if ($request->notes) {
                    $transaction->notes = ($transaction->notes ? $transaction->notes . "\n" : '') . 'Admin: ' . $request->notes;
                }
                $transaction->save();
            }

            DB::commit();

            $transaction->load(['booking', 'worker', 'processedBy']);

            return response()->json([
                'success' => true,
                'message' => "Commission payment {$request->action}d successfully",
                'data' => $transaction
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Process commission payment failed: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process commission payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get bookings with online payment that need to be sent to workers
     */
    public function getPendingOnlinePayments(): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Get paid bookings with online payment method that don't have completed payment transaction
            $bookings = Booking::where('status', 'paid')
                ->where('payment_method', 'online')
                ->whereDoesntHave('paymentTransactions', function ($query) {
                    $query->where('transaction_type', 'online_payment')
                        ->where('status', 'completed');
                })
                ->with(['worker', 'serviceSubcategory', 'customer'])
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);

        } catch (\Throwable $e) {
            Log::error('Get pending online payments failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending online payments',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Admin sends online payment to worker
     */
    public function sendOnlinePayment(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:booking,id',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $booking = Booking::find($request->booking_id);

            if (!$booking || $booking->status !== 'paid' || $booking->payment_method !== 'online') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid booking or payment method'
                ], 400);
            }

            // Check if already sent
            $existingTransaction = PaymentTransaction::where('booking_id', $booking->id)
                ->where('transaction_type', 'online_payment')
                ->where('status', 'completed')
                ->first();

            if ($existingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Online payment already sent to worker'
                ], 400);
            }

            DB::beginTransaction();

            // Create payment transaction and mark as completed immediately
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $booking->worker_id,
                'transaction_type' => 'online_payment',
                'amount' => $booking->total_amount,
                'status' => 'completed',
                'notes' => $request->notes,
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);

            DB::commit();

            $transaction->load(['booking.serviceSubcategory', 'booking.customer', 'worker', 'processedBy']);

            return response()->json([
                'success' => true,
                'message' => 'Online payment sent to worker successfully',
                'data' => $transaction
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Send online payment failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send online payment',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get all payment transactions (admin view)
     */
    public function getAllTransactions(): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user || $user->role !== 'Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $transactions = PaymentTransaction::with([
                'booking.serviceSubcategory',
                'booking.customer',
                'worker',
                'processedBy'
            ])
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Throwable $e) {
            Log::error('Get all transactions failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Return HTML redirect page for SSL Commerz callbacks
     * This is more reliable than HTTP redirects for payment gateway callbacks
     */
    private function htmlRedirect(string $url): \Illuminate\Http\Response
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">
    <title>Redirecting...</title>
    <script type="text/javascript">
        window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
    </script>
</head>
<body>
    <p>Redirecting to payment page...</p>
    <p>If you are not redirected automatically, <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">click here</a>.</p>
</body>
</html>';

        return response($html, 200)->header('Content-Type', 'text/html');
    }
}
