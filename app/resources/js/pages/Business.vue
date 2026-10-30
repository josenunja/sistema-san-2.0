<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import BusinessShell from '@/components/BusinessShell.vue';
import ProductSearch from '@/components/ProductSearch.vue';
import OperationalOptions from '@/components/OperationalOptions.vue';
import BusinessAdministration from '@/components/BusinessAdministration.vue';
import PurchaseDocumentForm from '@/components/PurchaseDocumentForm.vue';
import InvitationList, {
    type Invitation,
} from '@/components/InvitationList.vue';
import { money, minor, state, localDate } from '@/lib/commerce';

type Row = Record<string, string | number | boolean | null>;
type Warehouse = { id: number; name: string; branch: string };
type Product = {
    id: number;
    name: string;
    sku: string;
    barcode?: string;
    price_minor: number;
};
type Field = {
    name: string;
    label: string;
    type?: string;
    options?: [string | number, string][];
    optional?: boolean;
};
type Action = { action: string; label: string; fields: Field[] };
const props = defineProps<{
    tenant: { id: number; name: string; slug: string };
    section: string;
    role: string;
    warehouses: Warehouse[];
    data: Record<string, unknown>;
}>();
const page = usePage();
const invitation = reactive({ email: '', role: 'cashier' });
const invitations = computed(
    () => (props.data.invitations || []) as Invitation[],
);
function inviteMember() {
    router.post(
        '/invitaciones',
        { tenant_id: props.tenant.id, ...invitation },
        { onError: (e) => (errors.value = e) },
    );
}
const resultHref = computed(
    () =>
        '/gestion/' +
        props.tenant.slug +
        '/pedido/' +
        (page.props.operationResult as { id: string } | undefined)?.id,
);
const auditRows = computed(
    () => (props.data.audit as { data: Row[] } | undefined)?.data || [],
);
const busy = ref(false);
const errors = ref<Record<string, string>>({});
const active = ref('');
const draft = reactive<Record<string, string>>({});
const key = ref(crypto.randomUUID());
const extraProducts = ref<Product[]>([]);
const products = computed(() => [
    ...extraProducts.value,
    ...((props.data.products || []) as Product[]).filter(
        (p) => !extraProducts.value.some((e) => e.id === p.id),
    ),
]);
function foundProduct(product: Product) {
    extraProducts.value = [
        product,
        ...extraProducts.value.filter((p) => p.id !== product.id),
    ];
    picker.value = String(product.id);
}
const customerTerm = ref('');
const extraCustomers = ref<Row[]>([]);
const customers = computed(() => [
    ...extraCustomers.value,
    ...((props.data.customers || []) as Row[]).filter(
        (c) => !extraCustomers.value.some((e) => e.id === c.id),
    ),
]);
const historyPages = computed(
    () =>
        (props.data.pages || []) as {
            label: string;
            links: { url: string | null; label: string; active: boolean }[];
        }[],
);
async function findCustomer() {
    try {
        const response = await fetch(
            `/gestion/${props.tenant.slug}/buscar-clientes?q=${encodeURIComponent(customerTerm.value)}`,
            { headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error('No se pudo buscar clientes.');
        extraCustomers.value = (await response.json()) as Row[];
        if (extraCustomers.value[0]) {
            sale.customer_id = String(extraCustomers.value[0].id);
            sale.customer_name = String(extraCustomers.value[0].name);
            sale.phone = String(extraCustomers.value[0].phone || '');
        }
    } catch (error) {
        errors.value = { operation: (error as Error).message };
    }
}

const sessions = computed(() =>
    ((props.data.cashSessions || []) as Row[]).filter((c) => !c.closed_at),
);
const wh = computed<[number, string][]>(() =>
    props.warehouses.map((w) => [w.id, `${w.name} · ${w.branch}`]),
);
const productOptions = computed<[number, string][]>(() =>
    products.value.map((p) => [p.id, `${p.sku} · ${p.name}`]),
);
const cashOptions = computed<[string | number, string][]>(() =>
    sessions.value.map((s) => [s.id as number, `Caja ${s.id}`]),
);
const field = (
    name: string,
    label: string,
    type = 'text',
    options?: [string | number, string][],
    optional = false,
): Field => ({ name, label, type, options, optional });
const warehouse = () => field('warehouse_id', 'Almacén', 'select', wh.value);
const product = () =>
    field('product_id', 'Producto / variante', 'select', productOptions.value);
const quantity = () => field('quantity', 'Unidades', 'number');
const reason = () => field('reason', 'Motivo', 'textarea');
const actions = computed<Action[]>(() => {
    const list: Record<string, Action[]> = {
        compras: [
            {
                action: 'supplier',
                label: 'Crear proveedor',
                fields: [
                    field('name', 'Nombre'),
                    field(
                        'reference',
                        'Documento / referencia',
                        'text',
                        undefined,
                        true,
                    ),
                    field('contact', 'Contacto', 'text', undefined, true),
                ],
            },
            {
                action: 'purchase',
                label: 'Borrador de compra',
                fields: [
                    field(
                        'supplier_id',
                        'Proveedor',
                        'select',
                        ((props.data.suppliers || []) as Row[]).map((s) => [
                            s.id as number,
                            String(s.name),
                        ]),
                    ),
                    warehouse(),
                    product(),
                    field('reference', 'Referencia documental'),
                    quantity(),
                    field(
                        'unit_cost_minor',
                        'Costo unitario gerencial S/',
                        'amount',
                    ),
                ],
            },
            {
                action: 'purchase_confirm',
                label: 'Confirmar compra',
                fields: [field('purchase_id', 'Número de compra', 'number')],
            },
            {
                action: 'purchase_receive',
                label: 'Recepción parcial',
                fields: [
                    field('purchase_id', 'Número de compra', 'number'),
                    quantity(),
                ],
            },
            {
                action: 'purchase_close',
                label: 'Cerrar saldo pendiente',
                fields: [field('purchase_id', 'Número de compra', 'number')],
            },
            {
                action: 'purchase_reverse',
                label: 'Revertir recepción',
                fields: [
                    field('purchase_id', 'Número de compra', 'number'),
                    field(
                        'receipt_id',
                        'Número de recepción de compra',
                        'number',
                    ),
                    reason(),
                ],
            },
        ],
        inventario: [
            {
                action: 'stock_count',
                label: 'Aprobar conteo físico',
                fields: [
                    warehouse(),
                    product(),
                    field('expected_physical', 'Existencia al corte', 'number'),
                    field('counted_physical', 'Unidades contadas', 'number'),
                    reason(),
                ],
            },
            {
                action: 'warehouse',
                label: 'Crear almacén',
                fields: [field('name', 'Nombre'), field('branch', 'Sucursal')],
            },
            {
                action: 'receipt',
                label: 'Recibir mercadería',
                fields: [
                    warehouse(),
                    product(),
                    quantity(),
                    field('unit_cost_minor', 'Costo unitario S/', 'amount'),
                    field('supplier', 'Proveedor'),
                    field('reference', 'Documento o referencia'),
                ],
            },
            {
                action: 'transfer',
                label: 'Enviar traslado',
                fields: [
                    field('source_id', 'Origen', 'select', wh.value),
                    field('destination_id', 'Destino', 'select', wh.value),
                    product(),
                    quantity(),
                ],
            },
            {
                action: 'receive_transfer',
                label: 'Recibir traslado',
                fields: [
                    field(
                        'transfer_id',
                        'Traslado',
                        'select',
                        ((props.data.transfers || []) as Row[]).map((t) => [
                            t.id as number,
                            `#${t.id} · ${Number(t.quantity) - Number(t.received) - Number(t.damaged)} por recibir`,
                        ]),
                    ),
                    quantity(),
                    field(
                        'damaged',
                        'De las recibidas, unidades dañadas (bloqueadas)',
                        'number',
                        undefined,
                        true,
                    ),
                ],
            },
            {
                action: 'stock_adjust',
                label: 'Bloquear / desbloquear',
                fields: [
                    warehouse(),
                    product(),
                    quantity(),
                    field('mode', 'Operación', 'select', [
                        ['block', 'Bloquear'],
                        ['unblock', 'Desbloquear'],
                        ['damage', 'Marcar dañado'],
                    ]),
                    reason(),
                ],
            },
        ],
        caja: [
            {
                action: 'reopen_cash',
                label: 'Reabrir caja cerrada',
                fields: [
                    field(
                        'cash_session_id',
                        'Caja cerrada',
                        'select',
                        ((props.data.cashSessions || []) as Row[])
                            .filter((c) => c.closed_at)
                            .map((c) => [
                                Number(c.id),
                                `Caja ${c.id} · ${localDate(String(c.closed_at))}`,
                            ]),
                    ),
                    reason(),
                ],
            },
            {
                action: 'expense',
                label: 'Registrar gasto',
                fields: [
                    field('amount_minor', 'Importe S/', 'amount'),
                    field('category', 'Categoría'),
                    reason(),
                    field(
                        'cash_session_id',
                        'Caja si se pagó en efectivo',
                        'select',
                        [
                            ['', 'Sin movimiento de efectivo'],
                            ...cashOptions.value,
                        ],
                        true,
                    ),
                ],
            },
            {
                action: 'open_cash',
                label: 'Abrir mi caja',
                fields: [
                    warehouse(),
                    field('opening_minor', 'Fondo inicial S/', 'amount'),
                ],
            },
            {
                action: 'cash_movement',
                label: 'Ingreso / retiro de caja',
                fields: [
                    field(
                        'cash_session_id',
                        'Caja',
                        'select',
                        cashOptions.value,
                    ),
                    field(
                        'amount_minor',
                        'Importe S/ (negativo para retiro)',
                        'signedamount',
                    ),
                    reason(),
                ],
            },
            {
                action: 'close_cash',
                label: 'Cerrar y arquear',
                fields: [
                    field(
                        'cash_session_id',
                        'Caja',
                        'select',
                        cashOptions.value,
                    ),
                    field('counted_minor', 'Efectivo contado S/', 'amount'),
                ],
            },
        ],
        clientes: [
            {
                action: 'customer_credit',
                label: 'Autorizar límite de crédito',
                fields: [
                    field(
                        'customer_id',
                        'Cliente',
                        'select',
                        customers.value.map((c) => [
                            c.id as number,
                            String(c.name),
                        ]),
                    ),
                    field('credit_limit_minor', 'Límite S/', 'amount'),
                    field('credit_days', 'Plazo en días', 'number'),
                ],
            },
            {
                action: 'customer',
                label: 'Nuevo cliente',
                fields: [
                    field('name', 'Nombre'),
                    field('phone', 'Teléfono', 'text', undefined, true),
                    field('email', 'Correo', 'email', undefined, true),
                    field('address', 'Dirección', 'textarea', undefined, true),
                    field(
                        'marketing_consent',
                        'Acepta comunicaciones comerciales',
                        'select',
                        [
                            ['0', 'No'],
                            ['1', 'Sí'],
                        ],
                    ),
                ],
            },
        ],
        promociones: [
            {
                action: 'price_rule',
                label: 'Programar oferta automática',
                fields: [
                    field('name', 'Nombre de la oferta'),
                    field('percent', 'Descuento %', 'number'),
                    field(
                        'product_id',
                        'Variante (opcional)',
                        'number',
                        undefined,
                        true,
                    ),
                    field(
                        'category',
                        'Categoría exacta (opcional)',
                        'text',
                        undefined,
                        true,
                    ),
                    field(
                        'channel',
                        'Canal',
                        'select',
                        [
                            ['', 'Ambos'],
                            ['web', 'Tienda'],
                            ['pos', 'POS'],
                        ],
                        true,
                    ),
                    field(
                        'starts_at',
                        'Desde (hora de Lima)',
                        'datetime-local',
                    ),
                    field('ends_at', 'Hasta (hora de Lima)', 'datetime-local'),
                ],
            },
            {
                action: 'coupon',
                label: 'Crear cupón',
                fields: [
                    field('code', 'Código'),
                    field('percent', 'Descuento %', 'number'),
                    field(
                        'amount_minor',
                        'O importe fijo S/ (reemplaza el porcentaje)',
                        'amount',
                        undefined,
                        true,
                    ),
                    field(
                        'category',
                        'Solo categoría exacta (opcional)',
                        'text',
                        undefined,
                        true,
                    ),
                    field(
                        'product_id',
                        'Solo número de variante (opcional)',
                        'number',
                        undefined,
                        true,
                    ),
                    field(
                        'channel',
                        'Solo canal (opcional)',
                        'select',
                        [
                            ['web', 'Tienda'],
                            ['pos', 'POS'],
                        ],
                        true,
                    ),
                    field(
                        'per_customer',
                        'Usos por cliente identificado (opcional)',
                        'number',
                        undefined,
                        true,
                    ),
                    field('minimum_minor', 'Compra mínima S/', 'amount'),
                    field('usage_limit', 'Usos máximos', 'number'),
                    field(
                        'starts_at',
                        'Inicio (hora de Lima)',
                        'datetime-local',
                    ),
                    field('ends_at', 'Fin (hora de Lima)', 'datetime-local'),
                ],
            },
        ],
        configuracion: [
            {
                action: 'delivery_zone',
                label: 'Crear zona o punto de recojo',
                fields: [
                    field('name', 'Distrito / nombre del punto'),
                    field('method', 'Método', 'select', [
                        ['pickup', 'Recojo'],
                        ['delivery', 'Delivery'],
                        ['courier', 'Courier'],
                        ['shipping', 'Envío nacional'],
                    ]),
                    field(
                        'address',
                        'Dirección del punto o zona',
                        'text',
                        undefined,
                        true,
                    ),
                    field('fee_minor', 'Tarifa S/', 'amount'),
                ],
            },
            {
                action: 'delivery_slot',
                label: 'Crear franja de atención',
                fields: [
                    field('method', 'Método', 'select', [
                        ['pickup', 'Recojo'],
                        ['delivery', 'Delivery'],
                        ['courier', 'Courier'],
                        ['shipping', 'Envío nacional'],
                    ]),
                    field(
                        'starts_at',
                        'Desde (hora de Lima)',
                        'datetime-local',
                    ),
                    field('ends_at', 'Hasta (hora de Lima)', 'datetime-local'),
                    field('capacity', 'Cupos máximos', 'number'),
                ],
            },
            {
                action: 'settings',
                label: 'Editar mi tienda',
                fields: [
                    field('publication', 'Guardar como', 'select', [
                        ['publish', 'Publicar en tienda'],
                        ['draft', 'Borrador privado'],
                    ]),
                    field(
                        'tagline',
                        'Descripción de bienvenida',
                        'textarea',
                        undefined,
                        true,
                    ),
                    field('theme', 'Color', 'select', [
                        ['verde', 'Verde'],
                        ['azul', 'Azul'],
                        ['violeta', 'Violeta'],
                    ]),
                    field(
                        'whatsapp',
                        'WhatsApp con código de país, sin +',
                        'text',
                        undefined,
                        true,
                    ),
                    field(
                        'yape_instructions',
                        'Instrucciones de Yape (titular y número)',
                        'textarea',
                        undefined,
                        true,
                    ),
                    field(
                        'bcp_instructions',
                        'Instrucciones BCP (titular y cuenta)',
                        'textarea',
                        undefined,
                        true,
                    ),
                    field(
                        'delivery_minor',
                        'Tarifa de entrega S/ (vacío: solo recojo)',
                        'amount',
                        undefined,
                        true,
                    ),
                ],
            },
        ],
    };
    return (list[props.section] || []).filter(
        (a) =>
            props.role === 'owner' ||
            ![
                'warehouse',
                'stock_adjust',
                'cash_movement',
                'customer_credit',
                'stock_count',
                'expense',
                'reopen_cash',
            ].includes(a.action),
    );
});
const selected = computed(() =>
    actions.value.find((a) => a.action === active.value),
);
function open(action: Action) {
    active.value = action.action;
    errors.value = {};
    key.value = crypto.randomUUID();
    for (const name of Object.keys(draft)) delete draft[name];
    for (const f of action.fields)
        draft[f.name] = f.options?.[0] ? String(f.options[0][0]) : '';
    if (action.action === 'settings') {
        const settings = (props.data.settings || {}) as Row;
        for (const f of action.fields)
            draft[f.name] =
                settings[f.name] == null
                    ? ''
                    : f.type === 'amount'
                      ? (Number(settings[f.name]) / 100).toFixed(2)
                      : String(settings[f.name]);
        draft.theme ||= 'verde';
    }
}
function send(action: string, data: Record<string, unknown>) {
    busy.value = true;
    errors.value = {};
    router.post(
        `/gestion/${props.tenant.slug}/operacion`,
        { action, key: key.value, ...data } as Parameters<
            typeof router.post
        >[1],
        {
            preserveScroll: true,
            onError: (e) => (errors.value = e),
            onSuccess: () => {
                key.value = crypto.randomUUID();
                active.value = '';
                if (action === 'pos_sale') {
                    cart.value = [];
                    sale.cash = '';
                    sale.received = '';
                    sale.digital = '';
                    sale.reference = '';
                    sale.digital_confirmed = false;
                    sale.customer_id = '';
                    sale.customer_name = '';
                    sale.phone = '';
                    sale.coupon = '';
                    sale.document_type = 'ticket';
                    sale.customer_document_type = 'dni';
                    sale.customer_document_number = '';
                    sale.customer_legal_name = '';
                }
            },
            onFinish: () => (busy.value = false),
        },
    );
}
function submit() {
    if (!selected.value) return;
    const data: Record<string, unknown> = {};
    try {
        for (const f of selected.value.fields) {
            const value = draft[f.name];
            data[f.name] =
                f.optional && !value
                    ? null
                    : f.type === 'amount'
                      ? minor(value)
                      : f.type === 'signedamount'
                        ? value.startsWith('-')
                            ? -minor(value.slice(1))
                            : minor(value)
                        : f.name === 'marketing_consent'
                          ? value === '1'
                          : value;
        }
        send(selected.value.action, data);
    } catch (e) {
        errors.value = { operation: (e as Error).message };
    }
}
const cart = ref<{ product_id: number; quantity: number }[]>([]);
const picker = ref('');
const units = ref(1);
const sale = reactive({
    warehouse_id: '',
    cash_session_id: '',
    customer_id: '',
    customer_name: '',
    phone: '',
    cash: '',
    received: '',
    digital_method: 'yape',
    digital: '',
    reference: '',
    digital_confirmed: false,
    coupon: '',
    document_type: 'ticket',
    customer_document_type: 'dni',
    customer_document_number: '',
    customer_legal_name: '',
});
function add() {
    const p = products.value.find((p) => p.id === Number(picker.value));
    if (!p || units.value < 1) return;
    const old = cart.value.find((l) => l.product_id === p.id);
    if (old) old.quantity += units.value;
    else cart.value.push({ product_id: p.id, quantity: units.value });
}
const subtotal = computed(() =>
    cart.value.reduce(
        (a, l) =>
            a +
            (products.value.find((p) => p.id === l.product_id)?.price_minor ||
                0) *
                l.quantity,
        0,
    ),
);
function sell() {
    if (sale.digital && !sale.digital_confirmed) {
        errors.value = {
            operation: 'Confirma que verificaste el pago digital en tu cuenta.',
        };
        return;
    }
    if (sale.document_type === 'factura') {
        const ruc = sale.customer_document_number.trim();
        if (!/^(10|15|17|20)\d{9}$/.test(ruc)) {
            errors.value = {
                operation:
                    'Para emitir Factura ingresa un RUC válido de 11 dígitos que inicie con 10, 15, 17 o 20.',
            };
            return;
        }
        if (!sale.customer_legal_name.trim()) {
            errors.value = {
                operation:
                    'Para emitir Factura es obligatoria la Razón Social de la empresa compradora.',
            };
            return;
        }
    }
    if (
        sale.document_type === 'boleta' &&
        subtotal.value >= 70000 &&
        !sale.customer_document_number.trim()
    ) {
        errors.value = {
            operation:
                'SUNAT: Para boletas de venta iguales o mayores a S/ 700.00 es obligatorio el DNI/documento del cliente.',
        };
        return;
    }
    try {
        send('pos_sale', {
            warehouse_id: sale.warehouse_id,
            cash_session_id: sale.cash_session_id,
            customer_id: sale.customer_id || null,
            customer_name:
                sale.document_type === 'factura' && sale.customer_legal_name
                    ? sale.customer_legal_name
                    : sale.customer_name,
            phone: sale.phone,
            delivery_method: 'pickup',
            items: cart.value,
            cash_minor: minor(sale.cash || '0'),
            cash_received_minor: minor(sale.received || sale.cash || '0'),
            payments: sale.digital
                ? [
                      {
                          method: sale.digital_method,
                          amount_minor: minor(sale.digital),
                          reference: sale.reference,
                      },
                  ]
                : [],
            coupon: sale.coupon || null,
            document_type: sale.document_type,
            customer_document_type:
                sale.document_type === 'factura'
                    ? 'ruc'
                    : sale.customer_document_type,
            customer_document_number: sale.customer_document_number.trim(),
            customer_legal_name: sale.customer_legal_name.trim(),
        });
    } catch (e) {
        errors.value = { operation: (e as Error).message };
    }
}
const rows = (name: string) => (props.data[name] || []) as Row[];
const report = computed(() => (props.data.report || {}) as Row);
const orders = computed(
    () =>
        (props.data.orders || { data: [], links: [] }) as {
            data: Row[];
            links: { url: string | null; label: string; active: boolean }[];
        },
);
const titles: Record<string, string> = {
    resumen: 'Tu negocio, en números',
    inventario: 'Inventario y almacenes',
    compras: 'Compras y proveedores',
    pedidos: 'Pedidos y ventas',
    pos: 'Punto de venta',
    caja: 'Control de caja',
    clientes: 'Tus clientes',
    promociones: 'Promociones',
    configuracion: 'La identidad de tu tienda',
    auditoria: 'Historial de actividad',
};
const reportCards = [
    ['Ventas brutas', 'gross_minor'],
    ['Descuentos', 'discount_minor'],
    ['Devoluciones', 'returns_minor'],
    ['Ventas netas', 'net_minor'],
    ['Cobros verificados', 'collections_minor'],
    ['Reembolsos', 'refunds_minor'],
    ['Deuda actual', 'debt_minor'],
    ['Costo de ventas', 'cost_minor'],
    ['Utilidad bruta parcial', 'gross_profit_minor'],
    ['Gastos registrados', 'expenses_minor'],
    ['Pérdidas de inventario', 'inventory_loss_minor'],
    ['Resultado gerencial parcial', 'management_result_minor'],
];
</script>

<template>
    <Head :title="titles[section]" /><BusinessShell
        :tenant="tenant"
        :role="role"
        :section="section"
    >
        <BusinessAdministration
            v-if="section === 'configuracion'"
            :tenant="tenant"
            :warehouses="warehouses"
            :data="data"
        />
        <ProductSearch
            v-if="['pos', 'inventario', 'compras'].includes(section)"
            :slug="tenant.slug"
            @selected="foundProduct"
        />
        <OperationalOptions :tenant="tenant" :data="data" :section="section" />
        <p class="mb-2 text-xs font-semibold tracking-widest text-emerald-700">
            GESTIÓN EMPRESARIAL
        </p>
        <h1 class="text-3xl font-semibold tracking-tight">
            {{ titles[section] }}
        </h1>
        <p class="mt-3 text-sm text-slate-500">
            Importes en soles · Hora de Lima · {{ tenant.name }}
        </p>
        <p
            v-if="page.props.success"
            role="status"
            class="mt-6 rounded-xl bg-emerald-100 p-4 text-emerald-900"
        >
            {{ page.props.success }}
        </p>
        <div
            v-if="Object.keys(errors).length"
            role="alert"
            class="my-5 rounded-xl bg-red-50 p-4 text-red-800"
        >
            <p v-for="(error, name) in errors" :key="name">{{ error }}</p>
        </div>
        <div class="my-7 flex flex-wrap gap-3">
            <button
                v-for="a in actions"
                :key="a.action"
                class="rounded-xl border bg-white px-4 py-2.5 text-sm font-medium hover:border-emerald-600"
                @click="open(a)"
            >
                {{ a.label }}
            </button>
        </div>
        <form
            v-if="selected"
            class="mb-8 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
            @submit.prevent="submit"
        >
            <h2 class="text-xl font-semibold md:col-span-2">
                {{ selected.label }}
            </h2>
            <label
                v-for="f in selected.fields"
                :key="f.name"
                class="grid gap-2 text-sm font-medium"
                >{{ f.label
                }}<select
                    v-if="f.options"
                    v-model="draft[f.name]"
                    class="rounded-lg border bg-white p-3"
                    :required="!f.optional"
                >
                    <option
                        v-for="[value, label] in f.options"
                        :key="value"
                        :value="String(value)"
                    >
                        {{ label }}
                    </option></select
                ><textarea
                    v-else-if="f.type === 'textarea'"
                    v-model="draft[f.name]"
                    :required="!f.optional"
                    rows="3"
                    class="rounded-lg border p-3" /><input
                    v-else
                    v-model="draft[f.name]"
                    :type="
                        ['amount', 'signedamount'].includes(f.type || '')
                            ? 'text'
                            : f.type
                    "
                    :inputmode="
                        ['amount', 'signedamount'].includes(f.type || '')
                            ? 'decimal'
                            : undefined
                    "
                    :required="!f.optional"
                    :min="
                        [
                            'expected_physical',
                            'counted_physical',
                            'credit_days',
                        ].includes(f.name)
                            ? 0
                            : f.type === 'number'
                              ? 1
                              : undefined
                    "
                    class="rounded-lg border p-3"
            /></label>
            <p
                v-if="selected.action === 'settings'"
                class="text-sm text-slate-500 md:col-span-2"
            >
                Los medios sin instrucciones permanecen deshabilitados. Confirma
                tu contraseña en Seguridad antes de cambiar cuentas de cobro.
            </p>
            <div class="flex gap-3 md:col-span-2">
                <button
                    :disabled="busy"
                    class="rounded-xl bg-emerald-800 px-6 py-3 text-white disabled:opacity-50"
                >
                    {{ busy ? 'Guardando…' : 'Guardar' }}</button
                ><button type="button" class="px-4" @click="active = ''">
                    Cancelar
                </button>
            </div>
        </form>

        <template v-if="section === 'resumen'"
            ><form method="get" class="mb-7 flex flex-wrap items-end gap-4">
                <label class="grid gap-2 text-sm"
                    >Desde<input
                        type="date"
                        name="from"
                        :value="report.from"
                        class="rounded-xl border bg-white p-3" /></label
                ><label class="grid gap-2 text-sm"
                    >Hasta<input
                        type="date"
                        name="to"
                        :value="report.to"
                        class="rounded-xl border bg-white p-3" /></label
                ><button class="rounded-xl bg-slate-900 p-3 text-white">
                    Aplicar periodo
                </button>
            </form>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="[label, value] in reportCards"
                    :key="value"
                    class="metric-card rounded-2xl border bg-white p-6"
                >
                    <p class="text-sm text-slate-500">{{ label }}</p>
                    <p class="mt-3 text-3xl font-semibold">
                        {{
                            report[value] === null
                                ? 'Costo incompleto'
                                : money(Number(report[value] || 0))
                        }}
                    </p>
                </article>
            </div>
            <p class="mt-6 text-sm leading-relaxed text-slate-500">
                La utilidad es gerencial y bruta: no incluye gastos ni ajustes
                tributarios. Las devoluciones y los cobros se cuentan en la
                fecha en que se registran. La deuda muestra el saldo actual de
                todas las ventas.
            </p></template
        >

        <template v-if="section === 'pos'"
            ><div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-2xl border bg-white p-6">
                    <h2 class="mb-5 text-lg font-semibold">Armar venta</h2>
                    <div class="flex gap-3">
                        <select
                            v-model="picker"
                            aria-label="Producto"
                            class="min-w-0 flex-1 rounded-xl border p-3"
                        >
                            <option value="">Selecciona producto</option>
                            <option
                                v-for="p in products"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.sku }} · {{ p.name }} ·
                                {{ money(p.price_minor) }}
                            </option></select
                        ><input
                            v-model.number="units"
                            aria-label="Cantidad"
                            type="number"
                            min="1"
                            max="10000"
                            class="w-20 rounded-xl border p-3"
                        /><button
                            class="rounded-xl bg-emerald-800 px-4 text-white"
                            @click="add"
                        >
                            Añadir
                        </button>
                    </div>
                    <ul class="mt-6 divide-y">
                        <li
                            v-for="(l, i) in cart"
                            :key="l.product_id"
                            class="flex justify-between gap-3 py-4"
                        >
                            <span
                                >{{ l.quantity }} ×
                                {{
                                    products.find((p) => p.id === l.product_id)
                                        ?.name
                                }}</span
                            ><button
                                class="text-sm text-red-700"
                                @click="cart.splice(i, 1)"
                            >
                                Quitar
                            </button>
                        </li>
                    </ul>
                    <p class="mt-5 text-2xl font-semibold">
                        Subtotal {{ money(subtotal) }}
                    </p>
                    <p class="mt-2 text-sm text-slate-500">
                        Los cupones se recalculan al confirmar.
                    </p>
                </section>
                <form
                    class="grid gap-4 rounded-2xl border bg-white p-6"
                    @submit.prevent="sell"
                >
                    <h2 class="text-lg font-semibold">
                        Cobrar o registrar crédito
                    </h2>

                    <!-- Comprobante Fiscal (Boleta / Factura / Nota de venta) -->
                    <div
                        class="rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <label
                            class="mb-2 block text-xs font-semibold tracking-wider text-slate-700 uppercase"
                        >
                            Comprobante a emitir
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                :class="[
                                    'rounded-xl border p-2 text-center text-xs font-medium transition',
                                    sale.document_type === 'ticket'
                                        ? 'border-emerald-700 bg-emerald-700 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100',
                                ]"
                                @click="
                                    sale.document_type = 'ticket';
                                    sale.customer_document_type = 'dni';
                                "
                            >
                                Nota de Venta
                                <span
                                    class="block font-mono text-[10px] opacity-80"
                                    >T001</span
                                >
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded-xl border p-2 text-center text-xs font-medium transition',
                                    sale.document_type === 'boleta'
                                        ? 'border-emerald-700 bg-emerald-700 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100',
                                ]"
                                @click="
                                    sale.document_type = 'boleta';
                                    sale.customer_document_type = 'dni';
                                "
                            >
                                Boleta de Venta
                                <span
                                    class="block font-mono text-[10px] opacity-80"
                                    >B001</span
                                >
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded-xl border p-2 text-center text-xs font-medium transition',
                                    sale.document_type === 'factura'
                                        ? 'border-emerald-700 bg-emerald-700 text-white shadow-sm'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-100',
                                ]"
                                @click="
                                    sale.document_type = 'factura';
                                    sale.customer_document_type = 'ruc';
                                "
                            >
                                Factura Comercial
                                <span
                                    class="block font-mono text-[10px] opacity-80"
                                    >F001</span
                                >
                            </button>
                        </div>

                        <!-- Detalle cuando es Factura -->
                        <div
                            v-if="sale.document_type === 'factura'"
                            class="mt-3 grid gap-3 border-t border-slate-200 pt-3"
                        >
                            <div>
                                <label
                                    class="block text-xs font-medium text-slate-700"
                                    >RUC de la empresa (11 dígitos) *</label
                                >
                                <input
                                    v-model="sale.customer_document_number"
                                    type="text"
                                    maxlength="11"
                                    placeholder="Ej. 20601234567"
                                    required
                                    class="mt-1 w-full rounded-xl border bg-white p-2.5 text-sm"
                                />
                                <span class="text-[11px] text-slate-500"
                                    >Debe comenzar con 10, 15, 17 o 20.</span
                                >
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-slate-700"
                                    >Razón Social *</label
                                >
                                <input
                                    v-model="sale.customer_legal_name"
                                    type="text"
                                    placeholder="Ej. DISTRIBUIDORA COMERCIAL S.A.C."
                                    required
                                    class="mt-1 w-full rounded-xl border bg-white p-2.5 text-sm"
                                />
                            </div>
                        </div>

                        <!-- Detalle cuando es Boleta -->
                        <div
                            v-else-if="sale.document_type === 'boleta'"
                            class="mt-3 grid gap-3 border-t border-slate-200 pt-3"
                        >
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                        >Doc.</label
                                    >
                                    <select
                                        v-model="sale.customer_document_type"
                                        class="mt-1 w-full rounded-xl border bg-white p-2.5 text-sm"
                                    >
                                        <option value="dni">DNI</option>
                                        <option value="ce">C.E.</option>
                                        <option value="pasaporte">
                                            Pasap.
                                        </option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Número
                                        {{
                                            subtotal >= 70000
                                                ? '*'
                                                : '(opcional)'
                                        }}
                                    </label>
                                    <input
                                        v-model="sale.customer_document_number"
                                        type="text"
                                        maxlength="15"
                                        :placeholder="
                                            sale.customer_document_type ===
                                            'dni'
                                                ? '8 dígitos'
                                                : 'Número'
                                        "
                                        :required="subtotal >= 70000"
                                        class="mt-1 w-full rounded-xl border bg-white p-2.5 text-sm"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="subtotal >= 70000"
                                class="text-[11px] font-medium text-amber-800"
                            >
                                SUNAT: Boletas iguales o mayores a S/ 700.00
                                requieren identificar al cliente con su
                                documento.
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <label
                            >Buscar cliente por nombre, teléfono o correo<input
                                v-model="customerTerm"
                                class="mt-2 w-full rounded-xl border p-3" /></label
                        ><button
                            type="button"
                            class="text-left text-emerald-800"
                            @click="findCustomer"
                        >
                            Buscar cliente en todo el negocio
                        </button>
                    </div>
                    <label class="grid gap-2 text-sm"
                        >Almacén<select
                            v-model="sale.warehouse_id"
                            required
                            class="rounded-xl border p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="w in warehouses"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }}
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Mi caja abierta<select
                            v-model="sale.cash_session_id"
                            required
                            class="rounded-xl border p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="c in sessions"
                                :key="String(c.id)"
                                :value="c.id"
                            >
                                Caja {{ c.id }}
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Cliente para crédito<select
                            v-model="sale.customer_id"
                            class="rounded-xl border p-3"
                            @change="
                                sale.customer_name = String(
                                    customers.find(
                                        (c) =>
                                            String(c.id) ===
                                            String(sale.customer_id),
                                    )?.name || sale.customer_name,
                                );
                                sale.phone = String(
                                    customers.find(
                                        (c) =>
                                            String(c.id) ===
                                            String(sale.customer_id),
                                    )?.phone || sale.phone,
                                );
                            "
                        >
                            <option value="">
                                Sin crédito / cliente ocasional
                            </option>
                            <option
                                v-for="c in customers"
                                :key="String(c.id)"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Nombre<input
                            v-model="sale.customer_name"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Teléfono<input
                            v-model="sale.phone"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Efectivo aplicado S/<input
                            v-model="sale.cash"
                            inputmode="decimal"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Cupón (opcional)<input
                            v-model="sale.coupon"
                            class="rounded-xl border p-3"
                    /></label>
                    <label class="grid gap-2 text-sm"
                        >Efectivo recibido S/<input
                            v-model="sale.received"
                            inputmode="decimal"
                            placeholder="Igual al aplicado si se deja vacío"
                            class="rounded-xl border p-3"
                    /></label>
                    <fieldset
                        v-if="role === 'owner'"
                        class="grid gap-3 rounded-xl border p-4"
                    >
                        <legend class="px-2 font-medium">
                            Pago mixto verificado (opcional)
                        </legend>
                        <select
                            v-model="sale.digital_method"
                            aria-label="Medio digital"
                            class="rounded-lg border p-3"
                        >
                            <option value="yape">Yape</option>
                            <option value="bcp">BCP / transferencia</option>
                        </select>
                        <label
                            >Importe digital S/<input
                                v-model="sale.digital"
                                inputmode="decimal"
                                class="mt-2 w-full rounded-lg border p-3"
                        /></label>
                        <label
                            >Referencia<input
                                v-model="sale.reference"
                                :required="!!sale.digital"
                                maxlength="120"
                                class="mt-2 w-full rounded-lg border p-3"
                        /></label>
                        <label
                            ><input
                                v-model="sale.digital_confirmed"
                                type="checkbox"
                            />
                            Verifiqué este ingreso en la cuenta del
                            negocio.</label
                        >
                    </fieldset>
                    <button
                        :disabled="busy || !cart.length"
                        class="rounded-xl bg-emerald-800 p-4 font-semibold text-white disabled:opacity-50"
                    >
                        Confirmar venta</button
                    ><Link
                        v-if="page.props.operationResult"
                        :href="resultHref"
                        class="text-emerald-700"
                        >Ver última operación →</Link
                    >
                </form>
            </div>
            <p class="mt-5 text-sm text-slate-500">
                El cajero registra efectivo y crédito. El propietario también
                puede registrar un pago mixto después de verificar el ingreso
                digital. Una captura no confirma el pago.
            </p></template
        >

        <template v-if="section === 'pedidos'"
            ><div class="overflow-x-auto rounded-2xl border bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="p-4">Referencia</th>
                            <th class="p-4">Cliente</th>
                            <th class="p-4">Canal</th>
                            <th class="p-4">Estado</th>
                            <th class="p-4">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="o in orders.data" :key="String(o.id)">
                            <td class="p-4">
                                <Link
                                    :href="`/gestion/${tenant.slug}/pedido/${o.id}`"
                                    class="font-semibold text-emerald-700"
                                    >{{ String(o.id).slice(0, 8) }} ↗</Link
                                >
                            </td>
                            <td class="p-4">{{ o.customer_name }}</td>
                            <td class="p-4">{{ state(o.channel) }}</td>
                            <td class="p-4">{{ state(o.status) }}</td>
                            <td class="p-4">
                                {{ money(Number(o.total_minor)) }}
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length">
                            <td
                                colspan="5"
                                class="p-12 text-center text-slate-400"
                            >
                                Todavía no hay pedidos ni ventas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav class="mt-5 flex gap-3">
                <template v-for="(l, i) in orders.links" :key="i"
                    ><Link
                        v-if="l.url"
                        :href="l.url"
                        class="rounded-lg border bg-white p-3"
                        >{{
                            i === 0
                                ? 'Anterior'
                                : i === orders.links.length - 1
                                  ? 'Siguiente'
                                  : l.label
                        }}</Link
                    ></template
                >
            </nav></template
        >

        <template v-if="section === 'inventario'"
            ><h2 class="mb-4 text-lg font-semibold">Existencias por almacén</h2>
            <div class="overflow-auto rounded-2xl border bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-4">Producto</th>
                            <th class="p-4">Almacén</th>
                            <th class="p-4">Físico</th>
                            <th class="p-4">Reservado</th>
                            <th class="p-4">Bloqueado</th>
                            <th class="p-4">Disponible</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(s, i) in rows('stock')" :key="i">
                            <td class="p-4">
                                {{
                                    products.find(
                                        (p) => p.id === Number(s.product_id),
                                    )?.name
                                }}
                            </td>
                            <td class="p-4">
                                {{
                                    warehouses.find(
                                        (w) => w.id === Number(s.warehouse_id),
                                    )?.name
                                }}
                            </td>
                            <td class="p-4">{{ s.physical }}</td>
                            <td class="p-4">{{ s.reserved }}</td>
                            <td class="p-4">{{ s.blocked }}</td>
                            <td class="p-4 font-semibold">
                                {{
                                    Number(s.physical) -
                                    Number(s.reserved) -
                                    Number(s.blocked) -
                                    Number(s.offline)
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!rows('stock').length"
                    class="p-12 text-center text-slate-400"
                >
                    Crea un almacén y registra tu primera recepción.
                </p>
            </div>
            <h2 class="mt-9 mb-4 text-lg font-semibold">Últimos movimientos</h2>
            <div class="divide-y rounded-2xl border bg-white">
                <div
                    v-for="m in rows('movements')"
                    :key="String(m.id)"
                    class="grid gap-2 p-4 text-sm md:grid-cols-4"
                >
                    <span>{{ localDate(String(m.created_at)) }}</span
                    ><strong>{{ state(m.kind) }}</strong
                    ><span
                        >{{
                            products.find((p) => p.id === Number(m.product_id))
                                ?.name
                        }}
                        · {{ m.quantity }} unidades</span
                    ><span class="text-slate-500">{{ m.reason }}</span>
                </div>
            </div></template
        >

        <template v-if="section === 'compras'">
            <PurchaseDocumentForm
                :tenant="tenant"
                :products="products"
                :warehouses="warehouses"
                :suppliers="rows('suppliers')"
            />
            <p class="mb-5 text-sm text-slate-600">
                Cada borrador corresponde a una variante. El costo unitario
                incluye costos gerenciales asignados por el responsable; no
                calcula impuestos recuperables. Confirmar no aumenta
                existencias: recibir sí. Un reverso necesita unidades
                disponibles y costo suficiente.
            </p>
            <h2 class="mb-3 text-lg font-semibold">Documentos de compra</h2>
            <div class="overflow-auto rounded-2xl border bg-white">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th class="p-4">Compra</th>
                            <th>Referencia</th>
                            <th>Producto</th>
                            <th>Solicitadas / recibidas</th>
                            <th>Costo unitario</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in rows('purchases')"
                            :key="String(p.id)"
                            class="border-t"
                        >
                            <td class="p-4">#{{ p.id }}</td>
                            <td>{{ p.reference }}</td>
                            <td>
                                {{
                                    products.find(
                                        (x) => x.id === Number(p.product_id),
                                    )?.name
                                }}
                            </td>
                            <td>{{ p.quantity }} / {{ p.received }}</td>
                            <td>{{ money(Number(p.unit_cost_minor)) }}</td>
                            <td>{{ state(p.status) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <h2 class="mt-7 mb-3 text-lg font-semibold">Recepciones</h2>
            <div
                v-for="r in rows('purchaseReceipts')"
                :key="String(r.id)"
                class="border-b bg-white p-4 text-sm"
            >
                Recepción #{{ r.id }} · Compra #{{ r.purchase_id }} ·
                {{ r.quantity }} unidades ·
                {{
                    r.reversed
                        ? 'Revertida con registro compensatorio'
                        : 'Aplicada'
                }}
            </div>
        </template>
        <template v-if="section === 'caja'"
            ><div class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="c in rows('cashSessions')"
                    :key="String(c.id)"
                    class="rounded-2xl border bg-white p-6"
                >
                    <h2 class="font-semibold">
                        Caja {{ c.id }} ·
                        {{ c.closed_at ? 'Cerrada' : 'Abierta' }}
                    </h2>
                    <p class="mt-4 text-sm text-slate-500">Efectivo esperado</p>
                    <p class="text-2xl font-semibold">
                        {{ money(Number(c.expected_minor)) }}
                    </p>
                    <p v-if="c.closed_at" class="mt-2 text-sm">
                        Contado {{ money(Number(c.counted_minor)) }} ·
                        Diferencia
                        {{
                            money(
                                Number(c.counted_minor) -
                                    Number(c.expected_minor),
                            )
                        }}
                    </p>
                </article>
            </div>
            <div class="mt-8 divide-y rounded-2xl border bg-white">
                <div
                    v-for="e in rows('cashEntries')"
                    :key="String(e.id)"
                    class="flex justify-between gap-4 p-4 text-sm"
                >
                    <span>{{ e.reason }}</span
                    ><strong>{{ money(Number(e.amount_minor)) }}</strong>
                </div>
            </div></template
        >
        <template v-if="section === 'clientes'"
            ><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="c in customers"
                    :key="String(c.id)"
                    class="rounded-2xl border bg-white p-6"
                >
                    <h2 class="font-semibold">{{ c.name }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ c.phone }}<br />{{ c.email }}
                    </p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div>
                            Compras: {{ c.purchases_count || 0 }} · Gasto
                            acumulado: {{ money(Number(c.spent_minor || 0)) }}
                        </div>
                        <div>
                            Deuda actual: {{ money(Number(c.debt_minor || 0)) }}
                        </div>
                        <div>
                            Última compra:
                            {{
                                c.last_purchase_at
                                    ? localDate(String(c.last_purchase_at))
                                    : 'Sin compras confirmadas'
                            }}
                        </div>
                    </dl>
                    <p class="mt-2 text-xs font-medium text-emerald-700">
                        {{
                            Number(c.debt_minor) > 0
                                ? 'Con deuda pendiente'
                                : Number(c.purchases_count) >= 3
                                  ? 'Cliente recurrente'
                                  : c.last_purchase_at &&
                                      Date.now() -
                                          new Date(
                                              String(c.last_purchase_at),
                                          ).getTime() >
                                          90 * 86400000
                                    ? 'Sin compras en 90 días'
                                    : 'Seguimiento habitual'
                        }}
                    </p>
                    <p class="mt-3 text-xs text-slate-400">
                        {{
                            c.marketing_consent
                                ? 'Comunicaciones autorizadas'
                                : 'Sin autorización de marketing'
                        }}
                    </p>
                </article>
            </div></template
        >
        <template v-if="section === 'promociones'"
            ><div class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="c in rows('coupons')"
                    :key="String(c.id)"
                    class="rounded-2xl border bg-white p-6"
                >
                    <h2 class="text-2xl font-semibold">
                        {{ c.code }} ·
                        {{
                            c.amount_minor
                                ? money(Number(c.amount_minor))
                                : `${c.percent}%`
                        }}
                    </h2>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ c.used }} de {{ c.usage_limit }} usos · Compra mínima
                        {{ money(Number(c.minimum_minor)) }}
                    </p>
                    <p class="mt-2 text-sm">
                        {{ localDate(String(c.starts_at)) }} —
                        {{ localDate(String(c.ends_at)) }}
                    </p>
                </article>
            </div></template
        >
        <template v-if="section === 'configuracion'"
            ><div class="rounded-2xl border bg-white p-7">
                <h2 class="text-xl font-semibold">Tu tienda pública</h2>
                <p class="mt-3 text-slate-500">
                    Abre el diseñador para cambiar tu logo, datos y estilo
                    visual. La configuración de entrega y medios de pago se
                    mantiene en esta sección.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Link
                        :href="`/gestion/${tenant.slug}/configurar`"
                        class="inline-block rounded-xl bg-emerald-800 px-5 py-3 text-white"
                        >Diseñar mi tienda</Link
                    ><Link
                        :href="`/tienda/${tenant.slug}`"
                        class="inline-block rounded-xl border px-5 py-3"
                        >Ver tienda ↗</Link
                    >
                </div>
            </div>
            <div
                class="mt-6 rounded-2xl border bg-amber-50 p-6 text-sm text-amber-900"
            >
                SUNAT: sin proveedor contratado. Los tickets son documentos
                internos. No se emitirán comprobantes electrónicos reales.
            </div>
            <section class="mt-7 rounded-2xl border bg-white p-6">
                <h2 class="text-xl font-semibold">Versiones de mi tienda</h2>
                <article
                    v-for="v in rows('versions')"
                    :key="String(v.id)"
                    class="mt-4 flex flex-wrap justify-between gap-3 border-t pt-4 text-sm"
                >
                    <span
                        >Versión {{ v.id }} ·
                        {{ v.published_at ? 'Publicada' : 'Borrador' }} ·
                        {{ localDate(String(v.created_at)) }}</span
                    >
                    <div class="flex gap-4">
                        <Link
                            :href="`/gestion/${tenant.slug}/vista-previa/${v.id}`"
                            class="text-emerald-700"
                            >Vista previa</Link
                        ><button
                            :disabled="busy"
                            @click="
                                send('restore_settings', { version_id: v.id })
                            "
                        >
                            Publicar esta versión
                        </button>
                    </div>
                </article>
            </section>
            <section class="mt-7 rounded-2xl border bg-white p-6">
                <h2 class="text-xl font-semibold">Mi equipo</h2>
                <div
                    v-for="m in rows('members')"
                    :key="String(m.id)"
                    class="mt-4 flex flex-wrap justify-between gap-3 border-t pt-4 text-sm"
                >
                    <div>
                        <strong>{{ m.name }}</strong>
                        <p class="text-slate-500">
                            {{ m.email }} ·
                            {{
                                m.role === 'owner'
                                    ? 'Propietario'
                                    : m.role === 'cashier'
                                      ? 'Caja'
                                      : m.role === 'catalog'
                                        ? 'Catálogo'
                                        : 'Almacén'
                            }}
                        </p>
                    </div>
                    <Link
                        :href="`/gestion/${tenant.slug}/equipo/${m.id}`"
                        method="delete"
                        as="button"
                        class="text-red-700"
                        >Revocar acceso</Link
                    >
                </div>
                <form
                    class="mt-6 grid gap-4 md:grid-cols-3"
                    @submit.prevent="inviteMember"
                >
                    <label class="grid gap-2 text-sm"
                        >Correo a invitar<input
                            v-model="invitation.email"
                            type="email"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Rol<select
                            v-model="invitation.role"
                            class="rounded-xl border p-3"
                        >
                            <option value="cashier">Caja</option>
                            <option value="warehouse">Almacén</option>
                            <option value="owner">Propietario</option>
                            <option value="catalog">Solo catálogo</option>
                        </select></label
                    ><button
                        class="self-end rounded-xl bg-emerald-800 p-3 text-white"
                    >
                        Crear invitación
                    </button>
                </form>
                <p class="mt-4 text-xs text-slate-500">
                    Requiere contraseña confirmada recientemente. En local el
                    correo se guarda en el registro de desarrollo.
                </p>
                <InvitationList
                    :invitations="invitations"
                    :tenant-id="tenant.id"
                />
            </section>
        </template>
        <template v-if="section === 'auditoria'"
            ><div class="divide-y rounded-2xl border bg-white">
                <div
                    v-for="a in auditRows"
                    :key="String(a.id)"
                    class="grid gap-2 p-4 text-sm md:grid-cols-3"
                >
                    <span>{{ localDate(String(a.created_at)) }}</span
                    ><strong>{{ a.action }}</strong
                    ><span class="truncate text-slate-500"
                        >Actor {{ a.actor_id || 'Invitado' }} ·
                        {{ a.reference }}</span
                    >
                </div>
            </div></template
        >
        <nav
            v-for="history in historyPages"
            :key="history.label"
            :aria-label="`Paginación de ${history.label}`"
            class="mt-8 flex flex-wrap items-center gap-3"
        >
            <strong class="mr-3 text-sm">{{ history.label }}</strong>
            <template v-for="(link, index) in history.links" :key="index"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'rounded-lg border px-3 py-2 text-sm',
                        link.active
                            ? 'bg-emerald-800 text-white'
                            : 'bg-white text-emerald-900',
                    ]"
                    >{{
                        index === 0
                            ? 'Anterior'
                            : index === history.links.length - 1
                              ? 'Siguiente'
                              : link.label
                    }}</Link
                ></template
            >
        </nav>
    </BusinessShell>
</template>
