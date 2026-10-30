<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    sessions: { key: string; agent: string; last_activity: number; current: boolean }[];
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seguridad de la cuenta',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Seguridad de la cuenta" />

    <h1 class="sr-only">Seguridad de la cuenta</h1>
    <section
        v-if="props.sessions.length"
        class="mb-8 space-y-4 rounded-xl border p-5"
    >
        <h2 class="text-lg font-semibold">Sesiones de tu cuenta</h2>
        <article
            v-for="session in props.sessions"
            :key="session.key"
            class="flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm"
        >
            <div>
                <p class="max-w-sm break-words">{{ session.agent }}</p>
                <p class="text-muted-foreground">
                    {{
                        new Date(session.last_activity * 1000).toLocaleString(
                            'es-PE',
                        )
                    }}
                    · {{ session.current ? 'Esta sesión' : 'Otro acceso' }}
                </p>
            </div>
            <button
                v-if="!session.current"
                type="button"
                class="text-red-700"
                @click="
                    router.delete(`/settings/sessions/${session.key}`, {
                        preserveScroll: true,
                    })
                "
            >
                Cerrar sesión
            </button>
        </article>
    </section>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Cambiar contraseña"
            description="Usa una contraseña larga y exclusiva para esta cuenta"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Contraseña actual</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Contraseña actual"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Nueva contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Nueva contraseña"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Confirmar contraseña"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>
