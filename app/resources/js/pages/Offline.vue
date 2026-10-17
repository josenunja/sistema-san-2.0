<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import BusinessShell from '@/components/BusinessShell.vue';
import { jsonPost, localDate } from '@/lib/commerce';
defineProps<{
    tenant: { name: string; slug: string };
    role: string;
    products: { id: number; name: string; sku: string }[];
    cashSessions: { id: number }[];
    devices: {
        id: string;
        name: string;
        expires_at: string;
        reconciled_at: string | null;
    }[];
}>();
const selected = ref('');
const quantity = ref(1);
const name = ref('');
const cash = ref('');
const items = ref<{ product_id: number; quantity: number }[]>([]);
const error = ref('');
const busy = ref(false);
const key = ref(crypto.randomUUID());
const lastSequence = ref<Record<string, number>>({});
const reviewOperations = ref<
    {
        operation_id: string;
        sequence: number;
        total_minor: number;
        device_id: string;
        captured_at: string;
        items: { product_id: number; quantity: number }[];
    }[]
>([]);
const reviewReason = ref('');
async function loadReview(event: Event) {
    try {
        const file = (event.target as HTMLInputElement).files?.[0];
        if (!file) return;
        if (file.size > 5242880) throw new Error('Archivo demasiado grande.');
        const value = JSON.parse(await file.text());
        if (!Array.isArray(value.operations) || value.operations.length > 100)
            throw new Error('El archivo debe contener hasta 100 operaciones.');
        reviewOperations.value = value.operations;
    } catch (e) {
        error.value = (e as Error).message;
    }
}
async function approve(slug: string) {
    busy.value = true;
    error.value = '';
    try {
        await jsonPost(`/gestion/${slug}/offline/aprobar`, {
            key: key.value,
            reason: reviewReason.value,
            operations: reviewOperations.value,
        });
        key.value = crypto.randomUUID();
        reviewOperations.value = [];
        error.value =
            'Revisión guardada. El dispositivo debe reintentar la sincronización con su misma cuenta.';
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
function add() {
    if (!selected.value || quantity.value < 1) return;
    const old = items.value.find(
        (i) => i.product_id === Number(selected.value),
    );
    if (old) old.quantity += quantity.value;
    else
        items.value.push({
            product_id: Number(selected.value),
            quantity: quantity.value,
        });
}
async function grant(slug: string) {
    busy.value = true;
    error.value = '';
    try {
        const data = await jsonPost(`/gestion/${slug}/offline/asignar`, {
            key: key.value,
            name: name.value,
            cash_session_id: cash.value,
            items: items.value,
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(
            new Blob([JSON.stringify(data)], { type: 'application/json' }),
        );
        a.download = `asignacion-${data.id}.json`;
        a.click();
        URL.revokeObjectURL(a.href);
        key.value = crypto.randomUUID();
        items.value = [];
        router.reload();
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        busy.value = false;
    }
}
function reconcile(slug: string, id: string) {
    if (
        !Number.isInteger(lastSequence.value[id]) ||
        lastSequence.value[id] < 0
    ) {
        error.value =
            'Indica la última secuencia que muestra el dispositivo, incluso si es cero.';
        return;
    }
    if (
        !window.confirm(
            'Verifica que el dispositivo haya sincronizado o exportado todas sus ventas. Esta acción cierra su permiso y libera las unidades restantes. ¿Confirmas la conciliación?',
        )
    )
        return;
    router.post(`/gestion/${slug}/offline/conciliar`, {
        key: crypto.randomUUID(),
        device_id: id,
        confirmed_empty: true,
        last_sequence: lastSequence.value[id],
    });
}
if ('serviceWorker' in navigator)
    void navigator.serviceWorker.register('/offline-sw.js');
</script>
<template>
    <Head title="POS sin conexión" /><BusinessShell
        :tenant="tenant"
        :role="role"
        section="pos"
        ><h1 class="text-3xl font-semibold">Preparar caja sin conexión</h1>
        <p class="mt-4 max-w-3xl text-sm leading-relaxed text-slate-500">
            Asigna existencias exclusivas a un dispositivo antes de
            desconectarlo. Las unidades dejan de estar disponibles para la
            tienda y otras cajas. El permiso dura 12 horas y solo permite ventas
            completas en efectivo.
        </p>
        <div class="my-7 flex flex-wrap gap-3">
            <a
                href="/offline.html"
                target="_blank"
                rel="noopener"
                class="rounded-xl bg-emerald-800 px-5 py-3 text-white"
                >Abrir POS sin conexión ↗</a
            ><a
                href="/offline.html"
                class="rounded-xl border bg-white px-5 py-3"
                >Sincronizar ventas guardadas</a
            >
        </div>
        <form
            class="grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
            @submit.prevent="grant(tenant.slug)"
        >
            <h2 class="text-xl font-semibold md:col-span-2">
                Nueva asignación
            </h2>
            <label class="grid gap-2 text-sm"
                >Nombre del equipo<input
                    v-model="name"
                    required
                    maxlength="100"
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2 text-sm"
                >Tu caja abierta<select
                    v-model="cash"
                    required
                    class="rounded-xl border p-3"
                >
                    <option value="">Seleccionar</option>
                    <option v-for="c in cashSessions" :key="c.id" :value="c.id">
                        Caja {{ c.id }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >Producto<select
                    v-model="selected"
                    class="rounded-xl border p-3"
                >
                    <option value="">Seleccionar</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">
                        {{ p.sku }} · {{ p.name }}
                    </option>
                </select></label
            ><label class="grid gap-2 text-sm"
                >Unidades<input
                    v-model.number="quantity"
                    type="number"
                    min="1"
                    max="10000"
                    class="rounded-xl border p-3" /></label
            ><button type="button" class="rounded-xl border p-3" @click="add">
                Añadir a la asignación
            </button>
            <ul class="md:col-span-2">
                <li
                    v-for="(i, index) in items"
                    :key="i.product_id"
                    class="flex justify-between border-t py-3 text-sm"
                >
                    <span
                        >{{ i.quantity }} ×
                        {{
                            products.find((p) => p.id === i.product_id)?.name
                        }}</span
                    ><button
                        type="button"
                        class="text-red-700"
                        @click="items.splice(index, 1)"
                    >
                        Quitar
                    </button>
                </li>
            </ul>
            <p
                v-if="error"
                role="alert"
                class="text-sm text-red-700 md:col-span-2"
            >
                {{ error }}
            </p>
            <button
                :disabled="busy || !items.length"
                class="rounded-xl bg-emerald-800 p-3 text-white disabled:opacity-50"
            >
                {{ busy ? 'Asignando…' : 'Asignar y descargar permiso' }}
            </button>
            <p class="text-xs leading-relaxed text-slate-500">
                Importa el archivo en el POS sin conexión y protégelo con una
                clave local. No contiene contraseñas ni credenciales
                administrativas.
            </p>
        </form>
        <h2 class="mt-9 mb-4 text-xl font-semibold">
            Dispositivos registrados
        </h2>
        <div class="divide-y rounded-2xl border bg-white">
            <article
                v-for="d in devices"
                :key="d.id"
                class="flex flex-wrap items-center justify-between gap-4 p-5"
            >
                <div>
                    <h3 class="font-semibold">{{ d.name }}</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ d.id }} · Vence {{ localDate(d.expires_at) }}
                    </p>
                    <p
                        v-if="d.reconciled_at"
                        class="mt-2 text-sm text-emerald-700"
                    >
                        Conciliado
                    </p>
                </div>
                <label
                    v-if="role === 'owner' && !d.reconciled_at"
                    class="grid gap-2 text-xs"
                    >Última secuencia que muestra el equipo<input
                        v-model.number="lastSequence[d.id]"
                        type="number"
                        min="0"
                        required
                        class="w-40 rounded-xl border p-2"
                /></label>
                <button
                    v-if="role === 'owner' && !d.reconciled_at"
                    class="rounded-xl border px-4 py-2 text-sm"
                    @click="reconcile(tenant.slug, d.id)"
                >
                    Cerrar y conciliar
                </button>
            </article>
        </div>
        <form
            v-if="role === 'owner'"
            class="mt-8 grid gap-4 rounded-2xl border bg-white p-6"
            @submit.prevent="approve(tenant.slug)"
        >
            <h2 class="text-xl font-semibold">
                Revisar ventas de un permiso vencido
            </h2>
            <p class="text-sm text-slate-500">
                Importa la solicitud de revisión exportada por el dispositivo,
                verifica las ventas físicas y registra el motivo. La aprobación
                se limita a estas operaciones exactas y requiere confirmar tu
                contraseña.
            </p>
            <input
                type="file"
                accept="application/json,.json"
                required
                aria-label="Solicitud de revisión offline"
                @change="loadReview"
            />
            <ul class="text-sm">
                <li
                    v-for="operation in reviewOperations"
                    :key="operation.operation_id"
                >
                    Secuencia {{ operation.sequence }} · S/
                    {{ (operation.total_minor / 100).toFixed(2) }} ·
                    {{ operation.operation_id }}
                </li>
            </ul>
            <label class="grid gap-2 text-sm"
                >Motivo de la revisión<input
                    v-model="reviewReason"
                    required
                    minlength="5"
                    maxlength="300"
                    class="rounded-xl border p-3" /></label
            ><button
                :disabled="busy || !reviewOperations.length"
                class="rounded-xl bg-emerald-800 p-3 text-white disabled:opacity-50"
            >
                Aprobar operaciones revisadas
            </button>
        </form>
        <p class="mt-5 text-sm text-amber-800">
            Los permisos vencidos conservan su stock comprometido hasta
            conciliar. Una venta en conflicto permanece en el dispositivo para
            revisión; nunca se descarta automáticamente.
        </p></BusinessShell
    >
</template>
