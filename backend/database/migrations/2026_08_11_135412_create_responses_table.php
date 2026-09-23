<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_form_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20); // zalo|paper
            $table->string('choice', 20); // agree|disagree
            $table->string('zalo_user_id')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedTinyInteger('coverage_weight')->default(1);
            $table->string('paper_code')->nullable();
            $table->string('image_path')->nullable();
            $table->string('ocr_suggestion', 20)->nullable();
            $table->decimal('ocr_confidence', 5, 4)->nullable();
            $table->string('status', 20)->default('valid'); // valid|void
            $table->nullableMorphs('created_by');
            $table->timestamps();

            $table->unique(['class_form_id', 'zalo_user_id'], 'responses_class_form_zalo_unique');
            $table->index(['class_form_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
