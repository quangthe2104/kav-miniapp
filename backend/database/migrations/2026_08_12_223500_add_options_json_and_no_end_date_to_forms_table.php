<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('no_end_date')->default(false)->after('ends_at');
            $table->json('options_json')->nullable()->after('scope_json');
        });

        // Custom option slugs may exceed the original varchar(20).
        DB::statement('ALTER TABLE responses MODIFY choice VARCHAR(32) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['no_end_date', 'options_json']);
        });

        DB::statement('ALTER TABLE responses MODIFY choice VARCHAR(20) NOT NULL');
    }
};
