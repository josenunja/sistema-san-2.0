<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE customers ADD COLUMN user_id bigint REFERENCES users(id);
CREATE UNIQUE INDEX customer_account_unique ON customers(tenant_id,user_id) WHERE user_id IS NOT NULL;
CREATE TABLE storefront_versions (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL REFERENCES tenants(id), settings jsonb NOT NULL,
 actor_id bigint REFERENCES users(id), published_at timestamptz, created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,id)
);
CREATE TABLE product_images (
 id bigserial PRIMARY KEY, tenant_id bigint NOT NULL, product_id bigint NOT NULL, path varchar(250) NOT NULL,
 created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(tenant_id,id),
 FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id)
);
SQL);
        foreach (['storefront_versions', 'product_images'] as $table) {
            DB::statement("ALTER TABLE $table ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE $table FORCE ROW LEVEL SECURITY");
            DB::statement("CREATE POLICY tenant_access ON $table USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)");
        }
        DB::statement('CREATE TRIGGER immutable_history BEFORE UPDATE OR DELETE ON storefront_versions FOR EACH ROW EXECUTE FUNCTION deny_ledger_change()');
    }

    public function down(): void
    {
        throw new RuntimeException('Use una migración compensatoria para conservar el historial.');
    }
};
