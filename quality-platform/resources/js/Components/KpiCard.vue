<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number, null],
        default: '-',
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ title }}</p>
        <p class="mt-2 text-3xl font-extrabold text-[var(--qd-blue-900)]">{{ value ?? '-' }}</p>
        <p v-if="subtitle" class="mt-2 text-xs text-slate-500">{{ subtitle }}</p>
    </div>
</template>
