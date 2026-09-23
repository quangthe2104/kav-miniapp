<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\School;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ThanhHoaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $wardsPath = database_path('data/wards_thanh_hoa.json');
        $schoolsPath = database_path('data/schools_thanh_hoa.json');

        $wardPayload = json_decode(file_get_contents($wardsPath), true, 512, JSON_THROW_ON_ERROR);
        $schools = json_decode(file_get_contents($schoolsPath), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($wardPayload, $schools) {
            $province = Province::query()->updateOrCreate(
                ['code' => $wardPayload['province']['code']],
                ['name' => $wardPayload['province']['name']]
            );

            $wardIdByCode = [];
            foreach ($wardPayload['wards'] as $ward) {
                $model = Ward::query()->updateOrCreate(
                    [
                        'province_id' => $province->id,
                        'code' => $ward['code'],
                    ],
                    [
                        'name' => $ward['name'],
                        'level' => $ward['level'] ?? null,
                    ]
                );
                $wardIdByCode[$ward['code']] = $model->id;
            }

            foreach ($schools as $school) {
                $wardId = $wardIdByCode[$school['ward_code']] ?? null;
                if (! $wardId) {
                    continue;
                }

                School::query()->updateOrCreate(
                    ['external_id' => $school['external_id']],
                    [
                        'ward_id' => $wardId,
                        'name' => $school['name'],
                        'level' => $school['level'] ?? null,
                    ]
                );
            }
        });
    }
}
