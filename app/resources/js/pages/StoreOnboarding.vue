<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    CheckCircle2,
    Clock3,
    Crown,
    Footprints,
    Gem,
    Heart,
    Eye,
    ImagePlus,
    Layers3,
    MapPin,
    Monitor,
    Palette,
    Paintbrush,
    Save,
    Shirt,
    Smartphone,
    Sparkles,
    Store,
    Trophy,
    Upload,
    Zap,
} from '@lucide/vue';

type Theme =
    | 'classic'
    | 'urban'
    | 'elegant'
    | 'feminine'
    | 'pastel'
    | 'glamour'
    | 'sport'
    | 'footwear'
    | 'minimal'
    | 'vintage';
type Settings = {
    store_name?: string;
    business_type?: string;
    tagline?: string;
    description?: string;
    whatsapp?: string;
    location?: string;
    contact_email?: string;
    storefront_theme?: Theme;
    instagram_url?: string;
    facebook_url?: string;
    tiktok_url?: string;
    primary_color?: string;
    accent_color?: string;
    section_order?: string[];
    business_hours?: BusinessHour[];
    delivery_methods?: string[];
    delivery_price?: string;
    courier_price?: string;
    shipping_price?: string;
    custom_domain?: string | null;
    featured_coupon_code?: string;
    onboarding_completed_at?: string | null;
};
type BusinessHour = {
    day: string;
    open: string;
    close: string;
    closed: boolean;
};

const props = defineProps<{
    tenant: { name: string; slug: string };
    settings: Settings;
    logoUrl: string | null;
    bannerUrl: string | null;
}>();

const step = ref(1);
const device = ref<'desktop' | 'mobile'>('desktop');
const logoPreview = ref<string | null>(props.logoUrl);
const bannerPreview = ref<string | null>(props.bannerUrl);
let objectUrl: string | null = null;
let bannerObjectUrl: string | null = null;
const defaultHours: BusinessHour[] = [
    ['monday', 'Lunes'],
    ['tuesday', 'Martes'],
    ['wednesday', 'Miércoles'],
    ['thursday', 'Jueves'],
    ['friday', 'Viernes'],
    ['saturday', 'Sábado'],
    ['sunday', 'Domingo'],
].map(([day]) => ({
    day,
    open: '09:00',
    close: '18:00',
    closed: day === 'sunday',
}));
const sectionLabels: Record<string, string> = {
    all: 'Todo',
    women: 'Mujer',
    men: 'Hombre',
    kids: 'Niños',
    trends: 'Tendencias',
    promotions: 'Promociones',
};
const dayLabels: Record<string, string> = {
    monday: 'Lunes',
    tuesday: 'Martes',
    wednesday: 'Miércoles',
    thursday: 'Jueves',
    friday: 'Viernes',
    saturday: 'Sábado',
    sunday: 'Domingo',
};
const form = useForm({
    name: props.settings.store_name || props.tenant.name,
    business_type: props.settings.business_type || 'fashion',
    tagline:
        props.settings.tagline ||
        'Prendas que hablan de ti, seleccionadas con intención.',
    description: props.settings.description || '',
    whatsapp: props.settings.whatsapp || '',
    location: props.settings.location || '',
    contact_email: props.settings.contact_email || '',
    instagram_url: props.settings.instagram_url || '',
    facebook_url: props.settings.facebook_url || '',
    tiktok_url: props.settings.tiktok_url || '',
    primary_color: props.settings.primary_color || '#1f6b4f',
    accent_color: props.settings.accent_color || '#b9e769',
    section_order: props.settings.section_order || [
        'all',
        'women',
        'men',
        'kids',
        'trends',
        'promotions',
    ],
    business_hours: props.settings.business_hours || defaultHours,
    delivery_methods: props.settings.delivery_methods || ['pickup'],
    delivery_price: props.settings.delivery_price || '',
    courier_price: props.settings.courier_price || '',
    shipping_price: props.settings.shipping_price || '',
    custom_domain: props.settings.custom_domain || '',
    featured_coupon_code: props.settings.featured_coupon_code || '',
    storefront_theme: props.settings.storefront_theme || ('classic' as Theme),
    publication: 'draft' as 'draft' | 'publish',
    logo: null as File | null,
    banner: null as File | null,
    confirmation: false,
});

const themes: {
    id: Theme;
    name: string;
    description: string;
    icon: typeof Store;
}[] = [
    {
        id: 'classic',
        name: 'Clásico',
        description: 'Limpio, cercano y fácil de recorrer.',
        icon: Store,
    },
    {
        id: 'urban',
        name: 'Urban Freestyle',
        description: 'Oscuro, energético y con movimiento.',
        icon: Zap,
    },
    {
        id: 'elegant',
        name: 'Elegante',
        description: 'Editorial, sobrio y con detalles dorados.',
        icon: Crown,
    },
    {
        id: 'feminine',
        name: 'Femenino Chic',
        description: 'Rosa suave, crema y una presencia delicada.',
        icon: Heart,
    },
    {
        id: 'pastel',
        name: 'Boutique Pastel',
        description: 'Lavanda, menta y tarjetas redondeadas.',
        icon: Palette,
    },
    {
        id: 'glamour',
        name: 'Glamour',
        description: 'Negro, rosa intenso y destellos dorados.',
        icon: Gem,
    },
    {
        id: 'sport',
        name: 'Deportivo',
        description: 'Contrastes fuertes y energía en movimiento.',
        icon: Trophy,
    },
    {
        id: 'footwear',
        name: 'Calzado Premium',
        description: 'Fotografía protagonista y estilo editorial.',
        icon: Footprints,
    },
    {
        id: 'minimal',
        name: 'Minimalista',
        description: 'Blanco, negro y todo el foco en el producto.',
        icon: Store,
    },
    {
        id: 'vintage',
        name: 'Vintage',
        description: 'Tonos tierra, tipografía retro y textura cálida.',
        icon: Clock3,
    },
];
const steps = [
    { number: 1, name: 'Tu marca', detail: 'Nombre e identidad' },
    { number: 2, name: 'Tu estilo', detail: 'Diseño del catálogo' },
    { number: 3, name: 'Contacto', detail: 'Redes y dominio' },
    { number: 4, name: 'Publicar', detail: 'Horarios y entregas' },
] as const;
const activeTheme = computed(() =>
    themes.find((theme) => theme.id === form.storefront_theme),
);
const progress = computed(() => `${Math.round((step.value / 4) * 100)}%`);

