<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PageLink } from '@/types/business';

defineProps<{ label: string; links: PageLink[] }>();
</script>

<template>
    <nav
        v-if="links.length > 3"
        :aria-label="`Paginación de ${label}`"
        class="my-5 flex flex-wrap items-center gap-2"
    >
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                :aria-current="link.active ? 'page' : undefined"
                class="rounded-lg border px-3 py-2 text-sm"
                :class="
                    link.active
                        ? 'bg-emerald-800 text-white'
                        : 'bg-white text-emerald-900'
                "
            >
                {{
                    index === 0
                        ? 'Anterior'
                        : index === links.length - 1
                          ? 'Siguiente'
                          : link.label
                }}
            </Link>
        </template>
    </nav>
</template>
