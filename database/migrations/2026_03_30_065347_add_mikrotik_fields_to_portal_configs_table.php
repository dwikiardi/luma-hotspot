<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->string('hotspot_profile_name')->default('luma-portal')->after('custom_login_placeholder');
            $table->string('address_pool_name')->default('hotspot-pool')->after('hotspot_profile_name');
            $table->string('dns_name')->default('portal.lumanetwork.id')->after('address_pool_name');
            $table->integer('session_timeout')->default(14400)->after('dns_name');
            $table->integer('idle_timeout')->default(1800)->after('session_timeout');
            $table->integer('shared_users')->default(3)->after('idle_timeout');
        });
    }

    public function down(): void
    {
        Schema::table('portal_configs', function (Blueprint $table) {
            $table->dropColumn([
                'hotspot_profile_name',
                'address_pool_name',
                'dns_name',
                'session_timeout',
                'idle_timeout',
                'shared_users',
            ]);
        });
    }
};
