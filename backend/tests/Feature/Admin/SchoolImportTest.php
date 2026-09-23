<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Province;
use App\Models\School;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_redirects_to_schools(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.catalog.index'))
            ->assertRedirect(route('admin.schools.index'));
    }

    public function test_schools_index_filters_and_import_upsert(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $province = Province::query()->create(['code' => '38', 'name' => 'Thanh Hóa']);
        $ward = Ward::query()->create([
            'province_id' => $province->id,
            'code' => '14812',
            'name' => 'Phường Bỉm Sơn',
        ]);
        School::query()->create([
            'ward_id' => $ward->id,
            'external_id' => '38381402',
            'name' => 'Trường Cũ',
            'level' => 'tieu_hoc',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.schools.index', ['province_id' => $province->id, 'q' => 'Cũ']))
            ->assertOk()
            ->assertSee('Trường Cũ')
            ->assertSee('Danh sách Trường');

        $csv = implode("\n", [
            'external_id,name,ward_code,level,province_code',
            '38381402,Trường Tiểu học Hà Lan,14812,tieu_hoc,38',
            '99999001,Trường Mới Import,14812,thcs,38',
        ]);
        $file = UploadedFile::fake()->createWithContent('schools.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.schools.import.upload'), ['file' => $file])
            ->assertRedirect(route('admin.schools.import.map'));

        $this->actingAs($admin)
            ->post(route('admin.schools.import.validate'), [
                'map' => [
                    'external_id' => 0,
                    'name' => 1,
                    'ward_code' => 2,
                    'ward_name' => '',
                    'level' => 3,
                    'province_code' => 4,
                ],
            ])
            ->assertRedirect(route('admin.schools.import.confirm'));

        $this->actingAs($admin)
            ->post(route('admin.schools.import.commit'))
            ->assertRedirect(route('admin.schools.index'));

        $this->assertDatabaseHas('schools', [
            'external_id' => '38381402',
            'name' => 'Trường Tiểu học Hà Lan',
        ]);
        $this->assertDatabaseHas('schools', [
            'external_id' => '99999001',
            'name' => 'Trường Mới Import',
            'level' => 'thcs',
        ]);
        $this->assertSame(2, School::query()->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.schools.import',
        ]);
        $log = AuditLog::query()->where('action', 'admin.schools.import')->first();
        $this->assertNotNull($log);
        $this->assertSame(1, (int) ($log->payload['added'] ?? 0));
        $this->assertSame(1, (int) ($log->payload['updated'] ?? 0));
    }
}
