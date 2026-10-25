<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE invitations ADD COLUMN revoked_at timestamptz;
ALTER TABLE invitations ADD COLUMN last_sent_at timestamptz;
UPDATE invitations SET last_sent_at = created_at;
ALTER TABLE memberships DROP CONSTRAINT memberships_role_check;
ALTER TABLE memberships ADD CONSTRAINT memberships_role_check CHECK(role IN ('owner','cashier','warehouse','catalog'));
ALTER TABLE invitations DROP CONSTRAINT invitations_role_check;
ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK(role IN ('owner','cashier','warehouse','catalog'));
CREATE INDEX invitations_tenant_pending ON invitations(tenant_id,expires_at) WHERE accepted_at IS NULL AND revoked_at IS NULL;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve invitation history and catalog permissions.');
    }
};
