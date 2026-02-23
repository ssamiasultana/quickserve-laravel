<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchBookingRequest;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->with(['customer', 'worker', 'service', 'serviceSubcategory'])
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
            'worker',
            'service',
            'serviceSubcategory'
        ])
        ->orderByDesc('created_at')
        ->get();

    return response()->json([
        'success' => true,
        'data' => BookingResource::collection($bookings),
        'total_bookings' => $bookings->count(),
        'message' => 'All bookings retrieved successfully'
    ]);
    }

    /**
     * Get all bookings for a worker based on their services.
     * Filters by worker_id if provided in query parameter.
     */
    public function getBookingsByWorker(Request $request): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get the worker with their services
        // First try to find by user_id
        $worker = \App\Models\Worker::with('services')->where('user_id', $user->id)->first();
        
        // If not found by user_id, try to find by email (fallback for older records)
        if (!$worker) {
            $worker = \App\Models\Worker::with('services')->where('email', $user->email)->first();
            
            // If found by email but user_id is missing, update it
            if ($worker && !$worker->user_id) {
                $worker->user_id = $user->id;
                $worker->save();
            }
        }
        
        if (!$worker) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total_bookings' => 0,
                'message' => 'Worker profile not found. Please complete your worker profile first.'
            ]);
        }

        // Get service IDs that the worker provides
        $serviceIds = $worker->services->pluck('id')->toArray();
        
        if (empty($serviceIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total_bookings' => 0,
                'message' => 'No services assigned to worker'
            ]);
        }

        // Get bookings assigned to this worker
        // Filter by worker_id to show only bookings assigned to the authenticated worker
        $bookings = Booking::whereIn('service_id', $serviceIds)
            ->where('worker_id', $worker->id) // Filter by authenticated worker's ID
            ->with(['customer', 'service', 'serviceSubcategory'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings),
            'total_bookings' => $bookings->count(),
            'message' => 'Worker bookings retrieved successfully'
        ]);
    }

    /**
     * Update booking status (confirm or cancel).
     * Workers and Moderators can update booking status.
     */
    public function updateBookingStatus(Request $request, Booking $booking): JsonResponse
    {
        try {
            $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validate the request - allow paid, confirmed, or cancelled
            $request->validate([
                'status' => 'required|in:paid,confirmed,cancelled',
            ]);

            $newStatus = $request->input('status');
            $currentStatus = $booking->status;

            // Status transition rules:
            // - cancelled: only from pending
            // - confirmed: from pending
            // - paid: from pending or confirmed (when payment is received)
            if ($newStatus === 'cancelled' && $currentStatus !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a booking that is already confirmed, paid, or cancelled'
                ], 400);
            }

            if ($newStatus === 'confirmed' && $currentStatus !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot confirm a booking that is already confirmed, paid, or cancelled'
                ], 400);
            }

            if ($newStatus === 'paid' && !in_array($currentStatus, ['pending', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only mark pending or confirmed bookings as paid'
                ], 400);
            }

            // Check if user is Moderator or Admin - they have full access
            $isModeratorOrAdmin = in_array($user->role, ['Moderator', 'Admin']);
            
            if ($isModeratorOrAdmin) {
                // Moderators and Admins can update any booking
                // Also assign worker if booking doesn't have one and status is being confirmed
                if ($newStatus === 'confirmed' && !$booking->worker_id && $request->has('worker_id')) {
                    $booking->worker_id = $request->input('worker_id');
                }
            } else {
                // For Workers, verify they have access to this booking
                $worker = \App\Models\Worker::with('services')->where('user_id', $user->id)->first();
                
                // If not found by user_id, try to find by email (fallback for older records)
                if (!$worker) {
                    $worker = \App\Models\Worker::with('services')->where('email', $user->email)->first();
                    
                    // If found by email but user_id is missing, update it
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

                // Get service IDs that the worker provides
                $serviceIds = $worker->services->pluck('id')->toArray();
                
                // If booking has a worker_id, verify it matches the authenticated worker
                if ($booking->worker_id && $booking->worker_id !== $worker->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to update this booking'
                    ], 403);
                }
                
                // If booking doesn't have a worker_id, verify the worker provides the service
                if (!$booking->worker_id && !in_array($booking->service_id, $serviceIds)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have permission to update this booking'
                    ], 403);
                }
                
                // If confirming and no worker assigned, assign this worker
                if ($newStatus === 'confirmed' && !$booking->worker_id) {
                    $booking->worker_id = $worker->id;
                }
            }

            // Update the booking status
            $booking->status = $newStatus;
            $booking->save();

            // Reload relationships
            $booking->load(['customer', 'service', 'serviceSubcategory', 'worker']);

            return response()->json([
                'success' => true,
                'message' => "Booking {$newStatus} successfully",
                'data' => new BookingResource($booking)
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update booking status: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking status',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong',
            ], 500);
        }
    }
}