function chooseLogo(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    form.logo = file;
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = file ? URL.createObjectURL(file) : null;
    logoPreview.value = objectUrl || props.logoUrl;
}

function chooseBanner(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    form.banner = file;
    if (bannerObjectUrl) URL.revokeObjectURL(bannerObjectUrl);
    bannerObjectUrl = file ? URL.createObjectURL(file) : null;
    bannerPreview.value = bannerObjectUrl || props.bannerUrl;
}

function moveSection(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= form.section_order.length) return;
    const order = [...form.section_order];
    [order[index], order[target]] = [order[target], order[index]];
    form.section_order = order;
}

function toggleDelivery(method: string) {
    form.delivery_methods = form.delivery_methods.includes(method)
        ? form.delivery_methods.filter((item) => item !== method)
        : [...form.delivery_methods, method];
}

function submit(publication: 'draft' | 'publish') {
    form.publication = publication;
    form.post(`/gestion/${props.tenant.slug}/configurar`, {
        forceFormData: true,
        preserveScroll: true,
    });
}

onUnmounted(() => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    if (bannerObjectUrl) URL.revokeObjectURL(bannerObjectUrl);
});
</script>

<template>
    <Head title="Diseña tu tienda" />
    <main class="onboarding-page">
        <div class="onboarding-atmosphere" aria-hidden="true">
            <span v-for="particle in 12" :key="particle"></span>
        </div>

        <header class="onboarding-header">
            <Link href="/dashboard" class="onboarding-brand">
                <span><Layers3 :size="20" /></span>
                <strong>SAS<span>SAN</span></strong>
            </Link>
            <div class="onboarding-header-actions">
                <span class="secure-state"
                    ><CheckCircle2 :size="15" /> Cambios protegidos</span
                >
                <Link :href="`/gestion/${tenant.slug}`" class="exit-link">
                    Ir al catálogo <ArrowRight :size="15" />
                </Link>
            </div>
        </header>

        <section class="onboarding-shell">
            <aside class="onboarding-progress">
                <span class="progress-kicker">CONFIGURACIÓN DE TIENDA</span>
                <h1>Dale identidad a tu negocio.</h1>
                <p>
                    Tu catálogo, tus datos y tu estilo se mantienen separados de
                    las demás empresas.
                </p>
                <div class="progress-line">
                    <i :style="{ width: progress }"></i>
                </div>
                <nav aria-label="Pasos de configuración">
                    <button
                        v-for="item in steps"
                        :key="item.number"
                        type="button"
                        :class="{
                            active: step === item.number,
                            complete: step > item.number,
                        }"
                        @click="step = item.number"
                    >
                        <span
                            ><Check
                                v-if="step > item.number"
                                :size="15"
                            /><template v-else>{{
                                item.number
                            }}</template></span
                        >
                        <span
                            ><strong>{{ item.name }}</strong
                            ><small>{{ item.detail }}</small></span
                        >
                    </button>
                </nav>
                <div class="onboarding-note">
                    <Sparkles :size="18" />
                    <p>
                        <strong>Una sola plataforma.</strong> Cada tienda
                        conserva su propio diseño, productos y clientes.
                    </p>
                </div>
            </aside>

            <section class="onboarding-workspace">
                <form @submit.prevent="submit('publish')">
                    <div v-if="step === 1" class="form-step">
                        <div class="step-heading">
                            <span>PASO 01</span>
                            <h2>Empecemos por tu marca</h2>
                            <p>
                                Estos datos serán la carta de presentación de tu
                                tienda.
                            </p>
                        </div>
                        <div class="brand-fields">
                            <label class="logo-upload">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="chooseLogo"
                                />
                                <span class="logo-preview">
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        alt="Vista previa del logo"
                                    />
                                    <ImagePlus v-else :size="30" />
                                </span>
                                <span
                                    ><strong>{{
                                        logoPreview
                                            ? 'Cambiar logo'
                                            : 'Sube tu logo'
                                    }}</strong
                                    ><small
                                        >PNG, JPG o WEBP · máximo 3 MB</small
                                    ></span
                                >
                                <Upload :size="17" />
                            </label>
                            <label class="banner-upload">
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="chooseBanner"
                                />
                                <span class="banner-preview">
                                    <img
                                        v-if="bannerPreview"
                                        :src="bannerPreview"
                                        alt="Vista previa de la portada"
                                    />
                                    <ImagePlus v-else :size="30" />
                                </span>
                                <span>
                                    <strong>{{
                                        bannerPreview
                                            ? 'Cambiar imagen de portada'
                                            : 'Sube una imagen de portada'
                                    }}</strong>
                                    <small
                                        >Panorámica recomendada · máximo 5
                                        MB</small
                                    >
                                </span>
                                <Upload :size="17" />
                            </label>
                            <div class="field-grid">
                                <label
                                    >Nombre de la tienda<input
                                        v-model="form.name"
                                        required
                                        maxlength="160"
                                        placeholder="Ej. Distrito Norte"
                                /></label>
                                <label
                                    >Tipo de negocio<select
                                        v-model="form.business_type"
                                        required
                                    >
                                        <option value="fashion">
                                            Moda y ropa
                                        </option>
                                        <option value="footwear">
                                            Calzado
                                        </option>
                                        <option value="accessories">
                                            Accesorios
                                        </option>
                                        <option value="mixed">
                                            Tienda variada
                                        </option>
                                        <option value="other">Otro</option>
                                    </select></label
                                >
                            </div>
                            <label
                                >Frase principal<input
                                    v-model="form.tagline"
                                    required
                                    maxlength="300"
                                    placeholder="Una frase corta que conecte con tus clientes"
                            /></label>
                            <label
                                >Historia o descripción<textarea
                                    v-model="form.description"
                                    rows="4"
                                    maxlength="1000"
                                    placeholder="Cuenta qué hace diferente a tu negocio…"
                                ></textarea>
                            </label>
                        </div>
                    </div>

                    <div v-else-if="step === 2" class="form-step">
                        <div class="step-heading">
                            <span>PASO 02</span>
                            <h2>Elige el estilo de tu vitrina</h2>
                            <p>
                                El contenido es el mismo; cambia la personalidad
                                visual.
                            </p>
                        </div>
                        <div class="theme-options">
                            <button
                                v-for="theme in themes"
                                :key="theme.id"
                                type="button"
                                :class="[
                                    'theme-option',
                                    theme.id,
                                    {
                                        selected:
                                            form.storefront_theme === theme.id,
                                    },
                                ]"
                                @click="form.storefront_theme = theme.id"
                            >
                                <span class="theme-swatch"
                                    ><component :is="theme.icon" :size="22"
                                /></span>
                                <span
                                    ><strong>{{ theme.name }}</strong
                                    ><small>{{
                                        theme.description
                                    }}</small></span
                                >
                                <span class="theme-check"
                                    ><Check :size="14"
                                /></span>
                            </button>
                        </div>
                        <div class="customization-grid">
                            <label>
                                Color principal
                                <span class="color-control">
                                    <input
                                        v-model="form.primary_color"
                                        type="color"
                                    />
                                    <input
                                        v-model="form.primary_color"
                                        pattern="#[0-9A-Fa-f]{6}"
                                        maxlength="7"
                                    />
                                </span>
                            </label>
                            <label>
                                Color de acento
                                <span class="color-control">
                                    <input
                                        v-model="form.accent_color"
                                        type="color"
                                    />
                                    <input
                                        v-model="form.accent_color"
                                        pattern="#[0-9A-Fa-f]{6}"
                                        maxlength="7"
                                    />
                                </span>
                            </label>
                        </div>
                        <div class="section-sorter">
                            <div>
                                <strong>Orden del menú</strong>
                                <small
                                    >Decide qué sección verá primero tu
                                    cliente.</small
                                >
                            </div>
                            <ol>
                                <li
                                    v-for="(
                                        sectionName, index
                                    ) in form.section_order"
                                    :key="sectionName"
                                >
                                    <span>{{
                                        sectionLabels[sectionName]
                                    }}</span>
                                    <button
                                        type="button"
                                        :disabled="index === 0"
                                        aria-label="Mover sección hacia arriba"
                                        @click="moveSection(index, -1)"
                                    >
                                        ↑
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="
                                            index ===
                                            form.section_order.length - 1
                                        "
                                        aria-label="Mover sección hacia abajo"
                                        @click="moveSection(index, 1)"
                                    >
                                        ↓
                                    </button>
                                </li>
                            </ol>
                        </div>
                        <div class="preview-toolbar">
                            <span><Eye :size="16" /> Vista previa en vivo</span>
                            <div>
                                <button
                                    type="button"
                                    :class="{ active: device === 'desktop' }"
                                    aria-label="Vista de escritorio"
                                    @click="device = 'desktop'"
                                >
                                    <Monitor :size="16" />
                                </button>
                                <button
                                    type="button"
                                    :class="{ active: device === 'mobile' }"
                                    aria-label="Vista móvil"
                                    @click="device = 'mobile'"
                                >
                                    <Smartphone :size="16" />
                                </button>
                            </div>
                        </div>
                        <div :class="['store-preview-wrap', device]">
                            <div
                                :class="[
                                    'store-preview',
                                    `preview-${form.storefront_theme}`,
                                ]"
                                :style="{
                                    '--preview-primary': form.primary_color,
                                    '--preview-accent': form.accent_color,
                                }"
                            >
                                <div
                                    class="preview-particles"
                                    aria-hidden="true"
                                >
                                    <i v-for="item in 6" :key="item"></i>
                                </div>
                                <header>
                                    <span class="preview-logo"
                                        ><img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="" /><Shirt v-else :size="18"
                                    /></span>
                                    <strong>{{
                                        form.name || 'Tu tienda'
                                    }}</strong>
                                    <nav>
                                        <span>Mujer</span><span>Hombre</span
                                        ><span>Ofertas</span>
                                    </nav>
                                </header>
                                <section
                                    :class="{ 'has-banner': bannerPreview }"
                                    :style="
                                        bannerPreview
                                            ? {
                                                  backgroundImage: `linear-gradient(90deg, ${form.primary_color}e8, ${form.primary_color}70), url(${bannerPreview})`,
                                              }
                                            : undefined
                                    "
                                >
                                    <small
                                        >{{ activeTheme?.name.toUpperCase() }} ·
                                        NUEVA COLECCIÓN</small
                                    >
                                    <h3>
                                        {{ form.name || 'Tu nueva tienda' }}
                                    </h3>
                                    <p>
                                        {{
                                            form.tagline ||
                                            'Una frase que define tu negocio.'
                                        }}
                                    </p>
                                    <button
                                        type="button"
                                        :style="{
                                            backgroundColor: form.accent_color,
                                            color: form.primary_color,
                                        }"
                                    >
                                        VER PRODUCTOS
                                    </button>
                                </section>
                                <div class="preview-products">
                                    <i></i><i></i><i></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="step === 3" class="form-step">
                        <div class="step-heading">
                            <span>PASO 03</span>
                            <h2>Contacto, redes y dirección digital</h2>
                            <p>
                                Ayuda a tus clientes a encontrarte y conversar
                                contigo.
                            </p>
                        </div>
                        <div class="field-grid contact-grid">
                            <label
                                >WhatsApp<input
                                    v-model="form.whatsapp"
                                    inputmode="numeric"
                                    maxlength="15"
                                    placeholder="51987654321"
                                /><small
                                    >Incluye código de país, sin + ni
                                    espacios.</small
                                ></label
                            >
                            <label
                                >Correo de contacto<input
                                    v-model="form.contact_email"
                                    type="email"
                                    maxlength="254"
                                    placeholder="ventas@tutienda.com"
                            /></label>
                        </div>
                        <label
                            >Ubicación o zona de atención<span
                                class="input-with-icon"
                                ><MapPin :size="17" /><input
                                    v-model="form.location"
                                    maxlength="300"
                                    placeholder="Ej. Miraflores, Lima · envíos a todo el Perú" /></span
                        ></label>
                        <div class="field-grid social-grid">
                            <label
                                >Instagram<input
                                    v-model="form.instagram_url"
                                    type="url"
                                    placeholder="https://instagram.com/tu_tienda"
                            /></label>
                            <label
                                >Facebook<input
                                    v-model="form.facebook_url"
                                    type="url"
                                    placeholder="https://facebook.com/tu_tienda"
                            /></label>
                            <label
                                >TikTok<input
                                    v-model="form.tiktok_url"
                                    type="url"
                                    placeholder="https://tiktok.com/@tu_tienda"
                            /></label>
                            <label
                                >Dominio personalizado<input
                                    v-model="form.custom_domain"
                                    inputmode="url"
                                    placeholder="tienda.tudominio.com"
                                /><small
                                    >Sin https://. Para usarlo públicamente
                                    también necesitarás configurar DNS y
                                    TLS.</small
                                ></label
                            >
                        </div>
                    </div>

                    <div v-else class="form-step">
                        <div class="step-heading">
                            <span>PASO 04</span>
                            <h2>Horarios, entregas y promoción</h2>
                            <p>
                                Define cómo atenderás los pedidos antes de
                                publicar.
                            </p>
                        </div>
                        <div class="hours-editor">
                            <strong>Horario de atención</strong>
                            <div
                                v-for="hours in form.business_hours"
                                :key="hours.day"
                                class="hours-row"
                            >
                                <span>{{ dayLabels[hours.day] }}</span>
                                <label class="closed-toggle">
                                    <input
                                        v-model="hours.closed"
                                        type="checkbox"
                                    />
                                    Cerrado
                                </label>
                                <input
                                    v-model="hours.open"
                                    type="time"
                                    :disabled="hours.closed"
                                    :required="!hours.closed"
                                    :aria-label="`Apertura del ${dayLabels[hours.day]}`"
                                />
                                <span>—</span>
                                <input
                                    v-model="hours.close"
                                    type="time"
                                    :disabled="hours.closed"
                                    :required="!hours.closed"
                                    :aria-label="`Cierre del ${dayLabels[hours.day]}`"
                                />
                            </div>
                        </div>
                        <div class="delivery-editor">
                            <strong>Métodos de entrega</strong>
                            <div class="delivery-options">
                                <label
                                    v-for="method in [
                                        ['pickup', 'Recojo en tienda'],
                                        ['delivery', 'Delivery local'],
                                        ['courier', 'Courier'],
                                        ['shipping', 'Envío nacional'],
                                    ]"
                                    :key="method[0]"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            form.delivery_methods.includes(
                                                method[0],
                                            )
                                        "
                                        @change="toggleDelivery(method[0])"
                                    />
                                    {{ method[1] }}
                                </label>
                            </div>
                            <div class="field-grid delivery-prices">
                                <label
                                    v-if="
                                        form.delivery_methods.includes(
                                            'delivery',
                                        )
                                    "
                                    >Tarifa delivery S/<input
                                        v-model="form.delivery_price"
                                        type="number"
                                        min="0"
                                        max="9999999.99"
                                        step="0.01"
                                        required
                                /></label>
                                <label
                                    v-if="
                                        form.delivery_methods.includes(
                                            'courier',
                                        )
                                    "
                                    >Tarifa courier S/<input
                                        v-model="form.courier_price"
                                        type="number"
                                        min="0"
                                        max="9999999.99"
                                        step="0.01"
                                        required
                                /></label>
                                <label
                                    v-if="
                                        form.delivery_methods.includes(
                                            'shipping',
                                        )
                                    "
                                    >Tarifa envío nacional S/<input
                                        v-model="form.shipping_price"
                                        type="number"
                                        min="0"
                                        max="9999999.99"
                                        step="0.01"
                                        required
                                /></label>
                            </div>
                        </div>
                        <label
                            >Cupón destacado (opcional)<input
                                v-model="form.featured_coupon_code"
                                maxlength="40"
                                placeholder="BIENVENIDA10"
                            /><small
                                >Créalo primero en Promociones. Aparecerá en la
                                portada mientras esté vigente.</small
                            ></label
                        >
                        <label class="publish-confirmation">
                            <input
                                v-model="form.confirmation"
                                type="checkbox"
                                required
                            />
                            <span
                                ><strong
                                    >Revisé la información de mi tienda</strong
                                ><small
                                    >Entiendo que “Publicar” hará visibles estos
                                    datos y el diseño en el catálogo
                                    público.</small
                                ></span
                            >
                        </label>
                        <div class="final-summary">
                            <span
                                :class="`summary-theme ${form.storefront_theme}`"
                                ><Paintbrush :size="20"
                            /></span>
                            <div>
                                <small>DISEÑO ELEGIDO</small
                                ><strong>{{ activeTheme?.name }}</strong>
                            </div>
                            <div>
                                <small>DIRECCIÓN PÚBLICA</small
                                ><strong>/tienda/{{ tenant.slug }}</strong>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="Object.keys(form.errors).length"
                        class="form-errors"
                        role="alert"
                    >
                        <p v-for="(error, field) in form.errors" :key="field">
                            {{ error }}
                        </p>
                    </div>

                    <footer class="form-actions">
                        <button
                            v-if="step > 1"
                            type="button"
                            class="button-back"
                            @click="step--"
                        >
                            <ArrowLeft :size="16" /> Atrás
                        </button>
                        <span v-else></span>
                        <div>
                            <button
                                v-if="step === 4"
                                type="button"
                                class="button-draft"
                                :disabled="form.processing"
                                @click="submit('draft')"
                            >
                                <Save :size="16" /> Guardar borrador
                            </button>
                            <button
                                v-if="step < 4"
                                type="button"
                                class="button-next"
                                @click="step++"
                            >
                                Continuar <ArrowRight :size="16" />
                            </button>
                            <button
                                v-else
                                type="submit"
                                class="button-publish"
                                :disabled="
                                    form.processing || !form.confirmation
                                "
                            >
                                <Sparkles :size="16" />
                                {{
                                    form.processing
                                        ? 'Guardando…'
                                        : settings.onboarding_completed_at
                                          ? 'Actualizar tienda'
                                          : 'Publicar mi tienda'
                                }}
                            </button>
                        </div>
                    </footer>
                </form>
            </section>
        </section>
    </main>
