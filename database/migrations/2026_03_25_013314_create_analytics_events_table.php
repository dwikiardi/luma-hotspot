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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('router_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->foreignId('device_id')->nullable()->constrained();
            $table->enum('event_type', [
                'portal_opened',
                'login_success',
                'login_failed',
                'auto_reconnect',
                'forced_relogin',
                'session_start',
                'session_end',
                'portal_impression',
            ]);
            $table->string('mac_address')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('login_method')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
