<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE tenants ADD COLUMN custom_domain varchar(253);
CREATE UNIQUE INDEX tenants_custom_domain_unique ON tenants(lower(custom_domain)) WHERE custom_domain IS NOT NULL;

ALTER TABLE orders DROP CONSTRAINT orders_delivery_method_check;
ALTER TABLE orders ADD CONSTRAINT orders_delivery_method_check
 CHECK(delivery_method IN ('pickup','delivery','courier','shipping'));
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve custom domains and delivery history.');
    }
};
