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
const loading = ref(false);
let generation = 0;
let timer: ReturnType<typeof setTimeout>;

async function performSearch(
    query: string,
    isInstant: boolean = false,
): Promise<Product[]> {
    if (!query.trim()) {
        results.value = [];
        return [];
    }
    const current = ++generation;
    loading.value = true;
    try {
        const response = await fetch(
            `/gestion/${props.slug}/buscar-productos?q=${encodeURIComponent(query)}`,
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
            loading.value = false;
        }
        return rows;
    } catch (caught) {
        if (current === generation) {
            error.value = (caught as Error).message;
            loading.value = false;
        }
        return [];
    }
}

watch(term, (value) => {
    clearTimeout(timer);
    if (!value.trim()) {
        results.value = [];
        return;
    }
    timer = setTimeout(() => {
        performSearch(value);
    }, 250);
});

async function handleEnter() {
    clearTimeout(timer);
    const query = term.value.trim();
    if (!query) return;

    // Si ya hay un único resultado visible, seleccionarlo
    if (results.value.length === 1) {
        select(results.value[0]);
        return;
    }

    // Búsqueda inmediata (típica de lector de código de barras)
    const rows = await performSearch(query, true);
    if (rows.length === 1) {
        select(rows[0]);
    } else if (rows.length > 1) {
        // Si hay coincidencia exacta por código de barras o SKU
        const exact = rows.find(
            (r) =>
                r.barcode?.toLowerCase() === query.toLowerCase() ||
                r.sku.toLowerCase() === query.toLowerCase(),
        );
        if (exact) {
            select(exact);
        }
    }
}

function select(product: Product) {
    emit('selected', product);
    term.value = '';
    results.value = [];
}
</script>
<template>
    <section class="mb-5 rounded-xl border bg-white p-4">
        <label class="grid gap-2 text-sm font-medium"
            >Buscar en todo el catálogo o leer código de barras
            <div class="relative">
                <input
                    v-model="term"
                    autocomplete="off"
                    maxlength="100"
                    class="w-full rounded-lg border p-3 pr-10 focus:ring-2 focus:ring-emerald-500"
                    placeholder="Nombre, SKU o código de barras"
                    @keydown.enter.prevent="handleEnter"
                />
                <span
                    v-if="loading"
                    class="absolute top-3.5 right-3 text-xs text-gray-400"
                    >Buscando...</span
                >
            </div>
        </label>
        <p v-if="error" class="mt-2 text-sm text-red-700">{{ error }}</p>
        <ul v-if="results.length" class="mt-3 max-h-64 divide-y overflow-auto">
            <li v-for="product in results" :key="product.id">
                <button
                    type="button"
                    class="w-full p-3 text-left hover:bg-emerald-50 focus:bg-emerald-50"
                    @click="select(product)"
                >
                    <span
                        class="font-mono text-xs font-semibold text-gray-500"
                        >{{ product.sku }}</span
                    >
                    <span
                        v-if="product.barcode"
                        class="ml-2 font-mono text-xs text-gray-400"
                    >
                        [{{ product.barcode }}]
                    </span>
                    <span class="ml-2">· {{ product.name }}</span>
                </button>
            </li>
        </ul>
    </section>
</template>
