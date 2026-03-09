<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    week: {
        type: Number,
        required: true,
    },
    isAllWeeks: {
        type: Boolean,
        required: true,
    },
    weeks: {
        type: Array,
        required: true,
    },
    weekCycle: {
        type: Object,
        required: true,
    },
    kpis: {
        type: Object,
        required: true,
    },
    reviewerAnalytics: {
        type: Array,
        required: true,
    },
    topFastReviewers: {
        type: Array,
        required: true,
    },
    flagMetrics: {
        type: Object,
        required: true,
    },
    avgScoreByMentor: {
        type: Array,
        required: true,
    },
    avgScoreByTeamLead: {
        type: Array,
        required: true,
    },
    reviewerFlagAnalytics: {
        type: Array,
        required: true,
    },
    topNegativeComments: {
        type: Array,
        required: true,
    },
    weeklyScoreTrend: {
        type: Array,
        required: true,
    },
    weeklyFlagTrend: {
        type: Array,
        required: true,
    },
    reviewerEditAnalytics: {
        type: Array,
        required: true,
    },
    recentEditLogs: {
        type: Array,
        required: true,
    },
    reviewerEditScopeHasData: {
        type: Boolean,
        required: true,
    },
});

const selectedWeek = ref(props.week);
const activeTab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'trends', label: 'Trend Charts' },
    { key: 'performance', label: 'Reviewer Performance' },
    { key: 'quality', label: 'Quality Signals' },
];
const weekLabel = computed(() => (props.isAllWeeks ? 'All Weeks' : `Week ${props.week}`));

