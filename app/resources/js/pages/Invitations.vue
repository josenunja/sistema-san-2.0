<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import InvitationList, {
    type Invitation,
} from '@/components/InvitationList.vue';
defineProps<{
    tenant: { id: number; name: string };
    backHref: string;
    invitations: {
        data: Invitation[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const page = usePage();
</script>
<template>
    <Head title="Gestionar invitaciones" />
    <main class="min-h-screen bg-stone-100 p-5 text-slate-900 lg:p-10">
        <div class="mx-auto max-w-4xl">
            <Link :href="backHref" class="font-medium text-emerald-800"
                >← Volver a la gestión</Link
            >
            <h1 class="mt-7 text-3xl font-semibold">
                Invitaciones de {{ tenant.name }}
            </h1>
            <p class="mt-3 text-sm text-slate-500">
                {{ invitations.total }} invitaciones en el historial. Para
                retirar el acceso de alguien que ya aceptó, utiliza Mi equipo.
            </p>
            <p
                v-if="page.props.success"
                role="status"
                class="mt-5 rounded-xl bg-emerald-100 p-4"
            >
                {{ page.props.success }}
            </p>
            <InvitationList :invitations="invitations.data" />
            <nav
                v-if="invitations.last_page > 1"
                aria-label="Páginas de invitaciones"
                class="mt-6 flex items-center justify-between gap-4 text-sm"
            >
                <Link
                    v-if="invitations.prev_page_url"
                    :href="invitations.prev_page_url"
                    class="rounded-lg border bg-white px-4 py-2"
                    >Anterior</Link
                ><span v-else class="text-slate-400">Anterior</span>
                <span
                    >Página {{ invitations.current_page }} de
                    {{ invitations.last_page }}</span
                >
                <Link
                    v-if="invitations.next_page_url"
                    :href="invitations.next_page_url"
                    class="rounded-lg border bg-white px-4 py-2"
                    >Siguiente</Link
                ><span v-else class="text-slate-400">Siguiente</span>
            </nav>
        </div>
    </main>
</template>
