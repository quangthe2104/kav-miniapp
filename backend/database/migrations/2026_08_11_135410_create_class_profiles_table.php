<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->unsignedSmallInteger('quota');
            $table->foreignId('created_by_teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'class_name', 'created_by_teacher_id'], 'class_profiles_unique_teacher_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_profiles');
    }
};
