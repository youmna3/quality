<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center rounded-lg bg-[var(--qd-blue-100)] px-3 py-2 text-sm font-semibold text-[var(--qd-blue-700)] transition'
        : 'inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-[var(--qd-blue-700)]'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
