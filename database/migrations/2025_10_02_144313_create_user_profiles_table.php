<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 100)->nullable();
            $table->string('bio', 160)->nullable();
            $table->string('avatar_url', 512)->nullable();
            $table->string('cover_url', 512)->nullable();
            $table->date('birthdate')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('gender', ['male','female'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
