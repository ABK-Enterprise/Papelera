<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Locality;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArgentinaGeodataController extends Controller
{
    /**
     * Get all Argentine provinces.
     */
    public function provinces(): JsonResponse
    {
        if (Province::count() === 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\ArgentinaGeodataSeeder',
                '--force' => true,
            ]);
            Cache::forget('argentina:provinces');
        }

        $provinces = Cache::rememberForever('argentina:provinces', function () {
            return Province::orderBy('name')
                ->select(['id', 'name', 'iso_code'])
                ->get();
        });

        return response()->json($provinces);
    }

    /**
     * Get departments for a given province.
     */
    public function departments(Request $request): JsonResponse
    {
        $provinceId = (string) $request->query('provincia_id');

        if (empty($provinceId)) {
            return response()->json(['error' => 'El parámetro provincia_id es requerido'], 422);
        }

        $cacheKey = "argentina:departments:{$provinceId}";
        $departments = Cache::remember($cacheKey, 86400, function () use ($provinceId) {
            return Department::where('province_id', $provinceId)
                ->orderBy('name')
                ->select(['id', 'province_id', 'name'])
                ->get();
        });

        return response()->json($departments);
    }

    /**
     * Get localities for a given department.
     */
    public function localities(Request $request): JsonResponse
    {
        $departmentId = (string) $request->query('departamento_id');
        $provinceId = (string) $request->query('provincia_id');

        if (empty($departmentId) && empty($provinceId)) {
            return response()->json(['error' => 'El parámetro departamento_id o provincia_id es requerido'], 422);
        }

        $cacheKey = ! empty($departmentId)
            ? "argentina:localities:dept:{$departmentId}"
            : "argentina:localities:prov:{$provinceId}";

        $localities = Cache::remember($cacheKey, 86400, function () use ($departmentId, $provinceId) {
            $query = Locality::query();

            if (! empty($departmentId)) {
                $query->where('department_id', $departmentId);
            } elseif (! empty($provinceId)) {
                $query->where('province_id', $provinceId);
            }

            return $query->orderBy('name')
                ->select(['id', 'department_id', 'province_id', 'name'])
                ->get();
        });

        return response()->json($localities);
    }
}
