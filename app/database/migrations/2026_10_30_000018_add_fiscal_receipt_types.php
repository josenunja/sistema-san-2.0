<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE fiscal_series_sequences (
  id bigserial PRIMARY KEY,
  tenant_id bigint NOT NULL REFERENCES tenants(id),
  document_type varchar(20) NOT NULL CHECK(document_type IN ('ticket', 'boleta', 'factura')),
  series varchar(10) NOT NULL,
  current_number integer NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE(tenant_id, document_type, series)
);

ALTER TABLE orders ADD COLUMN document_type varchar(20) NOT NULL DEFAULT 'ticket' CHECK(document_type IN ('ticket', 'boleta', 'factura'));
ALTER TABLE orders ADD COLUMN document_series varchar(10) NOT NULL DEFAULT 'T001';
ALTER TABLE orders ADD COLUMN document_number integer NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN customer_document_type varchar(10) NOT NULL DEFAULT 'dni' CHECK(customer_document_type IN ('dni', 'ruc', 'ce', 'pasaporte', 'otro'));
ALTER TABLE orders ADD COLUMN customer_document_number varchar(20) NOT NULL DEFAULT '';
ALTER TABLE orders ADD COLUMN customer_legal_name varchar(200) NOT NULL DEFAULT '';
ALTER TABLE orders ADD COLUMN tax_base_minor bigint NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN tax_igv_minor bigint NOT NULL DEFAULT 0;

CREATE INDEX idx_orders_fiscal_doc ON orders(tenant_id, document_type, document_series, document_number);
SQL);

        DB::statement('ALTER TABLE fiscal_series_sequences ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fiscal_series_sequences FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tenant_access ON fiscal_series_sequences USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::bigint)");
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar historial y correlativos de comprobantes fiscales.');
    }
};
