<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import KpiCard from '@/Components/KpiCard.vue';
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
    kpis: {
        type: Object,
        required: true,
    },
    weekCycle: {
        type: Object,
        required: true,
    },
    assignments: {
        type: Object,
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
    opsStats: {
        type: Object,
        required: true,
    },
});

const selectedWeek = ref(props.week);
const search = ref(props.filters.search ?? '');
const assignForm = useForm({
    week: props.week,
});
const redoForm = useForm({
    week: props.week,
});
const cycleForm = useForm({
    week: props.week,
    starts_at: props.weekCycle.starts_at ?? '',
    deadline_at: props.weekCycle.deadline_at ?? '',
});
const opsGroupForm = useForm({
    week: props.week,
    file: null,
});
const opsIssueForm = useForm({
    week: props.week,
    file: null,
});
let searchDebounceTimer = null;

watch(selectedWeek, (value) => {
    assignForm.week = value;
    redoForm.week = value;
    cycleForm.week = value;
    opsGroupForm.week = value;
    opsIssueForm.week = value;
});

watch(
    () => props.weekCycle,
    (cycle) => {
        cycleForm.starts_at = cycle?.starts_at ?? '';
        cycleForm.deadline_at = cycle?.deadline_at ?? '';
    }
);

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
            route('admin.assignments.index'),
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

const applyWeekFilter = () => {
    router.get(
        route('admin.assignments.index'),
        { week: selectedWeek.value, search: search.value || null },
        { preserveState: true, replace: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    search.value = '';
    router.get(route('admin.assignments.index'), { week: selectedWeek.value }, { preserveState: true, replace: true, preserveScroll: true });
};

const autoAssign = () => {
    assignForm.post(route('admin.assignments.auto-assign'), {
        preserveScroll: true,
    });
};

const redoAssign = () => {
    if (!window.confirm(`Redo assignments for Week ${selectedWeek.value}? This will rebalance all active tutors in that week.`)) {
        return;
    }

    redoForm.post(route('admin.assignments.redo-assign'), {
        preserveScroll: true,
    });
};

const saveWeekCycle = () => {
    cycleForm.put(route('admin.assignments.cycle.update'), {
        preserveScroll: true,
    });
};

const clearWeekCycle = () => {
    cycleForm.starts_at = '';
    cycleForm.deadline_at = '';
    saveWeekCycle();
};

const uploadOpsGroups = () => {
    opsGroupForm.post(route('admin.assignments.import-ops-groups'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            opsGroupForm.reset('file');
        },
    });
};

const uploadOpsIssues = () => {
    opsIssueForm.post(route('admin.assignments.import-ops-issues'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            opsIssueForm.reset('file');
        },
    });
};
</script>

<template>
    <Head title="Weekly Assignments" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Weekly Assignments</h1>
                    <p class="text-sm text-slate-500">Auto assign tutors to mentor/coordinator reviewers by week.</p>
                    <p v-if="searchAcrossWeeks" class="text-xs font-semibold text-[var(--qd-blue-700)]">
                        Search mode: showing tutor/reviewer assignments across all weeks.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Type tutor ID to show all weeks..."
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    />
                    <select
                        v-model.number="selectedWeek"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    >
                        <option v-for="w in weeks" :key="w" :value="w">Week {{ w }}</option>
                    </select>

                    <SecondaryButton type="button" @click="applyWeekFilter">Apply Week</SecondaryButton>
                    <SecondaryButton type="button" @click="resetFilters">Reset</SecondaryButton>
                    <PrimaryButton type="button" :disabled="assignForm.processing" @click="autoAssign">
                        Auto Assign Week
                    </PrimaryButton>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-bold uppercase tracking-wider text-amber-700 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="redoForm.processing"
                        @click="redoAssign"
                    >
                        Redo Assignment
                    </button>
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-5">
            <KpiCard :title="`Assigned Week ${week}`" :value="kpis.assigned_this_week" />
            <KpiCard title="Active Tutors" :value="kpis.active_tutors" />
            <KpiCard :title="`Pending Week ${week}`" :value="kpis.pending_tutors" />
            <KpiCard title="Mentor Assigned" :value="kpis.mentor_assigned" />
            <KpiCard title="Coordinator Assigned" :value="kpis.coordinator_assigned" />
        </div>

        <div class="mt-4 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Week {{ week }} Type Ratio</p>
                    <p class="mt-1 text-xl font-extrabold text-[var(--qd-blue-900)]">
                        Mentor : Coordinator = 1 : {{ kpis.type_ratio ?? 0 }}
                    </p>
                </div>

                <div class="ml-auto grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cycle Start</label>
                        <input
                            v-model="cycleForm.starts_at"
                            type="datetime-local"
                            class="w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cycle Deadline</label>
                        <input
                            v-model="cycleForm.deadline_at"
                            type="datetime-local"
                            class="w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        />
                    </div>
                    <div class="flex items-center gap-2 pb-0.5">
                        <PrimaryButton type="button" :disabled="cycleForm.processing" @click="saveWeekCycle">Save Cycle</PrimaryButton>
                        <SecondaryButton type="button" :disabled="cycleForm.processing" @click="clearWeekCycle">Clear</SecondaryButton>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-[var(--qd-blue-900)]">Session Data Upload (Admin Only)</p>
                    <p class="text-xs text-slate-500">Upload once. Reviewers will auto-use it by tutor + date + slot.</p>
                    <p class="mt-1 text-xs text-slate-500">Stored rows: Group Mapping {{ opsStats.group_rows }} | Session Issues {{ opsStats.issue_rows }}</p>
                </div>
            </div>

            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session Group Mapping</p>
                    <input
                        type="file"
                        accept=".csv,.txt"
                        class="mt-2 block w-full text-sm"
                        @change="opsGroupForm.file = $event.target.files[0]"
                    />
                    <div class="mt-2">
                        <PrimaryButton type="button" :disabled="opsGroupForm.processing || !opsGroupForm.file" @click="uploadOpsGroups">
                            Upload Session Group CSV
                        </PrimaryButton>
                    </div>
                </div>

                <div class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Session Issue Form</p>
                    <input
                        type="file"
                        accept=".csv,.txt"
                        class="mt-2 block w-full text-sm"
                        @change="opsIssueForm.file = $event.target.files[0]"
                    />
                    <div class="mt-2">
                        <PrimaryButton type="button" :disabled="opsIssueForm.processing || !opsIssueForm.file" @click="uploadOpsIssues">
                            Upload Session Issue CSV
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Week</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer Type</th>
                    </tr>
                </template>

                <tr v-for="item in assignments.data" :key="item.id">
                    <td class="px-4 py-3 text-sm font-semibold text-[var(--qd-blue-700)]">Week {{ item.week_number }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.tutor?.tutor_code || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.tutor?.name_en || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.tutor?.mentor_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        {{ item.reviewer?.name || '-' }} <span class="text-xs text-slate-500">({{ item.reviewer?.email || '-' }})</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.reviewer_type || item.reviewer?.reviewer_type || '-' }}</td>
                </tr>
            </DataTable>

            <div
                v-if="assignments.data.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                <span v-if="searchAcrossWeeks">No assignments matched your search across all weeks.</span>
                <span v-else>No assignments found for Week {{ week }}.</span>
            </div>

            <PaginationLinks :links="assignments.links" />
        </div>
    </AuthenticatedLayout>
</template>
