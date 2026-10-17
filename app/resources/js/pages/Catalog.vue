<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Layers3, Package, ShoppingBag, ArrowRight } from '@lucide/vue';
import BusinessShell from '@/components/BusinessShell.vue';
import { money, minor, jsonPost } from '@/lib/commerce';
type Product = {
    id: number;
    sku: string;
    name: string;
    price_minor: number;
    published: boolean;
    category: string;
    variant_group: string;
    description: string;
    image_url: string | null;
};
const props = defineProps<{
    tenant: { name: string; slug: string };
    managing: boolean;
    preview?: boolean;
    categories: string[];
    category: string;
    importPreview: { token: string; count: number; sample: Product[] } | null;
    search: string;
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    warehouses: { id: number; name: string; branch: string }[];
    settings: { tagline?: string; theme?: string; delivery_minor?: number };
    availability: { product_id: number; available: number }[];
}>();
const page = usePage();
const editing = ref<number | null>(null);
const form = useForm({
    sku: '',
    name: '',
    price_minor: 0,
    published: false,
    category: '',
    variant_group: '',
    description: '',
});
const importFile = useForm({ file: null as File | null });
const importCommit = useForm({ token: props.importPreview?.token || '' });
const photoProduct = ref('');
const photo = useForm({ image: null as File | null });
function edit(p: Product) {
    editing.value = p.id;
    form.sku = p.sku;
    form.name = p.name;
    form.price_minor = p.price_minor;
    form.published = p.published;
    form.category = p.category;
    form.variant_group = p.variant_group;
    form.description = p.description;
    price.value = (p.price_minor / 100).toFixed(2);
}
function reset() {
    editing.value = null;
    form.reset();
    price.value = '';
}
const price = ref('');
function save() {
    try {
        form.price_minor = minor(price.value);
        form.submit(
            editing.value ? 'patch' : 'post',
            `/gestion/${props.tenant.slug}/productos${editing.value ? '/' + editing.value : ''}`,
            {
                onSuccess: () => {
                    form.reset();
                    price.value = '';
                    editing.value = null;
                },
            },
        );
    } catch (e) {
        form.setError('price_minor', (e as Error).message);
    }
}
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
    customer_name: '',
    phone: '',
    email: '',
    address: '',
    delivery_method: 'pickup',
    coupon: '',
    items: [] as { product_id: number; quantity: number }[],
    expected_total_minor: 0,
    attach_account: false,
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
    props.settings.theme === 'azul'
        ? '#173e68'
        : props.settings.theme === 'violeta'
          ? '#503267'
          : '#163c32',
);
</script>
<template>
    <Head :title="tenant.name" /><component
        :is="managing ? BusinessShell : 'div'"
        :tenant="tenant"
        role="owner"
        section="catalogo"
        class="sas-app storefront min-h-screen text-slate-900"
    >
        <header
            v-if="!managing"
            class="storefront-header border-b bg-white p-5"
        >
            <nav class="mx-auto flex max-w-7xl items-center justify-between">
                <Link href="/" class="sidebar-brand"
                    ><span class="brand-symbol"><Layers3 :size="20" /></span
                    ><strong
                        >SAS<span class="brand-accent">SAN</span></strong
                    ></Link
                >
                <div class="flex gap-5 text-sm">
                    <Link :href="`/tienda/${tenant.slug}/seguimiento`"
                        >Seguir mi pedido</Link
                    ><a href="#carrito" class="storefront-cart-link"
                        ><ShoppingBag :size="16" />Carrito
                        <span>{{ count }}</span></a
                    ><Link :href="`/tienda/${tenant.slug}/cuenta`"
                        >Mi cuenta</Link
                    >
                </div>
            </nav>
        </header>
        <div :class="managing ? '' : 'mx-auto max-w-7xl p-6 lg:p-10'">
            <section
                v-if="!managing"
                class="storefront-hero mb-10 rounded-3xl px-8 py-12 text-white md:px-12"
                :style="{ backgroundColor: theme }"
            >
                <p class="text-xs tracking-widest opacity-70">
                    BIENVENIDO A NUESTRA TIENDA
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
            </section>
            <template v-else
                ><p
                    class="text-xs font-semibold tracking-widest text-emerald-700"
                >
                    GESTIÓN EMPRESARIAL
                </p>
                <h1 class="mt-2 text-3xl font-semibold">Tu catálogo</h1>
                <p class="mt-3 text-slate-500">
                    Cada SKU identifica una unidad o variante vendible.
                </p></template
            >
            <form method="get" class="catalog-search my-7 flex gap-3">
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
                class="mb-9 grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2"
                @submit.prevent="save"
            >
                <h2 class="text-lg font-semibold md:col-span-2">
                    {{
                        editing
                            ? 'Editar producto'
                            : 'Nuevo producto o variante'
                    }}
                </h2>
                <label class="grid gap-2 text-sm"
                    >SKU<input
                        v-model="form.sku"
                        required
                        maxlength="64"
                        class="rounded-xl border p-3"
                    /><span class="text-red-700">{{
                        form.errors.sku
                    }}</span></label
                ><label class="grid gap-2 text-sm"
                    >Nombre y variante<input
                        v-model="form.name"
                        required
                        maxlength="160"
                        placeholder="Ej. Polo algodón · Azul · M"
                        class="rounded-xl border p-3"
                    /><span class="text-red-700">{{
                        form.errors.name
                    }}</span></label
                ><label class="grid gap-2 text-sm"
                    >Precio S/<input
                        v-model="price"
                        inputmode="decimal"
                        required
                        placeholder="0.00"
                        class="rounded-xl border p-3"
                    /><span class="text-red-700">{{
                        form.errors.price_minor
                    }}</span></label
                ><label class="flex items-center gap-3 text-sm"
                    ><input v-model="form.published" type="checkbox" />Publicar
                    en tienda</label
                ><label class="grid gap-2 text-sm"
                    >Categoría<input
                        v-model="form.category"
                        maxlength="100"
                        class="rounded-xl border p-3" /></label
                ><label class="grid gap-2 text-sm"
                    >Grupo de variantes (opcional)<input
                        v-model="form.variant_group"
                        maxlength="100"
                        placeholder="Ej. Polo algodón"
                        class="rounded-xl border p-3" /></label
                ><label class="grid gap-2 text-sm md:col-span-2"
                    >Descripción<textarea
                        v-model="form.description"
                        maxlength="3000"
                        rows="3"
                        class="rounded-xl border p-3"
                    /></label
                ><button
                    :disabled="form.processing"
                    class="rounded-xl bg-emerald-800 p-3 text-white disabled:opacity-50"
                >
                    Guardar producto
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
            <section v-if="managing" class="mb-8 grid gap-5 lg:grid-cols-2">
                <form
                    class="grid gap-4 rounded-2xl border bg-white p-6"
                    @submit.prevent="
                        importFile.post(`/gestion/${tenant.slug}/importar`, {
                            forceFormData: true,
                        })
                    "
                >
                    <h2 class="font-semibold">Importar catálogo CSV</h2>
                    <p class="text-xs leading-relaxed text-slate-500">
                        Hasta 2000 filas. Encabezados:
                        sku,nombre,precio,categoria,grupo,publicado. Precio en
                        soles, punto decimal; publicado: 1 o 0. Los SKU
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
                        Hasta 3 MB y 4000 px. Se procesa una copia sin metadatos
                        originales. Máximo ocho fotos.
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
                            <p class="text-xs text-slate-400">{{ p.sku }}</p>
                            <h2 class="my-2 text-lg font-semibold">
                                <Link
                                    :href="`/tienda/${tenant.slug}/producto/${p.id}`"
                                    >{{ p.name }}</Link
                                >
                            </h2>
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
                    <nav aria-label="Paginación" class="mt-7 flex gap-3">
                        <template v-for="(l, i) in products.links" :key="i"
                            ><Link
                                v-if="l.url"
                                :href="l.url"
                                class="rounded-lg border bg-white p-3"
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
                        ><p class="my-5 flex justify-between font-semibold">
                            <span>Subtotal estimado</span
                            ><span>{{ money(total) }}</span>
                        </p>
                        <form class="grid gap-4" @submit.prevent="order">
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
                                    <option value="pickup">
                                        Recojo en tienda
                                    </option>
                                    <option
                                        v-if="settings.delivery_minor != null"
                                        value="delivery"
                                    >
                                        Entrega ·
                                        {{ money(settings.delivery_minor) }}
                                    </option>
                                </select></label
                            ><label
                                v-if="checkout.delivery_method === 'delivery'"
                                class="grid gap-2 text-sm"
                                >Dirección y referencia<textarea
                                    v-model="checkout.address"
                                    required
                                    rows="3"
                                    class="rounded-xl border p-3"
                                /></label
                            ><label class="grid gap-2 text-sm"
                                >Cupón (opcional)<input
                                    v-model="checkout.coupon"
                                    class="rounded-xl border p-3" /></label
                            ><button
                                type="button"
                                :disabled="quoting"
                                class="rounded-xl border p-3 font-medium"
                                @click="review"
                            >
                                {{ quoting ? 'Calculando…' : 'Revisar total' }}
                            </button>
                            <p
                                v-if="quoteError"
                                role="alert"
                                class="text-sm text-red-700"
                            >
                                {{ quoteError }}
                            </p>
                            <div
                                v-if="quote"
                                class="rounded-xl bg-emerald-50 p-4"
                            >
                                <p class="text-sm">
                                    Descuento
                                    {{ money(Number(quote.discount_minor)) }}
                                </p>
                                <p class="mt-2 text-xl font-semibold">
                                    Total {{ money(Number(quote.total_minor)) }}
                                </p>
                            </div>
                            <label
                                v-if="page.props.auth?.user"
                                class="flex items-start gap-3 text-sm"
                                ><input
                                    v-model="checkout.attach_account"
                                    type="checkbox"
                                    class="mt-1"
                                /><span
                                    >Guardar en mi cuenta de esta tienda.
                                    <Link
                                        :href="`/tienda/${tenant.slug}/cuenta`"
                                        class="text-emerald-700 underline"
                                        >Configurar mi perfil</Link
                                    ></span
                                ></label
                            >
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
        </div></component
    >
</template>
