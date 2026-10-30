<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { localDate, money } from '@/lib/commerce';
const props = defineProps<{
    tenant: { slug: string };
    data: Record<string, unknown>;
    section: string;
}>();
type Row = Record<string, string | number | boolean | null>;
const rows = (key: string) => (props.data[key] || []) as Row[];
function toggle(kind: string, row: Row) {
    router.post(
        `/gestion/${props.tenant.slug}/operacion`,
        {
            key: crypto.randomUUID(),
            action: 'toggle_option',
            kind,
            id: row.id,
            enabled: !row.enabled,
        },
        { preserveScroll: true },
    );
}
</script>
<template>
    <section
        v-if="section === 'promociones' && rows('priceRules').length"
        class="mb-6 grid gap-3 rounded-2xl border bg-white p-6"
    >
        <h2 class="text-xl font-semibold">Ofertas automáticas programadas</h2>
        <p class="text-sm text-slate-600">
            Se aplica la mejor oferta vigente. Al introducir un cupón se usa su
            regla en lugar de las ofertas automáticas; no se acumulan.
        </p>
        <article
            v-for="rule in rows('priceRules')"
            :key="String(rule.id)"
            class="flex flex-wrap justify-between gap-3 border-t pt-3"
        >
            <p>
                {{ rule.name }} · {{ rule.percent }}% ·
                {{ localDate(String(rule.starts_at)) }} a
                {{ localDate(String(rule.ends_at)) }}
            </p>
            <button
                class="text-emerald-800"
                @click="toggle('price_rules', rule)"
            >
                {{ rule.enabled ? 'Desactivar' : 'Activar' }}
            </button>
        </article>
    </section>
    <section
        v-if="section === 'configuracion'"
        class="mb-6 grid gap-5 rounded-2xl border bg-white p-6"
    >
        <h2 class="text-xl font-semibold">Zonas, puntos de recojo y franjas</h2>
        <p class="text-sm text-slate-600">
            Activa primero el método en el diseñador de tienda. Con zonas o
            franjas activas, el comprador debe elegir una opción disponible.
        </p>
        <article
            v-for="zone in rows('zones')"
            :key="'z' + zone.id"
            class="flex flex-wrap justify-between gap-3 border-t pt-3"
        >
            <p>
                {{ zone.name }} · {{ zone.method }} ·
                {{ money(Number(zone.fee_minor)) }} · {{ zone.address }}
            </p>
            <button
                class="text-emerald-800"
                @click="toggle('delivery_zones', zone)"
            >
                {{ zone.enabled ? 'Desactivar' : 'Activar' }}
            </button>
        </article>
        <article
            v-for="slot in rows('slots')"
            :key="'s' + slot.id"
            class="flex flex-wrap justify-between gap-3 border-t pt-3"
        >
            <p>
                {{ slot.method }} · {{ localDate(String(slot.starts_at)) }} a
                {{ localDate(String(slot.ends_at)) }} ·
                {{ slot.capacity }} cupos
            </p>
            <button
                class="text-emerald-800"
                @click="toggle('delivery_slots', slot)"
            >
                {{ slot.enabled ? 'Desactivar' : 'Activar' }}
            </button>
        </article>
    </section>
</template>
