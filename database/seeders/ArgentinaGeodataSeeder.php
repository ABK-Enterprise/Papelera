<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Locality;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ArgentinaGeodataSeeder extends Seeder
{
    /**
     * Run the geodata seeder.
     */
    public function run(): void
    {
        $jsonPath = database_path('data/argentina_geodata.json');

        if (! File::exists($jsonPath)) {
            $this->command?->error("Geodata file not found at: {$jsonPath}");

            return;
        }

        $data = json_decode(File::get($jsonPath), true);
        if (! $data) {
            $this->command?->error('Failed to parse json geodata file');

            return;
        }

        DB::transaction(function () use ($data) {
            $now = now();

            // 1. Provinces
            if (! empty($data['provinces'])) {
                $provinces = array_map(function ($p) use ($now) {
                    return [
                        'id' => $p['id'],
                        'name' => $p['name'],
                        'iso_code' => $p['iso_code'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $data['provinces']);

                Province::upsert($provinces, ['id'], ['name', 'iso_code', 'updated_at']);
            }

            // 2. Departments
            if (! empty($data['departments'])) {
                $departments = array_map(function ($d) use ($now) {
                    return [
                        'id' => $d['id'],
                        'province_id' => $d['province_id'],
                        'name' => $d['name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $data['departments']);

                foreach (array_chunk($departments, 500) as $chunk) {
                    Department::upsert($chunk, ['id'], ['province_id', 'name', 'updated_at']);
                }
            }

            // 3. Localities
            if (! empty($data['localities'])) {
                $localities = array_map(function ($loc) use ($now) {
                    return [
                        'id' => $loc['id'],
                        'department_id' => $loc['department_id'],
                        'province_id' => $loc['province_id'],
                        'name' => $loc['name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $data['localities']);

                foreach (array_chunk($localities, 500) as $chunk) {
                    Locality::upsert($chunk, ['id'], ['department_id', 'province_id', 'name', 'updated_at']);
                }
            }
        });

        Cache::forget('argentina:provinces');
    }
}
