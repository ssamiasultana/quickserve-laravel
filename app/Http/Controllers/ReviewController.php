<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReviewController extends Controller
{
    /**
     * Create a review for a completed booking.
     * Only customers can create reviews, and only for paid/completed bookings.
     */
    public function createReview(Request $request): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Only customers can create reviews
            if ($user->role !== 'Customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only customers can create reviews'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|exists:booking,id',
                'rating' => 'required|integer|min:1|max:5',
                'review' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the booking
            $booking = Booking::findOrFail($request->booking_id);

            // Verify the booking belongs to the authenticated customer
            if ($booking->customer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review your own bookings'
                ], 403);
            }

            // Check if booking is completed (paid status)
            if ($booking->status !== 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review completed (paid) bookings'
                ], 400);
            }

            // Check if review already exists for this booking
            if ($booking->review()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this booking'
                ], 400);
            }

            // Verify worker exists
            if (!$booking->worker_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking does not have an assigned worker'
                ], 400);
            }

            // Create the review
            $review = Review::create([
                'booking_id' => $booking->id,
                'customer_id' => $user->id,
                'worker_id' => $booking->worker_id,
                'rating' => $request->rating,
                'review' => $request->review,
            ]);

            // Load relationships
            $review->load(['customer', 'worker', 'booking']);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all reviews for a specific worker.
     */
    public function getWorkerReviews($workerId): JsonResponse
    {
        try {
            $worker = \App\Models\Worker::findOrFail($workerId);
            
            $reviews = Review::where('worker_id', $workerId)
                ->with(['customer', 'booking'])
                ->orderBy('created_at', 'desc')
                ->get();

            $averageRating = $reviews->avg('rating');
            $totalReviews = $reviews->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'average_rating' => $averageRating ? round($averageRating, 2) : 0,
                    'total_reviews' => $totalReviews,
                ],
                'message' => 'Worker reviews retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review for a specific booking.
     */
    public function getBookingReview($bookingId): JsonResponse
    {
        try {
            $review = Review::where('booking_id', $bookingId)
                ->with(['customer', 'worker', 'booking'])
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found for this booking'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve review: ' . $e->getMessage()
            ], 500);
        }
    }
}
