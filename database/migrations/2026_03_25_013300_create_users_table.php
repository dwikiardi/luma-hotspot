<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identity_value');
            $table->enum('identity_type', ['google', 'wa', 'email', 'room']);
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
            $table->unique(['identity_value', 'identity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