const applyWeekFilter = () => {
    router.get(route('admin.analytics.index'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};

const summary = computed(() => [
    { title: 'Reviewer Accounts', value: props.kpis.total_reviewers },
    { title: 'Active Reviewers', value: props.kpis.active_reviewers },
    { title: `Assigned ${weekLabel.value}`, value: props.kpis.assigned_week },
    { title: `Completed ${weekLabel.value}`, value: props.kpis.completed_week },
    { title: `Pending ${weekLabel.value}`, value: props.kpis.pending_week },
    { title: 'On-Time Completion', value: `${props.kpis.on_time_rate}%` },
    { title: 'Reviewer Edit Events', value: props.kpis.reviewer_edit_events },
    { title: 'Reviewers With Edits', value: props.kpis.reviewers_with_edits },
]);

const formatHours = (value) => (value === null ? '-' : `${Number(value).toFixed(2)} h`);
const formatPercent = (value) => `${Number(value ?? 0).toFixed(1)}%`;
const formatScore = (value) => Number(value ?? 0).toFixed(1);
const formatSessionLine = (session) => {
    const parts = [
        session?.week_number ? `Week ${session.week_number}` : null,
        session?.session_date || null,
        session?.slot || null,
        session?.group_code ? `Group ${session.group_code}` : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' | ') : '-';
};

const maxFlagTrendValue = computed(() => {
    const values = props.weeklyFlagTrend.flatMap((row) => [row.yellow, row.red, row.both]);
    return Math.max(1, ...values.map((value) => Number(value || 0)));
});

const scorePeakWeek = computed(() => {
    const rows = props.weeklyScoreTrend.filter((row) => Number(row.reviews_count || 0) > 0);
    if (rows.length === 0) return null;

    return rows.reduce((best, row) => (Number(row.avg_score || 0) > Number(best.avg_score || 0) ? row : best));
});

const scoreLowWeek = computed(() => {
    const rows = props.weeklyScoreTrend.filter((row) => Number(row.reviews_count || 0) > 0);
    if (rows.length === 0) return null;

    return rows.reduce((worst, row) => (Number(row.avg_score || 0) < Number(worst.avg_score || 0) ? row : worst));
});

const highestFlagWeek = computed(() => {
    const rows = props.weeklyFlagTrend.filter((row) => Number(row.total_flags || 0) > 0);
    if (rows.length === 0) return null;

    return rows.reduce((peak, row) => (Number(row.total_flags || 0) > Number(peak.total_flags || 0) ? row : peak));
});

const hasTrendData = computed(() => props.weeklyScoreTrend.some((row) => Number(row.reviews_count || 0) > 0));
const isHighlightedWeek = (weekNumber) => !props.isAllWeeks && Number(weekNumber) === Number(props.week);
const getScoreBarHeight = (value) => `${Math.max(8, Math.min(100, Number(value || 0)))}%`;
const getFlagBarHeight = (value) => {
    const numericValue = Number(value || 0);
    if (numericValue <= 0) return '0%';

    const ratio = (numericValue / maxFlagTrendValue.value) * 100;
    return `${Math.max(10, ratio)}%`;
};
</script>

<template>
    <Head title="Reviewer Analytics" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Reviewer Analytics</h1>
                    <p class="text-sm text-slate-500">
                        Lateness and completion tracking (late threshold: {{ kpis.late_threshold_hours }} hours).
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Scope: {{ weekLabel }} |
                        Cycle Start: {{ isAllWeeks ? '-' : (weekCycle.starts_at || '-') }} |
                        Deadline: {{ isAllWeeks ? '-' : (weekCycle.deadline_at || '-') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model.number="selectedWeek"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    >
                        <option :value="0">All Weeks</option>
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
                        v-if="!isAllWeeks"
                        :href="route('admin.assignments.index', { week: selectedWeek })"
                        class="inline-flex items-center rounded-xl bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm"
                    >
                        Weekly Assignments
                    </Link>
                </div>
            </div>
        </template>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="activeTab === tab.key
                    ? 'bg-[linear-gradient(135deg,var(--qd-blue-700),var(--qd-blue-500))] text-white shadow-sm'
                    : 'border border-[var(--qd-blue-100)] bg-white text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <template v-if="activeTab === 'overview'">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <KpiCard v-for="item in summary" :key="item.title" :title="item.title" :value="item.value" />
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Late Submitted</p>
                <p class="mt-2 text-3xl font-extrabold text-rose-700">{{ kpis.late_submitted_week }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Late Pending</p>
                <p class="mt-2 text-3xl font-extrabold text-amber-700">{{ kpis.late_pending_week }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Top Fast Reviewers</p>
                <p class="mt-2 text-sm text-emerald-700">
                    {{ topFastReviewers.length }} reviewer(s) have at least one completed assignment in this scope.
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Best Score Cycle</p>
                <p class="mt-2 text-3xl font-extrabold text-emerald-700">
                    {{ scorePeakWeek ? `Week ${scorePeakWeek.week}` : '-' }}
                </p>
                <p class="mt-2 text-sm text-emerald-800">
                    Avg score: {{ scorePeakWeek ? formatScore(scorePeakWeek.avg_score) : '-' }} |
                    Reviews: {{ scorePeakWeek ? scorePeakWeek.reviews_count : 0 }}
                </p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Lowest Score Cycle</p>
                <p class="mt-2 text-3xl font-extrabold text-amber-700">
                    {{ scoreLowWeek ? `Week ${scoreLowWeek.week}` : '-' }}
                </p>
                <p class="mt-2 text-sm text-amber-800">
                    Avg score: {{ scoreLowWeek ? formatScore(scoreLowWeek.avg_score) : '-' }} |
                    Reviews: {{ scoreLowWeek ? scoreLowWeek.reviews_count : 0 }}
                </p>
            </div>
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Highest Flag Cycle</p>
                <p class="mt-2 text-3xl font-extrabold text-rose-700">
                    {{ highestFlagWeek ? `Week ${highestFlagWeek.week}` : '-' }}
                </p>
                <p class="mt-2 text-sm text-rose-800">
                    Total flags: {{ highestFlagWeek ? highestFlagWeek.total_flags : 0 }} |
                    Yellow/Red: {{ highestFlagWeek ? `${highestFlagWeek.yellow}/${highestFlagWeek.red}` : '0/0' }}
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <KpiCard title="Flags Issued" :value="flagMetrics.total_flags" />
            <KpiCard title="Flag Objections" :value="flagMetrics.objections" />
            <KpiCard title="Yellow Flags" :value="flagMetrics.yellow" />
            <KpiCard title="Red Flags" :value="flagMetrics.red" />
            <KpiCard title="Both Flags" :value="flagMetrics.both" />
            <KpiCard title="Removed (Red)" :value="flagMetrics.removed_red" />
            <KpiCard title="Removed (Yellow)" :value="flagMetrics.removed_yellow" />
            <KpiCard title="Removed (Both)" :value="flagMetrics.removed_both" />
            <KpiCard title="Partial Decisions" :value="flagMetrics.partial" />
            <KpiCard title="Color Changed" :value="flagMetrics.color_changed" />
        </div>
        </template>

        <template v-else-if="activeTab === 'trends'">
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Average Score Across All Weeks</h2>
                        <p class="mt-1 text-xs text-slate-500">Bar chart of weekly average tutor quality score.</p>
                    </div>
                    <div class="rounded-xl bg-[var(--qd-sky-50)] px-3 py-2 text-xs font-semibold text-[var(--qd-blue-700)]">
                        Score scale: 0 to 100
                    </div>
                </div>

                <div v-if="hasTrendData" class="mt-5 flex h-80 items-end gap-4 overflow-x-auto border-b border-[var(--qd-blue-100)] pb-2">
                    <div
                        v-for="row in weeklyScoreTrend"
                        :key="`score-week-${row.week}`"
                        class="flex min-w-[84px] flex-col items-center gap-2"
                    >
                        <div class="text-xs font-semibold text-slate-700">{{ formatScore(row.avg_score) }}</div>
                        <div
                            class="relative flex h-52 w-full items-end rounded-t-xl bg-[var(--qd-sky-50)]"
                            :class="isHighlightedWeek(row.week) ? 'ring-2 ring-[var(--qd-blue-300)]' : ''"
                        >
                            <div
                                class="w-full rounded-t-xl bg-[linear-gradient(180deg,#60a5fa,#1d4ed8)]"
                                :style="{ height: getScoreBarHeight(row.avg_score) }"
                            />
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-slate-800">W{{ row.week }}</p>
                            <p class="text-[11px] text-slate-500">{{ row.reviews_count }} reviews</p>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">No score trend data yet.</p>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Red and Yellow Flags by Cycle</h2>
                        <p class="mt-1 text-xs text-slate-500">Cycle-by-cycle view of yellow, red, and both-color flags.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700">Yellow</span>
                        <span class="rounded-full bg-rose-100 px-2 py-1 text-rose-700">Red</span>
                        <span class="rounded-full bg-orange-100 px-2 py-1 text-orange-700">Both</span>
                    </div>
                </div>

                <div class="mt-5 flex h-80 items-end gap-4 overflow-x-auto border-b border-[var(--qd-blue-100)] pb-2">
                    <div
                        v-for="row in weeklyFlagTrend"
                        :key="`flag-week-${row.week}`"
                        class="flex min-w-[92px] flex-col items-center gap-2"
                    >
                        <div class="flex h-52 items-end gap-2 rounded-t-xl bg-[var(--qd-sky-50)] px-3 py-3" :class="isHighlightedWeek(row.week) ? 'ring-2 ring-[var(--qd-blue-300)]' : ''">
                            <div class="w-5 rounded-t-lg bg-amber-400" :style="{ height: getFlagBarHeight(row.yellow) }" />
                            <div class="w-5 rounded-t-lg bg-rose-500" :style="{ height: getFlagBarHeight(row.red) }" />
                            <div class="w-5 rounded-t-lg bg-orange-500" :style="{ height: getFlagBarHeight(row.both) }" />
                        </div>
                        <div class="text-center">
                            <p class="text-xs font-semibold text-slate-800">W{{ row.week }}</p>
                            <p class="text-[11px] text-slate-500">Y {{ row.yellow }} | R {{ row.red }}</p>
                            <p class="text-[11px] text-slate-500">B {{ row.both }} | T {{ row.total_flags }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Cycle Trend Table</h2>
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Week</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Score</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviews</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Yellow</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Red</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Both</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Total Flags</th>
                    </tr>
                </template>
                <tr v-for="row in weeklyScoreTrend" :key="`trend-row-${row.week}`">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">Week {{ row.week }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ formatScore(row.avg_score) }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.reviews_count }}</td>
                    <td class="px-4 py-3 text-sm text-amber-700">{{ weeklyFlagTrend.find((item) => item.week === row.week)?.yellow || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-rose-700">{{ weeklyFlagTrend.find((item) => item.week === row.week)?.red || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-orange-700">{{ weeklyFlagTrend.find((item) => item.week === row.week)?.both || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ weeklyFlagTrend.find((item) => item.week === row.week)?.total_flags || 0 }}</td>
                </tr>
            </DataTable>
        </div>
        </template>

        <template v-else-if="activeTab === 'performance'">
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned (Week)</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Late</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Completion</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Completion %</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned (All Time)</th>
                        </tr>
                    </template>

                    <tr v-for="item in reviewerAnalytics" :key="item.id">
                        <td class="px-4 py-3 text-sm">
                            <p class="font-semibold text-slate-800">{{ item.name }}</p>
                            <p class="text-xs text-slate-500">{{ item.email }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.reviewer_type || '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.assigned_week }}</td>
                        <td class="px-4 py-3 text-sm text-emerald-700">{{ item.completed_week }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.pending_week }}</td>
                        <td class="px-4 py-3 text-sm text-rose-700">{{ item.late_submitted_week + item.late_pending_week }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ formatHours(item.avg_completion_hours) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ formatPercent(item.completion_rate) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.assigned_all_time }}</td>
                    </tr>
                </DataTable>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Top Fast Reviewers</h2>
                <p class="mt-1 text-xs text-slate-500">Sorted by lowest average completion time in {{ weekLabel }}.</p>

                <ul v-if="topFastReviewers.length > 0" class="mt-4 space-y-3">
                    <li
                        v-for="(item, index) in topFastReviewers"
                        :key="item.id"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2"
                    >
                        <p class="text-sm font-semibold text-slate-800">
                            #{{ index + 1 }} {{ item.name }}
                        </p>
                        <p class="text-xs text-slate-600">
                            Avg: {{ formatHours(item.avg_completion_hours) }} | Completed: {{ item.completed_week }}/{{ item.assigned_week }}
                        </p>
                    </li>
                </ul>

                <p v-else class="mt-3 text-sm text-slate-500">No completed assignments for this week yet.</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div class="xl:col-span-2">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Reviewer Edit Activity</h2>
                        <p class="mt-1 text-xs text-slate-500">All-weeks activity to avoid hiding reviewer edits when the selected week has no edit events.</p>
                    </div>
                    <div class="rounded-xl bg-[var(--qd-sky-50)] px-3 py-2 text-xs font-semibold text-[var(--qd-blue-700)]">
                        Scope edits: {{ kpis.reviewer_edit_events_scope }} | All edits: {{ kpis.reviewer_edit_events }}
                    </div>
                </div>
                <div
                    v-if="!reviewerEditScopeHasData && !isAllWeeks"
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    No reviewer edits were recorded in Week {{ week }}. Showing all-weeks reviewer edit activity instead.
                </div>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Edit Count</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviews Edited</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session Details</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Most Edited Fields</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Edit</th>
                        </tr>
                    </template>
                    <tr v-for="row in reviewerEditAnalytics" :key="row.reviewer_id || row.reviewer_name">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ row.reviewer_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.edit_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.reviews_edited_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            <div v-if="row.sessions.length > 0" class="space-y-2">
                                <div
                                    v-for="(session, index) in row.sessions.slice(0, 4)"
                                    :key="`${row.reviewer_name}-session-${index}`"
                                    class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2"
                                >
                                    <p class="text-xs font-semibold text-slate-800">
                                        {{ session.tutor_id || '-' }} - {{ session.tutor_name || 'Unknown Tutor' }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-600">
                                        {{ formatSessionLine(session) }}
                                    </p>
                                    <p class="mt-1 text-[11px] font-semibold text-[var(--qd-blue-700)]">
                                        {{ session.edit_count }} edit(s) on this session
                                    </p>
                                </div>
                            </div>
                            <span v-else>-</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="field in row.fields_changed.slice(0, 5)"
                                    :key="`${row.reviewer_name}-${field.field_key}`"
                                    class="rounded-full bg-[var(--qd-sky-50)] px-2 py-1 text-xs font-semibold text-[var(--qd-blue-700)]"
                                >
                                    {{ field.field_label }} ({{ field.count }})
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.last_edit_at || '-' }}</td>
                    </tr>
                </DataTable>
                <p v-if="reviewerEditAnalytics.length === 0" class="mt-3 text-sm text-slate-500">
                    No reviewer edit activity in this scope yet.
                </p>
            </div>
            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Average Score by Mentor Team</h2>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviews</th>
                        </tr>
                    </template>
                    <tr v-for="item in avgScoreByMentor" :key="item.mentor_name">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.mentor_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.avg_score }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.reviews_count }}</td>
                    </tr>
                </DataTable>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Average Score by Team Lead</h2>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Team Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviews</th>
                        </tr>
                    </template>
                    <tr v-for="item in avgScoreByTeamLead" :key="item.team_lead_name">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.team_lead_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.avg_score }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.reviews_count }}</td>
                    </tr>
                </DataTable>
            </div>
        </div>

        </template>

        <template v-else>
        <div class="mt-6">
            <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Reviewer Flag & Objection Analytics</h2>
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flags</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Objections</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Y/R/B</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Removed</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Partial</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Color Changed</th>
                    </tr>
                </template>
                <tr v-for="row in reviewerFlagAnalytics" :key="row.reviewer_name">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ row.reviewer_name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.flags_count }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.objections_count }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.yellow }}/{{ row.red }}/{{ row.both }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.removed }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.partial }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.color_changed }}</td>
                </tr>
            </DataTable>
        </div>

        <div class="mt-6">
            <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Most Repeated Negative Comments</h2>
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Comment</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Repeats</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutors</th>
                    </tr>
                </template>
                <tr v-for="(row, index) in topNegativeComments" :key="`${row.comment_text}-${index}`">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-700">{{ index + 1 }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.criterion_label }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.comment_text }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-rose-700">{{ row.occurrences }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.tutors_count }}</td>
                </tr>
            </DataTable>
            <p v-if="topNegativeComments.length === 0" class="mt-3 text-sm text-slate-500">
                No repeated negative comments in this scope yet.
            </p>
        </div>

        <div class="mt-6">
            <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Recent Report Edit Trail</h2>
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Week</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Edited Fields</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Edited At</th>
                    </tr>
                </template>
                <tr v-for="row in recentEditLogs" :key="row.id">
                    <td class="px-4 py-3 text-sm text-slate-700">Week {{ row.week_number || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-800">{{ row.actor_name }}</p>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ row.actor_role }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-800">{{ row.tutor_id || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ row.tutor_name || '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold text-slate-800">{{ row.session_date || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ row.slot || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ row.group_code ? `Group ${row.group_code}` : 'Group -' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="field in row.changed_fields"
                                :key="`${row.id}-${field}`"
                                class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"
                            >
                                {{ field }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ row.edited_at || '-' }}</td>
                </tr>
            </DataTable>
            <p v-if="recentEditLogs.length === 0" class="mt-3 text-sm text-slate-500">
                No report edits recorded in this scope yet.
            </p>
        </div>
        </template>
    </AuthenticatedLayout>
</template>
