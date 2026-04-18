<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->boolean('room_validation_enabled')->default(false);
            $table->string('room_validation_mode')->default('range'); // 'range', 'list', 'pattern'
            $table->jsonb('room_validation_config')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->dropColumn(['room_validation_enabled', 'room_validation_mode', 'room_validation_config']);
        });
    }
};
