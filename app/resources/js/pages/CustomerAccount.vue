<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { money, state, localDate } from '@/lib/commerce';
const props = defineProps<{
    tenant: { name: string; slug: string };
    customer: {
        name: string;
        phone: string;
        address: string;
        marketing_consent: boolean;
    } | null;
    orders: {
        data: {
            id: string;
            total_minor: number;
            status: string;
            created_at: string;
        }[];
    } | null;
    verified: boolean;
}>();
const page = usePage();
const register = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    consent: false,
});
const profile = useForm({
    name: props.customer?.name || '',
    phone: props.customer?.phone || '',
    address: props.customer?.address || '',
    marketing_consent: props.customer?.marketing_consent || false,
    consent: false,
});
</script>
<template>
    <Head title="Mi cuenta de comprador" />
    <main class="mx-auto max-w-5xl p-6 py-12 text-slate-900">
        <Link :href="`/tienda/${tenant.slug}`" class="text-sm text-emerald-700"
            >← {{ tenant.name }}</Link
        >
        <h1 class="mt-8 text-3xl font-semibold">Tu cuenta en esta tienda</h1>
        <p class="mt-4 text-slate-500">
            Tu perfil comercial y tus pedidos pertenecen únicamente a
            {{ tenant.name }}.
        </p>
        <p
            v-if="page.props.success"
            role="status"
            class="mt-6 rounded-xl bg-emerald-100 p-4"
        >
            {{ page.props.success }}
        </p>
        <form
            v-if="!page.props.auth?.user"
            class="mt-8 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
            @submit.prevent="
                register.post(`/tienda/${tenant.slug}/cuenta/registro`)
            "
        >
            <h2 class="text-xl font-semibold md:col-span-2">
                Crear cuenta de comprador
            </h2>
            <label class="grid gap-2 text-sm"
                >Nombre<input
                    v-model="register.name"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2 text-sm"
                >Correo<input
                    v-model="register.email"
                    type="email"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2 text-sm"
                >Contraseña<input
                    v-model="register.password"
                    type="password"
                    required
                    minlength="12"
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2 text-sm"
                >Repetir contraseña<input
                    v-model="register.password_confirmation"
                    type="password"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="grid gap-2 text-sm"
                >Teléfono<input
                    v-model="register.phone"
                    type="tel"
                    required
                    class="rounded-xl border p-3" /></label
            ><label class="flex items-center gap-3 text-sm"
                ><input
                    v-model="register.consent"
                    type="checkbox"
                    required
                />Quiero guardar mi perfil en esta empresa.</label
            >
            <div class="text-sm text-red-700 md:col-span-2">
                <p v-for="(e, k) in register.errors" :key="k">{{ e }}</p>
            </div>
            <button
                :disabled="register.processing"
                class="rounded-xl bg-emerald-800 p-3 text-white"
            >
                Crear cuenta</button
            ><Link href="/login" class="self-center text-sm text-emerald-700"
                >Ya tengo cuenta → Ingresar</Link
            >
        </form>
        <template v-else
            ><div v-if="!verified" class="mt-8 rounded-xl bg-amber-50 p-6">
                <p>Verifica tu correo antes de asociar tus pedidos.</p>
                <Link
                    href="/email/verify"
                    class="mt-3 inline-block text-emerald-700"
                    >Verificación de correo →</Link
                >
            </div>
            <form
                v-else
                class="mt-8 grid gap-5 rounded-2xl border bg-white p-6 md:grid-cols-2"
                @submit.prevent="
                    profile.post(`/tienda/${tenant.slug}/cuenta/perfil`)
                "
            >
                <h2 class="text-xl font-semibold md:col-span-2">
                    Mi perfil comercial
                </h2>
                <label class="grid gap-2 text-sm"
                    >Nombre<input
                        v-model="profile.name"
                        required
                        class="rounded-xl border p-3" /></label
                ><label class="grid gap-2 text-sm"
                    >Teléfono<input
                        v-model="profile.phone"
                        required
                        class="rounded-xl border p-3" /></label
                ><label class="grid gap-2 text-sm md:col-span-2"
                    >Dirección<textarea
                        v-model="profile.address"
                        class="rounded-xl border p-3"
                    /></label
                ><label class="flex items-center gap-3 text-sm"
                    ><input
                        v-model="profile.marketing_consent"
                        type="checkbox"
                    />Autorizo comunicaciones comerciales de esta tienda.</label
                ><label class="flex items-center gap-3 text-sm"
                    ><input
                        v-model="profile.consent"
                        type="checkbox"
                        required
                    />Confirmo guardar estos datos en esta empresa.</label
                >
                <div class="text-sm text-red-700 md:col-span-2">
                    <p v-for="(e, k) in profile.errors" :key="k">{{ e }}</p>
                </div>
                <button
                    :disabled="profile.processing"
                    class="rounded-xl bg-emerald-800 p-3 text-white"
                >
                    Guardar mi perfil
                </button>
            </form>
            <h2 class="mt-10 mb-5 text-xl font-semibold">
                Mis pedidos asociados
            </h2>
            <div class="divide-y rounded-2xl border bg-white">
                <Link
                    v-for="o in orders?.data || []"
                    :key="o.id"
                    :href="`/tienda/${tenant.slug}/cuenta/pedido/${o.id}`"
                    class="flex flex-wrap justify-between gap-3 p-5 text-sm"
                    ><strong>{{ o.id.slice(0, 8) }}</strong
                    ><span>{{ localDate(o.created_at) }}</span
                    ><span>{{ state(o.status) }}</span
                    ><span>{{ money(o.total_minor) }} →</span></Link
                >
                <p
                    v-if="!orders?.data.length"
                    class="p-8 text-sm text-slate-400"
                >
                    Todavía no hay pedidos asociados. Las compras que realices
                    en esta tienda quedarán vinculadas a tu cuenta.
                </p>
            </div></template
        >
    </main>
</template>
