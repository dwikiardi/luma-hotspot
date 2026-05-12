<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dhcp_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address', 17)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('vendor_class_id', 255)->nullable();
            $table->string('parameter_request_list', 500)->nullable();
            $table->string('client_id', 255)->nullable();
            $table->string('subnet_mask', 15)->nullable();
            $table->string('gateway', 45)->nullable();
            $table->string('dns_server', 45)->nullable();
            $table->string('fingerprint_hash', 64)->nullable()->index();
            $table->string('dhcp_server', 255)->nullable();
            $table->foreignId('router_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();

            $table->index(['mac_address', 'detected_at']);
            $table->index(['fingerprint_hash', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dhcp_fingerprints');
    }
};
