<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantLogoRequest extends FormRequest
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
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,webp,jpg',
                'max:5120', // Max 5MB
            ],
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
            'logo.required' => 'Debe seleccionar un archivo de imagen.',
            'logo.file' => 'El recurso subido debe ser un archivo.',
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'El formato debe ser JPG, PNG o WebP.',
            'logo.max' => 'La imagen no puede superar los 5 MB.',
        ];
    }
}
