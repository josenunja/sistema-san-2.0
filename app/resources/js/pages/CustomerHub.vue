<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    BellRing,
    CalendarDays,
    CheckCheck,
    CheckCircle2,
    Layers3,
    MapPin,
    PackageCheck,
    ShoppingBag,
    Store,
    UserRound,
} from '@lucide/vue';
import { localDate, money, state } from '@/lib/commerce';

type StoreSummary = { name: string; slug: string };
type CustomerProfile = {
    store: StoreSummary;
    name: string;
    email: string;
    phone: string;
    address: string;
};
type CustomerOrder = {
    id: string;
    status: string;
    delivery_status: string;
    delivery_method: string;
    total_minor: number;
    created_at: string;
    store: StoreSummary;
};
type CustomerNotification = {
    id: number;
    order_id: string;
    kind: string;
    title: string;
    message: string;
    read_at: string | null;
    created_at: string;
    store: StoreSummary;
};

const props = defineProps<{
    tab: 'account' | 'orders';
    user: { name: string; email: string };
    profiles: CustomerProfile[];
    orders: CustomerOrder[];
    notifications: CustomerNotification[];
}>();
const unreadNotifications = computed(
    () =>
        props.notifications.filter((notification) => !notification.read_at)
            .length,
);
function readNotifications() {
    router.post(
        '/mis-pedidos/notificaciones/leer',
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="tab === 'orders' ? 'Mis pedidos' : 'Mi cuenta'" />
    <div class="sas-app customer-hub-page">
        <div class="customer-hub-glow" aria-hidden="true"></div>
        <header class="customer-hub-header">
            <Link href="/" class="sidebar-brand">
                <span class="brand-symbol"><Layers3 :size="21" /></span>
                <strong>SAS<span class="brand-accent">SAN</span></strong>
            </Link>
            <nav aria-label="Cuenta de cliente">
                <Link href="/mis-pedidos" :class="{ active: tab === 'orders' }">
                    <PackageCheck :size="18" /> Mis pedidos
                </Link>
                <Link href="/mi-cuenta" :class="{ active: tab === 'account' }">
                    <UserRound :size="18" /> Mi cuenta
                </Link>
            </nav>
        </header>

        <main class="customer-hub-main">
            <Link href="/" class="customer-hub-back">
                <ArrowLeft :size="16" /> Seguir comprando
            </Link>

            <section class="customer-hub-intro">
                <div>
                    <span class="eyebrow">TU ESPACIO PERSONAL</span>
                    <h1>
                        {{
                            tab === 'orders'
                                ? 'Tus pedidos.'
                                : `Hola, ${user.name.split(' ')[0]}.`
                        }}
                    </h1>
                    <p>
                        {{
                            tab === 'orders'
                                ? 'Consulta el estado de tus compras.'
                                : 'Tu información y tus datos de contacto.'
                        }}
                    </p>
                </div>
                <span class="customer-hub-verified">
                    <CheckCircle2 :size="17" /> Cuenta verificada
                </span>
            </section>

            <section v-if="tab === 'account'" class="customer-account-grid">
                <article class="customer-account-primary">
                    <div class="customer-account-icon">
                        <UserRound :size="25" />
                    </div>
                    <span>CUENTA PRINCIPAL</span>
                    <h2>{{ user.name }}</h2>
                    <p>{{ user.email }}</p>
                </article>

                <article
                    v-for="profile in profiles"
                    :key="profile.store.slug"
                    class="customer-profile-card"
                >
                    <div class="customer-profile-heading">
                        <span
                            ><Store :size="17" /> {{ profile.store.name }}</span
                        >
                        <Link
                            :href="`/tienda/${profile.store.slug}/cuenta`"
                            aria-label="Abrir perfil de tienda"
                        >
                            <ArrowUpRight :size="18" />
                        </Link>
                    </div>
                    <dl>
                        <div>
                            <dt>Nombre</dt>
                            <dd>{{ profile.name }}</dd>
                        </div>
                        <div>
                            <dt>Teléfono</dt>
                            <dd>{{ profile.phone || 'Sin registrar' }}</dd>
                        </div>
                        <div class="profile-address">
                            <dt><MapPin :size="14" /> Dirección</dt>
                            <dd>{{ profile.address || 'Sin registrar' }}</dd>
                        </div>
                    </dl>
                </article>

                <article v-if="!profiles.length" class="customer-hub-empty">
                    <ShoppingBag :size="33" />
                    <h2>Aún no tienes perfiles de compra</h2>
                    <p>
                        Cuando compres o completes tu información en una tienda,
                        aparecerá aquí.
                    </p>
                    <Link href="/#productos">Explorar productos</Link>
                </article>
            </section>

            <template v-else>
                <section
                    v-if="notifications.length"
                    class="customer-notifications"
                    aria-labelledby="notifications-title"
                >
                    <div class="customer-notifications-heading">
                        <div>
                            <span class="customer-notifications-icon">
                                <BellRing :size="20" />
                                <b v-if="unreadNotifications">{{
                                    unreadNotifications
                                }}</b>
                            </span>
                            <div>
                                <small>NOVEDADES</small>
                                <h2 id="notifications-title">
                                    Notificaciones de tus pedidos
                                </h2>
                            </div>
                        </div>
                        <button
                            v-if="unreadNotifications"
                            type="button"
                            @click="readNotifications"
                        >
                            <CheckCheck :size="16" /> Marcar como leídas
                        </button>
                    </div>
                    <div class="customer-notification-list">
                        <Link
                            v-for="notification in notifications.slice(0, 8)"
                            :key="`${notification.store.slug}-${notification.id}`"
                            :href="`/tienda/${notification.store.slug}/cuenta/pedido/${notification.order_id}`"
                            class="customer-notification-item"
                            :class="{ unread: !notification.read_at }"
                        >
                            <span class="customer-notification-dot"></span>
                            <div>
                                <small>{{ notification.store.name }}</small>
                                <strong>{{ notification.title }}</strong>
                                <p>{{ notification.message }}</p>
                            </div>
                            <time :datetime="notification.created_at">{{
                                localDate(notification.created_at)
                            }}</time>
                        </Link>
                    </div>
                </section>

                <section class="customer-orders-list">
                    <article
                        v-for="order in orders"
                        :key="order.id"
                        class="customer-order-card"
                    >
                        <div class="customer-order-store">
                            <span class="customer-order-icon"
                                ><PackageCheck :size="23"
                            /></span>
                            <div>
                                <small>{{ order.store.name }}</small>
                                <h2>
                                    Pedido #{{
                                        order.id.slice(0, 8).toUpperCase()
                                    }}
                                </h2>
                            </div>
                        </div>
                        <div class="customer-order-meta">
                            <span
                                ><CalendarDays :size="15" />
                                {{ localDate(order.created_at) }}</span
                            >
                            <span class="customer-order-status">{{
                                state(order.delivery_status)
                            }}</span>
                        </div>
                        <div class="customer-order-total">
                            <small>Total</small>
                            <strong>{{ money(order.total_minor) }}</strong>
                        </div>
                        <Link
                            :href="`/tienda/${order.store.slug}/cuenta/pedido/${order.id}`"
                        >
                            Ver detalle <ArrowUpRight :size="17" />
                        </Link>
                    </article>

                    <article v-if="!orders.length" class="customer-hub-empty">
                        <PackageCheck :size="36" />
                        <h2>Todavía no hay pedidos</h2>
                        <p>
                            Explora el catálogo; podrás revisar aquí cada compra
                            que realices.
                        </p>
                        <Link href="/#productos">Descubrir productos</Link>
                    </article>
                </section>
            </template>
        </main>
    </div>
</template>
