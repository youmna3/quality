<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm font-medium text-slate-700 transition hover:bg-[var(--qd-sky-50)] hover:text-[var(--qd-blue-700)] focus:bg-[var(--qd-sky-50)] focus:text-[var(--qd-blue-700)] focus:outline-none"
    >
        <slot />
    </Link>
</template>
