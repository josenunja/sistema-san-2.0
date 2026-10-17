<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE receipts ADD UNIQUE(tenant_id,id);
ALTER TABLE purchase_receipts ADD UNIQUE(tenant_id,id);
ALTER TABLE purchase_receipts ADD FOREIGN KEY(tenant_id,receipt_id) REFERENCES receipts(tenant_id,id);
ALTER TABLE purchase_reversals ADD FOREIGN KEY(tenant_id,purchase_receipt_id) REFERENCES purchase_receipts(tenant_id,id);
ALTER TABLE coupons ADD amount_minor bigint CHECK(amount_minor>0), ADD category varchar(100), ADD product_id bigint,
 ADD channel varchar(10) CHECK(channel IN ('web','pos')), ADD per_customer integer CHECK(per_customer>0),
 ADD FOREIGN KEY(tenant_id,product_id) REFERENCES products(tenant_id,id);
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve promotion history.');
    }
};
