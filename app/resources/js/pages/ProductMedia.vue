<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import BusinessShell from '@/components/BusinessShell.vue';
const props = defineProps<{
    tenant: { name: string; slug: string };
    role: string;
    product: { id: number; name: string };
    images: { id: number; alt_text: string; cover: boolean; url: string }[];
}>();
const form = useForm({ image: null as File | null });
const base = `/gestion/${props.tenant.slug}/productos/${props.product.id}`;
function save(id: number, event: Event) {
    const fields = new FormData(event.target as HTMLFormElement);
    router.patch(
        `${base}/fotos/${id}`,
        {
            alt_text: String(fields.get('alt_text')),
            sort_order: Number(fields.get('sort_order')),
            cover: fields.get('cover') === 'on',
        },
        { preserveScroll: true },
    );
}
function remove(id: number) {
    if (
        window.confirm(
            '¿Eliminar esta foto? Las ventas y el producto se conservarán.',
        )
    )
        router.delete(`${base}/fotos/${id}`, { preserveScroll: true });
}
</script>
<template>
    <Head :title="`Fotos · ${product.name}`" />
    <BusinessShell :tenant="tenant" :role="role" section="catalogo">
        <Link :href="`/gestion/${tenant.slug}`" class="text-emerald-800"
            >← Catálogo</Link
        >
        <h1 class="my-6 text-3xl font-semibold">Fotos de {{ product.name }}</h1>
        <form
            class="mb-8 grid gap-4 rounded-2xl border bg-white p-6"
            @submit.prevent="
                form.post(`${base}/foto`, {
                    forceFormData: true,
                    onSuccess: () => form.reset(),
                })
            "
        >
            <label
                >Agregar foto (hasta ocho)<input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    required
                    class="mt-2 block w-full"
                    @change="
                        form.image =
                            ($event.target as HTMLInputElement).files?.[0] ||
                            null
                    "
            /></label>
            <p v-for="error in form.errors" :key="error" class="text-red-700">
                {{ error }}
            </p>
            <button
                :disabled="form.processing"
                class="rounded-xl bg-emerald-800 p-3 text-white"
            >
                Subir foto
            </button>
        </form>
        <p v-if="!images.length">
            Aún no hay fotos. Agrega la primera para mostrarla como portada.
        </p>
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <form
                v-for="(image, index) in images"
                :key="image.id"
                class="grid gap-4 rounded-2xl border bg-white p-5"
                @submit.prevent="save(image.id, $event)"
            >
                <img
                    :src="image.url"
                    :alt="image.alt_text || product.name"
                    class="h-52 w-full object-contain"
                />
                <label
                    >Descripción accesible<input
                        name="alt_text"
                        :value="image.alt_text || product.name"
                        required
                        maxlength="160"
                        class="mt-2 w-full rounded-lg border p-2"
                /></label>
                <label
                    >Orden<input
                        name="sort_order"
                        type="number"
                        min="0"
                        max="1000"
                        :value="index"
                        required
                        class="mt-2 w-full rounded-lg border p-2"
                /></label>
                <label
                    ><input
                        name="cover"
                        type="checkbox"
                        :checked="image.cover"
                    />
                    Usar como portada</label
                >
                <div class="flex justify-between">
                    <button
                        class="rounded-lg bg-emerald-800 px-4 py-2 text-white"
                    >
                        Guardar</button
                    ><button
                        type="button"
                        class="text-red-700"
                        @click="remove(image.id)"
                    >
                        Eliminar
                    </button>
                </div>
            </form>
        </div>
    </BusinessShell>
</template>
