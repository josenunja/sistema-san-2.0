<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowRight,
    ArrowUpRight,
    Layers3,
    LayoutDashboard,
    ShieldCheck,
    Store,
} from '@lucide/vue';
defineProps<{ stores: { name: string; slug: string; role?: string }[] }>();
</script>
<template>
    <Head title="SAS SAN · Cada negocio, su propio espacio" />
    <div class="sas-app directory-page">
        <header class="directory-header">
            <Link href="/" class="sidebar-brand"
                ><span class="brand-symbol"><Layers3 :size="21" /></span
                ><strong>SAS<span class="brand-accent">SAN</span></strong></Link
            ><Link
                :href="$page.props.auth?.user ? '/dashboard' : '/login'"
                class="directory-login"
                ><LayoutDashboard :size="15" />{{
                    $page.props.auth?.user
                        ? 'Mi espacio'
                        : 'Ingresar a gestión'
                }}<ArrowUpRight :size="15"
            /></Link>
        </header>
        <main class="directory-main">
            <section class="directory-hero">
                <div>
                    <span class="eyebrow"
                        ><span class="status-dot"></span> NEGOCIOS QUE CRECEN
                        CONECTADOS</span
                    >
                    <h1>Cada negocio,<br />su propio <em>espacio.</em></h1>
                    <p>
                        Descubre nuestras tiendas. Encuentra lo que necesitas y
                        conecta directamente con el negocio.
                    </p>
                    <a href="#tiendas" class="directory-explore"
                        >Explorar tiendas <ArrowDown :size="16"
                    /></a>
                </div>
                <div class="directory-art" aria-hidden="true">
                    <div class="art-orbit orbit-one"></div>
                    <div class="art-orbit orbit-two"></div>
                    <div class="art-store">
                        <Store :size="58" :stroke-width="1.2" /><span
                            >Hecho para tu negocio</span
                        >
                    </div>
                    <span class="art-label"
                        ><ShieldCheck :size="15" /> Un espacio
                        independiente</span
                    >
                </div>
            </section>
            <Link
                v-if="$page.props.auth?.user?.platform_owner"
                href="/plataforma"
                class="platform-shortcut"
                >Administrar plataforma <ArrowRight :size="15"
            /></Link>
            <section id="tiendas" class="directory-stores">
                <div class="directory-section-title">
                    <div>
                        <span class="eyebrow">DESCUBRE Y CONECTA</span>
                        <h2>
                            {{
                                $page.props.auth?.user
                                    ? 'Tus negocios'
                                    : 'Explora nuestras tiendas'
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
                                >Visitar tienda
                                <ArrowUpRight :size="17" /></Link
                            ><Link
                                v-if="store.role"
                                :href="
                                    store.role === 'cashier'
                                        ? `/gestion/${store.slug}/operacion/pos`
                                        : store.role === 'warehouse'
                                          ? `/gestion/${store.slug}/operacion/inventario`
                                          : `/gestion/${store.slug}/operacion/resumen`
                                "
                                class="manage-store"
                                >Gestionar <ArrowRight :size="14"
                            /></Link>
                        </div>
                    </article>
                </div>
            </section>
            <footer class="directory-footer">
                <span>SAS SAN</span>
                <p>Un lugar para hacer crecer cada negocio.</p>
                <span>© {{ new Date().getFullYear() }}</span>
            </footer>
        </main>
    </div>
</template>
