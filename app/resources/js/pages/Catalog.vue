<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Baby,
    BadgePercent,
    Flame,
    Layers3,
    LayoutGrid,
    Package,
    Shirt,
    ShoppingBag,
    Sparkles,
    MapPin,
} from '@lucide/vue';
import BusinessShell from '@/components/BusinessShell.vue';
import { money, minor, jsonPost } from '@/lib/commerce';
type Product = {
    id: number;
    sku: string;
    name: string;
    price_minor: number;
    compare_at_minor: number | null;
    published: boolean;
    category: string;
    audience: 'women' | 'men' | 'kids' | 'unisex';
    trending: boolean;
    variant_group: string;
    size: string;
    color: string;
    description: string;
    image_url: string | null;
};
const props = defineProps<{
    tenant: { name: string; slug: string };
    managing: boolean;
    role?: string | null;
    preview?: boolean;
    categories: string[];
    category: string;
    section: string;
    importPreview: { token: string; count: number; sample: Product[] } | null;
    search: string;
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    warehouses: { id: number; name: string; branch: string }[];
    settings: {
        tagline?: string;
        description?: string;
        theme?: string;
        storefront_theme?:
            | 'classic'
            | 'urban'
            | 'elegant'
            | 'feminine'
            | 'pastel'
            | 'glamour'
            | 'sport'
            | 'footwear'
            | 'minimal'
            | 'vintage';
        business_type?: string;
        location?: string;
        contact_email?: string;
        whatsapp?: string;
        delivery_minor?: number;
        courier_minor?: number;
        shipping_minor?: number;
        delivery_methods?: string[];
        logo_url?: string | null;
        banner_url?: string | null;
        instagram_url?: string;
        facebook_url?: string;
        tiktok_url?: string;
        primary_color?: string;
        accent_color?: string;
        section_order?: string[];
        business_hours?: {
            day: string;
            open: string;
            close: string;
            closed: boolean;
        }[];
        custom_domain?: string | null;
        marketplace_url?: string;
    };
    availability: { product_id: number; available: number }[];
    customer: {
        name: string;
        phone: string;
        email: string;
        address: string;
    } | null;
    featuredCoupon: {
        code: string;
        percent: number;
        amount_minor: number | null;
        minimum_minor: number;
        ends_at: string;
    } | null;
}>();
const page = usePage();
const editing = ref<number | null>(null);
const form = useForm({
    sku: '',
    name: '',
    price_minor: 0,
    compare_at_minor: null as number | null,
    published: false,
    category: '',
    audience: 'unisex' as 'women' | 'men' | 'kids' | 'unisex',
    trending: false,
    variant_group: '',
    size: '',
    color: '',
    description: '',
    image: null as File | null,
});
const photoVersion = ref(0);
const importFile = useForm({ file: null as File | null });
const importCommit = useForm({ token: props.importPreview?.token || '' });
const photoProduct = ref('');
const photo = useForm({ image: null as File | null });
function edit(p: Product) {
    editing.value = p.id;
    form.sku = p.sku;
    form.name = p.name;
    form.price_minor = p.price_minor;
    form.compare_at_minor = p.compare_at_minor;
    form.published = p.published;
    form.category = p.category;
    form.audience = p.audience;
    form.trending = p.trending;
    form.variant_group = p.variant_group;
    form.size = p.size;
    form.color = p.color;
    form.description = p.description;
    form.image = null;
    photoVersion.value++;
    price.value = (p.price_minor / 100).toFixed(2);
    comparePrice.value = p.compare_at_minor
        ? (p.compare_at_minor / 100).toFixed(2)
        : '';
}
function reset() {
    editing.value = null;
    form.reset();
    price.value = '';
    comparePrice.value = '';
    photoVersion.value++;
}
const price = ref('');
const comparePrice = ref('');
function save() {
    try {
        form.price_minor = minor(price.value);
        form.compare_at_minor = comparePrice.value.trim()
            ? minor(comparePrice.value)
            : null;
        form.transform((data) => ({
            ...data,
            ...(editing.value ? { _method: 'patch' } : {}),
        })).post(
            `/gestion/${props.tenant.slug}/productos${editing.value ? '/' + editing.value : ''}`,
            {
                forceFormData: true,
                onSuccess: () => {
                    form.reset();
                    price.value = '';
                    comparePrice.value = '';
                    editing.value = null;
                    photoVersion.value++;
                },
            },
        );
    } catch (e) {
        form.setError('price_minor', (e as Error).message);
    }
}
const audienceLabel = (audience: Product['audience']) =>
    ({ women: 'Mujer', men: 'Hombre', kids: 'Niños', unisex: 'Unisex' })[
        audience
    ];
const cart = ref<
    { product_id: number; quantity: number; name: string; unit: number }[]
