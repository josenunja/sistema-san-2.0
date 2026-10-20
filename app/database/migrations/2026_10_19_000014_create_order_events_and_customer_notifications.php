<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE order_events (
 id bigserial PRIMARY KEY,
 tenant_id bigint NOT NULL,
 order_id uuid NOT NULL,
 actor_id bigint REFERENCES users(id),
 kind varchar(40) NOT NULL,
 status varchar(30),
 title varchar(160) NOT NULL,
 message varchar(500) NOT NULL,
 created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id)
);
CREATE INDEX order_events_timeline ON order_events(tenant_id,order_id,created_at,id);

CREATE TABLE customer_notifications (
 id bigserial PRIMARY KEY,
 tenant_id bigint NOT NULL,
 user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 order_id uuid NOT NULL,
 kind varchar(40) NOT NULL,
 title varchar(160) NOT NULL,
 message varchar(500) NOT NULL,
 read_at timestamptz,
 created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(tenant_id,id),
 FOREIGN KEY(tenant_id,order_id) REFERENCES orders(tenant_id,id)
);
CREATE INDEX customer_notifications_feed ON customer_notifications(tenant_id,user_id,read_at,created_at DESC);

ALTER TABLE order_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE order_events FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_access ON order_events
 USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)
 WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint);

ALTER TABLE customer_notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE customer_notifications FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_access ON customer_notifications
 USING (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint)
 WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id',true),'')::bigint);

CREATE TRIGGER immutable_order_events
 BEFORE UPDATE OR DELETE ON order_events
 FOR EACH ROW EXECUTE FUNCTION deny_ledger_change();

INSERT INTO order_events (tenant_id,order_id,kind,status,title,message,created_at)
 SELECT tenant_id,id,'order_created','reserved','Pedido recibido',
        'La tienda recibió el pedido y reservó sus productos.',created_at
 FROM orders;

INSERT INTO order_events (tenant_id,order_id,kind,status,title,message,created_at)
 SELECT sales.tenant_id,sales.order_id,'order_confirmed','confirmed','Pedido confirmado',
        'El pago fue validado y la venta quedó confirmada.',sales.confirmed_at
 FROM sales;
SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve the customer order timeline and notification history.');
    }
};
