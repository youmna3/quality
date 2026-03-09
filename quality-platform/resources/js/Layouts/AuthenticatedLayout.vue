<script setup>
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const currentPath = computed(() => String(page.url || '/').split('?')[0]);

const isActiveLink = (href) => {
    if (href === '/') {
        return currentPath.value === '/';
    }

    if (['/admin', '/reviewer', '/tutor', '/team-lead'].includes(href)) {
        return currentPath.value === href;
    }

    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
};

const navigationLinks = computed(() => {
    if (user.value?.role === 'admin') {
        return [
            { label: 'Overview', href: '/admin' },
            { label: 'Tutors Data', href: '/admin/tutors' },
            { label: 'Reviewer Accounts', href: '/admin/reviewers' },
            { label: 'Mentor Coordinators', href: '/admin/mentor-coordinators' },
            { label: 'Team Leads', href: '/admin/team-leads' },
            { label: 'Weekly Assignments', href: '/admin/assignments' },
            { label: 'Analytics', href: '/admin/analytics' },
            { label: 'Reports', href: '/admin/reports' },
            { label: 'Flags', href: '/admin/flags' },
        ];
    }

    if (user.value?.role === 'reviewer') {
        return [{ label: 'Overview', href: '/reviewer' }];
    }

    if (user.value?.role === 'team_lead') {
        return [{ label: 'Overview', href: '/team-lead' }];
    }

    return [
        { label: 'Overview', href: '/tutor' },
        { label: 'Reports', href: '/tutor/reports' },
        { label: 'Flags', href: '/tutor/flags' },
    ];
});
</script>

<template>
    <div class="min-h-screen bg-transparent">
        <div class="sticky top-0 z-30 border-b border-[var(--qd-blue-100)] bg-white/90 backdrop-blur">
            <nav class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="inline-flex items-center gap-3">
                    <ApplicationLogo />
                    <div>
                        <p class="text-lg font-extrabold tracking-tight text-[var(--qd-blue-900)]">Quality Dashboard</p>
                        <p class="text-xs font-medium text-slate-500">iSchool Quality Operations</p>
                    </div>
                </Link>

                <div class="flex items-center gap-2">
                    <Link
                        v-for="item in navigationLinks"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'rounded-lg px-3 py-2 text-sm font-semibold transition',
                            isActiveLink(item.href)
                                ? 'bg-[var(--qd-blue-100)] text-[var(--qd-blue-700)]'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-[var(--qd-blue-700)]',
                        ]"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-lg bg-[var(--qd-blue-100)] px-3 py-2 text-xs font-bold text-[var(--qd-blue-700)]">
                        {{ user?.name ?? 'User' }}
                    </span>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-rose-600"
                    >
                        Log Out
                    </Link>
                </div>
            </nav>
        </div>

        <header v-if="$slots.header" class="mx-auto mt-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white px-6 py-5 shadow-sm">
                <slot name="header" />
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="flashSuccess" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ flashError }}
            </div>

            <slot />
        </main>
    </div>
</template>
