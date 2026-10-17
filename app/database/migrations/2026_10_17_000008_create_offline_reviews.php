<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE offline_reviews (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, device_id uuid NOT NULL, operation_id uuid NOT NULL,
 payload_hash varchar(64) NOT NULL, actor_id bigint NOT NULL REFERENCES users(id), reason varchar(300) NOT NULL,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,device_id,operation_id),
 FOREIGN KEY(tenant_id,device_id) REFERENCES offline_devices(tenant_id,id)
);
ALTER TABLE offline_reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE offline_reviews FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_access ON offline_reviews USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint);
CREATE TRIGGER immutable_offline_review BEFORE UPDATE OR DELETE ON offline_reviews FOR EACH ROW EXECUTE FUNCTION deny_ledger_change();
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve offline review evidence.');
    }
};
