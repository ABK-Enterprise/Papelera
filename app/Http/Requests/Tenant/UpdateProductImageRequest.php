<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->tenant_id !== null;
    }

    /**
     * Prepare the data for validation, decoding JSON string if sent via FormData.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('image_position') && is_string($this->input('image_position'))) {
            $decoded = json_decode($this->input('image_position'), true);
            if (is_array($decoded)) {
                $this->merge(['image_position' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,webp,jpg',
                'max:15360', // Max 15MB
            ],
            'image_position' => ['nullable', 'array'],
            'image_position.zoom' => ['nullable', 'numeric', 'min:1.0', 'max:5.0'],
            'image_position.x' => ['nullable', 'numeric', 'min:-100.0', 'max:100.0'],
            'image_position.y' => ['nullable', 'numeric', 'min:-100.0', 'max:100.0'],
            'remove_image' => ['nullable', 'boolean'],
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
            'image.file' => 'El archivo subido no es válido.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'El formato debe ser JPG, JPEG, PNG o WebP.',
            'image.max' => 'La imagen no debe superar los 15 MB.',
            'image_position.zoom.min' => 'El zoom no puede ser menor a 1.0.',
            'image_position.zoom.max' => 'El zoom no puede ser mayor a 5.0.',
        ];
    }
}
