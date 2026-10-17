<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE stock_movements ADD COLUMN pool_value_delta_minor bigint;
CREATE TABLE expenses (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), amount_minor bigint NOT NULL CHECK(amount_minor > 0),
 category varchar(80) NOT NULL, reason varchar(300) NOT NULL, cash_session_id bigint, actor_id bigint REFERENCES users(id),
 created_at timestamptz NOT NULL DEFAULT now(), FOREIGN KEY(tenant_id,cash_session_id) REFERENCES cash_sessions(tenant_id,id)
);
ALTER TABLE expenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE expenses FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_access ON expenses USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint);
CREATE TRIGGER immutable_expense BEFORE UPDATE OR DELETE ON expenses FOR EACH ROW EXECUTE FUNCTION deny_ledger_change();
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve inventory and expense records.');
    }
};
