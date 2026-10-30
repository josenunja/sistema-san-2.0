<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE price_rules (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), name varchar(120) NOT NULL,
 product_id bigint, category varchar(100), percent integer NOT NULL CHECK(percent BETWEEN 1 AND 100),
 channel varchar(10) CHECK(channel IN ('web','pos')), starts_at timestamptz NOT NULL,
 ends_at timestamptz NOT NULL, enabled boolean NOT NULL DEFAULT true,
 created_at timestamptz NOT NULL DEFAULT now(), CHECK(ends_at > starts_at),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE delivery_zones (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), name varchar(120) NOT NULL,
 method varchar(16) NOT NULL CHECK(method IN ('pickup','delivery','courier','shipping')),
 address varchar(300) NOT NULL DEFAULT '', fee_minor bigint NOT NULL CHECK(fee_minor BETWEEN 0 AND 999999999),
 enabled boolean NOT NULL DEFAULT true, UNIQUE(tenant_id,id), UNIQUE(tenant_id,method,name)
);
CREATE TABLE delivery_slots (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 method varchar(16) NOT NULL CHECK(method IN ('pickup','delivery','courier','shipping')),
 starts_at timestamptz NOT NULL, ends_at timestamptz NOT NULL, capacity integer NOT NULL CHECK(capacity BETWEEN 1 AND 10000),
 enabled boolean NOT NULL DEFAULT true, CHECK(ends_at > starts_at), UNIQUE(tenant_id,id)
);
ALTER TABLE orders ADD COLUMN delivery_zone_id bigint;
ALTER TABLE orders ADD COLUMN delivery_slot_id bigint;
ALTER TABLE orders ADD COLUMN delivery_label varchar(500) NOT NULL DEFAULT '';
ALTER TABLE orders ADD CONSTRAINT orders_zone_fk FOREIGN KEY(tenant_id,delivery_zone_id) REFERENCES delivery_zones(tenant_id,id);
ALTER TABLE orders ADD CONSTRAINT orders_slot_fk FOREIGN KEY(tenant_id,delivery_slot_id) REFERENCES delivery_slots(tenant_id,id);
SQL);
        foreach (['price_rules', 'delivery_zones', 'delivery_slots'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar referencias históricas de precios y entregas.');
    }
};
