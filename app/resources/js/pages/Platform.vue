<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
type Tenant = {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    plan: string;
};
defineProps<{
    tenants: { data: Tenant[] };
    plans: {
        slug: string;
        name: string;
        product_limit: number;
        warehouse_limit: number;
        user_limit: number;
    }[];
}>();
const page = usePage();
const form = useForm({ name: '', slug: '', plan: 'inicial' });
const invite = useForm({ tenant_id: '', email: '', role: 'owner' });
function update(t: Tenant) {
    router.patch(`/plataforma/${t.id}`, { active: !t.active, plan: t.plan });
}
function plan(t: Tenant) {
    router.patch(`/plataforma/${t.id}`, { active: t.active, plan: t.plan });
}
</script>
<template>
    <Head title="Administración de plataforma" />
    <main class="min-h-screen bg-stone-100 p-6 text-slate-900 lg:p-12">
        <div class="mx-auto max-w-6xl">
            <header class="flex items-center justify-between">
                <Link href="/dashboard" class="font-bold text-emerald-700"
                    >SAS SAN · Plataforma</Link
                ><Link href="/settings/security" class="text-sm"
                    >Mi seguridad</Link
                >
            </header>
            <h1 class="mt-10 text-4xl font-semibold">Empresas y planes</h1>
            <p class="mt-4 text-slate-500">
                El acceso a esta pantalla no concede acceso a los datos
                comerciales de cada empresa.
            </p>
            <p
                v-if="page.props.success"
                role="status"
                class="mt-5 rounded-xl bg-emerald-100 p-4"
            >
                {{ page.props.success }}
            </p>
            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <form
                    class="grid gap-4 rounded-2xl border bg-white p-6"
                    @submit.prevent="
                        form.post('/plataforma', {
                            onSuccess: () => form.reset(),
                        })
                    "
                >
                    <h2 class="text-xl font-semibold">Crear empresa</h2>
                    <label class="grid gap-2 text-sm"
                        >Nombre<input
                            v-model="form.name"
                            required
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Identificador<input
                            v-model="form.slug"
                            required
                            pattern="[a-z0-9]+(-[a-z0-9]+)*"
                            class="rounded-xl border p-3" /></label
                    ><label class="grid gap-2 text-sm"
                        >Plan<select
                            v-model="form.plan"
                            class="rounded-xl border p-3"
                        >
                            <option
                                v-for="p in plans"
                                :key="p.slug"
                                :value="p.slug"
                            >
                                {{ p.name }}
                            </option>
                        </select></label
                    >
                    <p
                        v-for="(e, k) in form.errors"
                        :key="k"
                        class="text-sm text-red-700"
                    >
                        {{ e }}
                    </p>
                    <button
                        :disabled="form.processing"
                        class="rounded-xl bg-emerald-800 p-3 text-white"
                    >
                        Crear empresa
                    </button>
                </form>
                <form
                    class="grid gap-4 rounded-2xl border bg-white p-6"
                    @submit.prevent="invite.post('/invitaciones')"
                >
                    <h2 class="text-xl font-semibold">
                        Invitar al primer propietario
                    </h2>
                    <label class="grid gap-2 text-sm"
                        >Empresa nueva<select
                            v-model="invite.tenant_id"
                            required
                            class="rounded-xl border p-3"
                        >
                            <option value="">Seleccionar</option>
                            <option
                                v-for="t in tenants.data"
                                :key="t.id"
                                :value="t.id"
                            >
                                {{ t.name }}
                            </option>
                        </select></label
                    ><label class="grid gap-2 text-sm"
                        >Correo de su propietario<input
                            v-model="invite.email"
                            required
                            type="email"
                            class="rounded-xl border p-3"
                    /></label>
                    <p class="text-sm text-slate-500">
                        Solo se permite iniciar empresas sin miembros. Para
                        equipos existentes, su propietario gestiona las
                        invitaciones.
                    </p>
                    <p
                        v-for="(e, k) in invite.errors"
                        :key="k"
                        class="text-sm text-red-700"
                    >
                        {{ e }}
                    </p>
                    <button
                        :disabled="invite.processing"
                        class="rounded-xl border p-3 font-medium"
                    >
                        Crear invitación
                    </button>
                </form>
            </div>
            <div class="mt-8 overflow-auto rounded-2xl border bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="p-4">Empresa</th>
                            <th class="p-4">Plan</th>
                            <th class="p-4">Estado</th>
                            <th class="p-4">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="t in tenants.data" :key="t.id">
                            <td class="p-4 font-semibold">
                                {{ t.name
                                }}<span
                                    class="mt-1 block text-xs font-normal text-slate-400"
                                    >{{ t.slug }}</span
                                >
                            </td>
                            <td class="p-4">
                                <select
                                    v-model="t.plan"
                                    class="rounded-lg border p-2"
                                    @change="plan(t)"
                                >
                                    <option
                                        v-for="p in plans"
                                        :key="p.slug"
                                        :value="p.slug"
                                    >
                                        {{ p.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="p-4">
                                {{ t.active ? 'Activa' : 'Suspendida' }}
                            </td>
                            <td class="p-4">
                                <button
                                    :class="
                                        t.active
                                            ? 'text-red-700'
                                            : 'text-emerald-700'
                                    "
                                    @click="update(t)"
                                >
                                    {{ t.active ? 'Suspender' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <article
                    v-for="p in plans"
                    :key="p.slug"
                    class="rounded-2xl border bg-white p-6"
                >
                    <h2 class="font-semibold">{{ p.name }}</h2>
                    <p class="mt-3 text-sm text-slate-500">
                        {{ p.product_limit }} productos<br />{{
                            p.warehouse_limit
                        }}
                        almacenes<br />{{ p.user_limit }} miembros
                    </p>
                </article>
            </div>
            <p class="mt-6 text-sm text-slate-500">
                La suscripción SaaS se gestiona manualmente. No hay cobros
                recurrentes ni pagos bancarios automáticos activados.
            </p>
        </div>
    </main>
</template>
