<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
defineProps<{ slug: string }>();
const form = useForm({ id: '', token: '' });
</script>
<template>
    <Head title="Seguir mi pedido" />
    <main class="mx-auto max-w-lg p-6 py-16 text-slate-900">
        <Link :href="`/tienda/${slug}`" class="text-sm text-emerald-700"
            >← Volver a la tienda</Link
        >
        <h1 class="mt-8 text-3xl font-semibold">Sigue tu pedido</h1>
        <p class="mt-4 text-slate-500">
            Ingresa el código y la clave privada que recibiste al comprar.
        </p>
        <form
            class="mt-8 grid gap-5"
            @submit.prevent="form.post(`/tienda/${slug}/seguimiento`)"
        >
            <label class="grid gap-2"
                >Código del pedido<input
                    v-model="form.id"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2"
                >Clave de seguimiento<input
                    v-model="form.token"
                    type="password"
                    required
                    autocomplete="off"
                    class="rounded-xl border p-3"
            /></label>
            <p
                v-for="(e, k) in form.errors"
                :key="k"
                class="text-sm text-red-700"
            >
                {{ e }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-xl bg-emerald-800 p-4 font-semibold text-white"
            >
                Consultar pedido
            </button>
        </form>
    </main>
</template>
