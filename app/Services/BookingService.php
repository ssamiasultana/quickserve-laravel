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
            $customerName = $data['customer_name'] ?? null;
            $customerEmail = $data['customer_email'] ?? null;
            $customerPhone = $data['customer_phone'] ?? null;
            $serviceAddress = $data['service_address'] ?? null;
            $specialInstructions = $data['special_instructions'] ?? null;
            $shiftType = $data['shift_type'];
            $scheduledAt = $data['scheduled_at'];
            $quantity = (int) ($data['quantity'] ?? 1);

            // Normalize shift type - map 'flexible' to 'day' if enum doesn't support it
            // This handles cases where the database enum hasn't been updated yet
            if ($shiftType === 'flexible') {
                $shiftType = 'day'; // Flexible workers work during day shifts in the booking system
            }

            if (! in_array($shiftType, ['day', 'night'], true)) {
                throw new InvalidArgumentException('Invalid shift type provided.');
            }

            foreach ($data['services'] as $service) {
                $bookings->push(
                    $this->createSingleBooking(
                        $customerId,
                        $customerName,
                        $customerEmail,
                        $customerPhone,
                        $serviceAddress,
                        $specialInstructions,
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
     * @param  string|null $customerName
     * @param  string|null $customerEmail
     * @param  string|null $customerPhone
     * @param  string|null $serviceAddress
     * @param  string|null $specialInstructions
     * @param  string $shiftType
     * @param  string $scheduledAt
     * @param  int    $quantity
     * @param  array<string,mixed> $service
     * @return \App\Models\Booking
     */
    protected function createSingleBooking(
        int $customerId,
        ?string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        ?string $serviceAddress,
        ?string $specialInstructions,
        string $shiftType,
        string $scheduledAt,
        int $quantity,
        array $service
    ): Booking {
        /** @var \App\Models\ServiceSubcategory $subcategory */
        $subcategory = ServiceSubcategory::findOrFail($service['service_subcategory_id']);

        $unitPrice = (float) $subcategory->base_price;
        $subtotal = $unitPrice * $quantity;

        // Calculate shift charge (flexible is already mapped to 'day' at this point)
        $shiftChargePercent = match ($shiftType) {
            'night' => config('services.booking_night_shift_percent', 20),
            default => 0, // 'day' or 'flexible' (mapped to day) = 0%
        };

        // Total amount = subtotal (unit_price * quantity) + shift charge
        $shiftCharge = $subtotal * ($shiftChargePercent / 100);
        $totalAmount = $subtotal + $shiftCharge;

        return Booking::create([
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'service_address' => $serviceAddress,
            'special_instructions' => $specialInstructions,
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


