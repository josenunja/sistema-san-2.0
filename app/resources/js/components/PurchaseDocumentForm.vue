<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { minor } from '@/lib/commerce';
defineProps<{
    tenant: { slug: string };
    products: { id: number; name: string; sku: string }[];
    warehouses: { id: number; name: string }[];
    suppliers: Record<string, string | number | boolean | null>[];
}>();
const form = useForm({
    key: crypto.randomUUID(),
    supplier_id: '',
    warehouse_id: '',
    reference: '',
    additional: '0',
    discount: '0',
    lines: [{ product_id: '', quantity: 1, unit_cost: '' }],
});
function save(slug: string) {
    try {
        form.transform((data) => ({
            key: data.key,
            action: 'purchase_document',
            supplier_id: data.supplier_id,
            warehouse_id: data.warehouse_id,
            reference: data.reference,
            additional_minor: minor(data.additional || '0'),
            discount_minor: minor(data.discount || '0'),
            lines: data.lines.map((line) => ({
                product_id: Number(line.product_id),
                quantity: line.quantity,
                unit_cost_minor: minor(line.unit_cost),
            })),
        })).post(`/gestion/${slug}/operacion`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.key = crypto.randomUUID();
            },
        });
    } catch (error) {
        form.setError('reference', (error as Error).message);
    }
}
</script>
<template>
    <details class="mb-7 rounded-2xl border bg-white p-6">
        <summary class="cursor-pointer text-xl font-semibold">
            Nueva compra con varios productos
        </summary>
        <form class="mt-6 grid gap-4" @submit.prevent="save(tenant.slug)">
            <div class="grid gap-4 md:grid-cols-3">
                <label
                    >Proveedor<select
                        v-model="form.supplier_id"
                        required
                        class="mt-2 w-full rounded-lg border p-3"
                    >
                        <option value="">Seleccionar</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="String(supplier.id)"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                        </option>
                    </select></label
                >
                <label
                    >Almacén<select
                        v-model="form.warehouse_id"
                        required
                        class="mt-2 w-full rounded-lg border p-3"
                    >
                        <option value="">Seleccionar</option>
                        <option
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                        </option>
                    </select></label
                >
                <label
                    >Documento/referencia<input
                        v-model="form.reference"
                        required
                        maxlength="120"
                        class="mt-2 w-full rounded-lg border p-3"
                /></label>
            </div>
            <div
                v-for="(line, index) in form.lines"
                :key="index"
                class="grid gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-4"
            >
                <label
                    >Producto<select
                        v-model="line.product_id"
                        required
                        class="mt-2 w-full rounded-lg border p-2"
                    >
                        <option value="">Seleccionar</option>
                        <option
                            v-for="product in products"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.sku }} · {{ product.name }}
                        </option>
                    </select></label
                >
                <label
                    >Unidades<input
                        v-model.number="line.quantity"
                        required
                        type="number"
                        min="1"
                        max="1000000"
                        class="mt-2 w-full rounded-lg border p-2"
                /></label>
                <label
                    >Costo unitario S/<input
                        v-model="line.unit_cost"
                        required
                        inputmode="decimal"
                        class="mt-2 w-full rounded-lg border p-2"
                /></label>
                <button
                    v-if="form.lines.length > 1"
                    type="button"
                    class="text-red-700"
                    @click="form.lines.splice(index, 1)"
                >
                    Quitar línea
                </button>
            </div>
            <button
                type="button"
                :disabled="form.lines.length >= 100"
                class="justify-self-start text-emerald-800"
                @click="
                    form.lines.push({
                        product_id: '',
                        quantity: 1,
                        unit_cost: '',
                    })
                "
            >
                + Agregar producto
            </button>
            <div class="grid gap-4 md:grid-cols-2">
                <label
                    >Descuento total S/<input
                        v-model="form.discount"
                        inputmode="decimal"
                        class="mt-2 w-full rounded-lg border p-3" /></label
                ><label
                    >Flete/otros costos S/<input
                        v-model="form.additional"
                        inputmode="decimal"
                        class="mt-2 w-full rounded-lg border p-3"
                /></label>
            </div>
            <p class="text-sm text-slate-600">
                Descuentos y costos se reparten según el valor de las líneas,
                conservando cada céntimo. Si un costo unitario necesita redondeo
                se separa en dos lotes. El documento crea borradores: confirma y
                recibe los lotes para registrar las existencias. Es control
                gerencial; no calcula impuestos fiscales.
            </p>
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="text-red-700"
            >
                {{ error }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-xl bg-emerald-800 p-3 text-white"
            >
                Guardar compra
            </button>
        </form>
    </details>
</template>
