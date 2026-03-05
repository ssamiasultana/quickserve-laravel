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
     * Worker submits 20% commission payment to admin (for cash payments)
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

            // Only allow commission submission for cash payments
            if ($booking->payment_method !== 'cash') {
                return response()->json([
                    'success' => false,
                    'message' => 'Commission can only be submitted for cash payments. Online payments are handled by admin.'
                ], 400);
            }

            // Calculate 20% commission
            $commissionAmount = $booking->total_amount * 0.20;

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

            // Only allow commission submission for cash payments
            if ($booking->payment_method !== 'cash') {
                return response()->json([
                    'success' => false,
                    'message' => 'Commission can only be submitted for cash payments. Online payments are handled by admin.'
                ], 400);
            }

            // Calculate 20% commission
            $commissionAmount = $booking->total_amount * 0.20;

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
     * Supports both single booking_id and multiple booking_ids
     */
    public function initiateCustomerSslCommerzPayment(Request $request): JsonResponse
    {
        $transactions = []; // Initialize to avoid undefined variable errors
        
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Accept either booking_id (single) or booking_ids (array)
            $validator = Validator::make($request->all(), [
                'booking_id' => 'required_without:booking_ids|exists:booking,id',
                'booking_ids' => 'required_without:booking_id|array',
                'booking_ids.*' => 'exists:booking,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get booking(s) - handle both single and multiple
            $bookingIds = $request->has('booking_ids') 
                ? $request->booking_ids 
                : [$request->booking_id];
            
            $bookings = Booking::whereIn('id', $bookingIds)->get();

            if ($bookings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking(s) not found'
                ], 404);
            }

            // Verify all bookings belong to the same customer
            $customerId = $bookings->first()->customer_id;
            $allSameCustomer = $bookings->every(function ($booking) use ($customerId) {
                return $booking->customer_id == $customerId;
            });

            if (!$allSameCustomer) {
                return response()->json([
                    'success' => false,
                    'message' => 'All bookings must belong to the same customer'
                ], 400);
            }

            // Check if any booking already has customer payment transaction
            $existingTransactions = PaymentTransaction::whereIn('booking_id', $bookingIds)
                ->where('transaction_type', 'customer_payment')
                ->whereIn('status', ['pending', 'completed'])
                ->get();

            if ($existingTransactions->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already initiated for one or more bookings'
                ], 400);
            }

            // Calculate total amount from all bookings
            $totalAmount = $bookings->sum('total_amount');

            // Use first booking for customer info (they should all be the same)
            $primaryBooking = $bookings->first();

            // Generate unique transaction ID
            $tranId = 'CUST' . date('YmdHis') . implode('_', $bookingIds) . rand(1000, 9999);

            // Create pending transactions for all bookings - customer payment goes to admin
            $transactions = [];
            foreach ($bookings as $booking) {
                $transactions[] = PaymentTransaction::create([
                    'booking_id' => $booking->id,
                    'worker_id' => $booking->worker_id,
                    'transaction_type' => 'customer_payment',
                    'amount' => $booking->total_amount,
                    'status' => 'pending',
                    'notes' => 'SSL Commerz Payment - Customer Booking - Payment to Admin - Transaction ID: ' . $tranId,
                ]);
            }

            // Use the first transaction ID for SSL Commerz value_c
            $primaryTransaction = $transactions[0];

            // Prepare SSL Commerz payment data
            $post_data = [];
            $post_data['total_amount'] = number_format($totalAmount, 2, '.', '');
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $tranId;

            // CUSTOMER INFORMATION
            $post_data['cus_name'] = $primaryBooking->customer_name ?? $user->name ?? 'Customer';
            $post_data['cus_email'] = $primaryBooking->customer_email ?? $user->email ?? '';
            $post_data['cus_add1'] = $primaryBooking->service_address ?? 'N/A';
            $post_data['cus_add2'] = "";
            $post_data['cus_city'] = "Dhaka";
            $post_data['cus_state'] = "";
            $post_data['cus_postcode'] = "";
            $post_data['cus_country'] = "Bangladesh";
            $post_data['cus_phone'] = $primaryBooking->customer_phone ?? '';
            $post_data['cus_fax'] = "";

            // SHIPMENT INFORMATION
            $post_data['shipping_method'] = "NO";
            $bookingCount = $bookings->count();
            $post_data['product_name'] = $bookingCount > 1 
                ? "Service Booking - {$bookingCount} Bookings" 
                : "Service Booking - Booking #" . $primaryBooking->id;
            $post_data['product_category'] = "Service Booking";
            $post_data['product_profile'] = "general";

            // OPTIONAL PARAMETERS
            $post_data['value_a'] = implode(',', $bookingIds); // Booking IDs (comma-separated)
            $post_data['value_b'] = $user->id; // Customer User ID
            $post_data['value_c'] = $primaryTransaction->id; // Primary Payment Transaction ID
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
                // Convert transactions array to collection for pluck
                $transactionIds = collect($transactions)->pluck('id')->toArray();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment session created successfully',
                    'payment_url' => $payment_data['data'],
                    'transaction_id' => $primaryTransaction->id,
                    'transaction_ids' => $transactionIds,
                    'booking_ids' => $bookingIds,
                    'total_amount' => $totalAmount,
                    'tran_id' => $tranId,
                ]);
            } else {
                // Delete all transactions if payment initiation fails
                foreach ($transactions as $transaction) {
                    $transaction->delete();
                }

                $errorMessage = 'Failed to initiate payment';
                if (is_array($payment_data) && isset($payment_data['message'])) {
                    $errorMessage = $payment_data['message'];
                } elseif (is_string($payment_options)) {
                    $errorMessage = $payment_options;
                }

                Log::error('SSL Commerz customer payment initiation failed', [
                    'payment_response' => $payment_options,
                    'decoded_response' => $payment_data,
                    'booking_ids' => $bookingIds,
                    'total_amount' => $totalAmount,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], 400);
            }

        } catch (\Throwable $e) {
            // Clean up any transactions that were created before the error
            if (isset($transactions) && is_array($transactions) && !empty($transactions)) {
                foreach ($transactions as $transaction) {
                    try {
                        $transaction->delete();
                    } catch (\Exception $deleteError) {
                        Log::warning('Failed to delete transaction during error cleanup', [
                            'transaction_id' => $transaction->id ?? 'unknown',
                            'error' => $deleteError->getMessage(),
                        ]);
                    }
                }
            }

            Log::error('SSL Commerz customer payment initiation failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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

            // Find all transactions by tran_id in notes (for multiple bookings)
            $transactions = PaymentTransaction::where('notes', 'like', '%' . $tranId . '%')
                ->where('status', 'pending')
                ->get();

            if ($transactions->isEmpty()) {
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

            // Use first transaction for type checking
            $primaryTransaction = $transactions->first();

            // Use official SSL Commerz library for validation
            $sslc = new SslCommerzNotification();
            $validation = $sslc->orderValidate($request->all(), $tranId, $amount, $currency);

            if ($validation) {
                DB::beginTransaction();

                // Check payment type
                $isCustomerPayment = $request->input('value_d') === 'customer_payment' 
                    || $primaryTransaction->transaction_type === 'customer_payment';
                $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment'
                    || ($primaryTransaction->transaction_type === 'online_payment' && strpos($primaryTransaction->notes, 'Admin to Worker') !== false);

                // Update all transaction statuses
                foreach ($transactions as $transaction) {
                    $transaction->update([
                        'status' => 'completed',
                        'processed_at' => now(),
                        'notes' => $transaction->notes . "\nSSL Commerz Val ID: " . ($request->input('val_id') ?? 'N/A') . "\nVerified: " . now(),
                    ]);
                }

                // Update booking status to 'paid' if it's a customer payment
                if ($isCustomerPayment) {
                    // Get booking IDs from value_a (comma-separated) or from transactions
                    $bookingIdsParam = $request->input('value_a');
                    $bookingIds = [];
                    
                    if ($bookingIdsParam && strpos($bookingIdsParam, ',') !== false) {
                        // Multiple booking IDs (comma-separated)
                        $bookingIds = array_map('intval', explode(',', $bookingIdsParam));
                    } else {
                        // Single booking ID or get from transactions
                        if ($bookingIdsParam) {
                            $bookingIds = [intval($bookingIdsParam)];
                        } else {
                            $bookingIds = $transactions->pluck('booking_id')->filter()->unique()->toArray();
                        }
                    }

                    if (!empty($bookingIds)) {
                        // Use direct database update to avoid any datetime casting issues with scheduled_at
                        // This ensures scheduled_at is not re-processed during the update
                        // Only update status and payment_method, explicitly exclude scheduled_at
                        DB::table('booking')
                            ->whereIn('id', $bookingIds)
                            ->where('status', '!=', 'paid')
                            ->update([
                                'status' => 'paid',
                                'payment_method' => 'online',
                                'updated_at' => now(),
                            ]);
                    
                        // Log for debugging
                        Log::info('Bookings updated after payment', [
                            'booking_ids' => $bookingIds,
                            'transaction_count' => $transactions->count(),
                        ]);
                    }
                }

                DB::commit();

                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                
                // Redirect based on payment type
                if ($isCustomerPayment) {
                    $redirectUrl = $frontendUrl . '/customer/my-booking?status=success&transaction_id=' . $primaryTransaction->id;
                } elseif ($isAdminToWorkerPayment) {
                    $redirectUrl = $frontendUrl . '/payments?status=success&transaction_id=' . $primaryTransaction->id;
                } else {
                    $redirectUrl = $frontendUrl . '/worker/submit-payment?status=success&transaction_id=' . $primaryTransaction->id;
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
                    || ($transaction && $transaction->transaction_type === 'customer_payment');
                $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment'
                    || ($transaction && $transaction->transaction_type === 'online_payment' && strpos($transaction->notes, 'Admin to Worker') !== false);
                    
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nValidation Failed at: " . now(),
                ]);

                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
                if ($isCustomerPayment) {
                    $redirectUrl = $frontendUrl . '/customer/my-booking?status=error&message=' . urlencode('Payment verification failed');
                } elseif ($isAdminToWorkerPayment) {
                    $redirectUrl = $frontendUrl . '/payments?status=error&message=' . urlencode('Payment verification failed');
                } else {
                    $redirectUrl = $frontendUrl . '/worker/submit-payment?status=error&message=' . urlencode('Payment verification failed');
                }
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
                || ($transaction && $transaction->transaction_type === 'customer_payment');
            $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment'
                || ($transaction && $transaction->transaction_type === 'online_payment' && strpos($transaction->notes, 'Admin to Worker') !== false);

            if ($transaction) {
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nPayment Failed: " . ($request->input('error') ?? 'Unknown error') . "\nFailed at: " . now(),
                ]);
            }

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            if ($isCustomerPayment) {
                $redirectUrl = $frontendUrl . '/customer/my-booking?status=failed&message=' . urlencode('Payment failed');
            } elseif ($isAdminToWorkerPayment) {
                $redirectUrl = $frontendUrl . '/payments?status=failed&message=' . urlencode('Payment failed');
            } else {
                $redirectUrl = $frontendUrl . '/worker/submit-payment?status=failed&message=' . urlencode('Payment failed');
            }
            return $this->htmlRedirect($redirectUrl);

        } catch (\Throwable $e) {
            Log::error('SSL Commerz fail callback error: ' . $e->getMessage());
            $isCustomerPayment = $request->input('value_d') === 'customer_payment';
            $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            if ($isCustomerPayment) {
                $redirectUrl = $frontendUrl . '/customer/my-booking?status=error';
            } elseif ($isAdminToWorkerPayment) {
                $redirectUrl = $frontendUrl . '/payments?status=error';
            } else {
                $redirectUrl = $frontendUrl . '/worker/submit-payment?status=error';
            }
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
                || ($transaction && $transaction->transaction_type === 'customer_payment');
            $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment'
                || ($transaction && $transaction->transaction_type === 'online_payment' && strpos($transaction->notes, 'Admin to Worker') !== false);

            if ($transaction) {
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => $transaction->notes . "\nPayment Cancelled by user\nCancelled at: " . now(),
                ]);
            }

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            if ($isCustomerPayment) {
                $redirectUrl = $frontendUrl . '/customer/my-booking?status=cancelled&message=' . urlencode('Payment cancelled');
            } elseif ($isAdminToWorkerPayment) {
                $redirectUrl = $frontendUrl . '/payments?status=cancelled&message=' . urlencode('Payment cancelled');
            } else {
                $redirectUrl = $frontendUrl . '/worker/submit-payment?status=cancelled&message=' . urlencode('Payment cancelled');
            }
            return $this->htmlRedirect($redirectUrl);

        } catch (\Throwable $e) {
            Log::error('SSL Commerz cancel callback error: ' . $e->getMessage());
            $isCustomerPayment = $request->input('value_d') === 'customer_payment';
            $isAdminToWorkerPayment = $request->input('value_d') === 'admin_to_worker_payment';
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            if ($isCustomerPayment) {
                $redirectUrl = $frontendUrl . '/customer/my-booking?status=error';
            } elseif ($isAdminToWorkerPayment) {
                $redirectUrl = $frontendUrl . '/payments?status=error';
            } else {
                $redirectUrl = $frontendUrl . '/worker/submit-payment?status=error';
            }
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

                // Update booking status to 'paid' if it's a customer payment
                if ($transaction->transaction_type === 'customer_payment' && $transaction->booking_id) {
                    DB::table('booking')
                        ->where('id', $transaction->booking_id)
                        ->where('status', '!=', 'paid')
                        ->update([
                            'status' => 'paid',
                            'payment_method' => 'online',
                            'updated_at' => now(),
                        ]);
                }

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
     * Get worker's pending payments from admin (online bookings where customer paid but admin hasn't sent money yet)
     */
    public function getWorkerPendingPayments(Request $request): JsonResponse
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

            // Get bookings where:
            // 1. Customer has paid (status=paid, payment_method=online)
            // 2. Has customer_payment transaction completed
            // 3. But no completed online_payment transaction to worker yet
            $bookings = Booking::where('worker_id', $worker->id)
                ->where('status', 'paid')
                ->where('payment_method', 'online')
                ->whereHas('paymentTransactions', function ($query) {
                    // Must have completed customer payment
                    $query->where('transaction_type', 'customer_payment')
                        ->where('status', 'completed');
                })
                ->whereDoesntHave('paymentTransactions', function ($query) {
                    // But no completed worker payment yet
                    $query->where('transaction_type', 'online_payment')
                        ->where('status', 'completed');
                })
                ->with(['serviceSubcategory', 'customer', 'paymentTransactions' => function ($query) {
                    $query->where('transaction_type', 'customer_payment')
                        ->where('status', 'completed')
                        ->orderByDesc('created_at')
                        ->limit(1);
                }])
                ->orderByDesc('created_at')
                ->get();

            // Calculate total pending amount
            $totalPendingAmount = $bookings->sum('total_amount');
            $workerPendingAmount = $totalPendingAmount * 0.80; // Worker gets 80%

            return response()->json([
                'success' => true,
                'data' => [
                    'bookings' => $bookings,
                    'total_pending_amount' => $totalPendingAmount,
                    'worker_pending_amount' => $workerPendingAmount,
                    'commission_amount' => $totalPendingAmount * 0.20,
                    'count' => $bookings->count(),
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('Get worker pending payments failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending payments',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get all commission payments (for admin) - shows all commission payments regardless of status
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

            // Get all commission payments (pending, approved, completed, rejected)
            $transactions = PaymentTransaction::where('transaction_type', 'commission_payment')
                ->with(['booking.serviceSubcategory', 'booking.customer', 'worker', 'processedBy'])
                ->orderByDesc('created_at')
                ->get();

            // Separate pending and all transactions
            $pendingTransactions = $transactions->where('status', 'pending')->values();
            $allTransactions = $transactions->values();

            return response()->json([
                'success' => true,
                'data' => $allTransactions,
                'pending_count' => $pendingTransactions->count(),
                'total_count' => $allTransactions->count(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Get commission payments failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve commission payments',
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

            // Get paid bookings with online payment method where customer has paid (customer_payment completed)
            // but admin hasn't sent payment to worker yet (no completed online_payment transaction to worker)
            $bookings = Booking::where('status', 'paid')
                ->where('payment_method', 'online')
                ->whereHas('paymentTransactions', function ($query) {
                    // Must have completed customer payment
                    $query->where('transaction_type', 'customer_payment')
                        ->where('status', 'completed');
                })
                ->whereDoesntHave('paymentTransactions', function ($query) {
                    // But no completed worker payment yet
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
     * Admin initiates SSL Commerz payment to worker
     */
    public function initiateAdminToWorkerPayment(Request $request): JsonResponse
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

            // Check if worker is assigned to the booking
            if (!$booking->worker_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send payment: No worker assigned to this booking. Please assign a worker first.'
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

            // Check if payment already initiated
            $pendingTransaction = PaymentTransaction::where('booking_id', $booking->id)
                ->where('transaction_type', 'online_payment')
                ->where('status', 'pending')
                ->first();

            if ($pendingTransaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already initiated for this booking'
                ], 400);
            }

            // Get worker
            $worker = Worker::find($booking->worker_id);
            if (!$worker) {
                return response()->json([
                    'success' => false,
                    'message' => 'Worker not found'
                ], 404);
            }

            // Calculate 20% commission (admin keeps this)
            $commissionAmount = $booking->total_amount * 0.20;
            // Worker receives 80% of the payment
            $workerAmount = $booking->total_amount * 0.80;

            // Create commission transaction (20% - admin keeps this)
            $commissionTransaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $booking->worker_id,
                'transaction_type' => 'commission_payment',
                'amount' => $commissionAmount,
                'status' => 'completed',
                'notes' => 'Commission deducted from online payment' . ($request->notes ? "\n" . $request->notes : ''),
                'processed_by' => $user->id,
                'processed_at' => now(),
            ]);

            // Generate unique transaction ID
            $tranId = 'ADMIN2WRK' . date('YmdHis') . $booking->id . rand(1000, 9999);

            // Create pending payment transaction for worker (80% - will be sent via SSL Commerz)
            $transaction = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'worker_id' => $booking->worker_id,
                'transaction_type' => 'online_payment',
                'amount' => $workerAmount,
                'status' => 'pending',
                'notes' => 'SSL Commerz Payment - Admin to Worker - Transaction ID: ' . $tranId . ($request->notes ? "\nNotes: " . $request->notes : ''),
                'processed_by' => $user->id,
            ]);

            // Prepare SSL Commerz payment data
            $post_data = [];
            $post_data['total_amount'] = number_format($workerAmount, 2, '.', '');
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $tranId;

            // WORKER INFORMATION (recipient)
            $post_data['cus_name'] = $worker->name ?? 'Worker';
            $post_data['cus_email'] = $worker->email ?? '';
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
            $post_data['product_name'] = "Worker Payment - Booking #" . $booking->id;
            $post_data['product_category'] = "Worker Payment";
            $post_data['product_profile'] = "general";

            // OPTIONAL PARAMETERS
            $post_data['value_a'] = $booking->id; // Booking ID
            $post_data['value_b'] = $worker->id; // Worker ID
            $post_data['value_c'] = $transaction->id; // Payment Transaction ID
            $post_data['value_d'] = "admin_to_worker_payment"; // Payment type identifier

            // Verify SSL Commerz config
            $sslConfig = config('sslcommerz');
            if (empty($sslConfig['apiCredentials']['store_id']) || empty($sslConfig['apiCredentials']['store_password'])) {
                Log::error('SSL Commerz credentials missing');
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
                    'worker_amount' => $workerAmount,
                    'commission_amount' => $commissionAmount,
                ]);
            } else {
                $transaction->delete();
                $commissionTransaction->delete();
                
                $errorMessage = 'Failed to initiate payment';
                if (is_array($payment_data) && isset($payment_data['message'])) {
                    $errorMessage = $payment_data['message'];
                }

                Log::error('SSL Commerz admin-to-worker payment initiation failed', [
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
            Log::error('Initiate admin-to-worker payment failed: ' . $e->getMessage(), [
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
     * Admin sends online payment to worker (deprecated - use initiateAdminToWorkerPayment)
     * @deprecated Use initiateAdminToWorkerPayment instead
     */
    public function sendOnlinePayment(Request $request): JsonResponse
    {
        // Redirect to new SSL Commerz flow
        return $this->initiateAdminToWorkerPayment($request);
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
