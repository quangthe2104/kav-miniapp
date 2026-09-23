<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_forms', function (Blueprint $table) {
            $table->text('teacher_note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('class_forms', function (Blueprint $table) {
            $table->dropColumn('teacher_note');
        });
    }
};
