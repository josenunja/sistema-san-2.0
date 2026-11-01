<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ShieldCheck, Store, ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
const page = usePage();
const access = computed(() => page.props.businessAccess);
const platform = computed(() => page.props.platformAccess);
</script>

<template>
    <section
        v-if="platform"
        aria-label="Administración general"
        class="mb-8 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5"
    >
        <h2 class="flex items-center gap-2 font-semibold">
            <ShieldCheck class="size-5 shrink-0" />Administrador general
        </h2>
        <p class="mt-2 text-sm leading-6">
            Puedes crear empresas, invitar a sus propietarios y gestionar sus
            accesos y límites. Cada propietario configura su tienda y da
            permisos a sus empleados.
        </p>
        <p v-if="platform.requiresMfa" class="mt-2 text-sm leading-6">
            Activa la verificación en dos pasos para abrir la administración
            general. Tu cuenta ya tiene este permiso.
        </p>
        <div class="mt-4">
            <Button as-child>
                <Link
                    :href="
                        platform.requiresMfa
                            ? '/settings/security#verificacion-dos-pasos'
                            : platform.manageUrl
                    "
                >
                    {{
                        platform.requiresMfa
                            ? 'Configurar seguridad del administrador'
                            : 'Administrar empresas'
                    }}<ArrowRight class="size-4" />
                </Link>
            </Button>
        </div>
    </section>
    <section
        v-if="access"
        aria-label="Acceso a tu tienda"
        class="mb-8 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-5"
    >
        <div class="flex items-center gap-2 font-semibold">
            <Store class="size-5 shrink-0" />
            <h2>{{ access.name }}</h2>
        </div>
        <p v-if="access.requiresMfa" class="mt-2 text-sm leading-6">
            Tu cuenta ya tiene acceso a este negocio. Activa la verificación en
            dos pasos para administrar productos, inventario y ventas. Puedes
            ver tu tienda mientras completas este paso.
        </p>
        <p v-else class="mt-2 text-sm leading-6">
            La seguridad de tu cuenta está configurada. Continúa a tu negocio o
            revisa cómo ven la tienda tus clientes.
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            <Button v-if="access.requiresMfa" as-child>
                <Link href="/settings/security#verificacion-dos-pasos"
                    ><ShieldCheck class="size-4" />Configurar seguridad</Link
                >
            </Button>
            <Button v-else as-child>
                <Link :href="access.manageUrl"
                    >Entrar a mi negocio<ArrowRight class="size-4"
                /></Link>
            </Button>
            <Button as-child variant="outline">
                <Link :href="access.storeUrl"
                    ><Store class="size-4" />Ver mi tienda</Link
                >
            </Button>
        </div>
    </section>
</template>
