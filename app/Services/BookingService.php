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
            $workerId = $data['worker_id'] ?? null;
            $customerName = $data['customer_name'] ?? null;
            $customerEmail = $data['customer_email'] ?? null;
            $customerPhone = $data['customer_phone'] ?? null;
            $serviceAddress = $data['service_address'] ?? null;
            $specialInstructions = $data['special_instructions'] ?? null;
            $shiftType = $data['shift_type'];
            $scheduledAt = $data['scheduled_at'];
            $quantity = (int) ($data['quantity'] ?? 1);
            $paymentMethod = $data['payment_method'] ?? 'cash';
            
            // Ensure scheduled_at is properly formatted
            // The frontend sends datetime in format: YYYY-MM-DDTHH:mm:ss (intended as local time in Asia/Dhaka)
            // Solution: Parse the datetime as Asia/Dhaka timezone, then convert to UTC for storage
            if (is_string($scheduledAt)) {
                try {
                    // Normalize the datetime string format
                    $datetimeStr = str_replace('T', ' ', $scheduledAt);
                    // Ensure seconds are present
                    if (substr_count($datetimeStr, ':') === 1) {
                        $datetimeStr .= ':00';
                    }
                    
                    // Parse the datetime string and treat it as Asia/Dhaka timezone
                    // The datetime string from frontend represents a time in Asia/Dhaka timezone
                    // We need to parse it as Asia/Dhaka and convert to UTC for storage
                    
                    // Parse the datetime components
                    // Format: "YYYY-MM-DD HH:mm:ss"
                    $parts = explode(' ', $datetimeStr);
                    if (count($parts) !== 2) {
                        throw new \Exception('Invalid datetime format: ' . $datetimeStr);
                    }
                    
                    list($datePart, $timePart) = $parts;
                    $dateComponents = explode('-', $datePart);
                    $timeComponents = explode(':', $timePart);
                    
                    if (count($dateComponents) !== 3 || count($timeComponents) < 2) {
                        throw new \Exception('Invalid datetime components: ' . $datetimeStr);
                    }
                    
                    list($year, $month, $day) = $dateComponents;
                    $hour = $timeComponents[0];
                    $minute = $timeComponents[1];
                    $second = $timeComponents[2] ?? '00';
                    
                    // Create datetime string
                    $datetimeString = sprintf('%04d-%02d-%02d %02d:%02d:%02d', 
                        (int)$year, (int)$month, (int)$day, 
                        (int)$hour, (int)$minute, (int)$second
                    );
                    
                    // Manual timezone conversion: Asia/Dhaka (UTC+6) to UTC
                    // Create Carbon instance in UTC, then subtract 6 hours
                    // This ensures the time is correctly converted
                    $carbon = \Carbon\Carbon::create(
                        (int)$year,
                        (int)$month,
                        (int)$day,
                        (int)$hour,
                        (int)$minute,
                        (int)$second,
                        'UTC'
                    );
                    
                    // Subtract 6 hours to convert from Asia/Dhaka to UTC
                    // Asia/Dhaka is UTC+6, so local time - 6 hours = UTC time
                    $carbon->subHours(6);
                    
                    // Format as UTC datetime string for database storage
                    $scheduledAt = $carbon->format('Y-m-d H:i:s');
                    
                    // Log for debugging
                    \Log::info('Booking scheduled_at conversion', [
                        'original_input' => $datetimeStr,
                        'parsed_datetime' => $datetimeString,
                        'asia_dhaka_time' => sprintf('%04d-%02d-%02d %02d:%02d:%02d Asia/Dhaka', 
                            (int)$year, (int)$month, (int)$day, 
                            (int)$hour, (int)$minute, (int)$second),
                        'utc_stored' => $scheduledAt,
                        'utc_carbon_format' => $carbon->format('Y-m-d H:i:s T'),
                        'payment_method' => $paymentMethod ?? 'unknown'
                    ]);
                    
                } catch (\Exception $e) {
                    // If parsing fails, log and use as-is (Laravel will try to parse it)
                    \Log::warning('Failed to parse scheduled_at', [
                        'scheduled_at' => $scheduledAt,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Normalize shift type - map 'flexible' to 'day' if enum doesn't support it
            // This handles cases where the database enum hasn't been updated yet
            if ($shiftType === 'flexible') {
                $shiftType = 'day'; // Flexible workers work during day shifts in the booking system
            }

            if (! in_array($shiftType, ['day', 'night'], true)) {
                throw new InvalidArgumentException('Invalid shift type provided.');
            }

            foreach ($data['services'] as $service) {
                // Use quantity from service array if provided, otherwise use the main quantity
                $serviceQuantity = isset($service['quantity']) 
                    ? (int) $service['quantity'] 
                    : $quantity;
                
                $bookings->push(
                    $this->createSingleBooking(
                        $customerId,
                        $workerId,
                        $customerName,
                        $customerEmail,
                        $customerPhone,
                        $serviceAddress,
                        $specialInstructions,
                        $shiftType,
                        $scheduledAt,
                        $serviceQuantity,
                        $paymentMethod,
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
        ?int $workerId,
        ?string $customerName,
        ?string $customerEmail,
        ?string $customerPhone,
        ?string $serviceAddress,
        ?string $specialInstructions,
        string $shiftType,
        string $scheduledAt,
        int $quantity,
        string $paymentMethod,
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

        // New bookings always start as 'pending' regardless of payment method
        // Workers need to confirm the booking first, then mark as paid when payment is received
        // Payment method indicates how customer will pay, not that payment has been received
        $bookingStatus = 'pending';

        return Booking::create([
            'customer_id' => $customerId,
            'worker_id' => $workerId,
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
            'payment_method' => $paymentMethod,
            'status' => $bookingStatus,
            'scheduled_at' => $scheduledAt,
        ]);
    }
}