>([]);
const storageKey = `cart:${props.tenant.slug}`;
try {
    const stored = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
    if (Array.isArray(stored)) cart.value = stored.slice(0, 100);
} catch {
    cart.value = [];
}
const checkout = useForm({
    key: crypto.randomUUID(),
    warehouse_id: props.warehouses[0]?.id || '',
    customer_name: props.customer?.name || '',
    phone: props.customer?.phone || '',
    email: props.customer?.email || '',
    address: props.customer?.address || '',
    delivery_method: props.settings.delivery_methods?.[0] || 'pickup',
    coupon: props.featuredCoupon?.code || '',
    items: [] as { product_id: number; quantity: number }[],
    expected_total_minor: 0,
});
const quote = ref<Record<string, unknown> | null>(null);
const quoteError = ref('');
const quoting = ref(false);
watch(
    cart,
    () => {
        sessionStorage.setItem(storageKey, JSON.stringify(cart.value));
        quote.value = null;
    },
    { deep: true },
);
watch(
    () => [checkout.coupon, checkout.delivery_method],
    () => (quote.value = null),
);
const count = computed(() => cart.value.reduce((a, l) => a + l.quantity, 0));
const total = computed(() =>
    cart.value.reduce((a, l) => a + l.quantity * l.unit, 0),
);
function add(p: Product) {
    const old = cart.value.find((l) => l.product_id === p.id);
    if (old) old.quantity++;
    else
        cart.value.push({
            product_id: p.id,
            quantity: 1,
            name: p.name,
            unit: p.price_minor,
        });
}
async function review() {
    quoting.value = true;
    quoteError.value = '';
    checkout.coupon = checkout.coupon.trim().toUpperCase();
    try {
        quote.value = await jsonPost(`/tienda/${props.tenant.slug}/cotizar`, {
            items: cart.value.map((l) => ({
                product_id: l.product_id,
                quantity: l.quantity,
            })),
            coupon: checkout.coupon || null,
            delivery_method: checkout.delivery_method,
        });
    } catch (e) {
        quoteError.value = (e as Error).message;
    } finally {
        quoting.value = false;
    }
}
function order() {
    if (!quote.value) return;
    checkout.items = cart.value.map((l) => ({
        product_id: l.product_id,
        quantity: l.quantity,
    }));
    checkout.expected_total_minor = Number(quote.value.total_minor);
    checkout.post(`/tienda/${props.tenant.slug}/pedido`, {
        onSuccess: () => {
            cart.value = [];
            sessionStorage.removeItem(storageKey);
        },
    });
}
const available = (id: number) =>
    Number(props.availability.find((a) => a.product_id === id)?.available || 0);
