<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admin users can create products
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price'       => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'stock'       => ['required', 'integer', 'min:0'],
            'sku'         => ['nullable', 'string', 'max:100', 'unique:products,sku'],
        ];
    }
}
