<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    week: {
        type: Number,
        required: true,
    },
    weeks: {
        type: Array,
        required: true,
    },
    kpis: {
        type: Object,
        required: true,
    },
    recentTutors: {
        type: Array,
        required: true,
    },
});

const selectedWeek = ref(props.week);

const applyWeekFilter = () => {
    router.get(route('admin.home'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Quality Dashboard - Admin" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Quality Dashboard</h1>
                    <p class="text-sm text-slate-500">Admin Layer</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model.number="selectedWeek"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    >
                        <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
                    </select>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                        @click="applyWeekFilter"
                    >
                        Apply Week
                    </button>
                    <Link
                        href="/admin/tutors"
                        class="inline-flex items-center rounded-xl bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm"
                    >
                        Tutors Data
                    </Link>
                    <Link
                        href="/admin/reviewers"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Reviewer Accounts
                    </Link>
                    <Link
                        href="/admin/mentor-coordinators"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Mentor Coordinators
                    </Link>
                    <Link
                        href="/admin/team-leads"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Team Leads
                    </Link>
                    <Link
                        href="/admin/assignments"
                        :data="{ week: selectedWeek }"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Weekly Assignments
                    </Link>
                    <Link
                        href="/admin/analytics"
                        :data="{ week: selectedWeek }"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Analytics
                    </Link>
                    <Link
                        href="/admin/reports"
                        :data="{ week: selectedWeek }"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Reports
                    </Link>
                    <Link
                        href="/admin/flags"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Flags
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <KpiCard title="Total Tutors" :value="kpis.total_tutors" />
            <KpiCard title="Active Tutors" :value="kpis.active_tutors" />
            <KpiCard title="Reviewer Accounts" :value="kpis.reviewers" />
            <KpiCard title="Admin Accounts" :value="kpis.admins" />
            <KpiCard :title="`Assigned Week ${week}`" :value="kpis.assigned_this_week" />
            <KpiCard :title="`Pending Week ${week}`" :value="kpis.pending_this_week" />
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Recent Tutors</h2>
            <p v-if="recentTutors.length === 0" class="mt-2 text-sm text-slate-500">No tutors added yet. Use the Tutors Data tab to create or import.</p>
            <ul v-else class="mt-3 grid gap-2 md:grid-cols-2">
                <li
                    v-for="tutor in recentTutors"
                    :key="tutor.id"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2 text-sm"
                >
                    <p class="font-semibold text-slate-800">{{ tutor.tutor_code }} - {{ tutor.name_en }}</p>
                    <p class="text-xs text-slate-500">Mentor: {{ tutor.mentor_name || '-' }} | Grade: {{ tutor.grade || '-' }}</p>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
