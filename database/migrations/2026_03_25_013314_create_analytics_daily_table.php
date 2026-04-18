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
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('router_id')->constrained();
            $table->date('date');
            $table->integer('unique_visitors')->default(0);
            $table->integer('total_sessions')->default(0);
            $table->integer('new_visitors')->default(0);
            $table->integer('returning_visitors')->default(0);
            $table->integer('auto_reconnects')->default(0);
            $table->integer('forced_relogins')->default(0);
            $table->decimal('reconnect_rate', 5, 2)->default(0);
            $table->integer('login_google')->default(0);
            $table->integer('login_wa')->default(0);
            $table->integer('login_room')->default(0);
            $table->integer('login_email')->default(0);
            $table->integer('avg_session_minutes')->default(0);
            $table->integer('peak_hour')->nullable();
            $table->unique(['tenant_id', 'router_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_daily');
    }
};
