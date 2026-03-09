<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    week: {
        type: Number,
        required: true,
    },
    weeks: {
        type: Array,
        required: true,
    },
    slotOptions: {
        type: Array,
        required: true,
    },
    profile: {
        type: Object,
        required: true,
    },
    recentIssues: {
        type: Array,
        required: true,
    },
    groupMappings: {
        type: Array,
        required: true,
    },
    assignment: {
        type: Object,
        default: null,
    },
});

const selectedWeek = ref(props.week);
const showIssueModal = ref(false);
const issueMode = ref('session');

const issueForm = useForm({
    issue_type: 'session',
    session_date: '',
    slot: '',
    group_code: '',
    complaint_text: '',
});

const issueModalTitle = computed(() =>
    issueMode.value === 'student' ? 'Student Issue Form' : 'Session Issue Form'
);

const issuePlaceholder = computed(() =>
    issueMode.value === 'student'
        ? 'Describe the student-related issue clearly with context.'
        : 'Describe the session issue clearly with context.'
);

const openIssueModal = (type) => {
    issueMode.value = type;
    issueForm.reset();
    issueForm.issue_type = type;
    issueForm.clearErrors();
    showIssueModal.value = true;
};

const closeIssueModal = () => {
    showIssueModal.value = false;
    issueForm.reset();
    issueForm.issue_type = 'session';
};

const applyWeekFilter = () => {
    router.get(route('tutor.home'), { week: selectedWeek.value }, { preserveState: true, replace: true });
};

const tryAutofillGroup = () => {
    if (!issueForm.slot) return;

    const exact = props.groupMappings.find(
        (row) => row.slot === issueForm.slot && row.session_date === issueForm.session_date
    );
    const fallback = props.groupMappings.find(
        (row) => row.slot === issueForm.slot && (!row.session_date || row.session_date === '')
    );
    const selected = exact ?? fallback;
    if (selected) {
        issueForm.group_code = selected.group_code;
    }
};

watch(
    () => [issueForm.session_date, issueForm.slot],
    () => tryAutofillGroup()
);

const submitIssue = () => {
    issueForm.post(route('tutor.issues.store'), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => closeIssueModal(),
    });
};
</script>

<template>
    <Head title="Quality Dashboard - Tutor" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Quality Dashboard</h1>
                    <p class="text-sm text-slate-500">Tutor Layer</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        href="/tutor/reports"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Weekly Report
                    </Link>
                    <Link
                        href="/tutor/flags"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Flags Tab
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] px-3 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-sm"
                        @click="openIssueModal('session')"
                    >
                        Session Issue Form
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                        @click="openIssueModal('student')"
                    >
                        Student Issue Form
                    </button>
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

        <div class="grid gap-4 md:grid-cols-3">
            <KpiCard title="Tutor ID" :value="profile.tutor_id ?? 'N/A'" />
            <KpiCard title="Mentor" :value="profile.mentor_name ?? 'N/A'" />
            <KpiCard :title="`Week ${week} Review Status`" :value="assignment?.is_assigned ? 'Assigned' : 'Not Assigned'" />
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Week {{ week }} Assignment</h2>
            <p v-if="!assignment" class="mt-2 text-sm text-slate-500">No reviewer has been assigned yet.</p>
            <p v-else class="mt-2 text-sm text-slate-600">Your session for this week is assigned for quality review.</p>
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Recent Submitted Issues</h2>
                <p class="text-xs text-slate-500">These entries are auto-used by reviewers based on Tutor + Session Date + Slot.</p>
            </div>
            <div class="mt-3 space-y-2">
                <div
                    v-for="issue in recentIssues"
                    :key="issue.id"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="issue.issue_type === 'student' ? 'bg-amber-100 text-amber-700' : 'bg-[var(--qd-blue-100)] text-[var(--qd-blue-700)]'"
                        >
                            {{ issue.issue_type === 'student' ? 'Student Issue' : 'Session Issue' }}
                        </span>
                        <p class="text-xs text-slate-500">{{ issue.session_date }} | {{ issue.slot }} | Group {{ issue.group_code }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-700">{{ issue.issue_text }}</p>
                </div>
                <p v-if="recentIssues.length === 0" class="text-sm text-slate-500">No issues submitted yet.</p>
            </div>
        </div>

        <Modal :show="showIssueModal" @close="closeIssueModal">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-[var(--qd-blue-900)]">{{ issueModalTitle }}</h2>
                <p class="mt-1 text-sm text-slate-500">Submit once. Reviewer form will auto-fill this issue when slot/date match.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="session_date" value="Session Date" />
                        <TextInput id="session_date" v-model="issueForm.session_date" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="issueForm.errors.session_date" />
                    </div>
                    <div>
                        <InputLabel for="slot" value="Slot" />
                        <select
                            id="slot"
                            v-model="issueForm.slot"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="">Select Slot</option>
                            <option v-for="slot in slotOptions" :key="slot" :value="slot">{{ slot }}</option>
                        </select>
                        <InputError class="mt-2" :message="issueForm.errors.slot" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="group_code" value="Group ID" />
                        <TextInput id="group_code" v-model="issueForm.group_code" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="issueForm.errors.group_code" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="complaint_text" :value="issueMode === 'student' ? 'Student Issue Details' : 'Session Issue Details'" />
                        <textarea
                            id="complaint_text"
                            v-model="issueForm.complaint_text"
                            rows="5"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                            :placeholder="issuePlaceholder"
                        />
                        <InputError class="mt-2" :message="issueForm.errors.complaint_text" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="closeIssueModal">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="issueForm.processing" @click="submitIssue">Submit</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
