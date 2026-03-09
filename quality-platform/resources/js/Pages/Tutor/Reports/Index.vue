<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import html2pdf from 'html2pdf.js';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    week: {
        type: Number,
        required: true,
    },
    weeks: {
        type: Array,
        required: true,
    },
    reportsPublished: {
        type: Boolean,
        required: true,
    },
    programType: {
        type: String,
        required: true,
    },
    programLogos: {
        type: Array,
        required: true,
    },
    criteriaHeaders: {
        type: Array,
        required: true,
    },
    groupHeaders: {
        type: Array,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    scoreTimeline: {
        type: Array,
        required: true,
    },
    reports: {
        type: Object,
        required: true,
    },
});

const selectedWeek = ref(props.week);
const isExportingPdf = ref(false);

const applyWeek = () => {
    router.get(route('tutor.reports.index'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};

const exportUrl = computed(() => route('tutor.reports.export', { week: selectedWeek.value }));
const hasProgramLogos = computed(() => Array.isArray(props.programLogos) && props.programLogos.length > 0);

const scoreDeltaClass = computed(() => {
    if (props.summary.score_delta === null) return 'text-slate-600';
    return Number(props.summary.score_delta) >= 0 ? 'text-emerald-700' : 'text-rose-700';
});

const scoreDeltaText = computed(() => {
    if (props.summary.score_delta === null) return 'No previous week baseline';
    const value = Number(props.summary.score_delta);
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value.toFixed(2)} vs last week`;
});

const groupAverageRows = computed(() => {
    const values = props.summary.group_averages || {};
    return Object.entries(values)
        .map(([label, score]) => ({ label, score: Number(score || 0) }))
        .sort((a, b) => b.score - a.score);
});

const maxCategoryScore = computed(() => {
    if (groupAverageRows.value.length === 0) return 100;
    const max = Math.max(...groupAverageRows.value.map((item) => Number(item.score || 0)));
    return Math.max(100, max);
});

const circleRadius = 40;
const circleCircumference = 2 * Math.PI * circleRadius;

const getCircleStrokeOffset = (value) => {
    const score = Math.max(0, Math.min(100, Number(value || 0)));
    return circleCircumference * (1 - (score / 100));
};

const getScoreTrendGradientId = (index) => `scoreTrendGradient-${index}`;

const downloadPdf = async () => {
    const reportElement = document.getElementById('weekly-report-export');
    if (!reportElement) return;

    const fileSuffix = props.programType ? `-${props.programType.toLowerCase()}` : '';
    const filename = `weekly-report-week-${selectedWeek.value}${fileSuffix}.pdf`;

    isExportingPdf.value = true;
    await nextTick();

    try {
        await html2pdf()
            .set({
                margin: [8, 8, 8, 8],
                filename,
                image: { type: 'jpeg', quality: 0.96 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    scrollX: 0,
                    scrollY: 0,
                    windowWidth: 1440,
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] },
            })
            .from(reportElement)
            .save();
    } finally {
        isExportingPdf.value = false;
    }
};

const shareOnLinkedIn = () => {
    const shareUrl = encodeURIComponent(window.location.href);
    window.open(`https://www.linkedin.com/sharing/share-offsite/?url=${shareUrl}`, '_blank', 'noopener,noreferrer');
};
</script>

<template>
    <Head title="My Weekly Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">My Weekly Report</h1>
                    <p class="text-sm text-slate-500">Shareable quality performance view without reviewer identity.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model.number="selectedWeek"
                        :disabled="weeks.length === 0"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    >
                        <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
                    </select>
                    <SecondaryButton type="button" :disabled="weeks.length === 0" @click="applyWeek">Apply Week</SecondaryButton>
                    <a
                        v-if="reportsPublished"
                        :href="exportUrl"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Download CSV
                    </a>
                    <button
                        v-if="reportsPublished"
                        type="button"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                        @click="downloadPdf"
                    >
                        Download PDF
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] px-3 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-sm"
                        @click="shareOnLinkedIn"
                    >
                        Share LinkedIn
                    </button>
                </div>
            </div>
        </template>

        <div
            v-if="!reportsPublished"
            class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-8 text-center shadow-sm"
        >
            <h2 class="text-xl font-bold text-[var(--qd-blue-900)]">No Published Reports Yet</h2>
            <p class="mt-2 text-sm text-slate-500">
                Weekly reports will appear here after the admin publishes the cycle.
            </p>
        </div>

        <div v-else id="weekly-report-export" :class="{ 'pdf-export-mode': isExportingPdf }">
            <div class="avoid-break rounded-3xl border border-[var(--qd-blue-100)] bg-[linear-gradient(135deg,#eaf2ff_0%,#f8fbff_45%,#ffffff_100%)] p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <ApplicationLogo />
                        <img
                            v-for="(logo, index) in programLogos"
                            :key="`program-logo-${index}`"
                            :src="logo"
                            :alt="`${programType} logo ${index + 1}`"
                            class="h-10 w-auto rounded-md bg-white p-1"
                        />
                    </div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-[var(--qd-blue-700)]">Quality Achievement Snapshot</p>
                    <p class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Week {{ week }} Performance Report</p>
                    <p v-if="hasProgramLogos" class="text-xs font-semibold uppercase tracking-wide text-[var(--qd-blue-700)]">{{ programType }} Program</p>
                    <p class="text-sm text-slate-600">Structured quality outcomes you can present in your professional portfolio.</p>
                </div>
                <div class="grid min-w-[250px] gap-2 rounded-2xl border border-[var(--qd-blue-100)] bg-white/90 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Weekly Trend</p>
                    <p class="text-3xl font-extrabold text-[var(--qd-blue-900)]">{{ Number(summary.avg_score || 0).toFixed(2) }}</p>
                    <p class="text-xs font-semibold" :class="scoreDeltaClass">{{ scoreDeltaText }}</p>
                </div>
            </div>
            </div>

            <div class="pdf-summary-grid mt-4 grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewed Sessions</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ summary.total_reviews }}</p>
            </div>
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average Score</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ Number(summary.avg_score || 0).toFixed(2) }}</p>
            </div>
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Best Score</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ Number(summary.best_score || 0).toFixed(2) }}</p>
            </div>
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Flags Issued</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ summary.flags_count }}</p>
                <p class="mt-1 text-xs text-slate-500">Y/R/B: {{ summary.yellow_flags }}/{{ summary.red_flags }}/{{ summary.both_flags }}</p>
            </div>
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Objections</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ summary.pending_objections }}</p>
            </div>
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Previous Week Avg</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">
                    {{ summary.previous_week_avg === null ? '-' : Number(summary.previous_week_avg).toFixed(2) }}
                </p>
            </div>
            </div>

            <div class="avoid-break mt-4 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <h2 class="text-base font-bold text-[var(--qd-blue-900)]">Category Averages</h2>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="group in groupAverageRows"
                    :key="group.label"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</p>
                    <p class="mt-1 text-lg font-extrabold text-[var(--qd-blue-900)]">{{ group.score.toFixed(2) }}%</p>
                </div>
            </div>
            </div>

            <div class="pdf-stack mt-4 grid gap-4 xl:grid-cols-2">
            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[var(--qd-blue-900)]">Score Trend (Circular)</h2>
                <p class="mt-1 text-xs text-slate-500">Each circular chart represents one reviewed session score.</p>
                <div
                    v-if="scoreTimeline.length > 0"
                    class="pdf-score-grid mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="point in scoreTimeline"
                        :key="`timeline-${point.index}`"
                        class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session {{ point.index }}</p>
                                <p class="text-sm font-bold text-[var(--qd-blue-900)]">{{ point.label }}</p>
                            </div>
                            <p class="text-xs font-semibold text-slate-600">{{ point.session_date || '-' }}</p>
                        </div>
                        <div class="mt-4 flex justify-center">
                            <div class="relative h-28 w-28">
                                <svg class="h-28 w-28 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="circleRadius"
                                        stroke="#dbeafe"
                                        stroke-width="10"
                                        fill="none"
                                    />
                                    <circle
                                        cx="50"
                                        cy="50"
                                        :r="circleRadius"
                                        :stroke="`url(#${getScoreTrendGradientId(point.index)})`"
                                        stroke-width="10"
                                        stroke-linecap="round"
                                        fill="none"
                                        :stroke-dasharray="circleCircumference"
                                        :stroke-dashoffset="getCircleStrokeOffset(point.score)"
                                    />
                                    <defs>
                                        <linearGradient :id="getScoreTrendGradientId(point.index)" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#60a5fa" />
                                            <stop offset="100%" stop-color="#1d4ed8" />
                                        </linearGradient>
                                    </defs>
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <p class="text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ Number(point.score || 0).toFixed(0) }}</p>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">out of 100</p>
                                </div>
                            </div>
                        </div>
                        <p class="mt-3 text-center text-[11px] text-slate-500">{{ point.slot || '-' }}</p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">No score timeline yet for this week.</p>
            </div>

            <div class="avoid-break rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-[var(--qd-blue-900)]">Category Score Breakdown</h2>
                <p class="mt-1 text-xs text-slate-500">Vertical bars show average percentage by main category.</p>
                <div
                    v-if="groupAverageRows.length > 0"
                    class="pdf-bar-chart mt-4 flex h-64 items-end gap-4 overflow-x-auto border-b border-[var(--qd-blue-100)] pb-2"
                >
                    <div
                        v-for="group in groupAverageRows"
                        :key="`group-chart-${group.label}`"
                        class="flex min-w-[88px] flex-col items-center gap-2"
                    >
                        <div class="text-xs font-semibold text-slate-700">{{ group.score.toFixed(1) }}%</div>
                        <div class="relative flex h-44 w-full items-end rounded-t-lg bg-[var(--qd-sky-50)]">
                            <div
                                class="w-full rounded-t-lg bg-[linear-gradient(180deg,#60a5fa,#1d4ed8)]"
                                :style="{ height: `${Math.max(6, (group.score / maxCategoryScore) * 100)}%` }"
                            />
                        </div>
                        <div class="text-center text-[11px] text-slate-500">{{ group.label }}</div>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">No category data available.</p>
            </div>
            </div>

            <div class="mt-4 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
            <h2 class="text-base font-bold text-[var(--qd-blue-900)]">Session Review Notes</h2>
            <p class="mt-1 text-xs text-slate-500">Detailed notes per session for reporting and coaching review.</p>

            <div class="mt-4 space-y-3">
                <div
                    v-for="(row, idx) in reports.data"
                    :key="`session-note-${idx}`"
                    class="pdf-session-note avoid-break rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-[var(--qd-blue-900)]">
                            {{ row.session_date || '-' }} | {{ row.slot || '-' }} | Group {{ row.group_code || '-' }}
                        </p>
                        <p class="text-sm font-bold text-slate-800">Score: {{ row.score ?? '-' }}</p>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Flags: {{ row.flag_type || 'none' }}</p>
                    <div v-if="row.flag_details && row.flag_details !== '-'" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Flag Comments</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-amber-900">{{ row.flag_details }}</p>
                    </div>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Positive Highlights</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ row.positive_concat || '-' }}</p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Improvement Points</p>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ row.negative_concat || '-' }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                        <a
                            v-if="row.zoom_link"
                            :href="row.zoom_link"
                            target="_blank"
                            class="font-semibold text-[var(--qd-blue-700)] hover:underline"
                        >
                            Session Link
                        </a>
                        <a
                            v-if="row.flag_screenshot_url"
                            :href="row.flag_screenshot_url"
                            target="_blank"
                            class="font-semibold text-[var(--qd-blue-700)] hover:underline"
                        >
                            Flag Screenshots ({{ row.flag_screenshot_urls?.length || 1 }})
                        </a>
                    </div>
                </div>
            </div>

            <div
                v-if="reports.data.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No weekly report rows for Week {{ week }}.
            </div>

            <PaginationLinks v-if="!isExportingPdf" :links="reports.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
#weekly-report-export {
    width: 100%;
}

.avoid-break,
.pdf-session-note {
    break-inside: avoid;
    page-break-inside: avoid;
}

.pdf-export-mode {
    width: 1120px;
    margin: 0 auto;
}

.pdf-export-mode .pdf-stack {
    grid-template-columns: minmax(0, 1fr);
}

.pdf-export-mode .pdf-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.pdf-export-mode .pdf-score-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.pdf-export-mode .pdf-bar-chart {
    overflow: visible;
    gap: 0.75rem;
}

.pdf-export-mode .pdf-bar-chart > div {
    min-width: 0;
    flex: 1 1 0;
}

.pdf-export-mode .pdf-session-note {
    margin-bottom: 0.75rem;
}
</style>
