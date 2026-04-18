<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('ip_address')->nullable()->after('nas_identifier');
            $table->string('model')->nullable()->after('ip_address');
            $table->string('firmware_version')->nullable()->after('model');
            $table->boolean('is_active')->default(true)->after('firmware_version');
            $table->text('notes')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'model', 'firmware_version', 'is_active', 'notes']);
        });
    }
};
