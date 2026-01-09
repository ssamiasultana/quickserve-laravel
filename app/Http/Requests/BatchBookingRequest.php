<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bookings' => ['required', 'array', 'min:1'],
            'bookings.*.user_id' => ['required', 'exists:users,id'],
            'bookings.*.customer_name' => ['required', 'string', 'max:255'],
            'bookings.*.customer_email' => ['required', 'email', 'max:255'],
            'bookings.*.customer_phone' => ['required', 'string', 'max:20'],
            'bookings.*.service_address' => ['required', 'string'],
            'bookings.*.special_instructions' => ['nullable', 'string'],
            'bookings.*.shift_type' => ['required', 'in:day,night,flexible'],
            'bookings.*.scheduled_at' => ['required', 'date', 'after:now'],
            'bookings.*.quantity' => ['required', 'integer', 'min:1'],
            'bookings.*.services' => ['required', 'array', 'min:1'],
            'bookings.*.services.*.service_id' => ['required', 'exists:services,id'],
            'bookings.*.services.*.service_subcategory_id' => ['required', 'exists:service_subcategories,id'],
        ];
    }

    /**
     * Get validated data with mapped fields.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Map user_id to customer_id for each booking
        if (isset($validated['bookings'])) {
            foreach ($validated['bookings'] as &$booking) {
                if (isset($booking['user_id'])) {
                    $booking['customer_id'] = $booking['user_id'];
                    unset($booking['user_id']);
                }
            }
        }
        
        return $validated;
    }
}


