<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_dna', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint_hash', 64)->unique();
            $table->json('known_macs')->default('[]');
            $table->json('known_hostnames')->default('[]');
            $table->json('known_ouis')->default('[]');
            $table->json('known_vendor_classes')->default('[]');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->decimal('confidence', 5, 2)->default(0.00);
            $table->unsignedInteger('match_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_dna');
    }
};
