<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE customers ADD COLUMN credit_limit_minor bigint NOT NULL DEFAULT 0 CHECK(credit_limit_minor >= 0);
ALTER TABLE customers ADD COLUMN credit_days integer NOT NULL DEFAULT 30 CHECK(credit_days >= 0 AND credit_days <= 365);
ALTER TABLE orders ADD COLUMN due_at timestamptz;
ALTER TABLE customers DROP CONSTRAINT customers_user_id_fkey;
ALTER TABLE customers ADD CONSTRAINT customers_user_id_fkey FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve credit policies and due dates.');
    }
};
