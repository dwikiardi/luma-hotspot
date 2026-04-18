<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE radacct ALTER COLUMN nasipaddress TYPE varchar(45)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN connectinfo_start TYPE varchar(128)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN connectinfo_stop TYPE varchar(128)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN calledstationid TYPE varchar(128)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN callingstationid TYPE varchar(128)');
        DB::statement('ALTER TABLE radpostauth ALTER COLUMN reply TYPE varchar(64)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE radpostauth ALTER COLUMN reply TYPE varchar(32)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN callingstationid TYPE varchar(50)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN calledstationid TYPE varchar(50)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN connectinfo_stop TYPE varchar(50)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN connectinfo_start TYPE varchar(50)');
        DB::statement('ALTER TABLE radacct ALTER COLUMN nasipaddress TYPE varchar(15)');
    }
};