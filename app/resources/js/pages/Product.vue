<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { BadgePercent, Flame } from '@lucide/vue';
import { money } from '@/lib/commerce';
const props = defineProps<{
    tenant: { name: string; slug: string };
    product: {
        id: number;
        name: string;
        sku: string;
        price_minor: number;
        compare_at_minor: number | null;
        category: string;
        audience: 'women' | 'men' | 'kids' | 'unisex';
        trending: boolean;
        variant_group: string;
        size: string;
        color: string;
        description: string;
        published: boolean;
    };
    images: string[];
    variants: {
        id: number;
        name: string;
        price_minor: number;
        size: string;
        color: string;
        available: number;
    }[];
    available: number;
}>();
const selected = ref(props.images[0] || '');
const quantity = ref(1);
const error = ref('');
function add() {
    try {
        const key = `cart:${props.tenant.slug}`;
        const rows = JSON.parse(sessionStorage.getItem(key) || '[]') as {
            product_id: number;
            quantity: number;
            name: string;
            unit: number;
        }[];
        const old = rows.find((l) => l.product_id === props.product.id);
        if (old) old.quantity += quantity.value;
        else
            rows.push({
                product_id: props.product.id,
                quantity: quantity.value,
                name: props.product.name,
                unit: props.product.price_minor,
            });
        sessionStorage.setItem(key, JSON.stringify(rows));
        router.visit(`/tienda/${props.tenant.slug}#carrito`);
    } catch {
        error.value = 'No se pudo guardar el carrito en este navegador.';
    }
}
</script>
<template>
    <Head :title="product.name" />
    <main class="mx-auto min-h-screen max-w-6xl p-6 py-10 text-slate-900">
        <Link :href="`/tienda/${tenant.slug}`" class="text-sm text-emerald-700"
            >← {{ tenant.name }}</Link
        >
        <div class="mt-8 grid gap-10 md:grid-cols-2">
            <section>
                <div
                    class="flex min-h-72 items-center justify-center rounded-3xl border bg-white p-8"
                >
                    <img
                        v-if="selected"
                        :src="selected"
                        :alt="product.name"
                        class="max-h-96 w-full object-contain"
                    /><span v-else class="text-8xl text-emerald-800">{{
                        product.name.slice(0, 1)
                    }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        v-for="(image, i) in images"
                        :key="image"
                        class="rounded-xl border bg-white p-2"
                        :aria-label="`Ver foto ${i + 1}`"
                        @click="selected = image"
                    >
                        <img
                            :src="image"
                            :alt="`Foto ${i + 1}`"
                            class="h-16 w-16 object-contain"
                        />
                    </button>
                </div>
            </section>
            <section>
                <p class="text-xs tracking-widest text-slate-500">
                    {{ product.category }} · {{ product.sku }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-if="product.trending"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800"
                        ><Flame :size="13" /> Tendencia</span
                    >
                    <span
                        v-if="product.compare_at_minor"
                        class="inline-flex items-center gap-1 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700"
                        ><BadgePercent :size="13" /> Promoción</span
                    >
                    <span
                        v-if="product.size"
                        class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                        >Talla {{ product.size }}</span
                    >
                    <span
                        v-if="product.color"
                        class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"
                        >{{ product.color }}</span
                    >
                </div>
                <h1 class="mt-4 text-4xl font-semibold tracking-tight">
                    {{ product.name }}
                </h1>
                <p
                    v-if="product.compare_at_minor"
                    class="mt-6 text-base text-slate-400 line-through"
                >
                    {{ money(product.compare_at_minor) }}
                </p>
                <p
                    class="text-3xl font-semibold"
                    :class="
                        product.compare_at_minor ? 'mt-1 text-red-700' : 'mt-6'
                    "
                >
                    {{ money(product.price_minor) }}
                </p>
                <p
                    class="mt-5 leading-relaxed whitespace-pre-wrap text-slate-500"
                >
                    {{ product.description }}
                </p>
                <div v-if="variants.length" class="mt-7">
                    <h2 class="text-sm font-semibold">Elige tu variante</h2>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <Link
                            v-for="v in variants"
                            :key="v.id"
                            :href="`/tienda/${tenant.slug}/producto/${v.id}`"
                            class="rounded-xl border px-4 py-3 text-sm"
                            :class="
                                v.id === product.id
                                    ? 'border-emerald-800 bg-emerald-50'
                                    : ''
                            "
                            ><strong class="block">{{
                                [v.size && `Talla ${v.size}`, v.color]
                                    .filter(Boolean)
                                    .join(' · ') || v.name
                            }}</strong>
                            <small class="mt-1 block text-xs text-slate-500"
                                >{{ money(v.price_minor) }} ·
                                {{
                                    v.available > 0
                                        ? `${v.available} disponibles`
                                        : 'Agotado'
                                }}</small
                            ></Link
                        >
                    </div>
                </div>
                <p class="mt-6 text-sm text-slate-500">
                    {{ available }} unidades disponibles entre nuestros
                    almacenes.
                </p>
                <form class="mt-6 flex gap-3" @submit.prevent="add">
                    <input
                        v-model.number="quantity"
                        aria-label="Unidades"
                        required
                        type="number"
                        min="1"
                        :max="Math.max(1, available)"
                        class="w-24 rounded-xl border p-3"
                    /><button
                        :disabled="!product.published || available < 1"
                        class="flex-1 rounded-xl bg-emerald-800 p-4 text-white disabled:opacity-40"
                    >
                        {{ available > 0 ? 'Añadir al carrito' : 'Agotado' }}
                    </button>
                </form>
                <p v-if="error" role="alert" class="mt-3 text-sm text-red-700">
                    {{ error }}
                </p>
            </section>
        </div>
    </main>
</template>
