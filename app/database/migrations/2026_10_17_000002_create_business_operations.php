<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE tenants ADD COLUMN settings jsonb NOT NULL DEFAULT '{}';
ALTER TABLE tenants ADD COLUMN plan varchar(30) NOT NULL DEFAULT 'inicial';
ALTER TABLE users ADD COLUMN platform_owner boolean NOT NULL DEFAULT false;
ALTER TABLE products ADD COLUMN category varchar(100) NOT NULL DEFAULT '';
ALTER TABLE products ADD COLUMN description text NOT NULL DEFAULT '';
ALTER TABLE products ADD COLUMN cost_units bigint NOT NULL DEFAULT 0 CHECK(cost_units >= 0);
ALTER TABLE products ADD COLUMN cost_value_minor bigint NOT NULL DEFAULT 0 CHECK(cost_value_minor >= 0);
ALTER TABLE products ADD COLUMN cost_complete boolean NOT NULL DEFAULT true;
CREATE TABLE warehouses (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 name varchar(120) NOT NULL, branch varchar(120) NOT NULL,
 UNIQUE(tenant_id,id), UNIQUE(tenant_id,name)
);
CREATE TABLE stock_positions (
 tenant_id bigint NOT NULL, warehouse_id bigint NOT NULL, product_id bigint NOT NULL,
 physical bigint NOT NULL DEFAULT 0, reserved bigint NOT NULL DEFAULT 0,
 blocked bigint NOT NULL DEFAULT 0, offline bigint NOT NULL DEFAULT 0,
 minimum bigint NOT NULL DEFAULT 0 CHECK(minimum >= 0),
 PRIMARY KEY(tenant_id,warehouse_id,product_id),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id),
 CHECK(physical >= 0 AND reserved >= 0 AND blocked >= 0 AND offline >= 0),
 CHECK(physical >= reserved + blocked + offline)
);
CREATE TABLE customers (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 name varchar(160) NOT NULL, phone varchar(30) NOT NULL DEFAULT '',
 email varchar(254) NOT NULL DEFAULT '', address text NOT NULL DEFAULT '',
 marketing_consent boolean NOT NULL DEFAULT false, created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id)
);
CREATE TABLE cash_sessions (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, warehouse_id bigint NOT NULL,
 user_id bigint NOT NULL REFERENCES users(id), opening_minor bigint NOT NULL CHECK(opening_minor >= 0),
 counted_minor bigint CHECK(counted_minor >= 0), opened_at timestamptz NOT NULL DEFAULT now(), closed_at timestamptz,
 UNIQUE(tenant_id,id), FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id)
);
CREATE UNIQUE INDEX one_open_cash_per_user ON cash_sessions(tenant_id,user_id) WHERE closed_at IS NULL;
CREATE TABLE coupons (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), code varchar(40) NOT NULL,
 percent integer NOT NULL CHECK(percent > 0 AND percent <= 100), minimum_minor bigint NOT NULL DEFAULT 0 CHECK(minimum_minor >= 0),
 usage_limit integer NOT NULL CHECK(usage_limit > 0), used integer NOT NULL DEFAULT 0 CHECK(used >= 0 AND used <= usage_limit),
 starts_at timestamptz NOT NULL, ends_at timestamptz NOT NULL CHECK(ends_at > starts_at),
 UNIQUE(tenant_id,id), UNIQUE(tenant_id,code)
);
CREATE TABLE orders (
 id uuid PRIMARY KEY, tenant_id bigint NOT NULL, warehouse_id bigint NOT NULL, customer_id bigint,
 channel varchar(10) NOT NULL CHECK(channel IN ('web','pos','offline')),
 status varchar(15) NOT NULL CHECK(status IN ('reserved','confirmed','cancelled','expired')),
 delivery_status varchar(15) NOT NULL DEFAULT 'pending' CHECK(delivery_status IN ('pending','preparing','ready','delivered')),
 delivery_method varchar(10) NOT NULL CHECK(delivery_method IN ('pickup','delivery')),
 customer_name varchar(160) NOT NULL, phone varchar(30) NOT NULL, email varchar(254) NOT NULL DEFAULT '', address text NOT NULL DEFAULT '',
 subtotal_minor bigint NOT NULL CHECK(subtotal_minor >= 0), discount_minor bigint NOT NULL CHECK(discount_minor >= 0),
 delivery_minor bigint NOT NULL DEFAULT 0 CHECK(delivery_minor >= 0), total_minor bigint NOT NULL CHECK(total_minor >= 0),
 returned_minor bigint NOT NULL DEFAULT 0 CHECK(returned_minor >= 0 AND returned_minor <= total_minor),
 coupon_id bigint, tracking_hash varchar(64) NOT NULL,
 expires_at timestamptz NOT NULL, created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id), FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,customer_id) REFERENCES customers(tenant_id,id),
 FOREIGN KEY(tenant_id,coupon_id) REFERENCES coupons(tenant_id,id)
);
CREATE TABLE order_lines (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_id uuid NOT NULL, product_id bigint NOT NULL,
 sku varchar(64) NOT NULL, name varchar(160) NOT NULL, quantity integer NOT NULL CHECK(quantity > 0),
 returned integer NOT NULL DEFAULT 0 CHECK(returned >= 0 AND returned <= quantity),
 unit_minor bigint NOT NULL CHECK(unit_minor >= 0), discount_minor bigint NOT NULL DEFAULT 0 CHECK(discount_minor >= 0),
 total_minor bigint NOT NULL CHECK(total_minor >= 0), cost_minor bigint CHECK(cost_minor >= 0),
 UNIQUE(tenant_id,id), UNIQUE(tenant_id,order_id,product_id),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE sales (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_id uuid NOT NULL,
 confirmed_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,id), UNIQUE(tenant_id,order_id),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id)
);
CREATE TABLE payments (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_id uuid NOT NULL, cash_session_id bigint,
 method varchar(12) NOT NULL CHECK(method IN ('cash','yape','bcp')),
 status varchar(10) NOT NULL CHECK(status IN ('pending','verified','rejected')),
 amount_minor bigint NOT NULL CHECK(amount_minor > 0), reference varchar(120), proof_path text,
 created_at timestamptz NOT NULL DEFAULT now(), verified_at timestamptz,
 UNIQUE(tenant_id,id), FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id),
 FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
