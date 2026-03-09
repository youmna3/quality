<template>
    <div class="overflow-hidden rounded-2xl border border-[var(--qd-blue-100)] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--qd-blue-100)]">
                <thead class="bg-[var(--qd-sky-50)]">
                    <slot name="head" />
                </thead>
                <tbody class="divide-y divide-[var(--qd-blue-100)] bg-white">
                    <slot />
                </tbody>
            </table>
        </div>
    </div>
</template>
