<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchBookingRequest;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {
    }

    /**
     * Create one or more bookings for a single customer.
     */
    public function createBooking(BookingRequest $request): JsonResponse
    {
        try {
            $bookings = $this->bookingService->createBooking($request->validated());

            // Load relationships on each booking model
            $bookings->each(function ($booking) {
                $booking->load(['customer', 'service', 'serviceSubcategory']);
            });

            // Calculate total: sum of total_amount from each booking (which already includes quantity and shift charge)
            $totalAmount = $bookings->sum('total_amount');

            return response()->json([
                'success' => true,
                'message' => 'Booking(s) created successfully',
                'data' => [
                    'bookings' => BookingResource::collection($bookings),
                    'total_bookings' => $bookings->count(),
                    'total_amount' => round($totalAmount, 2),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Booking creation failed: '.$e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking(s)',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }

    /**
     * Get a single booking by model binding.
     */
    public function getBooking(Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new BookingResource(
                $booking->load(['customer', 'serviceCategory', 'serviceSubcategory'])
            ),
        ]);
    }

    /**
     * Get all bookings for a specific customer by customer_id.
     */
    public function getBookingsByCustomer($customerId): JsonResponse
    {
        $bookings = Booking::where('customer_id', $customerId)
            ->with(['customer', 'service', 'serviceSubcategory'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'total_bookings' => $bookings->count(),
        ]);
    }

    /**
     * Create multiple bookings in batch.
     */
    public function batchStore(BatchBookingRequest $request): JsonResponse
    {
        try {
            $bookings = $this->bookingService->createBatchBooking($request->validated());

            // Load relationships on each booking model
            $bookings->each(function ($booking) {
                $booking->load(['customer', 'service', 'serviceSubcategory']);
            });

            // Calculate total: sum of total_amount from each booking (which already includes quantity and shift charge)
            $totalAmount = $bookings->sum('total_amount');

            return response()->json([
                'success' => true,
                'message' => $bookings->count().' booking(s) created successfully',
                'data' => [
                    'bookings' => BookingResource::collection($bookings),
                    'total_bookings' => $bookings->count(),
                    'total_amount' => round($totalAmount, 2),
                    'summary' => $this->bookingService->calculateBatchSummary($bookings),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Batch booking creation failed: '.$e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }
    public function getAllBookings(): JsonResponse
    {
     
        $bookings = Booking::with([
            'customer',
            'service',
            'serviceSubcategory'
        ])
        // ->orderByDesc('created_at')
        ->get();

    return response()->json([
        'success' => true,
        'data' => BookingResource::collection($bookings),
        'total_bookings' => $bookings->count(),
        'message' => 'All bookings retrieved successfully'
    ]);
    }
}