</template>

<style scoped>
.onboarding-page {
    --ink: #10251d;
    position: relative;
    min-height: 100vh;
    overflow: hidden;
    background: linear-gradient(135deg, #edf5ef 0%, #f7f4eb 48%, #f2e5d7 100%);
    color: var(--ink);
}
.onboarding-page:before {
    content: '';
    position: fixed;
    inset: 0;
    pointer-events: none;
    opacity: 0.26;
    background-image:
        linear-gradient(#6a8c7940 1px, transparent 1px),
        linear-gradient(90deg, #6a8c7940 1px, transparent 1px);
    background-size: 52px 52px;
}
.onboarding-atmosphere {
    position: fixed;
    inset: 0;
    pointer-events: none;
}
.onboarding-atmosphere span {
    position: absolute;
    width: 9px;
    height: 9px;
    border: 1px solid #4a8767;
    transform: rotate(45deg);
    opacity: 0.28;
    animation: onboarding-float 10s ease-in-out infinite;
}
.onboarding-atmosphere span:nth-child(3n) {
    border-radius: 50%;
}
.onboarding-atmosphere span:nth-child(1) {
    left: 4%;
    top: 18%;
}
.onboarding-atmosphere span:nth-child(2) {
    left: 12%;
    top: 73%;
    animation-delay: -3s;
}
.onboarding-atmosphere span:nth-child(3) {
    left: 37%;
    top: 8%;
    animation-delay: -6s;
}
.onboarding-atmosphere span:nth-child(4) {
    left: 82%;
    top: 13%;
    animation-delay: -2s;
}
.onboarding-atmosphere span:nth-child(5) {
    left: 94%;
    top: 48%;
    animation-delay: -7s;
}
.onboarding-atmosphere span:nth-child(6) {
    left: 77%;
    top: 88%;
    animation-delay: -4s;
}
.onboarding-atmosphere span:nth-child(7) {
    left: 48%;
    top: 92%;
    animation-delay: -8s;
}
.onboarding-atmosphere span:nth-child(8) {
    left: 24%;
    top: 52%;
    animation-delay: -5s;
}
.onboarding-atmosphere span:nth-child(9) {
    left: 67%;
    top: 37%;
    animation-delay: -1s;
}
.onboarding-atmosphere span:nth-child(10) {
    left: 9%;
    top: 39%;
    animation-delay: -9s;
}
.onboarding-atmosphere span:nth-child(11) {
    left: 56%;
    top: 17%;
    animation-delay: -4s;
}
.onboarding-atmosphere span:nth-child(12) {
    left: 89%;
    top: 69%;
    animation-delay: -2s;
}
@keyframes onboarding-float {
    0%,
    100% {
        transform: translateY(0) rotate(45deg);
    }
    50% {
        transform: translateY(-22px) rotate(80deg);
    }
}
.onboarding-header {
    position: relative;
    z-index: 2;
    display: flex;
    max-width: 1500px;
    margin: auto;
    padding: 24px 34px;
    align-items: center;
    justify-content: space-between;
}
.onboarding-brand {
    display: flex;
    align-items: center;
    gap: 11px;
    font-size: 21px;
    letter-spacing: -0.7px;
}
.onboarding-brand > span {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 13px;
    background: #20785d;
    color: white;
    box-shadow: 0 12px 25px #1a624932;
}
.onboarding-brand strong > span {
    color: #238060;
}
.onboarding-header-actions {
    display: flex;
    align-items: center;
    gap: 22px;
    font-size: 12px;
}
.secure-state,
.exit-link {
    display: flex;
    align-items: center;
    gap: 7px;
}
.secure-state {
    color: #4f7c68;
}
.exit-link {
    font-weight: 700;
}
.onboarding-shell {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    max-width: 1400px;
    min-height: 760px;
    margin: 6px auto 50px;
    border: 1px solid #b8cfc1;
    border-radius: 28px;
    background: #ffffffd9;
    box-shadow: 0 30px 90px #224b3820;
    backdrop-filter: blur(18px);
    overflow: hidden;
}
.onboarding-progress {
    padding: 46px 38px;
    color: #eefaf4;
    background: linear-gradient(165deg, #123e31, #09271f 65%, #071c17);
}
.progress-kicker,
.step-heading > span {
    color: #75d3ab;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 2px;
}
.onboarding-progress h1 {
    max-width: 230px;
    margin: 20px 0 15px;
    font-size: 33px;
    line-height: 1.08;
    letter-spacing: -1.4px;
}
.onboarding-progress > p {
    color: #a9c5b8;
    font-size: 13px;
    line-height: 1.7;
}
.progress-line {
    height: 3px;
    margin: 32px 0;
    border-radius: 9px;
    background: #ffffff18;
    overflow: hidden;
}
.progress-line i {
    display: block;
    height: 100%;
    background: #7adeb1;
    transition: width 0.4s ease;
}
.onboarding-progress nav {
    display: grid;
    gap: 7px;
}
.onboarding-progress nav button {
    display: grid;
    grid-template-columns: 38px 1fr;
    gap: 12px;
    width: 100%;
    padding: 11px;
    border-radius: 13px;
    color: #80a596;
    text-align: left;
    transition: 0.2s;
}
.onboarding-progress nav button > span:first-child {
    display: grid;
    width: 31px;
    height: 31px;
    place-items: center;
    border: 1px solid #ffffff26;
    border-radius: 10px;
    font-size: 11px;
}
.onboarding-progress nav button > span:last-child {
    display: grid;
    gap: 2px;
}
.onboarding-progress nav small {
    font-size: 10px;
    opacity: 0.7;
}
.onboarding-progress nav button.active {
    background: #ffffff10;
    color: white;
}
.onboarding-progress nav button.active > span:first-child {
    background: #70d8aa;
    color: #0c3528;
    border-color: transparent;
}
.onboarding-progress nav button.complete {
    color: #81c6a8;
}
.onboarding-note {
    display: flex;
    gap: 11px;
    margin-top: 50px;
    padding-top: 24px;
    border-top: 1px solid #ffffff17;
    color: #9ebcaf;
    font-size: 11px;
    line-height: 1.55;
}
.onboarding-note svg {
    flex: none;
    color: #70d8aa;
}
.onboarding-note strong {
    display: block;
    color: #d7eee4;
}
.onboarding-workspace {
    padding: 45px 54px 30px;
    overflow: hidden;
}
.onboarding-workspace form {
    max-width: 950px;
    margin: auto;
}
.form-step {
    min-height: 600px;
    animation: step-in 0.35s ease;
}
@keyframes step-in {
    from {
        opacity: 0;
        transform: translateX(14px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
.step-heading {
    margin-bottom: 30px;
}
.step-heading h2 {
    margin: 8px 0;
    font-size: 31px;
    letter-spacing: -1.2px;
}
.step-heading p {
    color: #718178;
    font-size: 13px;
}
.brand-fields,
.form-step {
    display: grid;
    gap: 20px;
}
.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.form-step label {
    display: grid;
    gap: 8px;
    color: #294238;
    font-size: 12px;
    font-weight: 700;
}
.form-step input,
.form-step select,
.form-step textarea {
    width: 100%;
    border: 1px solid #d6e2db;
    border-radius: 12px;
    background: #fbfdfb;
    padding: 13px 14px;
    color: #163027;
    outline: none;
    transition: 0.2s;
}
.form-step input:focus,
.form-step select:focus,
.form-step textarea:focus {
    border-color: #43a079;
    box-shadow: 0 0 0 3px #54ad8520;
}
.form-step label small {
    color: #84928b;
    font-size: 10px;
    font-weight: 500;
}
.logo-upload {
    grid-template-columns: auto 1fr auto !important;
    align-items: center;
    padding: 14px;
    border: 1px dashed #9fc5b1;
    border-radius: 15px;
    background: #f4faf6;
    cursor: pointer;
}
.logo-upload > input {
    display: none;
}
.logo-upload > span:nth-child(3) {
    display: grid;
    gap: 3px;
}
.logo-preview {
    display: grid;
    width: 70px;
    height: 70px;
    place-items: center;
    border-radius: 15px;
    background: white;
    color: #4c896d;
    overflow: hidden;
    box-shadow: 0 7px 18px #184e3510;
}
.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 7px;
}
.theme-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 13px;
}
.theme-option {
    position: relative;
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 11px;
    padding: 15px;
    border: 1px solid #dce4df;
    border-radius: 15px;
    text-align: left;
    transition: 0.2s;
}
.theme-option:hover,
.theme-option.selected {
    transform: translateY(-2px);
    border-color: #3c8c68;
    box-shadow: 0 12px 28px #194c3313;
}
.theme-option > span:nth-child(2) {
    display: grid;
    gap: 4px;
}
.theme-option small {
    color: #78867e;
    font-size: 9px;
    line-height: 1.4;
}
.theme-swatch {
    display: grid;
    width: 40px;
    height: 40px;
    place-items: center;
    border-radius: 11px;
}
.classic .theme-swatch {
    background: #e8f0e5;
    color: #315e45;
}
.urban .theme-swatch {
    background: #202426;
    color: #b9ff45;
}
.elegant .theme-swatch {
    background: #171512;
    color: #d7b66f;
}
.feminine .theme-swatch {
    background: #ffe5ee;
    color: #c54d79;
}
.pastel .theme-swatch {
    background: linear-gradient(135deg, #e8dcff, #d9f5e8);
    color: #755d9b;
}
.glamour .theme-swatch {
    background: #171116;
    color: #ff79ad;
    box-shadow: inset 0 0 0 1px #d9ae63;
}
.sport .theme-swatch {
    background: #102b52;
    color: #ff6b2c;
}
.footwear .theme-swatch {
    background: #e9e0d2;
    color: #27221d;
}
.minimal .theme-swatch {
    border: 1px solid #242424;
    border-radius: 3px;
    background: #fff;
    color: #111;
}
.vintage .theme-swatch {
    background: #d9b982;
    color: #673f25;
}
.theme-check {
    display: grid;
    width: 21px;
    height: 21px;
    place-items: center;
    border: 1px solid #cfdbd4;
    border-radius: 50%;
    color: transparent;
}
.selected .theme-check {
    background: #257b5b;
    border-color: #257b5b;
    color: white;
}
.preview-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 24px;
    padding: 8px 11px;
    border-radius: 10px;
    background: #eef3ef;
    color: #52675d;
    font-size: 11px;
}
.preview-toolbar > span {
    display: flex;
    align-items: center;
    gap: 7px;
}
.preview-toolbar > div {
    display: flex;
    gap: 3px;
}
.preview-toolbar button {
    display: grid;
    padding: 7px;
    place-items: center;
    border-radius: 7px;
}
.preview-toolbar button.active {
    background: white;
    color: #1f6d52;
    box-shadow: 0 2px 8px #163e2912;
}
.store-preview-wrap {
    display: flex;
    justify-content: center;
    margin-top: 10px;
    padding: 14px;
    border-radius: 15px;
    background: #e2e8e4;
    transition: 0.3s;
}
.store-preview-wrap.mobile .store-preview {
    max-width: 320px;
}
.store-preview {
    position: relative;
    width: 100%;
    min-height: 300px;
    border-radius: 12px;
    background: #f7f6f1;
    overflow: hidden;
    box-shadow: 0 12px 30px #203d2e18;
    transition: 0.3s;
}
.store-preview header {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 12px 18px;
    background: #fffc;
}
.store-preview header nav {
    display: flex;
    gap: 16px;
    margin-left: auto;
    font-size: 8px;
    font-weight: 700;
}
.preview-logo {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 8px;
    background: #183f32;
    color: white;
    overflow: hidden;
}
.preview-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: white;
}
.store-preview section {
    position: relative;
    z-index: 1;
    padding: 42px 42px 35px;
    min-height: 190px;
}
.store-preview section small {
    font-size: 7px;
    letter-spacing: 1.5px;
}
.store-preview h3 {
    max-width: 72%;
    margin: 8px 0;
    font-size: 31px;
    line-height: 1;
}
.store-preview section p {
    max-width: 65%;
    font-size: 9px;
    opacity: 0.7;
}
.store-preview section button {
    margin-top: 17px;
    padding: 8px 13px;
    border-radius: 6px;
    background: #204e3c;
    color: white;
    font-size: 7px;
    font-weight: 800;
}
.preview-products {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    padding: 9px 18px 18px;
}
.preview-products i {
    height: 45px;
    border-radius: 8px;
    background: #e5e9e4;
}
.preview-urban {
    background: #161a1d;
    color: #f7f8f4;
}
.preview-urban header {
    background: #101315dd;
    border-bottom: 1px solid #ffffff18;
}
.preview-urban .preview-logo,
.preview-urban section button {
    background: #c8ff45;
    color: #172019;
}
.preview-urban section {
    background: linear-gradient(115deg, #191d20, #283229);
}
.preview-urban h3 {
    text-transform: uppercase;
    font-style: italic;
}
.preview-urban .preview-products i {
    background: #282e31;
}
.preview-elegant {
    background: #f5efe5;
    color: #29231b;
}
.preview-elegant header {
    background: #fffaf1dd;
}
.preview-elegant .preview-logo,
.preview-elegant section button {
    background: #211d18;
    color: #e8c47a;
}
.preview-elegant section {
    background:
        radial-gradient(circle at 80% 20%, #e3c68e55, transparent 35%), #ede4d5;
}
.preview-elegant h3 {
    font-family: Georgia, serif;
    font-weight: 400;
}
.preview-elegant section button {
    border-radius: 0;
}
.preview-feminine {
    background: #fff7fa;
    color: #572c3d;
}
.preview-feminine header {
    background: #fffafbdd;
}
.preview-feminine .preview-logo,
.preview-feminine section button {
    background: #d85d89;
    color: white;
}
.preview-feminine section {
    background:
        radial-gradient(circle at 83% 24%, #ffc2d855, transparent 30%),
        linear-gradient(125deg, #fff5f7, #f8dce7);
}
.preview-feminine h3 {
    font-family: Georgia, serif;
    font-style: italic;
    font-weight: 400;
}
.preview-pastel {
    background: #f8f4ff;
    color: #514664;
}
.preview-pastel header {
    background: #fffafdde;
}
.preview-pastel .preview-logo,
.preview-pastel section button {
    background: #8972b0;
    color: white;
}
.preview-pastel section {
    background:
        radial-gradient(circle at 75% 28%, #bdebd2aa, transparent 28%),
        linear-gradient(120deg, #efe6ff, #ffe8ef);
}
.preview-pastel,
.preview-pastel section button,
.preview-pastel .preview-products i {
    border-radius: 20px;
}
.preview-pastel .preview-products i:nth-child(2) {
    background: #d8f0e3;
}
.preview-glamour {
    background: #130f12;
    color: #fff4f7;
}
.preview-glamour header {
    border-bottom: 1px solid #d4af6738;
    background: #0d0a0cdd;
}
.preview-glamour .preview-logo,
.preview-glamour section button {
    background: #ef4f91;
    color: #160b10;
}
.preview-glamour section {
    background:
        radial-gradient(circle at 80% 25%, #f6519950, transparent 28%),
        linear-gradient(115deg, #100b0f, #2d1722);
}
.preview-glamour h3 {
    color: #efc979;
    font-family: Georgia, serif;
}
.preview-glamour .preview-products i {
    background: #2a2026;
}
.preview-sport {
    background: #071a31;
    color: white;
}
.preview-sport header {
    background: #071426e8;
}
.preview-sport .preview-logo,
.preview-sport section button {
    background: #ff692b;
    color: #101b28;
}
.preview-sport section {
    background:
        linear-gradient(
            105deg,
            transparent 68%,
            #ff692b30 68% 73%,
            transparent 73%
        ),
        linear-gradient(120deg, #0b2341, #104f72);
}
.preview-sport h3 {
    font-weight: 900;
    text-transform: uppercase;
    transform: skewX(-5deg);
}
.preview-sport .preview-products i {
    background: #123451;
}
.preview-footwear {
    background: #e9e1d5;
    color: #27221d;
}
.preview-footwear header {
    background: #f9f5eedd;
}
.preview-footwear .preview-logo,
.preview-footwear section button {
    border-radius: 0;
    background: #27221d;
    color: white;
}
.preview-footwear section {
    background:
        linear-gradient(90deg, #f3eee7 62%, transparent 62%),
        radial-gradient(ellipse at 80% 58%, #a9967a 0 16%, transparent 17%),
        #d7ccbc;
}
.preview-footwear h3 {
    font-family: Georgia, serif;
    font-weight: 400;
}
.preview-footwear .preview-products i:first-child {
    grid-column: span 2;
}
.preview-minimal {
    border: 1px solid #111;
    border-radius: 0;
    background: #fff;
    color: #111;
    box-shadow: none;
}
.preview-minimal header {
    border-bottom: 1px solid #111;
    background: white;
}
.preview-minimal .preview-logo,
.preview-minimal section button {
    border-radius: 0;
    background: #111;
    color: white;
}
.preview-minimal section {
    background: white;
}
.preview-minimal h3 {
    font-weight: 500;
    letter-spacing: -1.5px;
}
.preview-minimal .preview-products i {
    border: 1px solid #ddd;
    border-radius: 0;
    background: white;
}
.preview-vintage {
    background: #dbc39d;
    color: #51331f;
}
.preview-vintage header {
    background: #f0dfbfdd;
}
.preview-vintage .preview-logo,
.preview-vintage section button {
    background: #74452a;
    color: #f4dfb8;
}
.preview-vintage section {
    background:
        repeating-linear-gradient(0deg, #6a40260b 0 1px, transparent 1px 5px),
        linear-gradient(120deg, #e8cfa5, #bf9567);
}
.preview-vintage h3 {
    font-family: Georgia, serif;
    font-weight: 400;
    text-shadow: 1px 1px #f3dfba;
}
.preview-vintage .preview-products i {
    background: #c4a579;
}
.preview-particles i {
    position: absolute;
    width: 6px;
    height: 6px;
    border: 1px solid currentColor;
    opacity: 0.15;
    animation: onboarding-float 7s ease-in-out infinite;
}
.preview-particles i:nth-child(1) {
    left: 70%;
    top: 25%;
}
.preview-particles i:nth-child(2) {
    left: 85%;
    top: 55%;
    animation-delay: -2s;
}
.preview-particles i:nth-child(3) {
    left: 58%;
    top: 70%;
    animation-delay: -4s;
}
.preview-particles i:nth-child(4) {
    left: 92%;
    top: 18%;
    animation-delay: -5s;
}
.preview-particles i:nth-child(5) {
    left: 48%;
    top: 24%;
    animation-delay: -1s;
}
.preview-particles i:nth-child(6) {
    left: 77%;
    top: 82%;
    animation-delay: -3s;
}
.input-with-icon {
    position: relative;
    display: flex !important;
    align-items: center;
}
.input-with-icon svg {
    position: absolute;
    left: 13px;
    color: #759082;
}
.input-with-icon input {
    padding-left: 40px;
}
.publish-confirmation {
    grid-template-columns: auto 1fr !important;
    align-items: start;
    padding: 17px;
    border: 1px solid #bcd8c8;
    border-radius: 14px;
    background: #f2faf5;
}
.publish-confirmation input {
    width: 17px;
    height: 17px;
    margin-top: 2px;
    accent-color: #247958;
}
.publish-confirmation > span {
    display: grid;
    gap: 4px;
}
.final-summary {
    display: grid;
    grid-template-columns: auto 1fr 1fr;
    gap: 18px;
    align-items: center;
    margin-top: 9px;
    padding: 18px;
    border-radius: 15px;
    background: #102c23;
    color: white;
}
.final-summary > div {
    display: grid;
    gap: 4px;
}
.final-summary small {
    color: #8db5a4;
    font-size: 8px;
    letter-spacing: 1.2px;
}
.summary-theme {
    display: grid;
    width: 45px;
    height: 45px;
    place-items: center;
    border-radius: 12px;
    background: #dff0e6;
    color: #205d46;
}
.summary-theme.urban {
    background: #c8ff45;
    color: #1d2a20;
}
.summary-theme.elegant {
    background: #d6b66d;
    color: #2b2315;
}
.summary-theme.feminine {
    background: #ffdce8;
    color: #ad3f68;
}
.summary-theme.pastel {
    background: linear-gradient(135deg, #e6daff, #d4f0e1);
    color: #6b5591;
}
.summary-theme.glamour {
    background: #ef4f91;
    color: #1a0d12;
}
.summary-theme.sport {
    background: #ff692b;
    color: #10233a;
}
.summary-theme.footwear,
.summary-theme.minimal {
    background: #f2ede4;
    color: #221e1a;
}
.summary-theme.vintage {
    background: #d5af72;
    color: #55331f;
}
.form-errors {
    margin-top: 16px;
    padding: 13px 15px;
    border-radius: 11px;
    background: #fff1f0;
    color: #a1362f;
    font-size: 11px;
}
.form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e3eae5;
}
.form-actions > div {
    display: flex;
    gap: 9px;
}
.form-actions button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 43px;
    padding: 0 18px;
    border-radius: 11px;
    font-size: 11px;
    font-weight: 800;
}
.button-back,
.button-draft {
    color: #52655c;
}
.button-draft {
    border: 1px solid #d7e0db;
}
.button-next,
.button-publish {
    background: #1e6f52;
    color: white;
    box-shadow: 0 10px 22px #1b61452b;
}
.form-actions button:disabled {
    opacity: 0.45;
}
.banner-upload {
    display: grid !important;
    grid-template-columns: 132px 1fr auto !important;
    align-items: center;
    gap: 14px;
    padding: 12px;
    border: 1px dashed #9fc5b1;
    border-radius: 15px;
    background: #f4faf6;
    cursor: pointer;
}
.banner-upload > input {
    display: none;
}
.banner-upload > span:nth-child(3) {
    display: grid;
    gap: 4px;
}
.banner-preview {
    display: grid;
    width: 132px;
    height: 72px;
    place-items: center;
    overflow: hidden;
    border-radius: 11px;
    background: #e3efe8;
    color: #4c896d;
}
.banner-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.customization-grid,
.delivery-prices {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 18px;
}
.color-control {
    display: grid;
    grid-template-columns: 52px 1fr;
    gap: 8px;
}
.color-control input[type='color'] {
    width: 52px;
    padding: 5px;
}
.section-sorter,
.hours-editor,
.delivery-editor {
    display: grid;
    gap: 13px;
    margin-top: 18px;
    padding: 18px;
    border: 1px solid #dfe8e2;
    border-radius: 16px;
    background: #fbfdfb;
}
.section-sorter > div {
    display: grid;
    gap: 3px;
}
.section-sorter small {
    color: #718078;
    font-size: 10px;
}
.section-sorter ol {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.section-sorter li {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 7px 8px 7px 12px;
    border: 1px solid #d8e5dc;
    border-radius: 999px;
    background: white;
    font-size: 11px;
    font-weight: 700;
}
.section-sorter button {
    display: grid;
    width: 24px;
    height: 24px;
    place-items: center;
    border-radius: 50%;
    background: #edf5ef;
}
.section-sorter button:disabled {
    opacity: 0.3;
}
.store-preview section.has-banner {
    background-size: cover;
    background-position: center;
    color: white;
}
.social-grid {
    margin-top: 18px;
}
.hours-row {
    display: grid;
    grid-template-columns: 100px 90px 1fr auto 1fr;
    align-items: center;
    gap: 9px;
}
.hours-row > input {
    min-width: 0;
    padding: 9px;
    border: 1px solid #dbe4de;
    border-radius: 9px;
    background: white;
}
.closed-toggle {
    display: flex !important;
    grid-template-columns: none !important;
    align-items: center;
    gap: 6px;
    font-size: 10px !important;
}
.closed-toggle input,
.delivery-options input {
    width: 15px !important;
    min-height: 15px !important;
}
.delivery-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 9px;
}
.delivery-options label {
    display: flex !important;
    grid-template-columns: none !important;
    flex-direction: row !important;
    align-items: center;
    gap: 8px;
    padding: 11px;
    border: 1px solid #dfe8e2;
    border-radius: 11px;
    background: white;
}
@media (max-width: 900px) {
    .onboarding-header {
        padding: 18px;
    }
    .secure-state {
        display: none;
    }
    .onboarding-shell {
        display: block;
        margin: 0 12px 30px;
        border-radius: 20px;
    }
    .onboarding-progress {
        padding: 26px;
    }
    .onboarding-progress h1,
    .onboarding-progress > p,
    .onboarding-note {
        display: none;
    }
    .progress-line {
        margin: 18px 0;
    }
    .onboarding-progress nav {
        grid-template-columns: repeat(4, 1fr);
    }
    .onboarding-progress nav button {
        grid-template-columns: auto 1fr;
        padding: 7px;
    }
    .onboarding-progress nav small {
        display: none;
    }
    .onboarding-workspace {
        padding: 30px 22px;
    }
    .form-step {
        min-height: auto;
    }
    .theme-options {
        grid-template-columns: 1fr;
    }
    .field-grid {
        grid-template-columns: 1fr;
    }
    .customization-grid,
    .delivery-prices,
    .delivery-options {
        grid-template-columns: 1fr;
    }
    .hours-row {
        grid-template-columns: 1fr 1fr;
    }
    .hours-row > span:nth-of-type(2) {
        display: none;
    }
    .banner-upload {
        grid-template-columns: 92px 1fr !important;
    }
    .banner-upload > svg {
        display: none;
    }
    .banner-preview {
        width: 92px;
    }
    .form-actions {
        align-items: flex-end;
    }
    .form-actions > div {
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .store-preview section {
        padding: 30px 25px;
    }
    .store-preview h3 {
        font-size: 25px;
    }
    .final-summary {
        grid-template-columns: auto 1fr;
    }
    .final-summary > div:last-child {
        grid-column: 1/-1;
    }
    .onboarding-header-actions .exit-link {
        font-size: 0;
    }
    .onboarding-header-actions .exit-link svg {
        width: 19px;
        height: 19px;
    }
}
</style>
