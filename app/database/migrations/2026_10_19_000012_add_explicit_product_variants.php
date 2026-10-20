<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE products
 ADD COLUMN size varchar(40) NOT NULL DEFAULT '',
 ADD COLUMN color varchar(60) NOT NULL DEFAULT '';
CREATE INDEX products_variant_options ON products(tenant_id,variant_group,size,color);
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve product variant attributes.');
    }
};
