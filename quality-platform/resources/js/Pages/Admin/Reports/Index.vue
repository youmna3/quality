<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
    filters: {
        type: Object,
        required: true,
    },
    searchAcrossWeeks: {
        type: Boolean,
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
    reports: {
        type: Object,
        required: true,
    },
    publishState: {
        type: Object,
        required: true,
    },
    pendingEditRequests: {
        type: Array,
        required: true,
    },
});

const selectedWeek = ref(props.week);
const search = ref(props.filters.search ?? '');
const showFullColumns = ref(false);
let searchDebounceTimer = null;

const applyWeek = () => {
    router.get(
        route('admin.reports.index'),
        { week: selectedWeek.value, search: search.value || null },
        { preserveState: true, replace: true, preserveScroll: true }
    );
};

const publishWeekReports = () => {
    router.post(
        route('admin.reports.publish'),
        { week: selectedWeek.value },
        { preserveScroll: true }
    );
};

watch(search, (value, previousValue) => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }

    const normalizedValue = String(value || '').trim();
    const normalizedPrevious = String(previousValue || '').trim();
    if (normalizedValue === normalizedPrevious) {
        return;
    }

    searchDebounceTimer = window.setTimeout(() => {
        router.get(
            route('admin.reports.index'),
            { week: selectedWeek.value, search: normalizedValue || null },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }, 300);
});

onBeforeUnmount(() => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
});
</script>

<template>
    <Head title="Weekly Reports" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Weekly Reports</h1>
                    <p class="text-sm text-slate-500">Submitted review data with full scoring columns.</p>
                    <p v-if="searchAcrossWeeks" class="text-xs font-semibold text-[var(--qd-blue-700)]">
                        Search mode: showing tutor reports across all weeks.
                    </p>
                    <p v-else class="text-xs text-slate-500">
                        Tutor visibility:
                        <span class="font-semibold" :class="publishState.is_published ? 'text-emerald-700' : 'text-amber-700'">
                            {{ publishState.is_published ? `Published at ${publishState.published_at}` : 'Not published yet' }}
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Type tutor ID to search all weeks..."
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    />
                    <label class="inline-flex items-center gap-2 rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                        <input v-model="showFullColumns" type="checkbox" class="rounded border-gray-300 text-[var(--qd-blue-700)]" />
                        Full Columns
                    </label>
                    <a
                        :href="route('admin.reports.export', { week: selectedWeek })"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Export CSV
                    </a>
                    <select
                        v-model.number="selectedWeek"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    >
                        <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
                    </select>
                    <PrimaryButton type="button" @click="publishWeekReports">Publish to Tutors</PrimaryButton>
                    <SecondaryButton type="button" @click="applyWeek">Apply Week</SecondaryButton>
                </div>
            </div>
        </template>

        <div
            v-if="pendingEditRequests.length > 0"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-amber-900">Pending Reviewer Edit Requests</h2>
                    <p class="mt-1 text-sm text-amber-800">These reviews are locked for reviewers and waiting for admin action.</p>
                </div>
                <div class="rounded-full bg-white px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-700">
                    {{ pendingEditRequests.length }} Pending
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="request in pendingEditRequests"
                    :key="request.id"
                    class="rounded-2xl border border-amber-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Week {{ request.week_number || '-' }}</p>
                    <p class="mt-2 text-sm font-bold text-slate-900">{{ request.tutor_id }} - {{ request.tutor_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Review by {{ request.reviewer_name }} | Requester: {{ request.requester_name }}</p>
                    <p class="mt-3 text-sm text-slate-700">{{ request.message }}</p>
                    <div class="mt-4 flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500">{{ request.created_at }}</span>
                        <a
                            v-if="request.edit_url"
                            :href="request.edit_url"
                            class="inline-flex items-center rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]"
                        >
                            Open Review
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Week</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">TimeStamp</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Slot</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Group ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type of flag</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flag Color - Subcategory: Reason [Duration]</th>
                    <th v-if="showFullColumns" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Positive Concat</th>
                    <th v-if="showFullColumns" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Negative Concat</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Zoom link</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flag Screenshot</th>

                    <th
                        v-for="criterion in (showFullColumns ? criteriaHeaders : [])"
                        :key="`criterion-${criterion}`"
                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        {{ criterion }}
                    </th>

                    <th
                        v-for="group in groupHeaders"
                        :key="`group-${group}`"
                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                        {{ group }} %
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Score</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                </tr>
            </template>

            <tr v-for="(row, idx) in reports.data" :key="idx">
                <td class="px-4 py-3 text-xs font-semibold text-[var(--qd-blue-700)]">Week {{ row.week_number || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.timestamp || '-' }}</td>
                <td class="px-4 py-3 text-xs font-semibold text-slate-800">{{ row.tutor_id || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.tutor_name || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.mentor_name || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.reviewer_name || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.session_date || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.slot || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.group_code || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700">{{ row.flag_type || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700 whitespace-pre-line">{{ row.flag_details || '-' }}</td>
                <td v-if="showFullColumns" class="px-4 py-3 text-xs text-slate-700 whitespace-pre-line break-words min-w-[360px] max-w-[720px] align-top">{{ row.positive_concat || '-' }}</td>
                <td v-if="showFullColumns" class="px-4 py-3 text-xs text-slate-700 whitespace-pre-line break-words min-w-[360px] max-w-[720px] align-top">{{ row.negative_concat || '-' }}</td>
                <td class="px-4 py-3 text-xs text-slate-700 break-all">
                    <a v-if="row.zoom_link" :href="row.zoom_link" target="_blank" class="font-semibold text-[var(--qd-blue-700)] hover:underline">Open</a>
                    <span v-else>-</span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-700">
                    <a
                        v-if="row.flag_screenshot_url"
                        :href="row.flag_screenshot_url"
                        target="_blank"
                        class="font-semibold text-[var(--qd-blue-700)] hover:underline"
                    >
                        Open ({{ row.flag_screenshot_urls?.length || 1 }})
                    </a>
                    <span v-else>-</span>
                </td>

                <td
                    v-for="criterion in (showFullColumns ? criteriaHeaders : [])"
                    :key="`row-${idx}-${criterion}`"
                    class="px-4 py-3 text-xs text-slate-700"
                >
                    {{ row.criteria_scores?.[criterion] ?? '-' }}
                </td>

                <td
                    v-for="group in groupHeaders"
                    :key="`row-group-${idx}-${group}`"
                    class="px-4 py-3 text-xs text-slate-700"
                >
                    {{ row.group_percentages?.[group] ?? '-' }}
                </td>

                <td class="px-4 py-3 text-xs font-semibold text-slate-800">{{ row.score ?? '-' }}</td>
                <td class="px-4 py-3 text-right text-xs">
                    <a
                        :href="row.edit_url"
                        class="inline-flex items-center rounded-lg border px-3 py-1.5 font-semibold"
                        :class="row.has_pending_edit_request
                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                            : 'border-[var(--qd-blue-100)] text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]'"
                    >
                        {{ row.has_pending_edit_request ? 'Resolve Edit Request' : 'Edit Report' }}
                    </a>
                </td>
            </tr>
        </DataTable>

        <div
            v-if="reports.data.length === 0"
            class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
        >
            <span v-if="searchAcrossWeeks">No submitted reviews matched this tutor search.</span>
            <span v-else>No submitted reviews for Week {{ week }}.</span>
        </div>

        <PaginationLinks :links="reports.links" />
    </AuthenticatedLayout>
</template>
