<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Permission;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tenant_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::notIn(['admin', 'superadmin', 'Administrador', 'Superadmin']),
                Rule::unique('roles', 'name')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $permissions = $this->input('permissions', []);
            if (empty($permissions)) {
                return;
            }

            // El permiso deposito.gestion es exclusivo del administrador y no puede ser delegado a roles de empleados
            if (in_array('deposito.gestion', $permissions, true)) {
                $validator->errors()->add(
                    'permissions',
                    'El permiso de gestión de depósitos es exclusivo del administrador y no puede asignarse a roles de empleados.'
                );

                return;
            }

            $user = $this->user();
            $tenant = $user->tenant;
            if (! $tenant) {
                $validator->errors()->add('permissions', 'Comercio no válido.');

                return;
            }


            setPermissionsTeamId($tenant->id);

            // Validar que los permisos correspondan a módulos activos del tenant
            $activeModuleIds = $tenant->modules()
                ->where('modules.is_active', true)
                ->where(function ($query) {
                    $query->whereNull('tenant_modules.expires_at')
                        ->orWhere('tenant_modules.expires_at', '>', now());
                })
                ->pluck('modules.id')
                ->all();

            $invalidModulePermissions = Permission::whereIn('name', $permissions)
                ->where(function ($query) use ($activeModuleIds) {
                    $query->whereNotIn('module_id', $activeModuleIds)
                        ->orWhereNull('module_id');
                })
                ->pluck('name')
                ->all();

            if (! empty($invalidModulePermissions)) {
                $validator->errors()->add(
                    'permissions',
                    'Uno o más permisos seleccionados no pertenecen a los módulos activos contratados por su comercio.'
                );

                return;
            }

            // Prevención de escalabilidad de privilegios: el usuario no puede delegar permisos que no posee
            if (! $user->isSuperAdmin()) {
                $userPermissions = $user->getAllPermissions()->pluck('name')->all();
                $unheldPermissions = array_diff($permissions, $userPermissions);

                if (! empty($unheldPermissions)) {
                    $validator->errors()->add(
                        'permissions',
                        'No puedes otorgar permisos que tu propio usuario no posee.'
                    );
                }
            }
        });
    }
}
