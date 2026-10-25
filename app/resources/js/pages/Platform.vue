<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import InvitationList, {
    type Invitation,
} from '@/components/InvitationList.vue';
import {
    ArrowRight,
    Check,
    KeyRound,
    Mail,
    ShieldCheck,
    Store,
} from '@lucide/vue';
type Tenant = {
    id: number;
    name: string;
    slug: string;
    active: boolean;
    plan: string;
    my_role: string | null;
    invitations: Invitation[];
};
defineProps<{
    tenants: {
        data: Tenant[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
    plans: {
        slug: string;
        name: string;
        product_limit: number;
        warehouse_limit: number;
        user_limit: number;
    }[];
}>();
const page = usePage();
const form = useForm({
    name: '',
    slug: '',
    plan: 'inicial',
    owner_email: '',
    manage_catalog: true,
});
const invite = useForm({ tenant_id: '', email: '', role: 'owner' });
function update(t: Tenant) {
    router.patch(`/plataforma/${t.id}`, { active: !t.active, plan: t.plan });
}
function plan(t: Tenant) {
    router.patch(`/plataforma/${t.id}`, { active: t.active, plan: t.plan });
}
const access = useForm({ enabled: false });
function catalogAccess(t: Tenant, enabled: boolean) {
    access.enabled = enabled;
    access.post(`/plataforma/${t.id}/catalogo`, { preserveScroll: true });
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
                Crea las tiendas de tus clientes y habilita tu ayuda de catálogo
                en cada una. El propietario puede subir sus productos por su
                cuenta.
            </p>
            <p
                v-if="
                    page.props.environment === 'native' ||
                    page.props.environment === 'local' ||
                    page.props.environment === 'testing'
                "
                class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"
            >
                En este entorno de pruebas, las invitaciones se guardan en el
                registro privado de correo. No se envían correos reales. Para
                entregar accesos automáticamente falta configurar el servicio de
                correo.
            </p>
            <p
                v-if="page.props.success"
                role="status"
                class="mt-5 rounded-xl bg-emerald-100 p-4"
            >
                {{ page.props.success }}
            </p>
            <div class="mt-8 grid gap-6 md:grid-cols-[1.2fr_.8fr]">
                <form
                    class="grid gap-4 rounded-2xl border bg-white p-6 shadow-sm"
                    @submit.prevent="
                        form.post('/plataforma', {
                            onSuccess: () => form.reset(),
                        })
                    "
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 text-emerald-800"
                            ><Store :size="21"
                        /></span>
                        <div>
                            <h2 class="text-xl font-semibold">
                                Crear empresa y dar acceso
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                La tienda y la invitación de su propietario se
                                crean juntas.
                            </p>
                        </div>
                    </div>
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
                    ><label class="grid gap-2 text-sm"
                        >Correo del propietario<input
                            v-model="form.owner_email"
                            required
                            type="email"
                            autocomplete="email"
                            placeholder="propietario@negocio.com"
                            class="rounded-xl border p-3"
                    /></label>
                    <p
                        class="flex gap-2 rounded-xl bg-emerald-50 p-3 text-xs leading-relaxed text-emerald-900"
                    >
                        <ShieldCheck :size="17" class="shrink-0" /> El
                        administrador no crea ni conoce la contraseña. El
                        propietario la elige al aceptar su enlace privado.
                    </p>
                    <label
                        class="flex items-start gap-3 rounded-xl border p-3 text-sm"
                    >
                        <input
                            v-model="form.manage_catalog"
                            type="checkbox"
                            class="mt-1"
                        />
                        <span
                            >También cargaré productos para este cliente<small
                                class="mt-1 block text-slate-500"
                                >Activa mi permiso de catálogo en esta tienda.
                                El cliente conserva su acceso de
                                propietario.</small
                            ></span
                        >
                    </label>
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
                        <span class="flex items-center justify-center gap-2"
                            >Crear y enviar invitación <ArrowRight :size="16"
                        /></span>
                    </button>
                </form>
                <aside
                    class="rounded-2xl bg-slate-950 p-7 text-white shadow-xl"
                >
                    <span
                        class="text-xs font-bold tracking-[.18em] text-emerald-400"
                        >FLUJO SEGURO</span
                    >
                    <h2 class="mt-3 text-2xl font-semibold">
                        Cada vendedor construye su propia tienda.
                    </h2>
                    <ol class="mt-7 grid gap-5 text-sm text-slate-300">
                        <li class="flex gap-3">
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/10 text-emerald-300"
                                ><Mail :size="16" /></span
                            ><span
                                ><strong class="block text-white"
                                    >1. Recibe la invitación</strong
                                >El enlace vence en 48 horas y solo funciona una
                                vez.</span
                            >
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/10 text-emerald-300"
                                ><KeyRound :size="16" /></span
                            ><span
                                ><strong class="block text-white"
                                    >2. Crea su acceso</strong
                                >Define su contraseña y activa la verificación
                                en dos pasos.</span
                            >
                        </li>
                        <li class="flex gap-3">
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/10 text-emerald-300"
                                ><Check :size="16" /></span
                            ><span
                                ><strong class="block text-white"
                                    >3. Diseña y publica</strong
                                >Sube logo, datos y elige Clásico, Urban o
                                Elegante.</span
                            >
                        </li>
                    </ol>
                </aside>
            </div>
            <details class="mt-6 rounded-2xl border bg-white p-6">
                <summary class="cursor-pointer font-semibold">
                    Invitar propietario de una empresa creada anteriormente
                </summary>
                <form
                    class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto]"
                    @submit.prevent="
                        invite.post('/invitaciones', {
                            onSuccess: () => invite.reset('email'),
                        })
                    "
                >
                    <label class="grid gap-2 text-sm"
                        >Empresa<select
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
                        >Correo del propietario<input
                            v-model="invite.email"
                            required
                            type="email"
                            class="rounded-xl border p-3"
                    /></label>
                    <button
                        :disabled="invite.processing"
                        class="self-end rounded-xl border px-5 py-3 font-medium"
                    >
                        Enviar acceso
                    </button>
                    <p
                        v-for="(error, field) in invite.errors"
                        :key="field"
                        class="text-sm text-red-700 md:col-span-3"
                    >
                        {{ error }}
                    </p>
                </form>
            </details>
            <p
                v-for="(error, key) in access.errors"
                :key="key"
                role="alert"
                class="mt-4 text-sm text-red-700"
            >
                {{ error }}
            </p>
            <section
                class="mt-8 space-y-4"
                aria-label="Accesos e invitaciones por tienda"
            >
                <article
                    v-for="t in tenants.data"
                    :key="t.id"
                    class="rounded-2xl border bg-white p-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <div>
                            <h2 class="font-semibold">{{ t.name }}</h2>
                            <p class="text-xs text-slate-500">{{ t.slug }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <Link
                                v-if="
                                    t.active &&
                                    ['owner', 'catalog'].includes(
                                        t.my_role || '',
                                    )
                                "
                                :href="`/gestion/${t.slug}`"
                                class="rounded-xl bg-emerald-800 px-4 py-2 text-white"
                                >Cargar productos</Link
                            >
                            <button
                                v-if="!t.my_role && t.active"
                                :disabled="access.processing"
                                class="rounded-xl border px-4 py-2"
                                @click="catalogAccess(t, true)"
                            >
                                Habilitar mi permiso de catálogo
                            </button>
                            <button
                                v-if="t.my_role === 'catalog'"
                                :disabled="access.processing"
                                class="rounded-xl border px-4 py-2 text-red-700"
                                @click="catalogAccess(t, false)"
                            >
                                Retirar mi permiso de catálogo
                            </button>
                        </div>
                    </div>
                    <InvitationList
                        :invitations="t.invitations"
                        :tenant-id="t.id"
                    />
                </article>
            </section>
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
            <nav
                v-if="tenants.last_page > 1"
                aria-label="Páginas de empresas"
                class="mt-4 flex items-center justify-between gap-4 text-sm"
            >
                <Link
                    v-if="tenants.prev_page_url"
                    :href="tenants.prev_page_url"
                    class="rounded-lg border bg-white px-4 py-2"
                    >Anterior</Link
                >
                <span v-else class="text-slate-400">Anterior</span>
                <span
                    >Página {{ tenants.current_page }} de
                    {{ tenants.last_page }}</span
                >
                <Link
                    v-if="tenants.next_page_url"
                    :href="tenants.next_page_url"
                    class="rounded-lg border bg-white px-4 py-2"
                    >Siguiente</Link
                >
                <span v-else class="text-slate-400">Siguiente</span>
            </nav>
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
