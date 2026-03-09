<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    week: {
        type: Number,
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
    assignments: {
        type: Array,
        required: true,
    },
    reviewerType: {
        type: String,
        default: null,
    },
    leaderboard: {
        type: Array,
        required: true,
    },
    slotOptions: {
        type: Array,
        required: true,
    },
});

const selectedWeek = ref(props.week);
const activeTab = ref('overview');
const showEditRequestModal = ref(false);
const selectedAssignmentForRequest = ref(null);
const editRequestForm = useForm({
    message: '',
});

const reviewChecklist = [
    'Confirm tutor role, session date, slot, group ID, and recording link before submission.',
    'Use yellow flag for first flagged issue and let the system recommend red when repetition qualifies.',
    'Choose at least one positive comment in every main category before submitting.',
    'Use comments to drive score deductions; red and both flags trigger extra penalty automatically.',
];

const dataSources = [
    'Group ID is auto-filled from admin session mapping using tutor + session date + slot.',
    'Session issue and student issue forms are auto-linked to the review when date + slot match.',
    'Previous flags and tutor history are available inside the review form before submission.',
];

const applyWeekFilter = () => {
    router.get(route('reviewer.home'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};

const formatPercent = (value) => `${Number(value ?? 0).toFixed(1)}%`;
const isCycleClosed = computed(() => !!props.weekCycle?.deadline_at && new Date(props.weekCycle.deadline_at).getTime() < Date.now());

const openEditRequestModal = (assignment) => {
    selectedAssignmentForRequest.value = assignment;
    editRequestForm.message = assignment?.latest_edit_request?.message || '';
    editRequestForm.clearErrors();
    showEditRequestModal.value = true;
};

const closeEditRequestModal = () => {
    showEditRequestModal.value = false;
    selectedAssignmentForRequest.value = null;
    editRequestForm.reset();
};

const submitEditRequest = () => {
    if (!selectedAssignmentForRequest.value) return;

    editRequestForm.post(route('reviewer.reviews.request-edit', selectedAssignmentForRequest.value.assignment_id), {
        preserveScroll: true,
        onSuccess: () => closeEditRequestModal(),
    });
};
</script>

<template>
    <Head title="Quality Dashboard - Reviewer" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Quality Dashboard</h1>
                    <p class="text-sm text-slate-500">
                        Reviewer Layer
                        <span v-if="reviewerType" class="font-semibold text-[var(--qd-blue-700)]">
                            ({{ reviewerType }})
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Cycle Start: {{ weekCycle.starts_at || '-' }} | Deadline: {{ weekCycle.deadline_at || '-' }}
                    </p>
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

        <div
            v-if="isCycleClosed"
            class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm"
        >
            Reviewer editing is closed because the cycle deadline has passed. Existing submitted reviews can only be changed through an admin edit request.
        </div>

        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            <KpiCard title="Active Tutors" :value="kpis.active_tutors" />
            <KpiCard title="Reviewer Accounts" :value="kpis.reviewers" />
            <KpiCard :title="`My Assignments Week ${week}`" :value="kpis.assigned_this_week" />
            <KpiCard :title="`Submitted Week ${week}`" :value="kpis.submitted_this_week" />
            <KpiCard :title="`Pending Week ${week}`" :value="kpis.pending_this_week" />
            <KpiCard :title="`Late Week ${week}`" :value="kpis.late_this_week" />
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-wider transition"
                :class="activeTab === 'overview' ? 'bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] text-white shadow-sm' : 'border border-[var(--qd-blue-100)] bg-white text-[var(--qd-blue-700)]'"
                @click="activeTab = 'overview'"
            >
                Overview
            </button>
            <button
                type="button"
                class="rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-wider transition"
                :class="activeTab === 'resources' ? 'bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] text-white shadow-sm' : 'border border-[var(--qd-blue-100)] bg-white text-[var(--qd-blue-700)]'"
                @click="activeTab = 'resources'"
            >
                Resources
            </button>
        </div>

        <div v-if="activeTab === 'overview'" class="mt-6">
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Project Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted At</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </template>

                <tr v-for="item in assignments" :key="item.id">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.tutor_code || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.tutor_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.mentor_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.project_type || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-semibold"
                            :class="item.is_submitted ? (item.is_late ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') : (item.is_late ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-700')"
                        >
                            {{
                                item.is_submitted
                                    ? (item.is_late ? 'Submitted Late' : 'Submitted')
                                    : (item.is_late ? 'Pending Late' : 'Pending')
                            }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ item.submitted_at || '-' }}</td>
                    <td class="px-4 py-3 text-right text-sm">
                        <div class="flex flex-col items-end gap-2">
                            <Link
                                v-if="!item.is_edit_locked"
                                :href="route('reviewer.reviews.create', item.assignment_id)"
                                class="inline-flex items-center rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] transition hover:bg-[var(--qd-sky-50)]"
                            >
                                {{ item.review_submitted ? 'Edit Review' : 'Start Review' }}
                            </Link>
                            <button
                                v-else-if="item.can_request_admin_edit"
                                type="button"
                                class="inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                                @click="openEditRequestModal(item)"
                            >
                                {{ item.latest_edit_request?.status === 'pending' ? 'Update Admin Request' : 'Request Admin Edit' }}
                            </button>
                            <span
                                v-else
                                class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                            >
                                Locked
                            </span>
                            <p
                                v-if="item.review_submitted"
                                class="max-w-[220px] text-right text-[11px] text-slate-500"
                            >
                                Reviewer edits: {{ item.reviewer_edit_count }}/{{ item.max_reviewer_edits }}
                                <span v-if="!item.edit_limit_reached"> | Remaining: {{ item.remaining_reviewer_edits }}</span>
                                <span v-if="item.latest_edit_request">
                                    | Request {{ item.latest_edit_request.status }}
                                </span>
                            </p>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="assignments.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No tutors assigned to you for Week {{ week }}.
            </div>
            <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Weekly Leaderboard</h2>
                <p class="mt-1 text-xs text-slate-500">Top reviewers by completed reviews, completion rate, and speed in Week {{ week }}.</p>

                <DataTable class="mt-4">
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Completion %</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Hours</th>
                        </tr>
                    </template>

                    <tr v-for="item in leaderboard" :key="item.id">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ item.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.reviewer_type || '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.assigned }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.completed }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ formatPercent(item.completion_rate) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ item.avg_completion_hours ?? '-' }}</td>
                    </tr>
                </DataTable>

                <p v-if="leaderboard.length === 0" class="mt-3 text-sm text-slate-500">
                    No leaderboard data for this week yet.
                </p>
            </div>
        </div>

        <div v-else class="mt-6 grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Review Checklist</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li v-for="item in reviewChecklist" :key="item">{{ item }}</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Slot Reference</h2>
                <div class="mt-3 grid gap-2">
                    <div
                        v-for="slot in slotOptions"
                        :key="slot"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2 text-sm font-semibold text-slate-700"
                    >
                        {{ slot }}
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Auto Data Sources</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                    <li v-for="item in dataSources" :key="item">{{ item }}</li>
                </ul>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                    Red is recommended only when the same flagged issue was already reviewed with the required time gap. The form calculates that automatically.
                </div>
            </div>
        </div>

        <Modal :show="showEditRequestModal" @close="closeEditRequestModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Request Admin Edit</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Reviewer editing is closed for this cycle. Send the exact correction needed and the admin can update the report.
                </p>

                <div class="mt-4">
                    <InputLabel for="message" value="What should be edited?" />
                    <textarea
                        id="message"
                        v-model="editRequestForm.message"
                        rows="6"
                        class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                        placeholder="Example: update the slot, replace one negative comment, and correct the score."
                    />
                    <InputError class="mt-2" :message="editRequestForm.errors.message" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="closeEditRequestModal">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="editRequestForm.processing" @click="submitEditRequest">
                        Send Request
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
