<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_upload_batches', function (Blueprint $table) {
            $table->text('pending_note_body')->nullable()->after('unknown_count');
            $table->json('pending_note_files')->nullable()->after('pending_note_body');
        });
    }

    public function down(): void
    {
        Schema::table('paper_upload_batches', function (Blueprint $table) {
            $table->dropColumn(['pending_note_body', 'pending_note_files']);
        });
    }
};
