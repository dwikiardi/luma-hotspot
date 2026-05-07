<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean duplicates first: keep only 1 latest active/disconnected per user+router
        DB::statement('
            UPDATE user_sessions SET status = \'expired\'
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id
                    FROM user_sessions
                    WHERE status IN (\'active\', \'disconnected\')
                    GROUP BY user_id, router_id
                ) AS kept
            )
            AND status IN (\'active\', \'disconnected\')
        ');

        // Add unique partial index: only 1 active/disconnected per user+router
        DB::statement('
            CREATE UNIQUE INDEX user_sessions_user_router_status_unique
            ON user_sessions (user_id, router_id)
            WHERE status IN (\'active\', \'disconnected\')
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_sessions_user_router_status_unique');
    }
};
