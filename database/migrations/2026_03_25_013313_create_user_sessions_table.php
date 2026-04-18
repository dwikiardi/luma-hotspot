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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('mac_address');
            $table->string('fingerprint_hash')->nullable();
            $table->string('cookie_token', 64)->nullable()->unique();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('login_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('expires_at');
            $table->enum('status', ['active', 'disconnected', 'expired', 'logged_out'])
                ->default('active');
            $table->string('nas_id');
            $table->string('login_method')->nullable();
            $table->string('user_agent')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->index(['mac_address', 'status']);
            $table->index(['fingerprint_hash', 'status']);
            $table->index(['disconnected_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
