<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE products ADD COLUMN variant_group varchar(100) NOT NULL DEFAULT '';
ALTER TABLE products ADD COLUMN image_path varchar(250);
ALTER TABLE products ADD CONSTRAINT bounded_cost_pool CHECK(cost_units <= 1000000000 AND cost_value_minor <= 1000000000000000);
CREATE TABLE plans (
 slug varchar(30) PRIMARY KEY, name varchar(100) NOT NULL,
 product_limit integer NOT NULL CHECK(product_limit > 0), warehouse_limit integer NOT NULL CHECK(warehouse_limit > 0),
 user_limit integer NOT NULL CHECK(user_limit > 0)
);
INSERT INTO plans VALUES ('inicial','Inicial',500,2,5),('negocio','Negocio',5000,10,25),('empresa','Empresa',50000,100,250);
ALTER TABLE tenants ADD CONSTRAINT tenant_plan FOREIGN KEY(plan) REFERENCES plans(slug);
CREATE TABLE invitations (
 id uuid PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), email varchar(254) NOT NULL,
 role varchar(20) NOT NULL CHECK(role IN ('owner','cashier','warehouse')), token_hash varchar(64) UNIQUE NOT NULL,
 expires_at timestamptz NOT NULL, accepted_at timestamptz, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE offline_devices (
 id uuid PRIMARY KEY, tenant_id bigint NOT NULL, user_id bigint NOT NULL REFERENCES users(id),
 warehouse_id bigint NOT NULL, cash_session_id bigint NOT NULL,
 name varchar(100) NOT NULL, expires_at timestamptz NOT NULL, revoked_at timestamptz, reconciled_at timestamptz,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,id),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
CREATE TABLE offline_allocations (
 tenant_id bigint NOT NULL, device_id uuid NOT NULL, product_id bigint NOT NULL,
 quantity integer NOT NULL CHECK(quantity > 0), consumed integer NOT NULL DEFAULT 0 CHECK(consumed >= 0 AND consumed <= quantity),
 unit_minor bigint NOT NULL CHECK(unit_minor >= 0), name varchar(160) NOT NULL, sku varchar(64) NOT NULL,
 PRIMARY KEY(tenant_id,device_id,product_id),
 FOREIGN KEY(tenant_id,device_id) REFERENCES offline_devices(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE offline_sales (
 tenant_id bigint NOT NULL, device_id uuid NOT NULL, operation_id uuid NOT NULL,
 order_id uuid NOT NULL, sequence integer NOT NULL CHECK(sequence > 0), captured_at varchar(50) NOT NULL,
 PRIMARY KEY(tenant_id,device_id,operation_id), UNIQUE(tenant_id,device_id,sequence),
 FOREIGN KEY(tenant_id,device_id) REFERENCES offline_devices(tenant_id,id),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id)
);
SQL);
        foreach (['offline_devices', 'offline_allocations', 'offline_sales'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Conserve asignaciones y ventas offline; use migraciones compensatorias.');
    }
};
