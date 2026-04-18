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
        Schema::create('portal_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->jsonb('active_login_methods')->default(json_encode([
                'google' => true,
                'wa' => true,
                'email' => false,
                'room' => false,
                'promo' => false,
            ]));
            $table->jsonb('branding')->default(json_encode([
                'name' => 'Guest WiFi',
                'color' => '#6366f1',
                'logo' => null,
            ]));
            $table->integer('grace_period_seconds')->default(7200);
            $table->boolean('grace_period_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_configs');
    }
};
