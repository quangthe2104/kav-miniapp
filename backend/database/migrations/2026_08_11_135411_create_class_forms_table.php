<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->string('invite_token_hash', 64)->unique();
            $table->string('status', 20)->default('open'); // open|quota_full|closed
            $table->timestamps();

            $table->unique(['class_profile_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_forms');
    }
};
