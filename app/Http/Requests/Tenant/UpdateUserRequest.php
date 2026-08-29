<?php

namespace App\Http\Requests\Tenant;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || $user->tenant_id === null) {
            return false;
        }

        $targetUser = $this->route('user');
        if ($targetUser instanceof User && (int) $targetUser->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($targetUser instanceof User) {
            setPermissionsTeamId($user->tenant_id);
            if ($targetUser->hasRole('admin') || $targetUser->is_superadmin || (int) $targetUser->id === (int) $user->id) {
                abort(403, 'No se puede modificar una cuenta de administrador desde la gestión de empleados.');
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $targetUser = $this->route('user');
        $userId = is_object($targetUser) ? $targetUser->id : $targetUser;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'role' => [
                'required',
                'string',
                Rule::notIn(['admin', 'superadmin', 'Administrador', 'Superadmin']),
                Rule::exists('roles', 'name')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
        ];
    }
}
