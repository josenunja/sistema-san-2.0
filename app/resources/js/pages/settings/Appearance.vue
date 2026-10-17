<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Preferencias de apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Preferencias de apariencia" />

    <h1 class="sr-only">Preferencias de apariencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Preferencias de apariencia"
            description="Elige la apariencia de tu cuenta"
        />
        <AppearanceTabs />
    </div>
</template>
