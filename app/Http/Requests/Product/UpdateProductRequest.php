<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name'        => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price'       => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999.99'],
            'stock'       => ['sometimes', 'required', 'integer', 'min:0'],
            'sku'         => ['sometimes', 'nullable', 'string', 'max:100', "unique:products,sku,{$productId}"],
        ];
    }
}
