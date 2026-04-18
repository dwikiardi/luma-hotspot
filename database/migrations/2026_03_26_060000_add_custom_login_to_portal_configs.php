<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->boolean('custom_login_enabled')->default(false);
            $table->string('custom_login_label')->nullable();
            $table->string('custom_login_placeholder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->dropColumn(['custom_login_enabled', 'custom_login_label', 'custom_login_placeholder']);
        });
    }
};
