<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class VoidReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Debes indicar el motivo de anulación del recibo.',
            'reason.min' => 'El motivo de anulación debe tener al menos 3 caracteres.',
        ];
    }
}
