<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    mentors: {
        type: Array,
        required: true,
    },
    reviewers: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

const search = ref('');

const toFormAssignments = (rows) =>
    rows.map((row) => ({
        mentor_name: row.mentor_name,
        tutors_count: row.tutors_count ?? 0,
        has_tutors: Boolean(row.has_tutors),
        coordinator_user_id: row.coordinator_user_id ? String(row.coordinator_user_id) : '',
    }));

const form = useForm({
    assignments: toFormAssignments(props.mentors),
});

const reviewerLookup = computed(() =>
    new Map(props.reviewers.map((reviewer) => [String(reviewer.id), reviewer]))
);

const filteredRows = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return form.assignments;
    }

    return form.assignments.filter((row) => row.mentor_name.toLowerCase().includes(keyword));
});

const mappedCount = computed(
    () => form.assignments.filter((row) => row.coordinator_user_id !== '').length
);

const saveAssignments = () => {
    form
        .transform((payload) => ({
            assignments: payload.assignments.map((row) => ({
                mentor_name: row.mentor_name,
                coordinator_user_id:
                    row.coordinator_user_id === '' ? null : Number(row.coordinator_user_id),
            })),
        }))
        .put(route('admin.mentor-coordinators.update'), {
            preserveScroll: true,
        });
};

const resetAssignments = () => {
    form.assignments = toFormAssignments(props.mentors);
};

const coordinatorHint = (row) => {
    if (!row.coordinator_user_id) {
        return 'Unassigned';
    }

    const reviewer = reviewerLookup.value.get(String(row.coordinator_user_id));
    if (!reviewer) {
        return 'Reviewer not found';
    }

    return reviewer.is_active ? 'Active reviewer' : 'Inactive reviewer';
};
</script>

<template>
    <Head title="Mentor Coordinators" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">
                        Mentor Coordinator Matrix
                    </h1>
                    <p class="text-sm text-slate-500">
                        Assign one coordinator reviewer for every mentor from admin.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="resetAssignments">Reset</SecondaryButton>
                    <PrimaryButton type="button" :disabled="form.processing" @click="saveAssignments">
                        Save Assignments
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.total_mentors }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mapped Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ mappedCount }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unmapped Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">
                    {{ form.assignments.length - mappedCount }}
                </p>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                <TextInput
                    v-model="search"
                    type="text"
                    placeholder="Search mentor name"
                    class="block w-full"
                />
                <p class="text-sm text-slate-500">
                    {{ filteredRows.length }} mentor(s) visible
                </p>
            </div>
        </div>

        <div class="mt-4">
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutors</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Coordinator Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </template>

                <tr v-for="row in filteredRows" :key="row.mentor_name">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">
                        {{ row.mentor_name }}
                        <span
                            v-if="!row.has_tutors"
                            class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700"
                        >
                            No tutors
                        </span>
                    </td>

                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ row.tutors_count }}
                    </td>

                    <td class="px-4 py-3 text-sm">
                        <select
                            v-model="row.coordinator_user_id"
                            class="block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-800 shadow-sm outline-none transition focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="">Unassigned</option>
                            <option v-for="reviewer in reviewers" :key="reviewer.id" :value="String(reviewer.id)">
                                {{ reviewer.name }} - {{ reviewer.email }} ({{ reviewer.mentors_count }} mentor(s))
                            </option>
                        </select>
                    </td>

                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ coordinatorHint(row) }}
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="filteredRows.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No mentors match your search.
            </div>
        </div>
    </AuthenticatedLayout>
</template>
