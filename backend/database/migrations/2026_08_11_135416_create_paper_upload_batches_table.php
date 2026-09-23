<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('processing'); // processing|ready|confirmed|cancelled
            $table->unsignedInteger('agree_count')->default(0);
            $table->unsignedInteger('disagree_count')->default(0);
            $table->unsignedInteger('unknown_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_upload_batches');
    }
};
