<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_macs TYPE jsonb USING known_macs::jsonb');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_hostnames TYPE jsonb USING known_hostnames::jsonb');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_ouis TYPE jsonb USING known_ouis::jsonb');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_vendor_classes TYPE jsonb USING known_vendor_classes::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_macs TYPE json USING known_macs::json');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_hostnames TYPE json USING known_hostnames::json');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_ouis TYPE json USING known_ouis::json');
        DB::statement('ALTER TABLE device_dna ALTER COLUMN known_vendor_classes TYPE json USING known_vendor_classes::json');
    }
};
