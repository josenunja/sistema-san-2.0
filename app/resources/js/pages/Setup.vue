<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { businessTypes } from '@/lib/businessTypes';
import {
    ArrowRight,
    Check,
    CheckCheck,
    Eye,
    EyeOff,
    Layers3,
    Package,
    ShoppingBag,
    Sparkles,
    Store,
    ShieldCheck,
} from '@lucide/vue';
const visible = ref(false);
const props = defineProps<{
    businessDefaults?: { name: string; slug: string; type: string };
}>();
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    business: props.businessDefaults?.name || '',
    slug: props.businessDefaults?.slug || '',
    business_type: props.businessDefaults?.type || 'general',
    local_confirmation: false,
});
const accountReady = computed(
    () =>
        !!form.name &&
        !!form.email &&
        form.password.length >= 12 &&
        form.password === form.password_confirmation,
);
</script>
<template>
    <Head title="Crea tu espacio · SAS SAN" />
    <main class="sas-app setup-page">
        <a class="setup-brand" href="/" aria-label="SAS SAN, inicio"
            ><span class="brand-symbol"><Layers3 :size="22" /></span
            ><span
                >SAS<span class="brand-accent">SAN</span
                ><small>EL ESPACIO DE TU NEGOCIO</small></span
            ></a
        >
        <div class="setup-layout">
            <section class="setup-story">
                <span class="eyebrow"
                    ><span class="status-dot"></span> UN NUEVO COMIENZO</span
                >
                <h1>
                    Tu negocio.<br />Más simple.<br /><span
                        >Más conectado.</span
                    >
                </h1>
                <p class="setup-intro">
                    Menos tareas sueltas. Más tiempo para crecer. Organiza tu
                    tienda, tus ventas y tu inventario en un solo lugar.
                </p>
                <div
                    class="setup-preview"
                    aria-label="Herramientas disponibles en tu espacio"
                >
                    <div class="preview-top">
                        <span class="preview-icon"><Store :size="20" /></span>
                        <div>
                            <strong>Tu próximo gran paso</strong
                            ><small>Un espacio para hacerlo realidad</small>
                        </div>
                        <span class="preview-spark"
                            ><Sparkles :size="18"
                        /></span>
                    </div>
                    <div class="preview-modules">
                        <div>
                            <span><Package :size="19" /></span
                            ><strong>Inventario</strong
                            ><small>Cada producto, en orden</small>
                        </div>
                        <div>
                            <span><ShoppingBag :size="19" /></span
                            ><strong>Ventas</strong
                            ><small>Todo bajo control</small>
                        </div>
                    </div>
                    <div class="preview-bottom">
                        <CheckCheck :size="17" /> Tu tienda y tu gestión,
                        siempre conectadas
                    </div>
                </div>
                <div class="setup-trust">
                    <ShieldCheck :size="20" />
                    <p>
                        <strong>Un espacio propio, desde el inicio.</strong
                        ><br />Tu empresa mantiene sus datos y accesos
                        independientes.
                    </p>
                </div>
            </section>
            <section class="setup-card">
                <div class="setup-card-heading">
                    <span class="eyebrow">CONFIGURACIÓN INICIAL</span
                    ><span class="setup-pill"
                        ><Sparkles :size="12" /> Empecemos</span
                    >
                </div>
                <h2>Crea tu espacio de trabajo</h2>
                <p class="setup-subtitle">
                    Cuéntanos un poco de ti y de tu negocio.
                </p>
                <div class="setup-steps" aria-label="Secciones del formulario">
                    <span :class="{ complete: accountReady }"
                        ><i
                            ><Check v-if="accountReady" :size="13" /><template
                                v-else
                                >1</template
                            ></i
                        >
                        Tu cuenta</span
                    >
                    <div></div>
                    <span><i>2</i> Tu negocio</span>
                </div>
                <form @submit.prevent="form.post('/instalacion')">
                    <div class="setup-section-label">
                        <span>01</span>
                        <h3>Tu cuenta principal</h3>
                    </div>
                    <div class="setup-fields">
                        <label
                            >Nombre completo<input
                                v-model="form.name"
                                required
                                autocomplete="name"
                                placeholder="¿Cómo te llamas?"
                                :aria-invalid="!!form.errors.name"
                            /><small
                                v-if="form.errors.name"
                                class="field-error"
                                >{{ form.errors.name }}</small
                            ></label
                        >
                        <label
                            >Correo electrónico<input
                                v-model="form.email"
                                required
                                type="email"
                                autocomplete="email"
                                placeholder="tu@correo.com"
                                :aria-invalid="!!form.errors.email"
                            /><small
                                v-if="form.errors.email"
                                class="field-error"
                                >{{ form.errors.email }}</small
                            ></label
                        >
                        <label
                            >Contraseña
                            <div class="password-field">
                                <input
                                    v-model="form.password"
                                    required
                                    minlength="12"
                                    :type="visible ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="Mínimo 12 caracteres"
                                /><button
                                    type="button"
                                    :aria-label="
                                        visible
                                            ? 'Ocultar contraseña'
                                            : 'Mostrar contraseña'
                                    "
                                    :aria-pressed="visible"
                                    @click="visible = !visible"
                                >
                                    <EyeOff v-if="visible" :size="17" /><Eye
                                        v-else
                                        :size="17"
                                    />
                                </button>
                            </div>
                            <small
                                v-if="form.errors.password"
                                class="field-error"
                                >{{ form.errors.password }}</small
                            ></label
                        >
                        <label
                            >Confirmar contraseña<input
                                v-model="form.password_confirmation"
                                required
                                :type="visible ? 'text' : 'password'"
                                autocomplete="new-password"
                                placeholder="Repite tu contraseña"
                        /></label>
                    </div>
                    <div class="setup-section-label business-label">
                        <span>02</span>
                        <h3>Tu negocio</h3>
                    </div>
                    <div class="setup-fields">
                        <label
                            >Tipo de negocio<select
                                v-model="form.business_type"
                                class="rounded-xl border bg-white p-3"
                            >
                                <option
                                    v-for="type in businessTypes"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select></label
                        >
                        <label
                            >Nombre del negocio<input
                                v-model="form.business"
                                required
                                placeholder="El nombre de tu marca"
                            /><small
                                v-if="form.errors.business"
                                class="field-error"
                                >{{ form.errors.business }}</small
                            ></label
                        >
                        <label
                            >Dirección de tu tienda
                            <div class="slug-field">
                                <span>/tienda/</span
                                ><input
                                    v-model="form.slug"
                                    required
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    placeholder="mi-negocio"
                                    aria-label="Dirección corta de la tienda"
                                />
                            </div>
                            <small
                                v-if="form.errors.slug"
                                class="field-error"
                                >{{ form.errors.slug }}</small
                            ></label
                        >
                    </div>
                    <label class="setup-consent"
                        ><input
                            v-model="form.local_confirmation"
                            type="checkbox"
                            required
                        /><span
                            >Confirmo que este es mi correo y que configuro esta
                            instalación local bajo mi control.<small
                                >En modo local no se envía un correo de
                                verificación externo.</small
                            ></span
                        ></label
                    >
                    <div
                        v-if="Object.keys(form.errors).length"
                        role="alert"
                        class="setup-errors"
                    >
                        <p v-for="(error, key) in form.errors" :key="key">
                            {{ error }}
                        </p>
                    </div>
                    <button :disabled="form.processing" class="setup-submit">
                        <span>{{
                            form.processing
                                ? 'Creando tu espacio…'
                                : 'Crear mi cuenta y empresa'
                        }}</span
                        ><ArrowRight :size="18" />
                    </button>
                    <p class="setup-note">
                        <ShieldCheck :size="14" /> Después configurarás la
                        autenticación en dos pasos.
                    </p>
                </form>
            </section>
        </div>
        <footer class="setup-footer">
            <span>© {{ new Date().getFullYear() }} SAS SAN</span
            ><span>Hecho para acompañar tu negocio.</span>
        </footer>
    </main>
</template>
