<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslCommerzService
{
    private $storeId;
    private $storePassword;
    private $apiUrl;
    private $successUrl;
    private $failUrl;
    private $cancelUrl;
    private $ipnUrl;

    public function __construct()
    {
        $this->storeId = config('services.sslcommerz.store_id');
        $this->storePassword = config('services.sslcommerz.store_password');
        $this->apiUrl = config('services.sslcommerz.api_url', 'https://sandbox.sslcommerz.com');
        $this->successUrl = config('services.sslcommerz.success_url', url('/api/payments/sslcommerz/success'));
        $this->failUrl = config('services.sslcommerz.fail_url', url('/api/payments/sslcommerz/fail'));
        $this->cancelUrl = config('services.sslcommerz.cancel_url', url('/api/payments/sslcommerz/cancel'));
        $this->ipnUrl = config('services.sslcommerz.ipn_url', url('/api/payments/sslcommerz/ipn'));
    }

    /**
     * Initiate SSL Commerz payment session
     */
    public function initiatePayment(array $data): array
    {
        try {
            $postData = [
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'total_amount' => number_format($data['amount'], 2, '.', ''),
                'currency' => $data['currency'] ?? 'BDT',
                'tran_id' => $data['tran_id'], // Unique transaction ID
                'success_url' => $this->successUrl,
                'fail_url' => $this->failUrl,
                'cancel_url' => $this->cancelUrl,
                'ipn_url' => $this->ipnUrl,
                'cus_name' => $data['customer_name'],
                'cus_email' => $data['customer_email'],
                'cus_phone' => $data['customer_phone'],
                'cus_add1' => $data['customer_address'] ?? '',
                'cus_city' => $data['customer_city'] ?? 'Dhaka',
                'cus_country' => $data['customer_country'] ?? 'Bangladesh',
                'shipping_method' => 'NO',
                'product_name' => $data['product_name'],
                'product_category' => $data['product_category'] ?? 'Service',
                'product_profile' => 'general',
                'value_a' => $data['booking_id'] ?? '', // Additional data
                'value_b' => $data['worker_id'] ?? '',
                'value_c' => $data['transaction_id'] ?? '',
            ];

            $response = Http::asForm()->post($this->apiUrl . '/gwprocess/v4/api.php', $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                    return [
                        'success' => true,
                        'payment_url' => $responseData['GatewayPageURL'],
                        'sessionkey' => $responseData['sessionkey'] ?? null,
                    ];
                } else {
                    Log::error('SSL Commerz payment initiation failed', [
                        'response' => $responseData,
                        'data' => $data,
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => $responseData['failedreason'] ?? 'Payment initiation failed',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to connect to SSL Commerz',
            ];

        } catch (\Exception $e) {
            Log::error('SSL Commerz payment initiation error: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment initiation error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify SSL Commerz payment
     */
    public function verifyPayment(string $valId, string $amount, string $currency = 'BDT'): array
    {
        try {
            $postData = [
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'val_id' => $valId,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
            ];

            $response = Http::asForm()->post($this->apiUrl . '/validator/api/validationserverAPI.php', $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['status']) && $responseData['status'] === 'VALID' || $responseData['status'] === 'VALIDATED') {
                    return [
                        'success' => true,
                        'data' => $responseData,
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Payment verification failed',
            ];

        } catch (\Exception $e) {
            Log::error('SSL Commerz payment verification error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment verification error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate unique transaction ID
     */
    public function generateTransactionId(int $bookingId, int $workerId): string
    {
        return 'TXN' . date('YmdHis') . $bookingId . $workerId . rand(1000, 9999);
    }
}
