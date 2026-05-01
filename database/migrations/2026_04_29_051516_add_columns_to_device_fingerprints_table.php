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
            $table->string('fingerprint_hash', 64)->unique()->after('device_id');
            $table->string('visitor_id', 64)->nullable()->after('fingerprint_hash');
            $table->string('canvas_hash', 64)->nullable()->after('visitor_id');
            $table->string('webgl_hash', 64)->nullable()->after('canvas_hash');
            $table->string('webgl_vendor')->nullable()->after('webgl_hash');
            $table->string('webgl_renderer')->nullable()->after('webgl_vendor');
            $table->string('fonts_hash', 64)->nullable()->after('webgl_renderer');
            $table->string('audio_hash', 64)->nullable()->after('fonts_hash');
            $table->string('screen_resolution')->nullable()->after('audio_hash');
            $table->unsignedSmallInteger('color_depth')->nullable()->after('screen_resolution');
            $table->unsignedSmallInteger('device_memory')->nullable()->after('color_depth');
            $table->unsignedSmallInteger('hardware_concurrency')->nullable()->after('device_memory');
            $table->string('timezone')->nullable()->after('hardware_concurrency');
            $table->string('languages')->nullable()->after('timezone');
            $table->boolean('touch_support')->nullable()->after('languages');
            $table->string('platform')->nullable()->after('touch_support');
            $table->string('os_name')->nullable()->after('platform');
            $table->string('os_version')->nullable()->after('os_name');
            $table->string('browser_name')->nullable()->after('os_version');
            $table->string('browser_version')->nullable()->after('browser_name');
            $table->string('user_agent')->nullable()->after('browser_version');
            $table->string('ip_address', 45)->nullable()->after('user_agent');
            $table->string('nas_id')->nullable()->after('ip_address');
            $table->string('mac_address')->nullable()->after('nas_id');
            $table->unsignedTinyInteger('trust_score')->default(0)->after('mac_address');
            $table->string('confidence', 20)->default('low')->after('trust_score');
            $table->boolean('is_known_device')->default(false)->after('confidence');
            $table->json('risk_factors')->nullable()->after('is_known_device');
            $table->unsignedInteger('match_count')->default(0)->after('risk_factors');

            $table->index('fingerprint_hash');
            $table->index('user_id');
            $table->index('nas_id');
        });
    }

    public function down(): void
    {
        Schema::table('device_fingerprints', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['device_id']);
            $table->dropColumn([
                'user_id', 'device_id', 'fingerprint_hash', 'visitor_id',
                'canvas_hash', 'webgl_hash', 'webgl_vendor', 'webgl_renderer',
                'fonts_hash', 'audio_hash', 'screen_resolution', 'color_depth',
                'device_memory', 'hardware_concurrency', 'timezone', 'languages',
                'touch_support', 'platform', 'os_name', 'os_version',
                'browser_name', 'browser_version', 'user_agent', 'ip_address',
                'nas_id', 'mac_address', 'trust_score', 'confidence',
                'is_known_device', 'risk_factors', 'match_count',
            ]);
        });
    }
};