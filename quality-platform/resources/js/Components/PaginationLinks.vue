<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div class="mt-4 flex flex-wrap gap-2">
        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in links"
            :key="index"
            :href="link.url || undefined"
            class="rounded-md border px-3 py-1 text-sm"
            :class="[
                link.active ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700',
                !link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-100',
            ]"
            v-html="link.label"
        />
    </div>
</template>
