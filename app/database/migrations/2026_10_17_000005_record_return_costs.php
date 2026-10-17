<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE returns ADD COLUMN cost_minor bigint CHECK(cost_minor >= 0)');
        // Existing history remains untouched; unknown historic return cost is reported as incomplete.
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve historical return costs.');
    }
};
