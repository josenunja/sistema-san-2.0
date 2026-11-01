<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Paintbrush, ShieldCheck, Store } from '@lucide/vue';

const props = defineProps<{ slug: string }>();
const page = usePage();
const access = computed(() => {
    const membership = page.props.businessAccess;
    return membership?.slug === props.slug ? membership : null;
});
const roleLabel = computed(() => {
    switch (access.value?.role) {
        case 'owner':
            return 'Tu cuenta es propietaria de esta tienda. Puedes configurar su diseño y administrar el negocio.';
        case 'catalog':
            return 'Tienes permiso para administrar los productos y las fotos de esta tienda.';
        default:
            return 'Tienes acceso al equipo de esta tienda. Entra al panel para trabajar con tus permisos.';
    }
});
</script>

<template>
    <section
        v-if="access"
        aria-label="Administración de esta tienda"
        class="border-b border-emerald-200 bg-emerald-50 px-5 py-4 text-slate-900"
    >
        <div
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4"
        >
            <div class="max-w-2xl">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <Store :size="18" class="shrink-0" />
                    Estás viendo tu tienda como cliente
                </h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    {{ roleLabel }}
                </p>
                <p
                    v-if="access.requiresMfa"
                    class="mt-1 text-sm leading-6 text-slate-600"
                >
                    Completa la verificación en dos pasos para entrar al panel.
                    No necesitas crear otra cuenta.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    v-if="access.requiresMfa"
                    href="/settings/security#verificacion-dos-pasos"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900"
                    ><ShieldCheck :size="17" />Configurar seguridad</Link
                >
                <template v-else>
                    <Link
                        v-if="access.role === 'owner'"
                        :href="`/gestion/${slug}/configurar`"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-800 bg-white px-4 py-3 text-sm font-semibold text-emerald-900 hover:bg-emerald-100"
                        ><Paintbrush :size="17" />Configurar diseño</Link
                    >
                    <Link
                        :href="access.manageUrl"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-900"
                        >{{
                            access.role === 'owner'
                                ? 'Administrar tienda'
                                : 'Entrar a mi trabajo'
                        }}<ArrowRight :size="17"
                    /></Link>
                </template>
            </div>
        </div>
    </section>
</template>
