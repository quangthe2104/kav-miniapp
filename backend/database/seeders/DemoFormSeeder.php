<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class DemoFormSeeder extends Seeder
{
    public function run(): void
    {
        Form::query()->updateOrCreate(
            ['title' => 'Đồng ý sử dụng Khan Academy'],
            [
                'content' => <<<'TXT'
Phiếu đồng ý của phụ huynh / người giám hộ về việc sử dụng các công cụ chuyển đổi số trong giáo dục (bao gồm Khan Academy) và thu thập dữ liệu học tập (giai đoạn 2026–2031).

Sau khi được nhà trường / giáo viên thông báo đầy đủ, phụ huynh lựa chọn Đồng ý hoặc Không đồng ý để nhà trường và các bên được phép thu thập, xử lý thông tin tài khoản học sinh và dữ liệu liên quan quá trình học tập, phục vụ dạy học, đánh giá, quản lý lớp và nâng cao chất lượng giáo dục.
TXT,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(6),
                'status' => 'active',
                'consent_pdf_path' => null,
                'scope_json' => ['province_codes' => ['38']],
            ]
        );

        Teacher::query()->updateOrCreate(
            ['phone' => '0900000001'],
            [
                'zalo_id' => 'dev-teacher-1',
                'name' => 'Cô Demo Thanh Hóa',
                'status' => 'active',
            ]
        );
    }
}
