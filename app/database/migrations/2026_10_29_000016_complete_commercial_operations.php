<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE products ADD COLUMN archived boolean NOT NULL DEFAULT false;
ALTER TABLE products ADD COLUMN barcode varchar(64);
ALTER TABLE products ADD COLUMN minimum_stock integer NOT NULL DEFAULT 0 CHECK(minimum_stock >= 0);
CREATE UNIQUE INDEX product_barcode_unique ON products(tenant_id,barcode) WHERE barcode IS NOT NULL AND barcode <> '';
ALTER TABLE product_images ADD COLUMN sort_order integer NOT NULL DEFAULT 0;
ALTER TABLE product_images ADD COLUMN alt_text varchar(160) NOT NULL DEFAULT '';
ALTER TABLE memberships ADD COLUMN warehouse_ids jsonb;
ALTER TABLE orders ADD COLUMN cash_received_minor bigint NOT NULL DEFAULT 0 CHECK(cash_received_minor >= 0);
ALTER TABLE orders ADD COLUMN change_minor bigint NOT NULL DEFAULT 0 CHECK(change_minor >= 0);
CREATE TABLE cash_reopenings (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 cash_session_id bigint NOT NULL, actor_id bigint NOT NULL REFERENCES users(id),
 reason varchar(300) NOT NULL, previous_closed_at timestamptz NOT NULL,
 previous_counted_minor bigint, created_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
CREATE TABLE purchase_documents (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id),
 supplier_id bigint NOT NULL, warehouse_id bigint NOT NULL, reference varchar(120) NOT NULL,
 additional_minor bigint NOT NULL DEFAULT 0 CHECK(additional_minor >= 0),
 discount_minor bigint NOT NULL DEFAULT 0 CHECK(discount_minor >= 0),
 total_minor bigint NOT NULL CHECK(total_minor >= 0), created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id), FOREIGN KEY(tenant_id,supplier_id) REFERENCES suppliers(tenant_id,id),
 FOREIGN KEY(tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id)
);
ALTER TABLE purchases ADD COLUMN document_id bigint;
ALTER TABLE purchases ADD CONSTRAINT purchases_document_fk FOREIGN KEY(tenant_id,document_id) REFERENCES purchase_documents(tenant_id,id);
SQL);
        foreach (['cash_reopenings', 'purchase_documents'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id=nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
        DB::statement('CREATE TRIGGER immutable_cash_reopening BEFORE UPDATE OR DELETE ON cash_reopenings FOR EACH ROW EXECUTE FUNCTION deny_ledger_change()');
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar documentos e historial; usar migraciones compensatorias.');
    }
};
