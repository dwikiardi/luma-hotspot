<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_fingerprints', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('confidence', 20)->default('low')->after('trust_score');
            $table->boolean('is_known_device')->default(false)->after('confidence');
            $table->unsignedInteger('match_count')->default(0)->after('risk_factors');
        });
    }

    public function down(): void
    {
        Schema::table('device_fingerprints', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['device_id']);
            $table->dropColumn(['user_id', 'device_id', 'confidence', 'is_known_device', 'match_count']);
        });
    }
};