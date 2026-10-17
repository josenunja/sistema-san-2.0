<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
const token = window.location.hash.slice(1);
history.replaceState(null, '', window.location.pathname);
const form = useForm({ token, name: '', password: '' });
</script>
<template>
    <Head title="Aceptar invitación" />
    <main class="mx-auto max-w-lg p-6 py-16 text-slate-900">
        <p class="font-bold text-emerald-700">SAS SAN</p>
        <h1 class="mt-8 text-3xl font-semibold">Únete a tu equipo</h1>
        <p class="mt-4 text-slate-500">
            Si ya tienes una cuenta, ingresa su contraseña actual. La invitación
            asigna únicamente el rol autorizado por tu empresa.
        </p>
        <form
            class="mt-8 grid gap-5"
            @submit.prevent="form.post('/invitacion')"
        >
            <label class="grid gap-2"
                >Nombre<input
                    v-model="form.name"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2"
                >Contraseña (mínimo 12 caracteres)<input
                    v-model="form.password"
                    type="password"
                    required
                    minlength="12"
                    class="rounded-xl border p-3"
            /></label>
            <div role="alert" class="text-sm text-red-700">
                <p v-for="(e, k) in form.errors" :key="k">{{ e }}</p>
            </div>
            <button
                :disabled="form.processing || !token"
                class="rounded-xl bg-emerald-800 p-4 text-white disabled:opacity-50"
            >
                Aceptar invitación
            </button>
            <p v-if="!token" class="text-sm text-red-700">
                Abre el enlace completo de tu invitación.
            </p>
        </form>
    </main>
</template>