CREATE UNIQUE INDEX payment_reference_unique ON payments(tenant_id,method,reference) WHERE reference IS NOT NULL AND status != 'rejected';
CREATE TABLE cash_entries (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, cash_session_id bigint NOT NULL,
 amount_minor bigint NOT NULL CHECK(amount_minor != 0), reason varchar(300) NOT NULL,
 created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
CREATE TABLE stock_movements (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, warehouse_id bigint NOT NULL, product_id bigint NOT NULL,
 quantity bigint NOT NULL, reserved_delta bigint NOT NULL DEFAULT 0, blocked_delta bigint NOT NULL DEFAULT 0,
 offline_delta bigint NOT NULL DEFAULT 0, kind varchar(30) NOT NULL, reason varchar(300) NOT NULL,
 actor_id bigint REFERENCES users(id), reference varchar(100), created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE receipts (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, warehouse_id bigint NOT NULL, product_id bigint NOT NULL,
 supplier varchar(160) NOT NULL, reference varchar(120) NOT NULL, quantity integer NOT NULL CHECK(quantity > 0),
 unit_cost_minor bigint NOT NULL CHECK(unit_cost_minor >= 0), actor_id bigint REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE transfers (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, source_id bigint NOT NULL, destination_id bigint NOT NULL,
 product_id bigint NOT NULL, quantity integer NOT NULL CHECK(quantity > 0), received integer NOT NULL DEFAULT 0,
 damaged integer NOT NULL DEFAULT 0, created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id), CHECK(source_id != destination_id), CHECK(received >= 0 AND damaged >= 0 AND received + damaged <= quantity),
 FOREIGN KEY(tenant_id,source_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,destination_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE returns (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_line_id bigint NOT NULL,
 quantity integer NOT NULL CHECK(quantity > 0), condition varchar(10) NOT NULL CHECK(condition IN ('available','damaged')),
 amount_minor bigint NOT NULL CHECK(amount_minor >= 0), reason varchar(300) NOT NULL, created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(tenant_id,order_line_id) REFERENCES order_lines(tenant_id,id)
);
CREATE TABLE refunds (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_id uuid NOT NULL, amount_minor bigint NOT NULL CHECK(amount_minor > 0),
 reference varchar(120) NOT NULL, method varchar(12) NOT NULL CHECK(method IN ('cash','yape','bcp')), cash_session_id bigint,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,reference),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id),
 FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
CREATE TABLE operations (
 tenant_id bigint NOT NULL REFERENCES tenants(id), key uuid NOT NULL, kind varchar(50) NOT NULL,
 payload_hash varchar(64) NOT NULL, result text NOT NULL, created_at timestamptz NOT NULL DEFAULT now(),
 PRIMARY KEY(tenant_id,key)
);
CREATE TABLE audit_entries (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), actor_id bigint REFERENCES users(id),
 action varchar(80) NOT NULL, reference varchar(100), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE fiscal_documents (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, order_id uuid NOT NULL,
 status varchar(15) NOT NULL DEFAULT 'unconfigured' CHECK(status IN ('unconfigured','pending','sent','accepted','rejected','error')),
 provider_reference varchar(100), created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,order_id), FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id)
);
CREATE INDEX orders_created ON orders(tenant_id,created_at);
CREATE INDEX movements_product ON stock_movements(tenant_id,product_id,created_at);
CREATE INDEX orders_expiring ON orders(tenant_id,expires_at) WHERE status = 'reserved';
CREATE OR REPLACE FUNCTION deny_ledger_change() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Los registros históricos son inmutables; registre una operación compensatoria'; END; $$;
SQL);
        foreach (['warehouses', 'stock_positions', 'customers', 'cash_sessions', 'coupons', 'orders', 'order_lines', 'sales', 'payments', 'cash_entries', 'stock_movements', 'receipts', 'transfers', 'returns', 'refunds', 'operations', 'audit_entries', 'fiscal_documents'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
        foreach (['stock_movements', 'cash_entries', 'receipts', 'returns', 'refunds', 'audit_entries', 'sales'] as $table) {
            DB::statement("CREATE TRIGGER immutable_ledger BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION deny_ledger_change()");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migración conserva registros comerciales. Use una migración compensatoria revisada.');
    }
};
