<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    ArrowUpRight,
    Baby,
    BadgePercent,
    ChevronDown,
    Flame,
    LayoutGrid,
    Layers3,
    Package,
    PackageCheck,
    Search,
    ShieldCheck,
    Shirt,
    ShoppingBag,
    ShoppingCart,
    Sparkles,
    Store,
    Tag,
    UserRound,
} from '@lucide/vue';
import { money } from '@/lib/commerce';

type StoreSummary = {
    name: string;
    slug: string;
    role?: string;
    onboarding_complete?: boolean;
};
type MarketplaceProduct = {
    id: number;
    name: string;
    price_minor: number;
    compare_at_minor: number | null;
    category: string;
    audience: 'women' | 'men' | 'kids' | 'unisex';
    trending: boolean;
    size: string;
    color: string;
    description: string;
    image_url: string | null;
    store: { name: string; slug: string };
};

const props = defineProps<{
    mode: 'marketplace' | 'dashboard';
    stores: StoreSummary[];
    products: {
        data: MarketplaceProduct[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    categories: string[];
    search: string;
    category: string;
    section: string;
}>();

const sectionTitle = computed(
    () =>
        ({
            women: 'Moda para mujer',
            men: 'Moda para hombre',
            kids: 'Moda para niños',
            trends: 'Tendencias del momento',
            promotions: 'Productos en promoción',
        })[props.section] || 'Productos de todas las tiendas',
);
const hasFilters = computed(() =>
    Boolean(props.search || props.category || props.section),
);

type CartLine = {
    product_id: number;
    quantity: number;
    name: string;
    unit: number;
};
type StoreCart = StoreSummary & { lines: CartLine[]; count: number };

const cartOpen = ref(false);
const storeCarts = ref<StoreCart[]>([]);
const cartCount = computed(() =>
    storeCarts.value.reduce((total, store) => total + store.count, 0),
);

function readCarts() {
    storeCarts.value = props.stores.flatMap((store) => {
        try {
            const value = JSON.parse(
                sessionStorage.getItem(`cart:${store.slug}`) || '[]',
            ) as unknown;
            if (!Array.isArray(value)) return [];
            const lines = value.filter(
                (line): line is CartLine =>
                    typeof line === 'object' &&
                    line !== null &&
                    typeof (line as CartLine).product_id === 'number' &&
                    typeof (line as CartLine).quantity === 'number' &&
                    typeof (line as CartLine).name === 'string' &&
                    typeof (line as CartLine).unit === 'number',
            );
            const count = lines.reduce(
                (total, line) => total + Math.max(0, line.quantity),
                0,
            );
            return count ? [{ ...store, lines, count }] : [];
        } catch {
            return [];
        }
    });
}

onMounted(() => {
    readCarts();
    window.addEventListener('storage', readCarts);
    window.addEventListener('focus', readCarts);
});
onUnmounted(() => {
    window.removeEventListener('storage', readCarts);
    window.removeEventListener('focus', readCarts);
});
</script>

<template>
    <Head
        :title="
            mode === 'marketplace'
                ? 'SAS SAN · Productos de nuestras tiendas'
                : 'SAS SAN · Mi espacio'
        "
    />
    <div class="sas-app directory-page">
        <div
            v-if="mode === 'marketplace'"
            class="urban-scene"
            aria-hidden="true"
        >
            <span
                v-for="particle in 16"
                :key="particle"
                :class="`urban-particle urban-particle-${particle}`"
            ></span>
        </div>
        <header class="directory-header">
            <Link href="/" class="sidebar-brand"
                ><span class="brand-symbol"><Layers3 :size="21" /></span
                ><strong>SAS<span class="brand-accent">SAN</span></strong></Link
            >
            <form
                v-if="mode === 'marketplace'"
                method="get"
                class="directory-top-search"
            >
                <input
                    v-if="section"
                    type="hidden"
                    name="section"
                    :value="section"
                />
                <Search :size="18" aria-hidden="true" />
                <input
                    name="q"
                    :value="search"
                    aria-label="Buscar en SAS SAN"
                    placeholder="¿Qué estás buscando?"
                />
                <button aria-label="Buscar productos">
                    <ArrowRight :size="18" />
                </button>
            </form>
            <div class="directory-customer-actions">
                <Link href="/mis-pedidos" class="directory-customer-link">
                    <PackageCheck :size="18" />
                    <span>Mis pedidos</span>
                </Link>
                <Link href="/mi-cuenta" class="directory-customer-link">
                    <UserRound :size="18" />
                    <span>Mi cuenta</span>
                </Link>
                <div class="directory-cart-wrap">
                    <button
                        type="button"
                        class="directory-cart-button"
                        aria-label="Abrir carrito"
                        :aria-expanded="cartOpen"
                        @click="cartOpen = !cartOpen"
                    >
                        <ShoppingCart :size="21" />
                        <span v-if="cartCount" class="directory-cart-count">{{
                            cartCount
                        }}</span>
                        <ChevronDown
                            :size="13"
                            class="directory-cart-chevron"
                        />
                    </button>
                    <div v-if="cartOpen" class="directory-cart-menu">
                        <div class="directory-cart-heading">
                            <div>
                                <span>TU CARRITO</span>
                                <strong
                                    >{{ cartCount }}
                                    {{
                                        cartCount === 1
                                            ? 'producto'
                                            : 'productos'
                                    }}</strong
                                >
                            </div>
                            <button
                                type="button"
                                aria-label="Cerrar carrito"
                                @click="cartOpen = false"
                            >
                                ×
                            </button>
                        </div>
                        <div
                            v-if="storeCarts.length"
                            class="directory-cart-stores"
                        >
                            <Link
                                v-for="cart in storeCarts"
                                :key="cart.slug"
                                :href="`/tienda/${cart.slug}#carrito`"
                                @click="cartOpen = false"
                            >
                                <span><Store :size="17" /></span>
                                <div>
                                    <strong>{{ cart.name }}</strong>
                                    <small
                                        >{{ cart.count }}
                                        {{
                                            cart.count === 1
                                                ? 'artículo'
                                                : 'artículos'
                                        }}</small
                                    >
                                </div>
                                <ArrowRight :size="16" />
                            </Link>
                        </div>
                        <div v-else class="directory-cart-empty">
                            <ShoppingBag :size="25" />
                            <p>Tu carrito está esperando algo especial.</p>
                            <a href="#productos" @click="cartOpen = false"
                                >Ver productos</a
                            >
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <nav
            v-if="mode === 'marketplace'"
            class="fashion-navigation"
            aria-label="Secciones del catálogo"
        >
            <Link href="/" :class="{ active: !section }"
                ><LayoutGrid :size="16" /> Todo</Link
            >
            <Link
                href="/?section=women#productos"
                :class="{ active: section === 'women' }"
                ><Sparkles :size="16" /> Mujer</Link
            >
            <Link
                href="/?section=men#productos"
                :class="{ active: section === 'men' }"
                ><Shirt :size="16" /> Hombre</Link
            >
            <Link
                href="/?section=kids#productos"
                :class="{ active: section === 'kids' }"
                ><Baby :size="16" /> Niños</Link
            >
            <Link
                href="/?section=trends#productos"
                :class="{ active: section === 'trends' }"
                ><Flame :size="16" /> Tendencias</Link
            >
            <Link
                href="/?section=promotions#productos"
                class="promotion-navigation-item"
                :class="{ active: section === 'promotions' }"
                ><BadgePercent :size="16" /> Promociones</Link
            >
            <a href="#tiendas"><ShoppingBag :size="16" /> Tiendas</a>
        </nav>

        <main class="directory-main">
            <section class="directory-hero">
                <div>
                    <span class="eyebrow"
                        ><span class="status-dot"></span>
                        {{
                            mode === 'marketplace'
                                ? 'TODAS LAS TIENDAS EN UN LUGAR'
                                : 'GESTIONA TUS NEGOCIOS'
                        }}</span
                    >
                    <h1 v-if="mode === 'marketplace'">
                        Encuentra eso que<br />estabas <em>buscando.</em>
                    </h1>
                    <h1 v-else>
                        Cada negocio,<br />su propio <em>espacio.</em>
                    </h1>
                    <p v-if="mode === 'marketplace'">
                        Explora ropa y productos publicados por nuestras
                        tiendas. Crea una cuenta recién cuando quieras comprar.
                    </p>
                    <p v-else>
                        Entra a la operación de tus tiendas y continúa donde lo
                        dejaste.
                    </p>
                    <a
                        :href="
                            mode === 'marketplace' ? '#productos' : '#tiendas'
                        "
                        class="directory-explore"
                        >{{
                            mode === 'marketplace'
                                ? 'Ver productos'
                                : 'Ver mis tiendas'
                        }}
                        <ArrowDown :size="16"
                    /></a>
                </div>
                <div class="directory-art" aria-hidden="true">
                    <div
                        v-if="mode === 'marketplace'"
                        class="fashion-particles"
                    >
                        <span class="fashion-particle particle-shirt"
                            ><Shirt :size="25"
                        /></span>
                        <span class="fashion-particle particle-bag"
                            ><ShoppingBag :size="23"
                        /></span>
                        <span class="fashion-particle particle-tag"
                            ><Tag :size="21"
                        /></span>
                        <span class="fashion-particle particle-sparkle"
                            ><Sparkles :size="22"
                        /></span>
                    </div>
                    <div class="art-orbit orbit-one"></div>
                    <div class="art-orbit orbit-two"></div>
                    <div class="art-store">
                        <component
                            :is="mode === 'marketplace' ? Package : Store"
                            :size="58"
                            :stroke-width="1.2"
                        /><span>{{
                            mode === 'marketplace'
                                ? 'Compra en negocios locales'
                                : 'Hecho para tu negocio'
                        }}</span>
                    </div>
                    <span class="art-label"
                        ><ShieldCheck :size="15" />
                        {{
                            mode === 'marketplace'
                                ? 'Compra por tienda'
                                : 'Un espacio independiente'
                        }}</span
                    >
                </div>
            </section>

            <Link
                v-if="$page.props.auth?.user?.platform_owner"
                href="/plataforma"
                class="platform-shortcut"
                >Administrar plataforma <ArrowRight :size="15"
            /></Link>

            <section
                v-if="mode === 'marketplace'"
                id="productos"
                class="marketplace-products"
            >
                <div class="directory-section-title">
                    <div>
                        <span class="eyebrow">CATÁLOGO GENERAL</span>
                        <h2>{{ sectionTitle }}</h2>
                    </div>
                    <span class="store-count"
                        >{{ products.total }}
                        {{
                            products.total === 1 ? 'producto' : 'productos'
                        }}</span
                    >
                </div>

                <form method="get" class="marketplace-search">
                    <input
                        v-if="section"
                        type="hidden"
                        name="section"
                        :value="section"
                    />
                    <Search :size="18" aria-hidden="true" />
                    <input
                        name="q"
                        :value="search"
                        aria-label="Buscar productos"
                        placeholder="Buscar ropa o productos…"
                    />
                    <select
                        name="category"
                        :value="category"
                        aria-label="Categoría"
                    >
                        <option value="">Todas las categorías</option>
                        <option
                            v-for="item in categories"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>
                    <button>Buscar</button>
                </form>

                <div v-if="!products.data.length" class="directory-empty">
                    <Package :size="34" :stroke-width="1.2" />
                    <h3>No encontramos productos</h3>
                    <p v-if="hasFilters">
                        Prueba con otra búsqueda o elimina los filtros.
                    </p>
                    <p v-else>Los productos publicados aparecerán aquí.</p>
                    <Link
                        v-if="hasFilters"
                        href="/"
                        class="mt-4 inline-block text-sm font-semibold text-emerald-700"
                        >Ver todo el catálogo</Link
                    >
                </div>

                <div class="marketplace-grid">
                    <article
                        v-for="product in products.data"
                        :key="`${product.store.slug}-${product.id}`"
                        class="marketplace-product"
                    >
                        <Link
                            :href="`/tienda/${product.store.slug}/producto/${product.id}`"
                            class="marketplace-product-link"
                        >
                            <div class="marketplace-product-media">
                                <img
                                    v-if="product.image_url"
                                    :src="product.image_url"
                                    :alt="product.name"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="marketplace-product-placeholder"
                                >
                                    <Package :size="38" :stroke-width="1.2" />
                                    <span>Producto</span>
                                </div>
                                <span
                                    v-if="product.compare_at_minor"
                                    class="marketplace-promotion-badge"
                                >
                                    <BadgePercent :size="13" /> Oferta
                                </span>
                                <span
                                    v-if="product.category"
                                    class="marketplace-category"
                                >
                                    {{ product.category }}
                                </span>
                            </div>
                            <div class="marketplace-product-body">
                                <span class="marketplace-store-name">
                                    <Store :size="13" />
                                    {{ product.store.name }}
                                </span>
                                <ul
                                    v-if="product.size || product.color"
                                    class="marketplace-variant-chips"
                                    aria-label="Opciones de la variante"
                                >
                                    <li v-if="product.size">
                                        Talla {{ product.size }}
                                    </li>
                                    <li v-if="product.color">
                                        {{ product.color }}
                                    </li>
                                </ul>
                                <h3>{{ product.name }}</h3>
                                <p>
                                    {{
                                        product.description ||
                                        'Visita la tienda para conocer este producto.'
                                    }}
                                </p>
                                <div>
                                    <div class="marketplace-price">
                                        <del v-if="product.compare_at_minor">{{
                                            money(product.compare_at_minor)
                                        }}</del>
                                        <strong>{{
                                            money(product.price_minor)
                                        }}</strong>
                                    </div>
                                    <span
                                        >Ver producto <ArrowUpRight :size="15"
                                    /></span>
                                </div>
                            </div>
                        </Link>
                    </article>
                </div>

                <nav
                    v-if="products.links.length > 3"
                    aria-label="Paginación de productos"
                    class="marketplace-pagination"
                >
                    <template
                        v-for="(link, index) in products.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            :class="{ active: link.active }"
                            >{{
                                index === 0
                                    ? 'Anterior'
                                    : index === products.links.length - 1
                                      ? 'Siguiente'
                                      : link.label
                            }}</Link
                        >
                    </template>
                </nav>
            </section>

            <section id="tiendas" class="directory-stores">
                <div class="directory-section-title">
                    <div>
                        <span class="eyebrow">TIENDAS</span>
                        <h2>
                            {{
                                mode === 'dashboard'
                                    ? 'Tus negocios'
                                    : 'Compra directamente a cada tienda'
                            }}
                        </h2>
                    </div>
                    <span class="store-count"
                        >{{ stores.length }}
                        {{ stores.length === 1 ? 'negocio' : 'negocios' }}</span
                    >
                </div>
                <div v-if="!stores.length" class="directory-empty">
                    <Store :size="34" :stroke-width="1.2" />
                    <h3>Pronto habrá más por descubrir</h3>
                    <p>Las empresas activas aparecerán aquí.</p>
                </div>
                <div class="directory-grid">
                    <article
                        v-for="(store, index) in stores"
                        :key="store.slug"
                        class="directory-store"
                    >
                        <div class="store-card-top">
                            <span
                                class="store-monogram"
                                :class="`monogram-${index % 3}`"
                                >{{
                                    store.name.slice(0, 1).toUpperCase()
                                }}</span
                            ><span class="store-open"
                                ><span></span> Tienda activa</span
                            >
                        </div>
                        <h3>{{ store.name }}</h3>
                        <p>/tienda/{{ store.slug }}</p>
                        <div class="store-card-links">
                            <Link :href="`/tienda/${store.slug}`"
                                >Ver productos <ArrowUpRight :size="17"
                            /></Link>
                            <Link
                                v-if="store.role"
                                :href="
                                    store.role === 'cashier'
                                        ? `/gestion/${store.slug}/operacion/pos`
                                        : store.role === 'catalog'
                                          ? `/gestion/${store.slug}`
                                          : store.role === 'warehouse'
                                            ? `/gestion/${store.slug}/operacion/inventario`
                                            : !store.onboarding_complete
                                              ? `/gestion/${store.slug}/configurar`
                                              : `/gestion/${store.slug}/operacion/resumen`
                                "
                                class="manage-store"
                                >{{
                                    store.role === 'owner' &&
                                    !store.onboarding_complete
                                        ? 'Configurar tienda'
                                        : 'Gestionar'
                                }}
                                <ArrowRight :size="14"
                            /></Link>
                        </div>
                    </article>
                </div>
            </section>

            <footer class="directory-footer">
                <span>SAS SAN</span>
                <p>
                    {{
                        mode === 'marketplace'
                            ? 'Explora primero. Ingresa cuando quieras comprar.'
                            : 'Un lugar para hacer crecer cada negocio.'
                    }}
                </p>
                <span>© {{ new Date().getFullYear() }}</span>
            </footer>
        </main>
    </div>
</template>
