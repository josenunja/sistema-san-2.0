<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import BusinessPagination from '@/components/BusinessPagination.vue';
import { localDate, money, state } from '@/lib/commerce';
import type { OrderFilters, OrderListItem, PageLink } from '@/types/business';

defineProps<{
    slug: string;
    orders: { data: OrderListItem[]; links: PageLink[] };
    filters: OrderFilters;
}>();

const page = usePage();
const paymentLabels: Record<string, string> = {
    unpaid: 'Pendiente de pago',
    under_review: 'Pago por verificar',
    partial: 'Pago parcial',
    paid: 'Cubierto',
    not_applicable: 'No aplica',
};
const choices = [
    { key: 'channel', label: 'Canal', values: ['web', 'pos', 'offline'] },
    {
        key: 'order_status',
        label: 'Estado del pedido',
        values: ['reserved', 'confirmed', 'cancelled', 'expired'],
    },
    {
        key: 'payment_status',
        label: 'Estado del pago',
        values: Object.keys(paymentLabels),
    },
    {
        key: 'delivery_status',
        label: 'Estado de la entrega',
        values: ['pending', 'preparing', 'ready', 'delivered'],
    },
] as const;
</script>

<template>
    <form
        :action="`/gestion/${slug}/operacion/pedidos`"
        method="get"
        aria-label="Filtrar pedidos y ventas"
        class="my-5 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-3"
    >
        <label class="grid gap-1 text-sm">
            Desde
            <input
                type="date"
                name="from"
                :value="filters.from"
                class="min-w-0 rounded-lg border p-2"
            />
        </label>
        <label class="grid gap-1 text-sm">
            Hasta
            <input
                type="date"
                name="to"
                :value="filters.to"
                class="min-w-0 rounded-lg border p-2"
            />
        </label>
        <div
            v-for="choice in choices"
            :key="choice.key"
            class="grid gap-1 text-sm"
        >
            <label :for="`order-filter-${choice.key}`">{{
                choice.label
            }}</label>
            <select
                :id="`order-filter-${choice.key}`"
                :name="choice.key"
                :value="filters[choice.key] || ''"
                class="min-w-0 rounded-lg border p-2"
            >
                <option value="">Todos</option>
                <option
                    v-for="value in choice.values"
                    :key="value"
                    :value="value"
                >
                    {{ paymentLabels[value] || state(value) }}
                </option>
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button
                type="submit"
                class="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
            >
                Filtrar pedidos
            </button>
            <Link
                :href="`/gestion/${slug}/operacion/pedidos`"
                class="text-sm font-semibold text-emerald-800"
                >Limpiar filtros</Link
            >
        </div>
        <p
            v-if="page.props.errors?.to || page.props.errors?.from"
            role="alert"
            class="text-sm text-red-800"
        >
            {{ page.props.errors.to || page.props.errors.from }}
        </p>
    </form>

    <p
        v-if="!orders.data.length"
        class="rounded-2xl border bg-white p-8 text-center text-slate-600"
    >
        No hay pedidos ni ventas para los filtros seleccionados.
    </p>
    <div v-else class="overflow-x-auto rounded-2xl border bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th scope="col" class="p-4">Referencia</th>
                    <th scope="col" class="p-4">Cliente</th>
                    <th scope="col" class="p-4">Canal</th>
                    <th scope="col" class="p-4">Pedido</th>
                    <th scope="col" class="p-4">Pago</th>
                    <th scope="col" class="p-4">Entrega</th>
                    <th scope="col" class="p-4 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="order in orders.data" :key="order.id">
                    <td class="p-4">
                        <Link
                            :href="`/gestion/${slug}/pedido/${order.id}`"
                            class="font-semibold text-emerald-800"
                        >
                            {{ order.id.slice(0, 8) }} ↗
                        </Link>
                        <span
                            class="mt-1 block text-xs whitespace-nowrap text-slate-600"
                            >{{ localDate(order.created_at) }}</span
                        >
                    </td>
                    <td class="p-4">{{ order.customer_name }}</td>
                    <td class="p-4">{{ state(order.channel) }}</td>
                    <td class="p-4">{{ state(order.status) }}</td>
                    <td class="p-4">
                        {{ paymentLabels[order.payment_status] }}
                    </td>
                    <td class="p-4">
                        {{
                            order.status === 'confirmed'
                                ? state(order.delivery_status)
                                : '—'
                        }}
                    </td>
                    <td class="p-4 text-right font-semibold whitespace-nowrap">
                        {{ money(order.total_minor) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <BusinessPagination label="pedidos" :links="orders.links" />
</template>
