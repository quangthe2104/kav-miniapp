<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paper_upload_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_upload_batch_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('ocr_suggestion', 20)->nullable(); // agree|disagree|unknown
            $table->decimal('ocr_confidence', 5, 4)->nullable();
            $table->string('confirmed_choice', 20)->nullable();
            $table->string('status', 20)->default('pending'); // pending|confirmed|skipped
            $table->foreignId('response_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_upload_items');
    }
};
