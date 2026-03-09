<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
        <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-[var(--qd-blue-100)] blur-2xl"></div>
        <div class="absolute -right-24 -bottom-24 h-72 w-72 rounded-full bg-sky-100 blur-2xl"></div>

        <div class="relative z-10 w-full max-w-5xl overflow-hidden rounded-3xl border border-[var(--qd-blue-100)] bg-white shadow-xl">
            <div class="grid md:grid-cols-[1.1fr_1fr]">
                <div class="hidden bg-[linear-gradient(135deg,var(--qd-blue-900),var(--qd-blue-600))] p-10 text-white md:block">
                    <div class="inline-flex items-center gap-3 rounded-2xl bg-white/10 px-4 py-3 backdrop-blur">
                        <ApplicationLogo text-class="text-white" />
                        <div>
                            <p class="text-lg font-extrabold tracking-tight text-white">Quality Dashboard</p>
                            <p class="text-xs text-blue-100">iSchool Quality Operations</p>
                        </div>
                    </div>

                    <h1 class="mt-10 text-4xl font-black leading-tight">One Platform For Admin, Reviewer, And Tutor Layers.</h1>
                    <p class="mt-4 max-w-md text-sm text-blue-100">Track tutor quality, import sheets fast, and keep all credentials organized in one place.</p>
                </div>

                <div class="bg-white p-6 sm:p-10">
                    <div class="mb-6 md:hidden">
                        <Link href="/" class="inline-flex items-center gap-3">
                            <ApplicationLogo />
                            <div>
                                <p class="text-lg font-extrabold tracking-tight text-[var(--qd-blue-900)]">Quality Dashboard</p>
                                <p class="text-xs text-slate-500">iSchool Quality Operations</p>
                            </div>
                        </Link>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
