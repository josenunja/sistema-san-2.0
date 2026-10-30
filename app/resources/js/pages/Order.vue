<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock3 } from '@lucide/vue';
import BusinessShell from '@/components/BusinessShell.vue';
import { money, minor, state, localDate } from '@/lib/commerce';
type Line = {
    id?: number;
    name: string;
    sku: string;
    quantity: number;
    returned: number;
    unit_minor: number;
    discount_minor: number;
    total_minor: number;
};
type Payment = {
    id: number;
    amount_minor: number;
    method: string;
    status: string;
    reference?: string;
    created_at: string;
};
type OrderEvent = {
    id: number;
    kind: string;
    status: string | null;
    title: string;
    message: string;
    created_at: string;
};
const props = defineProps<{
    tenant: { name: string; slug: string };
    order: {
        id: string;
        status: string;
        delivery_status: string;
        delivery_method: string;
        customer_name: string;
        phone: string;
        address: string;
        created_at: string;
        expires_at: string;
        total_minor: number;
        returned_minor: number;
        discount_minor: number;
        delivery_minor: number;
        cash_received_minor?: number;
        change_minor?: number;
        delivery_label?: string;
        document_type?: 'ticket' | 'boleta' | 'factura';
        document_series?: string;
        document_number?: number;
        customer_document_type?: string;
        customer_document_number?: string;
        customer_legal_name?: string;
        tax_base_minor?: number;
        tax_igv_minor?: number;
    };
    lines: Line[];
    payments: Payment[];
    events: OrderEvent[];
    paid: number;
    refunds: { amount_minor: number }[];
    managing: boolean;
    role: string;
    cashSessions: { id: number }[];
    trackingToken?: string;
    paymentUrl?: string;
    paymentInstructions?: {
        yape_instructions?: string;
        bcp_instructions?: string;
    };
    whatsapp: string | null;
}>();
const page = usePage();
const busy = ref(false);
const errors = ref<Record<string, string>>({});
const key = ref(crypto.randomUUID());
const fiscalTitle = computed(() => {
    switch (props.order.document_type) {
        case 'factura':
            return 'FACTURA ELECTRÓNICA';
        case 'boleta':
            return 'BOLETA DE VENTA ELECTRÓNICA';
        default:
            return 'NOTA DE VENTA / TICKET';
    }
});
const fiscalCorrelative = computed(() => {
    const defaultSeries =
        props.order.document_type === 'factura'
            ? 'F001'
            : props.order.document_type === 'boleta'
              ? 'B001'
              : 'T001';
    const series = props.order.document_series || defaultSeries;
    const num = String(props.order.document_number || 0).padStart(8, '0');
    return `${series}-${num}`;
});
const taxBase = computed(() =>
    props.order.tax_base_minor !== undefined && props.order.tax_base_minor > 0
        ? props.order.tax_base_minor
        : Math.round(props.order.total_minor / 1.18),
);
const taxIgv = computed(() =>
    props.order.tax_igv_minor !== undefined && props.order.tax_igv_minor > 0
        ? props.order.tax_igv_minor
        : props.order.total_minor - taxBase.value,
);
const refunded = computed(() =>
    props.refunds.reduce((a, r) => a + Number(r.amount_minor), 0),
);
const due = computed(() =>
    Math.max(
        0,
        props.order.total_minor - props.order.returned_minor - props.paid,
    ),
);
const credit = computed(() =>
    Math.max(
        0,
        props.paid -
            refunded.value -
            (props.order.status === 'cancelled'
                ? 0
                : props.order.total_minor - props.order.returned_minor),
    ),
);
const payment = reactive({
    amount: '',
    method: 'cash',
    cash_session_id: '',
    reference: '',
});
const reversal = reactive({
    line_id: '',
    quantity: 1,
    condition: 'available',
    reason: '',
});
const refund = reactive({
    amount: '',
    method: 'cash',
    cash_session_id: '',
    reference: '',
});
const cancellation = ref('');
function send(action: string, data: Record<string, unknown>) {
    busy.value = true;
    errors.value = {};
    router.post(
        `/gestion/${props.tenant.slug}/operacion`,
        { key: key.value, action, ...data } as Parameters<
            typeof router.post
        >[1],
        {
            preserveScroll: true,
            onError: (e) => (errors.value = e),
            onSuccess: () => (key.value = crypto.randomUUID()),
            onFinish: () => (busy.value = false),
        },
    );
}
function pay() {
    try {
        send('payment', {
            order_id: props.order.id,
            amount_minor: minor(payment.amount),
            method: payment.method,
            cash_session_id: payment.cash_session_id || null,
            reference: payment.reference || null,
        });
    } catch (e) {
        errors.value = { operation: (e as Error).message };
    }
}
function reimburse() {
    try {
        send('refund', {
            order_id: props.order.id,
            amount_minor: minor(refund.amount),
            method: refund.method,
            cash_session_id: refund.cash_session_id || null,
            reference: refund.reference,
        });
    } catch (e) {
        errors.value = { operation: (e as Error).message };
    }
}
const proof = useForm({
    key: crypto.randomUUID(),
    amount_minor: 0,
    method: props.paymentInstructions?.yape_instructions ? 'yape' : 'bcp',
    reference: '',
    proof: null as File | null,
});
const proofAmount = ref('');
function upload() {
    try {
        proof.amount_minor = minor(proofAmount.value);
        proof.post(props.paymentUrl || `/tienda/${props.tenant.slug}/pago`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                proof.key = crypto.randomUUID();
                proof.proof = null;
            },
        });
    } catch (e) {
        proof.setError('amount_minor', (e as Error).message);
    }
}
const nextDelivery = computed(
    () =>
        ({ pending: 'preparing', preparing: 'ready', ready: 'delivered' })[
            props.order.delivery_status
        ] || null,
);
const nextCustomerStep = computed(() => {
    if (props.order.status === 'cancelled')
        return 'Este pedido fue cancelado. Puedes volver a la tienda y realizar uno nuevo.';
    if (props.order.status === 'expired')
        return 'La reserva venció. Vuelve a la tienda para revisar existencias y crear otro pedido.';
    if (props.order.status === 'reserved' && due.value > 0)
        return 'Registra tu constancia de pago. La tienda la revisará antes de confirmar la venta.';
    if (props.order.status === 'reserved')
        return 'El pago está cubierto. Espera que la tienda confirme la venta.';
    if (props.order.delivery_status === 'pending')
        return 'La venta está confirmada. La tienda empezará a preparar tu pedido.';
    if (props.order.delivery_status === 'preparing')
        return 'Tu pedido está en preparación. Te avisaremos cuando esté listo.';
    if (props.order.delivery_status === 'ready')
        return props.order.delivery_method === 'pickup'
            ? 'Tu pedido está listo para recoger en la tienda.'
            : 'Tu pedido está listo para iniciar la entrega.';

    return 'Tu pedido fue entregado. Gracias por comprar en esta tienda.';
});
function printTicket() {
    window.print();
}
</script>
<template>
    <Head :title="`Pedido ${order.id.slice(0, 8)}`" /><component
        :is="managing ? BusinessShell : 'main'"
        :tenant="tenant"
        :role="role"
        section="pedidos"
        :class="managing ? '' : 'mx-auto max-w-5xl p-6 py-10 text-slate-900'"
    >
        <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link
                    :href="
                        managing
                            ? `/gestion/${tenant.slug}/operacion/pedidos`
                            : `/tienda/${tenant.slug}`
                    "
                    class="text-sm text-emerald-700 print:hidden"
                    >←
                    {{
                        managing ? 'Pedidos y ventas' : 'Volver a la tienda'
                    }}</Link
                >
                <p class="mt-6 text-xs tracking-widest text-slate-500">
                    {{ tenant.name }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-semibold">
                        Pedido {{ order.id.slice(0, 8) }}
                    </h1>
                    <span
                        class="rounded-lg bg-emerald-100 px-3 py-1 font-mono text-xs font-semibold text-emerald-800"
                    >
                        {{ fiscalTitle }} · {{ fiscalCorrelative }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-slate-500">
                    {{ localDate(order.created_at) }}
                </p>
            </div>
            <button
                v-if="order.status === 'confirmed'"
                class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-medium hover:bg-slate-50 print:hidden"
                @click="printTicket"
            >
                Imprimir comprobante
            </button>
        </div>
        <p
            v-if="page.props.success"
            role="status"
            class="mb-5 rounded-xl bg-emerald-50 p-4 text-emerald-800"
        >
            {{ page.props.success }}
        </p>
        <div
            v-if="Object.keys(errors).length"
            role="alert"
            class="mb-5 rounded-xl bg-red-50 p-4 text-red-800"
        >
            <p v-for="(e, k) in errors" :key="k">{{ e }}</p>
        </div>
        <div class="mb-7 grid gap-4 sm:grid-cols-3">
            <article class="rounded-2xl border bg-white p-5">
                <p class="text-xs text-slate-500">Pedido / venta</p>
                <strong class="mt-2 block">{{ state(order.status) }}</strong>
            </article>
            <article class="rounded-2xl border bg-white p-5">
                <p class="text-xs text-slate-500">Pago</p>
                <strong class="mt-2 block">{{
                    paid >= order.total_minor - order.returned_minor
                        ? 'Cubierto'
                        : paid > 0
                          ? 'Abono parcial'
                          : 'Pendiente'
                }}</strong>
            </article>
            <article class="rounded-2xl border bg-white p-5">
                <p class="text-xs text-slate-500">Entrega</p>
                <strong class="mt-2 block">{{
                    state(order.delivery_status)
                }}</strong>
            </article>
        </div>
        <div
            v-if="order.delivery_label || Number(order.change_minor) > 0"
            class="my-5 grid gap-2 rounded-xl border bg-white p-5 text-sm"
        >
            <p v-if="order.delivery_label">
                Entrega / recojo: {{ order.delivery_label }}
            </p>
            <p v-if="Number(order.change_minor) > 0">
                Efectivo recibido:
                {{ money(Number(order.cash_received_minor)) }} · Vuelto:
                {{ money(Number(order.change_minor)) }}
            </p>
        </div>
        <section class="order-status-experience">
            <div class="order-next-step">
                <Clock3 :size="21" />
                <div>
                    <span>SIGUIENTE PASO</span>
                    <p>{{ nextCustomerStep }}</p>
                </div>
            </div>
            <div class="order-timeline">
                <div class="order-timeline-heading">
                    <div>
                        <span>SEGUIMIENTO EN TIEMPO REAL</span>
                        <h2>Historia de tu pedido</h2>
                    </div>
                    <strong>{{ events.length }} actualizaciones</strong>
                </div>
                <ol v-if="events.length">
                    <li v-for="event in events" :key="event.id">
                        <span class="order-event-marker">
                            <CheckCircle2 :size="17" />
                        </span>
                        <div>
                            <div class="order-event-title">
                                <strong>{{ event.title }}</strong>
                                <time :datetime="event.created_at">{{
                                    localDate(event.created_at)
                                }}</time>
                            </div>
                            <p>{{ event.message }}</p>
                        </div>
                    </li>
                </ol>
                <p v-else class="order-timeline-empty">
                    El historial aparecerá aquí cuando la tienda actualice tu
                    pedido.
                </p>
            </div>
        </section>
        <div class="overflow-auto rounded-2xl border bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-4">Producto / variante</th>
                        <th class="p-4">Unidades</th>
                        <th class="p-4">Precio</th>
                        <th class="p-4">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="l in lines" :key="l.sku">
                        <td class="p-4">
                            <strong>{{ l.name }}</strong>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ l.sku }}
                            </p>
                        </td>
                        <td class="p-4">
                            {{ l.quantity
                            }}<span
                                v-if="l.returned"
                                class="block text-xs text-slate-500"
                                >{{ l.returned }} devueltas</span
                            >
                        </td>
                        <td class="p-4">{{ money(l.unit_minor) }}</td>
                        <td class="p-4">{{ money(l.total_minor) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <section
            class="my-7 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
        >
            <div>
                <span
                    class="inline-block rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold tracking-wider text-emerald-800 uppercase"
                >
                    {{ fiscalTitle }}
                </span>
                <p class="mt-2 font-mono text-lg font-bold text-slate-800">
                    {{ fiscalCorrelative }}
                </p>
                <h2 class="mt-2 font-semibold">{{ order.customer_name }}</h2>
                <p
                    v-if="order.customer_document_number"
                    class="text-sm text-slate-600"
                >
                    {{
                        (
                            order.customer_document_type ||
                            (order.document_type === 'factura' ? 'RUC' : 'DNI')
                        ).toUpperCase()
                    }}: {{ order.customer_document_number }}
                </p>
                <p
                    v-if="
                        order.customer_legal_name &&
                        order.customer_legal_name !== order.customer_name
                    "
                    class="text-sm text-slate-600"
                >
                    Razón Social: {{ order.customer_legal_name }}
                </p>
                <p class="mt-2 text-sm text-slate-500">{{ order.phone }}</p>
                <p class="mt-2 text-sm">
                    {{
                        order.delivery_method === 'pickup'
                            ? 'Recojo en tienda'
                            : order.address
                    }}
                </p>
                <p
                    v-if="order.status === 'reserved'"
                    class="mt-4 text-sm text-amber-800"
                >
                    Reserva hasta {{ localDate(order.expires_at) }}. Un pago
                    tardío requiere revisión del negocio.
                </p>
            </div>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt>Op. Gravadas (Base)</dt>
                <dd class="text-right">{{ money(taxBase) }}</dd>
                <dt>I.G.V. (18%)</dt>
                <dd class="text-right">{{ money(taxIgv) }}</dd>
                <template v-if="order.discount_minor > 0">
                    <dt>Descuento</dt>
                    <dd class="text-right text-emerald-700">
                        -{{ money(order.discount_minor) }}
                    </dd>
                </template>
                <template v-if="order.delivery_minor > 0">
                    <dt>Entrega</dt>
                    <dd class="text-right">
                        {{ money(order.delivery_minor) }}
                    </dd>
                </template>
                <dt class="border-t pt-2 text-xl font-semibold">Total</dt>
                <dd class="border-t pt-2 text-right text-xl font-semibold">
                    {{ money(order.total_minor) }}
                </dd>
                <dt>Devoluciones comerciales</dt>
                <dd class="text-right">{{ money(order.returned_minor) }}</dd>
                <dt>Pagos verificados</dt>
                <dd class="text-right">{{ money(paid) }}</dd>
                <dt class="font-semibold">Saldo pendiente</dt>
                <dd class="text-right font-semibold">{{ money(due) }}</dd>
                <template v-if="credit > 0"
                    ><dt>A favor del comprador</dt>
                    <dd class="text-right">{{ money(credit) }}</dd></template
                >
            </dl>
        </section>
        <div class="my-6 border-t pt-4 text-xs text-slate-500 print:hidden">
            <template
                v-if="
                    order.document_type === 'factura' ||
                    order.document_type === 'boleta'
                "
            >
                <p class="font-medium text-slate-700">
                    {{ fiscalTitle }} · {{ fiscalCorrelative }}
                </p>
                <p class="mt-1 text-slate-500">
                    Comprobante registrado en el sistema. Emisión y control
                    interno comercial según normativa tributaria.
                </p>
            </template>
            <template v-else>
                <p>
                    NOTA DE VENTA / DOCUMENTO INTERNO · No es comprobante
                    electrónico aceptado por SUNAT.
                </p>
            </template>
        </div>

        <!-- Formato Exclusivo para Impresoras Térmicas de Tickets (80mm / 58mm) -->
        <div
            class="print-ticket hidden font-mono text-xs leading-tight text-black print:block"
        >
            <div class="border-b border-dashed border-black pb-2 text-center">
                <h1 class="text-sm font-bold uppercase">{{ tenant.name }}</h1>
                <p class="mt-1 text-[11px] font-bold">{{ fiscalTitle }}</p>
                <p class="mt-0.5 text-sm font-bold tracking-widest">
                    {{ fiscalCorrelative }}
                </p>
                <p class="mt-1 text-[10px]">
                    {{ localDate(order.created_at) }}
                </p>
            </div>

            <div class="border-b border-dashed border-black py-2 text-[11px]">
                <p><strong>Cliente:</strong> {{ order.customer_name }}</p>
                <p v-if="order.customer_document_number">
                    <strong
                        >{{
                            (
                                order.customer_document_type ||
                                (order.document_type === 'factura'
                                    ? 'RUC'
                                    : 'DNI')
                            ).toUpperCase()
                        }}:</strong
                    >
                    {{ order.customer_document_number }}
                </p>
                <p
                    v-if="
                        order.customer_legal_name &&
                        order.customer_legal_name !== order.customer_name
                    "
                >
                    <strong>Razón Social:</strong>
                    {{ order.customer_legal_name }}
                </p>
                <p><strong>Teléfono:</strong> {{ order.phone }}</p>
                <p>
                    <strong>Entrega:</strong>
                    {{
                        order.delivery_method === 'pickup'
                            ? 'Recojo en tienda'
                            : order.address
                    }}
                </p>
            </div>

            <table
                class="my-2 w-full border-b border-dashed border-black pb-2 text-[11px]"
            >
                <thead>
                    <tr class="border-b border-black">
                        <th class="py-1 text-left">Cant. / Item</th>
                        <th class="py-1 text-right">P.U.</th>
                        <th class="py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="l in lines" :key="l.sku">
                        <td class="py-1">
                            <div>{{ l.quantity }} × {{ l.name }}</div>
                            <div class="text-[9px] text-slate-500">
                                {{ l.sku }}
                            </div>
                        </td>
                        <td class="py-1 text-right align-top">
                            {{ money(l.unit_minor) }}
                        </td>
                        <td class="py-1 text-right align-top">
                            {{ money(l.total_minor) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                class="space-y-1 border-b border-dashed border-black pb-2 text-[11px]"
            >
                <div class="flex justify-between">
                    <span>Op. Gravadas:</span>
                    <span>{{ money(taxBase) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>I.G.V. (18%):</span>
                    <span>{{ money(taxIgv) }}</span>
                </div>
                <div
                    v-if="order.discount_minor > 0"
                    class="flex justify-between"
                >
                    <span>Descuento:</span>
                    <span>-{{ money(order.discount_minor) }}</span>
                </div>
                <div
                    v-if="order.delivery_minor > 0"
                    class="flex justify-between"
                >
                    <span>Entrega:</span>
                    <span>{{ money(order.delivery_minor) }}</span>
                </div>
                <div
                    class="flex justify-between border-t border-black pt-1 text-sm font-bold"
                >
                    <span>TOTAL:</span>
                    <span>{{ money(order.total_minor) }}</span>
                </div>
                <div
                    v-if="Number(order.cash_received_minor) > 0"
                    class="flex justify-between pt-1 text-[10px]"
                >
                    <span
                        >Efectivo:
                        {{ money(Number(order.cash_received_minor)) }}</span
                    >
                    <span>Vuelto: {{ money(Number(order.change_minor)) }}</span>
                </div>
            </div>

            <div class="pt-3 text-center text-[10px]">
                <p
                    v-if="
                        order.document_type === 'factura' ||
                        order.document_type === 'boleta'
                    "
                >
                    Representación impresa del Comprobante de Pago Electrónico.
                </p>
                <p v-else>NOTA DE VENTA / TICKET DE CONTROL INTERNO</p>
                <p class="mt-1 font-bold">¡Gracias por su preferencia!</p>
            </div>
        </div>
        <div class="print:hidden">
            <section
                v-if="!managing && trackingToken"
                class="mb-7 rounded-2xl border border-amber-200 bg-amber-50 p-6"
            >
                <h2 class="font-semibold">Guarda tu acceso privado</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Estos datos permiten consultar tu pedido. No los compartas
                    públicamente.
                </p>
                <label class="mt-4 grid gap-2 text-xs"
                    >Código<input
                        readonly
                        :value="order.id"
                        class="rounded-lg border bg-white p-3 font-mono" /></label
                ><label class="mt-3 grid gap-2 text-xs"
                    >Clave de seguimiento<input
                        readonly
                        :value="trackingToken"
                        class="rounded-lg border bg-white p-3 font-mono" /></label
                ><a
                    v-if="whatsapp"
                    :href="whatsapp"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-5 inline-block rounded-xl bg-emerald-800 px-5 py-3 text-sm text-white"
                    >Coordinar por WhatsApp ↗</a
                >
                <p v-if="whatsapp" class="mt-3 text-xs text-slate-500">
                    El pedido ya está guardado. Tú decides si envías el mensaje;
                    el enlace no adjunta fotos ni confirma pago.
                </p>
            </section>
            <section
                v-if="payments.length"
                class="mb-7 rounded-2xl border bg-white p-6"
            >
                <h2 class="mb-5 text-lg font-semibold">Pagos registrados</h2>
                <div
                    v-for="p in payments"
                    :key="p.id"
                    class="flex flex-wrap items-center justify-between gap-3 border-t py-4 text-sm"
                >
                    <span
                        >{{ state(p.method) }} · {{ money(p.amount_minor) }} ·
                        {{ state(p.status)
                        }}<span
                            v-if="p.reference"
                            class="block text-xs text-slate-400"
                            >{{ p.reference }}</span
                        ></span
                    >
                    <div
                        v-if="managing && role === 'owner'"
                        class="flex flex-wrap gap-3"
                    >
                        <a
                            :href="`/gestion/${tenant.slug}/constancia/${p.id}`"
                            class="text-emerald-700"
                            >Descargar constancia</a
                        ><template v-if="p.status === 'pending'"
                            ><button
                                :disabled="busy"
                                class="font-semibold text-emerald-700"
                                @click="
                                    send('verify_payment', {
                                        payment_id: p.id,
                                        accept: true,
                                    })
                                "
                            >
                                Verificar pago recibido</button
                            ><button
                                :disabled="busy"
                                class="text-red-700"
                                @click="
                                    send('verify_payment', {
                                        payment_id: p.id,
                                        accept: false,
                                    })
                                "
                            >
                                Rechazar
                            </button></template
                        >
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Una constancia es evidencia pendiente de revisión; su
                    registro no confirma el ingreso del dinero.
                </p>
            </section>
            <section
                v-if="
                    !managing &&
                    ['reserved', 'confirmed'].includes(order.status) &&
                    due > 0 &&
                    (paymentInstructions?.yape_instructions ||
                        paymentInstructions?.bcp_instructions)
                "
                class="mb-7 rounded-2xl border bg-white p-6"
            >
                <h2 class="text-xl font-semibold">Registrar constancia</h2>
                <p
                    v-if="paymentInstructions?.yape_instructions"
                    class="mt-4 text-sm"
                >
                    Yape: {{ paymentInstructions.yape_instructions }}
                </p>
                <p
                    v-if="paymentInstructions?.bcp_instructions"
                    class="mt-3 text-sm"
                >
                    BCP: {{ paymentInstructions.bcp_instructions }}
                </p>
                <form
                    class="mt-6 grid gap-4 md:grid-cols-2"
                    @submit.prevent="upload"
                >
                    <label class="grid gap-2 text-sm"
                        >Medio<select
                            v-model="proof.method"
                            class="rounded-xl border p-3"
                        >
                            <option
                                v-if="paymentInstructions?.yape_instructions"
                                value="yape"
                            >
                                Yape
                            </option>
                            <option
                                v-if="paymentInstructions?.bcp_instructions"
                                value="bcp"
                            >
                                BCP
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Importe S/<input
                            v-model="proofAmount"
                            required
                            inputmode="decimal"
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Referencia de operación<input
                            v-model="proof.reference"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Constancia (JPG, PNG o PDF, hasta 5 MB)<input
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            class="rounded-xl border p-3"
                            @change="
                                proof.proof =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] || null
                            "
                    /></label>
                    <div
                        role="alert"
                        class="text-sm text-red-700 md:col-span-2"
                    >
                        <p v-for="(e, k) in proof.errors" :key="k">{{ e }}</p>
                    </div>
                    <button
                        :disabled="proof.processing"
                        class="rounded-xl bg-emerald-800 p-3 text-white"
                    >
                        Enviar a revisión
                    </button>
                </form>
            </section>
            <template v-if="managing"
                ><section
                    v-if="order.status === 'reserved' && role === 'owner'"
                    class="mb-7 flex flex-wrap gap-3 rounded-2xl border bg-white p-6"
                >
                    <button
                        :disabled="busy"
                        class="rounded-xl bg-emerald-800 px-5 py-3 text-white"
                        @click="send('confirm', { order_id: order.id })"
                    >
                        Confirmar venta pagada
                    </button>
                    <form
                        class="flex flex-wrap gap-3"
                        @submit.prevent="
                            send('cancel', {
                                order_id: order.id,
                                reason: cancellation,
                            })
                        "
                    >
                        <input
                            v-model="cancellation"
                            required
                            minlength="5"
                            placeholder="Motivo de cancelación"
                            aria-label="Motivo de cancelación"
                            class="rounded-xl border p-3"
                        /><button
                            :disabled="busy"
                            class="rounded-xl border px-5 py-3 text-red-700"
                        >
                            Cancelar pedido
                        </button>
                    </form>
                </section>
                <section
                    v-if="
                        order.status === 'confirmed' &&
                        nextDelivery &&
                        role === 'owner'
                    "
                    class="mb-7"
                >
                    <button
                        :disabled="busy"
                        class="rounded-xl bg-emerald-800 px-5 py-3 text-white"
                        @click="
                            send('delivery', {
                                order_id: order.id,
                                status: nextDelivery,
                            })
                        "
                    >
                        Pasar a {{ state(nextDelivery) }}
                    </button>
                </section>
                <form
                    v-if="
                        due > 0 &&
                        ['reserved', 'confirmed'].includes(order.status)
                    "
                    class="mb-7 grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2"
                    @submit.prevent="pay"
                >
                    <h2 class="text-lg font-semibold md:col-span-2">
                        Cobrar un abono
                    </h2>
                    <label class="grid gap-2 text-sm"
                        >Importe S/<input
                            v-model="payment.amount"
                            required
                            inputmode="decimal"
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Medio<select
                            v-model="payment.method"
                            class="rounded-xl border p-3"
                        >
                            <option value="cash">Efectivo</option>
                            <option value="yape">Yape (pendiente)</option>
                            <option value="bcp">BCP (pendiente)</option>
                        </select></label
                    ><label
                        v-if="payment.method === 'cash'"
                        class="grid gap-2 text-sm"
                        >Mi caja abierta<select
                            v-model="payment.cash_session_id"
                            required
                            class="rounded-xl border p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="c in cashSessions"
                                :key="c.id"
                                :value="c.id"
                            >
                                Caja {{ c.id }}
                            </option>
                        </select></label
                    ><label v-else class="grid gap-2 text-sm"
                        >Referencia<input
                            v-model="payment.reference"
                            required
                            class="rounded-xl border p-3" /></label
                    ><button
                        :disabled="busy"
                        class="rounded-xl bg-emerald-800 p-3 text-white"
                    >
                        Guardar abono
                    </button>
                </form>
                <form
                    v-if="order.status === 'confirmed' && role === 'owner'"
                    class="mb-7 grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2"
                    @submit.prevent="
                        send('return', {
                            line_id: reversal.line_id,
                            quantity: reversal.quantity,
                            condition: reversal.condition,
                            reason: reversal.reason,
                        })
                    "
                >
                    <h2 class="text-lg font-semibold md:col-span-2">
                        Recibir devolución
                    </h2>
                    <label class="grid gap-2 text-sm"
                        >Producto<select
                            v-model="reversal.line_id"
                            required
                            class="rounded-xl border p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="l in lines.filter(
                                    (l) => l.returned < l.quantity,
                                )"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.name }} ·
                                {{ l.quantity - l.returned }} disponibles para
                                devolución
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Unidades recibidas<input
                            v-model.number="reversal.quantity"
                            type="number"
                            required
                            min="1"
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Condición<select
                            v-model="reversal.condition"
                            class="rounded-xl border p-3"
                        >
                            <option value="available">Apto para venta</option>
                            <option value="damaged">Dañado / bloqueado</option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Motivo<input
                            v-model="reversal.reason"
                            required
                            minlength="5"
                            class="rounded-xl border p-3" /></label
                    ><button
                        :disabled="busy"
                        class="rounded-xl border p-3 font-medium"
                    >
                        Registrar devolución física y comercial
                    </button>
                    <p class="text-xs text-slate-500">
                        Esto no devuelve dinero. El reembolso se registra por
                        separado.
                    </p>
                </form>
                <form
                    v-if="credit > 0 && role === 'owner'"
                    class="grid gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-6 md:grid-cols-2"
                    @submit.prevent="reimburse"
                >
                    <h2 class="text-lg font-semibold md:col-span-2">
                        Registrar reembolso realmente ejecutado
                    </h2>
                    <label class="grid gap-2 text-sm"
                        >Importe S/<input
                            v-model="refund.amount"
                            required
                            inputmode="decimal"
                            class="rounded-xl border bg-white p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Medio<select
                            v-model="refund.method"
                            class="rounded-xl border bg-white p-3"
                        >
                            <option value="cash">Efectivo</option>
                            <option value="yape">Yape</option>
                            <option value="bcp">BCP</option>
                        </select></label
                    ><label
                        v-if="refund.method === 'cash'"
                        class="grid gap-2 text-sm"
                        >Mi caja abierta<select
                            v-model="refund.cash_session_id"
                            required
                            class="rounded-xl border bg-white p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="c in cashSessions"
                                :key="c.id"
                                :value="c.id"
                            >
                                Caja {{ c.id }}
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Referencia / constancia de ejecución<input
                            v-model="refund.reference"
                            required
                            minlength="5"
                            class="rounded-xl border bg-white p-3" /></label
                    ><button
                        :disabled="busy"
                        class="rounded-xl bg-slate-900 p-3 text-white"
                    >
                        Confirmar registro del reembolso
                    </button>
                    <p class="text-xs text-slate-600">
                        Requiere contraseña confirmada recientemente. El sistema
                        no mueve dinero por ti.
                    </p>
                </form>
            </template>
        </div></component
    >
</template>