const theme = computed(() =>
    props.settings.primary_color
        ? props.settings.primary_color
        : props.settings.theme === 'azul'
          ? '#173e68'
          : props.settings.theme === 'violeta'
            ? '#503267'
            : '#163c32',
);
const accent = computed(() => props.settings.accent_color || '#b9e769');
const storefrontStyle = computed(() => ({
    '--store-primary': theme.value,
    '--store-accent': accent.value,
}));
const fashionBusiness = computed(() =>
    ['fashion', 'footwear', 'accessories'].includes(
        props.settings.business_type ||
            (page.props.singleBusiness ? 'general' : 'fashion'),
    ),
);
const sectionDefinitions = {
    all: { label: 'Todo', icon: LayoutGrid, query: '' },
    women: { label: 'Mujer', icon: Sparkles, query: 'women' },
    men: { label: 'Hombre', icon: Shirt, query: 'men' },
    kids: { label: 'Niños', icon: Baby, query: 'kids' },
    trends: { label: 'Tendencias', icon: Flame, query: 'trends' },
    promotions: {
        label: 'Promociones',
        icon: BadgePercent,
        query: 'promotions',
    },
} as const;
const sectionLinks = computed(() => {
    const requested =
        props.settings.section_order || Object.keys(sectionDefinitions);
    return requested
        .filter((key): key is keyof typeof sectionDefinitions =>
            Object.hasOwn(sectionDefinitions, key),
        )
        .filter(
            (key) =>
                fashionBusiness.value ||
                !['women', 'men', 'kids'].includes(key),
        )
        .map((key) => ({
            key,
            ...sectionDefinitions[key],
            ...(key === 'trends' && !fashionBusiness.value
                ? { label: 'Destacados' }
                : {}),
        }));
});
const deliveryOptions = computed(() => {
    const enabled = props.settings.delivery_methods || [
        'pickup',
        ...(props.settings.delivery_minor != null ? ['delivery'] : []),
    ];
    const definitions: Record<string, { label: string; fee: number }> = {
        pickup: { label: 'Recojo en tienda', fee: 0 },
        delivery: {
            label: 'Delivery local',
            fee: props.settings.delivery_minor || 0,
        },
        courier: {
            label: 'Courier',
            fee: props.settings.courier_minor || 0,
        },
        shipping: {
            label: 'Envío nacional',
            fee: props.settings.shipping_minor || 0,
        },
    };
    return enabled
        .filter((key) => definitions[key])
        .map((key) => ({
            value: key,
            ...definitions[key],
        }));
});
const dayLabels: Record<string, string> = {
    monday: 'Lunes',
    tuesday: 'Martes',
    wednesday: 'Miércoles',
    thursday: 'Jueves',
    friday: 'Viernes',
    saturday: 'Sábado',
    sunday: 'Domingo',
};
const storefrontTheme = computed(() =>
    [
        'classic',
        'urban',
        'elegant',
        'feminine',
        'pastel',
        'glamour',
        'sport',
        'footwear',
        'minimal',
        'vintage',
    ].includes(props.settings.storefront_theme || '')
        ? props.settings.storefront_theme
        : 'classic',
);
const themeClass = computed(() => `storefront-theme-${storefrontTheme.value}`);
const themeEyebrow = computed(() =>
    !fashionBusiness.value
        ? 'BIENVENIDO A NUESTRO NEGOCIO'
        : {
              classic: 'BIENVENIDO A NUESTRA TIENDA',
              urban: 'NUEVA COLECCIÓN · STREET EDIT',
              elegant: 'UNA SELECCIÓN PARA TI',
              feminine: 'FEMENINO · SUAVE · AUTÉNTICO',
              pastel: 'BOUTIQUE DE TEMPORADA',
              glamour: 'GLAMOUR · EDICIÓN ESPECIAL',
              sport: 'MUÉVETE SIN LÍMITES',
              footwear: 'CALZADO · COLECCIÓN EDITORIAL',
              minimal: 'MENOS RUIDO · MÁS ESTILO',
              vintage: 'PIEZAS CON HISTORIA',
          }[storefrontTheme.value || 'classic'],
);
</script>
<template>
    <Head :title="tenant.name" /><component
        :is="managing ? BusinessShell : 'div'"
        :tenant="tenant"
        :role="role || 'owner'"
        section="catalogo"
        class="sas-app storefront min-h-screen text-slate-900"
        :class="themeClass"
        :style="storefrontStyle"
    >
        <header
            v-if="!managing"
            class="storefront-header border-b bg-white p-5"
        >
            <nav class="mx-auto flex max-w-7xl items-center justify-between">
                <Link :href="`/tienda/${tenant.slug}`" class="storefront-brand"
                    ><img
                        v-if="settings.logo_url"
                        :src="settings.logo_url"
                        :alt="`Logo de ${tenant.name}`"
                    /><span v-else class="brand-symbol"
                        ><Layers3 :size="20"
                    /></span>
                    <strong>{{ tenant.name }}</strong></Link
                >
                <div class="flex gap-5 text-sm">
                    <Link
                        v-if="!page.props.singleBusiness"
                        :href="settings.marketplace_url || '/'"
                        class="storefront-home-link"
                        aria-label="Volver a todas las tiendas"
                        ><ArrowLeft :size="16" />
                        <span>Todas las tiendas</span></Link
                    >
                    <Link v-else href="/">Inicio</Link>
                    <Link :href="`/tienda/${tenant.slug}/seguimiento`"
                        >Seguir mi pedido</Link
                    ><a href="#carrito" class="storefront-cart-link"
                        ><ShoppingBag :size="16" />Carrito
                        <span class="storefront-cart-count">{{
                            count
                        }}</span></a
                    ><Link :href="`/tienda/${tenant.slug}/cuenta`"
                        >Mi cuenta</Link
                    >
                </div>
            </nav>
        </header>
        <div :class="managing ? '' : 'mx-auto max-w-7xl p-6 lg:p-10'">
            <template v-if="!managing">
                <section
                    class="storefront-hero mb-10 rounded-3xl px-8 py-12 text-white md:px-12"
                    :class="{ 'has-custom-banner': settings.banner_url }"
                    :style="
                        settings.banner_url
                            ? {
                                  backgroundImage: `linear-gradient(90deg, ${theme}f2 0%, ${theme}b8 55%, ${theme}52 100%), url(${settings.banner_url})`,
                              }
                            : { backgroundColor: theme }
                    "
                >
                    <div class="storefront-theme-particles" aria-hidden="true">
                        <span v-for="particle in 8" :key="particle"></span>
                    </div>
                    <p class="text-xs tracking-widest opacity-70">
                        {{ themeEyebrow }}
                    </p>
                    <h1 class="mt-5 text-4xl font-semibold md:text-5xl">
                        {{ tenant.name }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg opacity-80">
                        {{
                            settings.tagline ||
                            'Encuentra lo que buscas. Haz tu pedido y coordina directamente con nuestro negocio.'
                        }}
                    </p>
                    <p
                        v-if="settings.description"
                        class="storefront-description"
                    >
                        {{ settings.description }}
                    </p>
                    <span v-if="settings.location" class="storefront-location"
                        ><MapPin :size="14" /> {{ settings.location }}</span
                    >
                    <div v-if="featuredCoupon" class="storefront-coupon">
                        <BadgePercent :size="18" />
                        <span>
                            <small>CUPÓN DESTACADO</small>
                            <strong>{{ featuredCoupon.code }}</strong>
                        </span>
                        <span>
                            {{
                                featuredCoupon.amount_minor
                                    ? money(featuredCoupon.amount_minor)
                                    : `${featuredCoupon.percent}%`
                            }}
                            de descuento
                        </span>
                    </div>
                </section>
                <nav
                    class="storefront-fashion-navigation"
                    aria-label="Secciones de la tienda"
                >
                    <Link
                        v-for="item in sectionLinks"
                        :key="item.key"
                        :href="
                            item.query
                                ? `/tienda/${tenant.slug}?section=${item.query}`
                                : `/tienda/${tenant.slug}`
                        "
                        :class="{
                            active: item.query
                                ? section === item.query
                                : !section,
                        }"
                        ><component :is="item.icon" :size="16" />
                        {{ item.label }}</Link
                    >
                </nav>
            </template>
            <template v-else
                ><p
                    class="text-xs font-semibold tracking-widest text-emerald-700"
                >
                    GESTIÓN EMPRESARIAL
                </p>
                <h1 class="mt-2 text-3xl font-semibold">Tu catálogo</h1>
                <p class="mt-3 text-slate-500">
                    Crea productos, añade fotos y decide cuáles mostrar en tu
                    tienda.
                </p></template
            >
            <form method="get" class="catalog-search my-7 flex gap-3">
                <input
                    v-if="section"
                    type="hidden"
                    name="section"
                    :value="section"
                />
                <select
                    v-if="categories.length"
                    name="category"
                    :value="category"
                    aria-label="Categoría"
                    class="max-w-40 rounded-xl border bg-white p-3"
                >
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c" :value="c">
                        {{ c }}
                    </option>
                </select>
                <input
                    name="q"
                    :value="search"
                    aria-label="Buscar productos"
                    placeholder="Buscar por nombre…"
                    class="w-full max-w-lg rounded-xl border bg-white p-3"
                /><button class="rounded-xl bg-slate-900 px-6 text-white">
                    Buscar
                </button>
            </form>
            <form
                id="product-form"
                v-if="managing"
                class="mb-9 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
                @submit.prevent="save"
            >
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold">
                        {{ editing ? 'Editar producto' : 'Subir un producto' }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        Completa el nombre y precio. Puedes añadir una foto
                        ahora. El propietario o almacén registra las existencias
                        desde Inventario.
                    </p>
                </div>
                <label class="grid gap-2 text-sm"
                    >Nombre del producto<input
                        v-model="form.name"
                        required
                        maxlength="160"
                        placeholder="Ej. Polo de algodón azul"
                        class="rounded-xl border p-3"
                /></label>
                <label class="grid gap-2 text-sm"
                    >Precio en soles<input
                        v-model="price"
                        inputmode="decimal"
                        required
                        placeholder="59.90"
                        class="rounded-xl border p-3"
                /></label>
                <label class="grid gap-2 text-sm md:col-span-2"
                    >Foto del producto (opcional)<input
                        :key="photoVersion"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="rounded-xl border p-3"
                        @change="
                            form.image =
                                ($event.target as HTMLInputElement)
                                    .files?.[0] || null
                        "
                    /><small class="text-slate-500"
                        >JPG, PNG o WEBP. Hasta 3 MB. Se procesa una copia sin
                        metadatos.</small
                    ></label
                >
                <label class="flex items-center gap-3 text-sm md:col-span-2"
                    ><input v-model="form.published" type="checkbox" />Mostrar
                    en la tienda pública</label
                >
                <details
                    :open="!!editing"
                    class="rounded-xl border p-4 md:col-span-2"
                >
                    <summary class="cursor-pointer font-medium">
                        Más opciones: código, categoría, tallas y promociones
                    </summary>
                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-2 text-sm"
                            >Código del producto (opcional)<input
                                v-model="form.sku"
                                :required="!!editing"
                                maxlength="64"
                                placeholder="Se genera automáticamente"
                                class="rounded-xl border p-3"
                        /></label>
                        <label class="grid gap-2 text-sm"
                            >Categoría<input
                                v-model="form.category"
                                maxlength="100"
                                placeholder="Ej. Ropa"
                                class="rounded-xl border p-3"
                        /></label>
                        <label v-if="fashionBusiness" class="grid gap-2 text-sm"
                            >Sección principal<select
                                v-model="form.audience"
                                class="rounded-xl border bg-white p-3"
                            >
                                <option value="unisex">Unisex</option>
                                <option value="women">Mujer</option>
                                <option value="men">Hombre</option>
                                <option value="kids">Niños</option>
                            </select></label
                        >
                        <label class="grid gap-2 text-sm"
                            >Precio anterior (promoción)<input
                                v-model="comparePrice"
                                inputmode="decimal"
                                placeholder="Opcional, mayor al precio actual"
                                class="rounded-xl border p-3"
                        /></label>
                        <label
                            class="flex items-center gap-3 text-sm md:col-span-2"
                            ><input
                                v-model="form.trending"
                                type="checkbox"
                            />Mostrar en Tendencias</label
                        >
                        <label class="grid gap-2 text-sm md:col-span-2"
                            >Grupo de variantes<input
                                v-model="form.variant_group"
                                maxlength="100"
                                placeholder="Ej. Polo de algodón"
                                class="rounded-xl border p-3"
                        /></label>
                        <label class="grid gap-2 text-sm"
                            >Talla<input
                                v-model="form.size"
                                maxlength="40"
                                placeholder="Ej. M, L, 38"
                                class="rounded-xl border p-3"
                        /></label>
                        <label class="grid gap-2 text-sm"
                            >Color<input
                                v-model="form.color"
                                maxlength="60"
                                placeholder="Ej. Azul"
                                class="rounded-xl border p-3"
                        /></label>
                        <label class="grid gap-2 text-sm md:col-span-2"
                            >Descripción<textarea
                                v-model="form.description"
                                maxlength="3000"
                                rows="3"
                                class="rounded-xl border p-3"
                            />
                        </label>
                    </div>
                </details>
                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-xl bg-red-50 p-4 text-sm text-red-700 md:col-span-2"
                >
                    <p v-for="(error, field) in form.errors" :key="field">
                        {{ error }}
                    </p>
                </div>
                <p
                    v-if="form.progress && form.image"
                    class="text-sm text-slate-500 md:col-span-2"
                >
                    Subiendo foto: {{ form.progress.percentage }}%
                </p>
                <button
                    :disabled="form.processing"
                    class="rounded-xl bg-emerald-800 p-3 font-semibold text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar producto' }}
                </button>
                <button
                    v-if="editing"
                    type="button"
                    class="rounded-xl border p-3"
                    @click="reset"
                >
                    Cancelar edición
                </button>
            </form>
            <p
                v-if="page.props.success"
                role="status"
                class="mb-6 rounded-xl bg-emerald-100 p-4 text-emerald-900"
            >
                {{ page.props.success }}
            </p>
            <div
                v-if="preview"
                class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm"
            >
                Vista previa privada.
                <Link
                    :href="`/gestion/${tenant.slug}/operacion/configuracion`"
                    class="font-semibold underline"
                    >Volver al editor</Link
                >
            </div>
            <details
                v-if="managing"
                class="mb-8 rounded-2xl border bg-white p-5"
            >
                <summary class="cursor-pointer font-medium">
                    Importar varios productos o añadir más fotos
                </summary>
                <section class="mt-5 grid gap-5 lg:grid-cols-2">
                    <form
                        class="grid gap-4 rounded-2xl border bg-white p-6"
                        @submit.prevent="
                            importFile.post(
                                `/gestion/${tenant.slug}/importar`,
                                {
                                    forceFormData: true,
                                },
                            )
                        "
                    >
                        <h2 class="font-semibold">Importar catálogo CSV</h2>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Hasta 2000 filas. Encabezados:
                            sku,nombre,precio,categoria,grupo,publicado. Precio
                            en soles, punto decimal; publicado: 1 o 0. Los SKU
                            existentes se actualizan.
                        </p>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            required
                            aria-label="Archivo de catálogo"
                            @change="
                                importFile.file =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] || null
                            "
                        />
                        <p
                            v-for="(e, k) in importFile.errors"
                            :key="k"
                            class="text-sm text-red-700"
                        >
                            {{ e }}
                        </p>
                        <button
                            :disabled="importFile.processing"
                            class="rounded-xl border p-3"
                        >
                            Validar y revisar muestra</button
                        ><a
                            :href="`/gestion/${tenant.slug}/exportar/productos`"
                            class="text-sm text-emerald-700"
                            >Exportar catálogo privado CSV</a
                        >
                    </form>
                    <form
                        class="grid gap-4 rounded-2xl border bg-white p-6"
                        @submit.prevent="
                            photo.post(
                                `/gestion/${tenant.slug}/productos/${photoProduct}/foto`,
                                {
                                    forceFormData: true,
                                    onSuccess: () => photo.reset(),
                                },
                            )
                        "
                    >
                        <h2 class="font-semibold">Fotos del producto</h2>
                        <select
                            v-model="photoProduct"
                            required
                            aria-label="Producto de la foto"
                            class="rounded-xl border p-3"
                        >
                            <option value="">
                                Seleccionar producto de esta página
                            </option>
                            <option
                                v-for="p in products.data"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.sku }} · {{ p.name }}
                            </option></select
                        ><input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                            aria-label="Imagen del producto"
                            @change="
                                photo.image =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] || null
                            "
                        />
                        <p class="text-xs text-slate-500">
                            Hasta 3 MB y 4000 px. Se procesa una copia sin
                            metadatos originales. Máximo ocho fotos.
                        </p>
                        <p
                            v-for="(e, k) in photo.errors"
                            :key="k"
                            class="text-sm text-red-700"
                        >
                            {{ e }}
                        </p>
                        <button
                            :disabled="photo.processing"
                            class="rounded-xl border p-3"
                        >
                            Guardar foto
                        </button>
                    </form>
                </section>
            </details>
            <section
                v-if="managing && importPreview"
                class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-6"
            >
                <h2 class="font-semibold">
                    Vista previa de importación ·
                    {{ importPreview.count }} filas
                </h2>
                <ul class="my-4 text-sm">
                    <li v-for="p in importPreview.sample" :key="p.sku">
                        {{ p.sku }} · {{ p.name }} · {{ money(p.price_minor) }}
                    </li>
                </ul>
                <button
                    :disabled="importCommit.processing"
                    class="rounded-xl bg-emerald-800 p-3 text-white"
                    @click="
                        importCommit.token = importPreview.token;
                        importCommit.post(
                            `/gestion/${tenant.slug}/importar/confirmar`,
                        );
                    "
                >
                    Confirmar importación
                </button>
                <p
                    v-for="(e, k) in importCommit.errors"
                    :key="k"
                    class="mt-3 text-sm text-red-700"
                >
                    {{ e }}
                </p>
            </section>
            <div
                class="grid gap-8"
                :class="!managing ? 'xl:grid-cols-[1fr_360px]' : ''"
            >
                <section>
                    <p
                        v-if="!products.data.length"
                        class="rounded-2xl border border-dashed bg-white p-12 text-center text-slate-400"
                    >
                        Todavía no hay productos disponibles.
                    </p>
                    <div
                        class="grid gap-5 sm:grid-cols-2"
                        :class="managing ? 'xl:grid-cols-3' : ''"
                    >
                        <article
                            v-for="p in products.data"
                            :key="p.id"
                            class="product-card rounded-2xl border bg-white p-6"
                        >
                            <img
                                v-if="p.image_url"
                                :src="p.image_url"
                                :alt="p.name"
                                loading="lazy"
                                class="product-image mb-5 h-40 w-full rounded-xl object-contain"
                            />
                            <div
                                v-else
                                class="product-placeholder mb-5 flex h-40 items-center justify-center rounded-xl"
                            >
                                <Package :size="44" :stroke-width="1" /><span>{{
                                    p.category || 'Catálogo'
                                }}</span>
                            </div>
                            <div class="product-commerce-badges">
                                <span v-if="fashionBusiness">{{
                                    audienceLabel(p.audience)
                                }}</span>
                                <span v-if="p.size" class="variant"
                                    >Talla {{ p.size }}</span
                                >
                                <span v-if="p.color" class="variant">{{
                                    p.color
                                }}</span>
                                <span v-if="p.trending" class="trend"
                                    ><Flame :size="12" /> Tendencia</span
                                >
                                <span
                                    v-if="p.compare_at_minor"
                                    class="promotion"
                                    ><BadgePercent :size="12" /> Promoción</span
                                >
                            </div>
                            <p class="text-xs text-slate-400">{{ p.sku }}</p>
                            <h2 class="my-2 text-lg font-semibold">
                                <Link
                                    :href="`/tienda/${tenant.slug}/producto/${p.id}`"
                                    >{{ p.name }}</Link
                                >
                            </h2>
                            <p
                                v-if="p.compare_at_minor"
                                class="text-sm text-slate-400 line-through"
                            >
                                {{ money(p.compare_at_minor) }}
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ money(p.price_minor) }}
                            </p>
                            <p class="mt-3 text-xs text-slate-500">
                                {{ available(p.id) }} unidades disponibles
                            </p>
                            <p v-if="managing" class="mt-3 text-sm">
                                {{ p.published ? 'Publicado' : 'Borrador' }}
                                <a
                                    href="#product-form"
                                    class="ml-4 text-emerald-700"
                                    @click="edit(p)"
                                    >Editar</a
                                >
                            </p>
                            <button
                                v-else
                                :disabled="available(p.id) < 1 || preview"
                                class="mt-5 w-full rounded-xl bg-emerald-800 p-3 text-sm font-medium text-white disabled:bg-slate-200 disabled:text-slate-400"
                                @click="add(p)"
                            >
                                <span>{{
                                    available(p.id) > 0
                                        ? 'Añadir al carrito'
                                        : 'Agotado'
                                }}</span
                                ><ArrowRight
                                    v-if="available(p.id) > 0"
                                    :size="15"
                                    class="ml-2 inline-block"
                                />
                            </button>
                        </article>
                    </div>
                    <nav
                        aria-label="Paginación"
                        class="catalog-pagination mt-7 flex gap-3"
                    >
                        <template v-for="(l, i) in products.links" :key="i"
                            ><Link
                                v-if="l.url"
                                :href="l.url"
                                class="catalog-page-link rounded-lg border p-3"
                                :class="{ active: l.active }"
                                >{{
                                    i === 0
                                        ? 'Anterior'
                                        : i === products.links.length - 1
                                          ? 'Siguiente'
                                          : l.label
                                }}</Link
                            ></template
                        >
                    </nav>
                </section>
                <aside
                    v-if="!managing"
                    id="carrito"
                    class="h-fit rounded-2xl border bg-white p-6 xl:sticky xl:top-6"
                >
                    <h2 class="text-xl font-semibold">
                        Tu carrito
                        <span class="text-slate-400">{{ count }}</span>
                    </h2>
                    <p v-if="!cart.length" class="my-8 text-sm text-slate-400">
                        Añade productos para empezar tu pedido.
                    </p>
                    <div
                        v-for="(l, i) in cart"
                        :key="l.product_id"
                        class="mt-5 border-b pb-5"
                    >
                        <p class="text-sm font-medium">{{ l.name }}</p>
                        <div class="mt-3 flex items-center justify-between">
                            <input
                                v-model.number="l.quantity"
                                type="number"
                                min="1"
                                max="10000"
                                :aria-label="`Unidades de ${l.name}`"
                                class="w-20 rounded-lg border p-2"
                            /><span class="text-sm">{{
                                money(l.unit * l.quantity)
                            }}</span
                            ><button
                                class="text-xs text-red-700"
                                @click="cart.splice(i, 1)"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                    <template v-if="cart.length"
                        ><nav
                            class="storefront-checkout-progress"
                            aria-label="Pasos de compra"
                        >
                            <span class="complete"><b>1</b> Carrito</span>
                            <span class="active"><b>2</b> Datos y entrega</span>
                            <span><b>3</b> Pago</span>
                        </nav>
                        <p class="my-5 flex justify-between font-semibold">
                            <span>Subtotal estimado</span
                            ><span>{{ money(total) }}</span>
                        </p>
                        <section class="storefront-coupon-entry">
                            <label for="storefront-coupon-code">
                                <strong>¿Tienes un cupón?</strong>
                                <span
                                    >Escribe el código para conocer tu descuento
                                    antes de registrarte.</span
                                >
                            </label>
                            <div class="storefront-coupon-controls">
                                <input
                                    id="storefront-coupon-code"
                                    v-model="checkout.coupon"
                                    type="text"
                                    maxlength="40"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    placeholder="Ejemplo: DEMO10"
                                    aria-label="Código de cupón"
                                    @keyup.enter="review"
                                />
                                <button
                                    type="button"
                                    :disabled="quoting"
                                    @click="review"
                                >
                                    {{
                                        quoting
                                            ? 'Validando…'
                                            : checkout.coupon
                                              ? 'Aplicar cupón'
                                              : 'Calcular total'
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="quoteError"
                                role="alert"
                                class="storefront-coupon-error"
                            >
                                {{ quoteError }}
                            </p>
                            <div
                                v-if="quote"
                                class="storefront-quote-summary"
                                aria-live="polite"
                            >
                                <p
                                    v-if="
                                        checkout.coupon &&
                                        Number(quote.discount_minor) > 0
                                    "
                                    class="storefront-coupon-success"
                                >
                                    Cupón {{ checkout.coupon }} aplicado
                                </p>
                                <p v-if="Number(quote.discount_minor) > 0">
                                    <span>Descuento</span>
                                    <strong
                                        >−{{
                                            money(Number(quote.discount_minor))
                                        }}</strong
                                    >
                                </p>
                                <p v-if="Number(quote.delivery_minor) > 0">
                                    <span>Entrega</span>
                                    <strong>{{
                                        money(Number(quote.delivery_minor))
                                    }}</strong>
                                </p>
                                <p class="storefront-quote-total">
                                    <span>Total</span>
                                    <strong>{{
                                        money(Number(quote.total_minor))
                                    }}</strong>
                                </p>
                            </div>
                        </section>
                        <div
                            v-if="!page.props.auth?.user"
                            class="grid gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
                        >
                            <p class="font-semibold text-emerald-950">
                                Ingresa para continuar con tu compra
                            </p>
                            <p class="text-sm leading-relaxed text-emerald-800">
                                Puedes explorar y armar el carrito libremente.
                                Solo necesitas una cuenta al confirmar el
                                pedido.
                            </p>
                            <Link
                                :href="`/tienda/${tenant.slug}/cuenta`"
                                class="rounded-xl bg-emerald-800 p-3 text-center text-sm font-semibold text-white"
                                >Ingresar o crear cuenta</Link
                            >
                        </div>
                        <div
                            v-else-if="!customer"
                            class="grid gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5"
                        >
                            <p class="font-semibold text-amber-950">
                                Completa tu perfil de comprador
                            </p>
                            <p class="text-sm leading-relaxed text-amber-800">
                                Antes de confirmar, guarda tus datos únicamente
                                para esta tienda.
                            </p>
                            <Link
                                :href="`/tienda/${tenant.slug}/cuenta`"
                                class="rounded-xl bg-amber-800 p-3 text-center text-sm font-semibold text-white"
                                >Completar perfil</Link
                            >
                        </div>
                        <form v-else class="grid gap-4" @submit.prevent="order">
                            <label class="grid gap-2 text-sm"
                                >Tu nombre<input
                                    v-model="checkout.customer_name"
                                    required
                                    autocomplete="name"
                                    class="rounded-xl border p-3" /></label
                            ><label class="grid gap-2 text-sm"
                                >Teléfono<input
                                    v-model="checkout.phone"
                                    type="tel"
                                    required
                                    autocomplete="tel"
                                    class="rounded-xl border p-3" /></label
                            ><label class="grid gap-2 text-sm"
                                >Correo (opcional)<input
                                    v-model="checkout.email"
                                    type="email"
                                    autocomplete="email"
                                    class="rounded-xl border p-3" /></label
                            ><label class="grid gap-2 text-sm"
                                >Atención desde<select
                                    v-model="checkout.warehouse_id"
                                    required
                                    class="rounded-xl border p-3"
                                >
                                    <option
                                        v-for="w in warehouses"
                                        :key="w.id"
                                        :value="w.id"
                                    >
                                        {{ w.name }} · {{ w.branch }}
                                    </option>
                                </select></label
                            ><label class="grid gap-2 text-sm"
                                >Entrega<select
                                    v-model="checkout.delivery_method"
                                    class="rounded-xl border p-3"
                                >
                                    <option
                                        v-for="option in deliveryOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                        {{
                                            option.fee
                                                ? ` · ${money(option.fee)}`
                                                : ''
                                        }}
                                    </option>
                                </select></label
                            ><label
                                v-if="checkout.delivery_method !== 'pickup'"
                                class="grid gap-2 text-sm"
                                >Dirección y referencia<textarea
                                    v-model="checkout.address"
                                    required
                                    rows="3"
                                    class="rounded-xl border p-3"
                                /></label
                            ><button
                                type="button"
                                :disabled="quoting"
                                class="rounded-xl border p-3 font-medium"
                                @click="review"
                            >
                                {{
                                    quoting ? 'Calculando…' : 'Actualizar total'
                                }}
                            </button>
                            <div role="alert" class="text-sm text-red-700">
                                <p
                                    v-for="(error, name) in checkout.errors"
                                    :key="name"
                                >
                                    {{ error }}
                                </p>
                            </div>
                            <button
                                :disabled="checkout.processing || !quote"
                                class="rounded-xl bg-emerald-800 p-4 font-semibold text-white disabled:opacity-40"
                            >
                                {{
                                    checkout.processing
                                        ? 'Guardando pedido…'
                                        : 'Confirmar pedido'
                                }}
                            </button>
                            <p class="text-xs leading-relaxed text-slate-500">
                                El pedido reserva unidades durante 30 minutos.
                                Tu pago se verifica por separado. Abrir WhatsApp
                                no confirma un pago.
                            </p>
                        </form></template
                    >
                </aside>
            </div>
        </div>
        <footer v-if="!managing" class="storefront-public-footer">
            <div>
                <section v-if="settings.business_hours?.length">
                    <strong>Horario de atención</strong>
                    <p
                        v-for="hours in settings.business_hours"
                        :key="hours.day"
                    >
                        <span>{{ dayLabels[hours.day] }}</span>
                        <span>{{
                            hours.closed
                                ? 'Cerrado'
                                : `${hours.open} – ${hours.close}`
                        }}</span>
                    </p>
                </section>
                <section>
                    <strong>Conecta con {{ tenant.name }}</strong>
                    <a
                        v-if="settings.instagram_url"
                        :href="settings.instagram_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Instagram ↗</a
                    >
                    <a
                        v-if="settings.facebook_url"
                        :href="settings.facebook_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Facebook ↗</a
                    >
                    <a
                        v-if="settings.tiktok_url"
                        :href="settings.tiktok_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        >TikTok ↗</a
                    >
                    <a
                        v-if="settings.contact_email"
                        :href="`mailto:${settings.contact_email}`"
                        >{{ settings.contact_email }}</a
                    >
                    <span
                        v-if="
                            !settings.instagram_url &&
                            !settings.facebook_url &&
                            !settings.tiktok_url &&
                            !settings.contact_email
                        "
                        >Datos de contacto próximamente.</span
                    >
                </section>
                <section>
                    <strong>Opciones de entrega</strong>
                    <span v-for="option in deliveryOptions" :key="option.value">
                        {{ option.label
                        }}{{ option.fee ? ` · ${money(option.fee)}` : '' }}
                    </span>
                    <small v-if="settings.custom_domain">
                        Dominio: {{ settings.custom_domain }}
                    </small>
                </section>
            </div>
        </footer>
    </component>
</template>
