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
        Schema::create('visitor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->integer('total_visits')->default(1);
            $table->integer('total_sessions')->default(1);
            $table->timestamp('first_visit_at');
            $table->timestamp('last_visit_at');
            $table->integer('avg_session_minutes')->default(0);
            $table->string('preferred_login_method')->nullable();
            $table->enum('visitor_type', ['new', 'returning', 'regular', 'loyal'])
                ->default('new');
            $table->unique(['tenant_id', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_profiles');
    }
};
