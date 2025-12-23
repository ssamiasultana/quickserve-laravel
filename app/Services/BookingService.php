<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ServiceSubcategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    /**
     * Create one or more bookings from a validated booking payload.
     *
     * @param  array<string,mixed>  $data
     * @return \Illuminate\Support\Collection<int,\App\Models\Booking>
     */
    public function createBooking(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $bookings = collect();

            $customerId = $data['customer_id'];
            $shiftType = $data['shift_type'];
            $scheduledAt = $data['scheduled_at'];
            $quantity = (int) ($data['quantity'] ?? 1);

            if (! in_array($shiftType, ['day', 'night', 'flexible'], true)) {
                throw new InvalidArgumentException('Invalid shift type provided.');
            }

            foreach ($data['services'] as $service) {
                $bookings->push(
                    $this->createSingleBooking(
                        $customerId,
                        $shiftType,
                        $scheduledAt,
                        $quantity,
                        $service
                    )
                );
            }

            return $bookings;
        });
    }

    /**
     * Create a collection of bookings from multiple booking payloads.
     *
     * @param  array<int,array<string,mixed>>  $batchData
     * @return \Illuminate\Support\Collection<int,\App\Models\Booking>
     */
    public function createBatchBooking(array $batchData): Collection
    {
        return DB::transaction(function () use ($batchData) {
            $allBookings = collect();

            foreach ($batchData['bookings'] as $payload) {
                $created = $this->createBooking($payload);
                $allBookings = $allBookings->merge($created);
            }

            return $allBookings;
        });
    }

    /**
     * Calculate a basic summary for a collection of bookings.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Booking>  $bookings
     * @return array<string,mixed>
     */
    public function calculateBatchSummary(Collection $bookings): array
    {
        return [
            'total_bookings' => $bookings->count(),
            'total_quantity' => $bookings->sum('quantity'),
            'total_amount' => $bookings->sum('total_amount'),
        ];
    }

    /**
     * Create a single booking record from service-specific data.
     *
     * @param  int    $customerId
     * @param  string $shiftType
     * @param  string $scheduledAt
     * @param  int    $quantity
     * @param  array<string,mixed> $service
     * @return \App\Models\Booking
     */
    protected function createSingleBooking(
        int $customerId,
        string $shiftType,
        string $scheduledAt,
        int $quantity,
        array $service
    ): Booking {
        /** @var \App\Models\ServiceSubcategory $subcategory */
        $subcategory = ServiceSubcategory::findOrFail($service['service_subcategory_id']);

        $unitPrice = (float) $subcategory->base_price;
        $subtotal = $unitPrice * $quantity;

        $shiftChargePercent = match ($shiftType) {
            'night' => config('services.booking_night_shift_percent', 20),
            'flexible' => config('services.booking_flexible_shift_percent', 0),
            default => 0,
        };

        // Total amount is just unit_price (not multiplied by quantity)
        $totalAmount = $unitPrice;

        return Booking::create([
            'customer_id' => $customerId,
            'service_id' => $service['service_id'],
            'service_subcategory_id' => $service['service_subcategory_id'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal_amount' => $subtotal,
            'shift_type' => $shiftType,
            'shift_charge_percent' => $shiftChargePercent,
            'total_amount' => $totalAmount,
            'scheduled_at' => $scheduledAt,
        ]);
    }
}


