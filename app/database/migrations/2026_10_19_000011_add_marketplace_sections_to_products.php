<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE products
 ADD COLUMN audience varchar(20) NOT NULL DEFAULT 'unisex',
 ADD COLUMN trending boolean NOT NULL DEFAULT false,
 ADD COLUMN compare_at_minor bigint,
 ADD CONSTRAINT products_audience_check CHECK (audience IN ('women','men','kids','unisex')),
 ADD CONSTRAINT products_compare_at_check CHECK (compare_at_minor IS NULL OR compare_at_minor > price_minor);
CREATE INDEX products_marketplace_sections ON products(tenant_id,audience,published,trending);
CREATE INDEX products_active_promotions ON products(tenant_id,published,compare_at_minor) WHERE compare_at_minor IS NOT NULL;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve product marketplace metadata.');
    }
};
