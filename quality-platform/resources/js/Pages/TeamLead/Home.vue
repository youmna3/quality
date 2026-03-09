<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import KpiCard from '@/Components/KpiCard.vue';

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
    mentorAnalytics: {
        type: Array,
        required: true,
    },
    reviewerPerformance: {
        type: Array,
        required: true,
    },
    flagMetrics: {
        type: Object,
        required: true,
    },
    topObjectionsByReviewer: {
        type: Array,
        required: true,
    },
});

const selectedWeek = ref(props.week);

const applyWeekFilter = () => {
    router.get(route('team-lead.home'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Team Lead Analytics" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Team Lead Analytics</h1>
                    <p class="text-sm text-slate-500">Mentor/tutor quality and reviewer performance for your team.</p>
                </div>
                <div class="flex items-center gap-2">
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
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-5">
            <KpiCard title="Mentors" :value="kpis.mentors" />
            <KpiCard title="Tutors" :value="kpis.tutors" />
            <KpiCard title="Avg Tutor Score" :value="kpis.avg_score" />
            <KpiCard title="Flags" :value="kpis.flags" />
            <KpiCard title="Objections" :value="kpis.objections" />
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <KpiCard title="Yellow Flags" :value="flagMetrics.yellow" />
            <KpiCard title="Red Flags" :value="flagMetrics.red" />
            <KpiCard title="Both Flags" :value="flagMetrics.both" />
            <KpiCard title="Color Changed" :value="flagMetrics.color_changed" />
            <KpiCard title="Removed (Red)" :value="flagMetrics.removed_red" />
            <KpiCard title="Removed (Yellow)" :value="flagMetrics.removed_yellow" />
            <KpiCard title="Removed (Both)" :value="flagMetrics.removed_both" />
            <KpiCard title="Partial Decisions" :value="flagMetrics.partial" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Mentors & Tutor Scores</h2>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutors</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Tutor Score</th>
                        </tr>
                    </template>

                    <tr v-for="row in mentorAnalytics" :key="row.mentor_name">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ row.mentor_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.tutors_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.avg_tutor_score ?? '-' }}</td>
                    </tr>
                </DataTable>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Reviewer Performance</h2>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flags</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Objections</th>
                        </tr>
                    </template>

                    <tr v-for="row in reviewerPerformance" :key="row.reviewer_id">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ row.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.reviewer_type || '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.assigned }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.completed }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.avg_score ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.flags_issued }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.objections }}</td>
                    </tr>
                </DataTable>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Most Objections by Reviewer</h2>
            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="item in topObjectionsByReviewer"
                    :key="item.reviewer_id"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-3"
                >
                    <p class="text-sm font-semibold text-slate-800">{{ item.name }}</p>
                    <p class="text-xs text-slate-500">Objections: {{ item.objections }}</p>
                    <p class="text-xs text-slate-500">Flags: {{ item.flags_issued }}</p>
                    <p class="text-xs text-slate-500">Y/R/B: {{ item.yellow_flags }}/{{ item.red_flags }}/{{ item.both_flags }}</p>
                </div>
                <p v-if="topObjectionsByReviewer.length === 0" class="text-sm text-slate-500">No objection data yet.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

