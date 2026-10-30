<script setup lang="ts">
import { ref, watch } from 'vue';
const props = defineProps<{ slug: string }>();
type Product = {
    id: number;
    name: string;
    sku: string;
    barcode?: string;
    price_minor: number;
};
const emit = defineEmits<{ selected: [product: Product] }>();
const term = ref('');
const results = ref<Product[]>([]);
const error = ref('');
let generation = 0;
let timer: ReturnType<typeof setTimeout>;
watch(term, (value) => {
    clearTimeout(timer);
    const current = ++generation;
    if (!value.trim()) {
        results.value = [];
        return;
    }
    timer = setTimeout(async () => {
        try {
            const response = await fetch(
                `/gestion/${props.slug}/buscar-productos?q=${encodeURIComponent(value)}`,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );
            if (!response.ok)
                throw new Error('No se pudo buscar. Revisa tu sesión.');
            const rows = (await response.json()) as Product[];
            if (current === generation) {
                results.value = rows;
                error.value = '';
            }
        } catch (caught) {
            if (current === generation) error.value = (caught as Error).message;
        }
    }, 250);
});
function select(product: Product) {
    emit('selected', product);
    term.value = '';
    results.value = [];
}
</script>
<template>
    <section class="mb-5 rounded-xl border bg-white p-4">
        <label class="grid gap-2 text-sm font-medium"
            >Buscar en todo el catálogo o leer código de barras<input
                v-model="term"
                autocomplete="off"
                maxlength="100"
                class="rounded-lg border p-3"
                placeholder="Nombre, SKU o código de barras"
                @keydown.enter.prevent="
                    results.length === 1 && select(results[0])
                "
        /></label>
        <p v-if="error" class="mt-2 text-sm text-red-700">{{ error }}</p>
        <ul v-if="results.length" class="mt-3 max-h-64 divide-y overflow-auto">
            <li v-for="product in results" :key="product.id">
                <button
                    type="button"
                    class="w-full p-3 text-left hover:bg-emerald-50"
                    @click="select(product)"
                >
                    {{ product.sku }} · {{ product.name }}
                </button>
            </li>
        </ul>
    </section>
</template>
