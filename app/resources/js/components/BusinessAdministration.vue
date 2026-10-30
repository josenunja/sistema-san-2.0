<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
const props = defineProps<{
    tenant: { slug: string };
    warehouses: { id: number; name: string }[];
    data: Record<string, unknown>;
}>();
type Member = {
    id: number;
    name: string;
    role: string;
    warehouse_ids: string | null;
};
const members = computed(() =>
    ((props.data.members || []) as Member[]).filter((m) =>
        ['cashier', 'warehouse'].includes(m.role),
    ),
);
const limits = computed(
    () =>
        (props.data.limits || {}) as Record<
            string,
            { used: number; pending: number; limit: number }
        >,
);
const labels: Record<string, string> = {
    product: 'Productos',
    warehouse: 'Almacenes',
    user: 'Personal',
};
function selected(member: Member, id: number) {
    return (
        member.warehouse_ids !== null &&
        (JSON.parse(member.warehouse_ids) as number[]).includes(id)
    );
}
function save(member: Member, event: Event) {
    const fields = new FormData(event.target as HTMLFormElement);
    router.patch(
        `/gestion/${props.tenant.slug}/equipo/${member.id}/almacenes`,
        {
            warehouse_ids: fields.has('all')
                ? null
                : fields.getAll('warehouses').map(Number),
        },
        { preserveScroll: true },
    );
}
</script>
<template>
    <section class="mb-7 rounded-2xl border bg-white p-6">
        <h2 class="text-xl font-semibold">Capacidad de tu instalación</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div
                v-for="(limit, key) in limits"
                :key="key"
                class="rounded-xl bg-slate-50 p-4"
            >
                <strong>{{ labels[key] }}</strong>
                <p>
                    {{ limit.used }} de {{ limit.limit }}
                    <span v-if="limit.pending"
                        >· {{ limit.pending }} invitaciones pendientes</span
                    >
                </p>
            </div>
        </div>
        <p v-if="data.singleBusiness" class="mt-4 text-sm text-slate-600">
            Esta instalación tiene límites propios. Tu proveedor puede
            ampliarlos desde su configuración sin cambiar tus datos.
        </p>
    </section>
    <section v-if="members.length" class="mb-7 rounded-2xl border bg-white p-6">
        <h2 class="text-xl font-semibold">Permisos por almacén</h2>
        <p class="mt-2 text-sm text-slate-600">
            Cierra la caja y concilia dispositivos antes de cambiar permisos.
            Sin almacenes seleccionados se bloquea la operación comercial de ese
            trabajador.
        </p>
        <form
            v-for="member in members"
            :key="member.id + ':' + member.warehouse_ids"
            class="mt-5 grid gap-3 border-t pt-4"
            @submit.prevent="save(member, $event)"
        >
            <strong>{{ member.name }}</strong
            ><label
                ><input
                    name="all"
                    type="checkbox"
                    :checked="member.warehouse_ids === null"
                />
                Todos los almacenes de este negocio</label
            >
            <div class="flex flex-wrap gap-4">
                <label v-for="warehouse in warehouses" :key="warehouse.id"
                    ><input
                        name="warehouses"
                        type="checkbox"
                        :value="warehouse.id"
                        :checked="selected(member, warehouse.id)"
                    />
                    {{ warehouse.name }}</label
                >
            </div>
            <button
                class="justify-self-start rounded-lg bg-emerald-800 px-4 py-2 text-white"
            >
                Guardar permisos
            </button>
        </form>
    </section>
</template>
