<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_coverage_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_profile_id')->constrained()->cascadeOnDelete();
            $table->string('phone_or_zalo_ref');
            $table->unsignedTinyInteger('children_count');
            $table->text('note_text')->nullable();
            $table->foreignId('created_by_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_profile_id', 'phone_or_zalo_ref'], 'parent_notes_unique_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_coverage_notes');
    }
};
