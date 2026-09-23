<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miniapp_users', function (Blueprint $table) {
            $table->id();
            $table->string('zalo_user_id', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('name')->nullable();
            $table->string('role', 20); // teacher|parent
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miniapp_users');
    }
};
