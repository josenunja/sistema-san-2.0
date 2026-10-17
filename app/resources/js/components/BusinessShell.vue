<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    BarChart3,
    Boxes,
    ChevronDown,
    ChevronRight,
    CircleHelp,
    CreditCard,
    Layers3,
    LogOut,
    Menu,
    Package,
    Settings2,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Store,
    Tags,
    Truck,
    Users,
    WifiOff,
    X,
} from '@lucide/vue';
const props = defineProps<{
    tenant: { name: string; slug: string };
    role?: string;
    section?: string;
}>();
const open = ref(false);
const items = [
    { key: 'resumen', label: 'Resumen', roles: ['owner'], icon: BarChart3 },
    { key: 'catalogo', label: 'Catálogo', roles: ['owner'], icon: Package },
    {
        key: 'inventario',
        label: 'Inventario',
        roles: ['owner', 'warehouse'],
        icon: Boxes,
    },
    {
        key: 'compras',
        label: 'Compras y proveedores',
        roles: ['owner'],
        icon: Truck,
    },
    {
        key: 'pedidos',
        label: 'Pedidos y ventas',
        roles: ['owner', 'cashier'],
        icon: ShoppingBag,
    },
    {
        key: 'pos',
        label: 'Punto de venta',
        roles: ['owner', 'cashier'],
        icon: ShoppingCart,
    },
    {
        key: 'offline',
        label: 'Caja sin conexión',
        roles: ['owner', 'cashier'],
        icon: WifiOff,
    },
    {
        key: 'caja',
        label: 'Mi caja',
        roles: ['owner', 'cashier'],
        icon: CreditCard,
    },
    {
        key: 'clientes',
        label: 'Clientes',
        roles: ['owner', 'cashier'],
        icon: Users,
    },
    { key: 'promociones', label: 'Promociones', roles: ['owner'], icon: Tags },
    {
        key: 'configuracion',
        label: 'Mi tienda',
        roles: ['owner'],
        icon: Settings2,
    },
    { key: 'auditoria', label: 'Actividad', roles: ['owner'], icon: Activity },
];
const allowed = computed(() =>
    items.filter((item) => item.roles.includes(props.role || 'owner')),
);
const current = computed(
    () =>
        items.find((item) => item.key === props.section)?.label || 'Mi negocio',
);
const roleLabel = computed(() =>
    props.role === 'cashier'
        ? 'Caja'
        : props.role === 'warehouse'
          ? 'Almacén'
          : 'Administración',
);
function href(key: string) {
    return key === 'catalogo'
        ? `/gestion/${props.tenant.slug}`
        : key === 'offline'
          ? `/gestion/${props.tenant.slug}/offline`
          : `/gestion/${props.tenant.slug}/operacion/${key}`;
}
function lockOffline() {
    localStorage.setItem('sas-offline-lock', String(Date.now()));
}
</script>
<template>
    <div class="sas-app business-layout">
        <a href="#business-content" class="skip-link">Saltar al contenido</a>
        <aside class="business-sidebar print:hidden">
            <div class="sidebar-brand-row">
                <Link href="/dashboard" class="sidebar-brand"
                    ><span class="brand-symbol"><Layers3 :size="21" /></span
                    ><strong
                        >SAS<span class="brand-accent">SAN</span></strong
                    ></Link
                ><button
                    class="mobile-nav-toggle"
                    :aria-expanded="open"
                    aria-controls="business-navigation"
                    :aria-label="open ? 'Cerrar menú' : 'Abrir menú'"
                    @click="open = !open"
                >
                    <X v-if="open" :size="22" /><Menu v-else :size="22" />
                </button>
            </div>
            <div class="workspace-card">
                <span class="workspace-avatar">{{
                    tenant.name.slice(0, 1).toUpperCase()
                }}</span
                ><span
                    ><small>MI ESPACIO DE TRABAJO</small
                    ><strong>{{ tenant.name }}</strong></span
                ><ChevronDown :size="14" />
            </div>
            <div
                id="business-navigation"
                class="sidebar-navigation"
                :class="{ 'mobile-open': open }"
            >
                <p class="sidebar-caption">GESTIÓN DEL NEGOCIO</p>
                <nav aria-label="Gestión empresarial">
                    <Link
                        v-for="item in allowed"
                        :key="item.key"
                        :href="href(item.key)"
                        class="sidebar-link"
                        :class="{ 'is-active': section === item.key }"
                        :aria-current="
                            section === item.key ? 'page' : undefined
                        "
                        @click="open = false"
                        ><component
                            :is="item.icon"
                            :size="18"
                            :stroke-width="1.7" /><span>{{ item.label }}</span
                        ><span
                            v-if="section === item.key"
                            class="nav-active-dot"
                        ></span
                    ></Link>
                </nav>
                <div class="sidebar-help">
                    <span><CircleHelp :size="18" /></span>
                    <p>
                        Todo para tu negocio<small
                            >Organiza, vende y sigue creciendo.</small
                        >
                    </p>
                </div>
                <div class="sidebar-bottom">
                    <Link :href="`/tienda/${tenant.slug}`"
                        ><Store :size="17" /> Ver mi tienda
                        <ArrowUpRight :size="14" /></Link
                    ><Link href="/settings/security"
                        ><ShieldCheck :size="17" /> Seguridad de mi cuenta</Link
                    ><Link
                        href="/logout"
                        method="post"
                        as="button"
                        @click="lockOffline"
                        ><LogOut :size="17" /> Cerrar sesión</Link
                    >
                </div>
            </div>
        </aside>
        <div class="business-body">
            <header class="business-topbar print:hidden">
                <div class="business-breadcrumb">
                    <span>Mi negocio</span><ChevronRight :size="13" /><strong>{{
                        current
                    }}</strong>
                </div>
                <div class="business-profile">
                    <span class="role-badge"><span></span>{{ roleLabel }}</span
                    ><span class="profile-initial">{{
                        tenant.name.slice(0, 1).toUpperCase()
                    }}</span>
                </div>
            </header>
            <main id="business-content" class="business-content"><slot /></main>
            <footer class="business-footer print:hidden">
                SAS SAN <span>Un lugar para hacer crecer tu negocio.</span>
            </footer>
        </div>
    </div>
</template>
