<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE suppliers (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), name varchar(160) NOT NULL,
 reference varchar(40), contact varchar(160), UNIQUE(tenant_id,id)
);
CREATE TABLE purchases (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), supplier_id bigint NOT NULL,
 warehouse_id bigint NOT NULL, product_id bigint NOT NULL, reference varchar(120) NOT NULL,
 quantity integer NOT NULL CHECK(quantity>0), unit_cost_minor bigint NOT NULL CHECK(unit_cost_minor>=0),
 received integer NOT NULL DEFAULT 0 CHECK(received>=0 AND received<=quantity),
 status varchar(16) NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','confirmed','closed','cancelled')),
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,id),
 FOREIGN KEY(tenant_id,supplier_id) REFERENCES suppliers(tenant_id,id),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
CREATE TABLE purchase_receipts (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, purchase_id bigint NOT NULL, receipt_id bigint NOT NULL,
 quantity integer NOT NULL CHECK(quantity>0), reversed boolean NOT NULL DEFAULT false,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,receipt_id),
 FOREIGN KEY(tenant_id,purchase_id) REFERENCES purchases(tenant_id,id),
 FOREIGN KEY(receipt_id) REFERENCES receipts(id)
);
CREATE TABLE purchase_reversals (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, purchase_receipt_id bigint NOT NULL UNIQUE REFERENCES purchase_receipts(id),
 reason varchar(300) NOT NULL, actor_id bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now()
);
SQL);
        foreach (['suppliers', 'purchases', 'purchase_receipts', 'purchase_reversals'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
        DB::statement('CREATE TRIGGER immutable_purchase_reversal BEFORE UPDATE OR DELETE ON purchase_reversals FOR EACH ROW EXECUTE FUNCTION deny_ledger_change()');
    }

    public function down(): void
    {
        throw new RuntimeException('Purchase history must be preserved.');
    }
};
