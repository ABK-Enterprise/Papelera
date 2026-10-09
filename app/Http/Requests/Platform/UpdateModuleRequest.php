<?php

namespace App\Http\Requests\Platform;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $module = $this->route('module');
        $moduleId = $module instanceof Module ? $module->id : $module;

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', Rule::unique('modules', 'code')->ignore($moduleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }
}
