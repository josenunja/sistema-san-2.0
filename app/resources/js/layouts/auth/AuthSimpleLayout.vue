<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Layers3,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
const page = usePage();
</script>

<template>
    <div class="premium-auth-page">
        <div class="premium-auth-grid" aria-hidden="true"></div>
        <div class="premium-auth-particles" aria-hidden="true">
            <span
                v-for="particle in 18"
                :key="particle"
                :class="`premium-particle premium-particle-${particle}`"
            ></span>
        </div>

        <Link :href="home()" class="premium-auth-home">
            <span><ArrowLeft :size="16" /></span> Volver al catálogo
        </Link>

        <main class="premium-auth-shell">
            <section class="premium-auth-story">
                <Link :href="home()" class="premium-auth-brand">
                    <span><Layers3 :size="25" /></span>
                    <strong v-if="page.props.singleBusiness">{{
                        page.props.name
                    }}</strong
                    ><strong v-else>SAS<em>SAN</em></strong>
                </Link>
                <div class="premium-auth-story-copy">
                    <span class="premium-auth-eyebrow"
                        ><Sparkles :size="14" /> TU ESPACIO, A TU MANERA</span
                    >
                    <h2>Compra.<br />Sigue.<br /><em>Gestiona.</em></h2>
                    <p>
                        Una sola cuenta para descubrir productos, revisar tus
                        pedidos y hacer crecer tu negocio.
                    </p>
                </div>
                <div class="premium-auth-benefits">
                    <span><Check :size="14" /> Catálogo abierto</span>
                    <span><Check :size="14" /> Pedidos protegidos</span>
                    <span
                        ><Check :size="14" />
                        {{
                            page.props.singleBusiness
                                ? 'Tu negocio independiente'
                                : 'Tiendas independientes'
                        }}</span
                    >
                </div>
            </section>

            <section class="premium-auth-card">
                <div class="premium-auth-card-head">
                    <Link :href="home()" class="premium-auth-mark">
                        <AppLogoIcon class="size-8" />
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <span class="premium-auth-secure"
                        ><ShieldCheck :size="14" /> Acceso seguro</span
                    >
                </div>
                <div class="premium-auth-title">
                    <span><ShoppingBag :size="15" /> BIENVENIDO DE NUEVO</span>
                    <h1>{{ title }}</h1>
                    <p>{{ description }}</p>
                </div>
                <div class="premium-auth-form"><slot /></div>
                <p class="premium-auth-footnote">
                    Tus datos se mantienen separados y protegidos en cada
                    tienda.
                </p>
            </section>
        </main>
    </div>
</template>
