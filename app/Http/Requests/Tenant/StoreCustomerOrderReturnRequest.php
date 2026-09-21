<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOrderReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tenant_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:customer_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.destination' => ['required', 'string', 'in:restock,scrap'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
