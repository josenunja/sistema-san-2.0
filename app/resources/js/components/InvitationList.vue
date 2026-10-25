<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { localDate } from '@/lib/commerce';
export type Invitation = {
    id: string;
    email: string;
    role: string;
    status: 'pending' | 'expired' | 'accepted' | 'revoked';
    expires_at: string;
    last_sent_at: string | null;
};
defineProps<{ invitations: Invitation[]; tenantId?: number }>();
const action = useForm({});
const confirming = ref<string | null>(null);
const labels = {
    pending: 'Pendiente',
    expired: 'Vencida',
    accepted: 'Aceptada',
    revoked: 'Revocada',
};
const roleLabels: Record<string, string> = {
    owner: 'Propietario',
    cashier: 'Caja',
    warehouse: 'Almacén',
    catalog: 'Catálogo',
};
function send(id: string, kind: 'reenviar' | 'revocar') {
    action.post(`/invitaciones/${id}/${kind}`, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = null;
        },
    });
}
</script>
<template>
    <div class="mt-5">
        <h3 class="font-semibold">Invitaciones y accesos pendientes</h3>
        <p class="mt-1 text-xs text-slate-500">
            Reenviar crea un enlace nuevo por 48 horas y desactiva el anterior.
            Revocar una invitación no retira una membresía ya aceptada.
        </p>
        <Link
            v-if="tenantId"
            :href="`/invitaciones/tienda/${tenantId}`"
            class="mt-3 inline-block text-sm font-medium text-emerald-800 underline"
            >Ver todo el historial de invitaciones</Link
        >
        <p v-if="!invitations.length" class="mt-4 text-sm text-slate-500">
            Todavía no hay invitaciones.
        </p>
        <p
            v-for="(error, key) in action.errors"
            :key="key"
            role="alert"
            class="mt-3 text-sm text-red-700"
        >
            {{ error }}
        </p>
        <article
            v-for="invitation in invitations"
            :key="invitation.id"
            class="mt-3 rounded-xl border bg-white p-4 text-sm"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-medium break-all">{{ invitation.email }}</p>
                    <p class="mt-1 text-slate-500">
                        {{ roleLabels[invitation.role] || invitation.role }} ·
                        {{ labels[invitation.status] }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Vence: {{ localDate(invitation.expires_at) }}
                    </p>
                </div>
                <div
                    v-if="['pending', 'expired'].includes(invitation.status)"
                    class="flex flex-wrap gap-3"
                >
                    <button
                        :disabled="action.processing"
                        class="rounded-lg border px-3 py-2 disabled:opacity-50"
                        @click="send(invitation.id, 'reenviar')"
                    >
                        Reenviar invitación
                    </button>
                    <button
                        :disabled="action.processing"
                        class="rounded-lg px-3 py-2 text-red-700 disabled:opacity-50"
                        @click="confirming = invitation.id"
                    >
                        Revocar invitación
                    </button>
                </div>
            </div>
            <div
                v-if="confirming === invitation.id"
                class="mt-4 rounded-lg bg-red-50 p-3"
            >
                <p>
                    ¿Revocar el acceso pendiente de {{ invitation.email }}? Su
                    enlace dejará de funcionar.
                </p>
                <div class="mt-3 flex gap-3">
                    <button
                        :disabled="action.processing"
                        class="rounded-lg bg-red-700 px-3 py-2 text-white"
                        @click="send(invitation.id, 'revocar')"
                    >
                        Confirmar revocación
                    </button>
                    <button
                        :disabled="action.processing"
                        class="rounded-lg border px-3 py-2"
                        @click="confirming = null"
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </article>
    </div>
</template>
