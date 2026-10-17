<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE tenants (
 id bigserial PRIMARY KEY, slug varchar(80) NOT NULL UNIQUE,
 name varchar(160) NOT NULL, active boolean NOT NULL DEFAULT true,
 created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE memberships (
 tenant_id bigint NOT NULL REFERENCES tenants(id),
 user_id bigint NOT NULL REFERENCES users(id),
 role varchar(20) NOT NULL CHECK (role IN ('owner','cashier','warehouse')),
 PRIMARY KEY (tenant_id,user_id)
);
CREATE TABLE products (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 sku varchar(64) NOT NULL, name varchar(160) NOT NULL,
 price_minor bigint NOT NULL CHECK(price_minor >= 0),
 published boolean NOT NULL DEFAULT false,
 created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,sku), UNIQUE(tenant_id,id)
);
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE products FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_products ON products
 USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)
 WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE products; DROP TABLE memberships; DROP TABLE tenants;');
    }
};
