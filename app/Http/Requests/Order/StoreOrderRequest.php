<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can place an order
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:1000'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'               => 'Order must contain at least one item.',
            'items.*.product_id.exists'    => 'One or more selected products do not exist.',
            'items.*.quantity.min'         => 'Quantity must be at least 1.',
        ];
    }
}
