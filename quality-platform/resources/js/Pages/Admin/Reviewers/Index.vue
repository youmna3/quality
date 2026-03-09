<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    reviewers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingReviewerId = ref(null);

const search = ref(props.filters.search ?? '');
const reviewerTypeFilter = ref(props.filters.reviewer_type ?? '');
const statusFilter = ref(
    props.filters.is_active === null || props.filters.is_active === undefined
        ? ''
        : String(Number(Boolean(props.filters.is_active)))
);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    reviewer_type: 'mentor',
    is_active: true,
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    reviewer_type: 'mentor',
    is_active: true,
});

const applyFilters = () => {
    router.get(
        route('admin.reviewers.index'),
        {
            search: search.value || null,
            reviewer_type: reviewerTypeFilter.value || null,
            is_active: statusFilter.value === '' ? null : Number(statusFilter.value),
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    reviewerTypeFilter.value = '';
    statusFilter.value = '';
    router.get(route('admin.reviewers.index'), {}, { preserveState: true, replace: true });
};

const submitCreate = () => {
    createForm.post(route('admin.reviewers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            createForm.reviewer_type = 'mentor';
            createForm.is_active = true;
        },
    });
};

const openEdit = (reviewer) => {
    editingReviewerId.value = reviewer.id;
    editForm.name = reviewer.name;
    editForm.email = reviewer.email;
    editForm.password = '';
    editForm.reviewer_type = reviewer.reviewer_type || 'mentor';
    editForm.is_active = Boolean(reviewer.is_active);
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingReviewerId.value) return;

    editForm.patch(route('admin.reviewers.update', editingReviewerId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingReviewerId.value = null;
            editForm.reset();
            editForm.reviewer_type = 'mentor';
            editForm.is_active = true;
        },
    });
};
</script>

<template>
    <Head title="Reviewer Accounts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Reviewer Accounts</h1>
                    <p class="text-sm text-slate-500">Create mentors and coordinators as reviewer users.</p>
                </div>

                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = true">New Reviewer</SecondaryButton>
                    <Link
                        href="/admin/tutors"
                        class="inline-flex items-center rounded-xl bg-[linear-gradient(135deg,var(--qd-blue-600),var(--qd-blue-500))] px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm"
                    >
                        Tutor Accounts
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Reviewers</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.total_reviewers }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.mentors }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Coordinators</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.coordinators }}</p>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-4">
                <TextInput
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email"
                    class="block w-full"
                />

                <select
                    v-model="reviewerTypeFilter"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                >
                    <option value="">All Types</option>
                    <option value="mentor">Mentor</option>
                    <option value="coordinator">Coordinator</option>
                </select>

                <select
                    v-model="statusFilter"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                >
                    <option value="">All Statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>

                <div class="flex items-center gap-2">
                    <PrimaryButton type="button" @click="applyFilters">Apply</PrimaryButton>
                    <SecondaryButton type="button" @click="resetFilters">Reset</SecondaryButton>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <DataTable>
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </template>

                <tr v-for="reviewer in reviewers.data" :key="reviewer.id">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ reviewer.name }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ reviewer.email }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ reviewer.reviewer_type || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium"
                            :class="reviewer.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ reviewer.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-sm">
                        <button
                            type="button"
                            class="rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] transition hover:bg-[var(--qd-sky-50)]"
                            @click="openEdit(reviewer)"
                        >
                            Edit
                        </button>
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="reviewers.data.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No reviewer accounts found.
            </div>

            <PaginationLinks :links="reviewers.links" />
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Create Reviewer Account</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="create_name" value="Name" />
                        <TextInput id="create_name" v-model="createForm.name" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="create_email" value="Email" />
                        <TextInput id="create_email" v-model="createForm.email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="create_password" value="Password" />
                        <TextInput id="create_password" v-model="createForm.password" type="password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="create_type" value="Reviewer Type" />
                        <select
                            id="create_type"
                            v-model="createForm.reviewer_type"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="mentor">Mentor</option>
                            <option value="coordinator">Coordinator</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.reviewer_type" />
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input id="create_active" v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="create_active" class="text-sm text-slate-700">Active account</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="createForm.processing" @click="submitCreate">Create</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Edit Reviewer Account</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="edit_name" value="Name" />
                        <TextInput id="edit_name" v-model="editForm.name" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="edit_email" value="Email" />
                        <TextInput id="edit_email" v-model="editForm.email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="edit_password" value="Password (Optional)" />
                        <TextInput id="edit_password" v-model="editForm.password" type="password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="edit_type" value="Reviewer Type" />
                        <select
                            id="edit_type"
                            v-model="editForm.reviewer_type"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="mentor">Mentor</option>
                            <option value="coordinator">Coordinator</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.reviewer_type" />
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input id="edit_active" v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="edit_active" class="text-sm text-slate-700">Active account</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEditModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="editForm.processing" @click="submitEdit">Update</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
