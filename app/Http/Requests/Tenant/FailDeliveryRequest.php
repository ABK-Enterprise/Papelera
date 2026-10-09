<?php

namespace App\Http\Requests\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FailDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'failure_reason' => [
                'required',
                'string',
                Rule::in(CustomerOrder::DELIVERY_FAILURE_REASONS),
            ],
            'delivery_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'failure_reason.required' => 'Debe indicar el motivo por el cual no se pudo entregar.',
            'failure_reason.in' => 'El motivo seleccionado no es válido.',
        ];
    }
}
