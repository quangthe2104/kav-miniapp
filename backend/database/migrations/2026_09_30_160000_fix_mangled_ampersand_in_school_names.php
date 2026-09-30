<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Thanh Hóa seed data had "TH&THCS" scraped as "THamp;THCS" (the "&" of "&amp;" was lost).
     */
    public function up(): void
    {
        DB::table('schools')
            ->where('name', 'like', '%amp;%')
            ->orderBy('id')
            ->each(function (object $school): void {
                DB::table('schools')->where('id', $school->id)->update([
                    'name' => str_replace(['&amp;', 'amp;'], '&', $school->name),
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
