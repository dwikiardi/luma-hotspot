<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_connections', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('hostname')->nullable();
            $table->foreignId('router_id')->constrained()->cascadeOnDelete();
            $table->string('dhcp_server')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['router_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_connections');
    }
};
