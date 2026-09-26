<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->tenant_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'cuit_tax_id' => ['nullable', 'string', 'max:20', 'regex:/^(?:\d{2}-?\d{8}-?\d{1}|\d{11})$/'],
            'tax_condition' => ['required', 'string', 'in:responsable_inscripto,monotributo,exento,consumidor_final'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'gross_income_number' => ['nullable', 'string', 'max:50'],
            'start_activity_date' => ['nullable', 'date'],
            'point_of_sale' => ['nullable', 'integer', 'min:1', 'max:99999'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del comercio es obligatorio.',
            'cuit_tax_id.regex' => 'El CUIT debe tener un formato válido (ej. 30-12345678-9 o 30123456789).',
            'tax_condition.required' => 'La condición frente al IVA es obligatoria.',
            'tax_condition.in' => 'La condición frente al IVA seleccionada no es válida.',
            'email.email' => 'El correo electrónico debe tener un formato válido.',
            'start_activity_date.date' => 'La fecha de inicio de actividades no tiene un formato de fecha válido.',
            'point_of_sale.integer' => 'El punto de venta debe ser un número entero.',
            'point_of_sale.min' => 'El punto de venta debe ser como mínimo 1.',
            'point_of_sale.max' => 'El punto de venta no puede superar 99999.',
        ];
    }
}
