<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'service_address' => ['required', 'string'],
            'special_instructions' => ['nullable', 'string'],
            'shift_type' => ['required', 'in:day,night,flexible'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'quantity' => ['required', 'integer', 'min:1'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.service_subcategory_id' => ['required', 'exists:service_subcategories,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'user_id.required' => 'User is required',
            'user_id.exists' => 'Selected user does not exist',
            'customer_name.required' => 'Customer name is required',
            'customer_email.required' => 'Customer email is required',
            'customer_email.email' => 'Customer email must be a valid email address',
            'customer_phone.required' => 'Customer phone number is required',
            'service_address.required' => 'Service address is required',
            'services.required' => 'At least one service is required',
            'services.*.service_id.required' => 'Service is required for each service',
            'services.*.service_id.exists' => 'Selected service does not exist',
            'services.*.service_subcategory_id.required' => 'Service subcategory is required for each service',
            'services.*.service_subcategory_id.exists' => 'Selected service subcategory does not exist',
            'quantity.required' => 'Quantity is required',
            'quantity.min' => 'Quantity must be at least 1',
            'scheduled_at.after' => 'Scheduled time must be in the future',
        ];
    }

    /**
     * Prepare the data for validation and map user_id to customer_id.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('user_id')) {
            $this->merge([
                'customer_id' => $this->user_id,
            ]);
        }
    }

    /**
     * Get validated data with mapped fields.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Map user_id to customer_id
        if (isset($validated['user_id'])) {
            $validated['customer_id'] = $validated['user_id'];
            unset($validated['user_id']);
        }
        
        // Map service_id in services array
        if (isset($validated['services'])) {
            foreach ($validated['services'] as &$service) {
                if (isset($service['service_id'])) {
                    $service['service_id'] = $service['service_id'];
                }
            }
        }
        
        return $validated;
    }
}
