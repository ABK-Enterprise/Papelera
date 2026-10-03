<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantPriceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tenant_id;
    }

    public function rules(): array
    {
        $isPercentage = $this->input('calculation_type') === 'percentage';

        return [
            'name' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'in:discount,surcharge'],
            'calculation_type' => ['required', 'in:fixed,percentage'],
            'value' => [
                'required',
                'numeric',
                'min:0.01',
                $isPercentage ? 'max:100' : 'max:100000000',
            ],
            'reason' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de ajuste es obligatorio (descuento o recargo).',
            'calculation_type.required' => 'El tipo de cálculo es obligatorio (fijo o porcentual).',
            'value.required' => 'El valor numérico es obligatorio.',
            'value.min' => 'El valor debe ser estrictamente positivo mayor a 0.',
            'value.max' => $this->input('calculation_type') === 'percentage'
                ? 'Para valores porcentuales, el valor máximo es 100%.'
                : 'El monto fijo no puede exceder $100.000.000.',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty(trim($this->input('name') ?? ''))) {
            $this->merge(['name' => 'Sin Nombre']);
        }
    }
}
