<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_forms', function (Blueprint $table) {
            $table->string('invite_token', 64)->nullable()->unique()->after('invite_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('class_forms', function (Blueprint $table) {
            $table->dropUnique(['invite_token']);
            $table->dropColumn('invite_token');
        });
    }
};
