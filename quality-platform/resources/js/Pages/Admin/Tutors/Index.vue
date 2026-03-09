<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
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
    tutors: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const showCreateModal = ref(false);
const showImportModal = ref(false);
const showEditModal = ref(false);
const editingTutorId = ref(null);

const search = ref(props.filters.search ?? '');
const projectTypeFilter = ref(props.filters.project_type ?? '');
const statusFilter = ref(
    props.filters.is_active === null || props.filters.is_active === undefined
        ? ''
        : String(Number(Boolean(props.filters.is_active)))
);

const baseTutorForm = {
    tutor_code: '',
    name_en: '',
    project_type: 'DEMI',
    mentor_name: '',
    grade: '',
    zoom_email: '',
    zoom_password: '',
    dashboard_password: '',
    is_active: true,
};

const createForm = useForm({
    ...baseTutorForm,
});

const editForm = useForm({
    ...baseTutorForm,
});

const importForm = useForm({
    sheet: null,
    default_project_type: 'DEMI',
});

const applyFilters = () => {
    router.get(
        route('admin.tutors.index'),
        {
            search: search.value || null,
            project_type: projectTypeFilter.value || null,
            is_active: statusFilter.value === '' ? null : Number(statusFilter.value),
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    projectTypeFilter.value = '';
    statusFilter.value = '';
    router.get(route('admin.tutors.index'), {}, { preserveState: true, replace: true });
};

const submitCreate = () => {
    createForm.post(route('admin.tutors.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            Object.assign(createForm, baseTutorForm);
        },
    });
};

const openEdit = (tutor) => {
    editingTutorId.value = tutor.id;
    editForm.tutor_code = tutor.tutor_code || '';
    editForm.name_en = tutor.name_en || '';
    editForm.project_type = tutor.project_type || 'DEMI';
    editForm.mentor_name = tutor.mentor_name || '';
    editForm.grade = tutor.grade || '';
    editForm.zoom_email = tutor.zoom_email || '';
    editForm.zoom_password = tutor.zoom_password || '';
    editForm.dashboard_password = tutor.dashboard_password || '';
    editForm.is_active = Boolean(tutor.is_active);
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingTutorId.value) return;

    editForm.patch(route('admin.tutors.update', editingTutorId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingTutorId.value = null;
            editForm.reset();
            Object.assign(editForm, baseTutorForm);
        },
    });
};

const deleteTutor = (tutor) => {
    const confirmed = window.confirm(`Deactivate tutor ${tutor.tutor_code} - ${tutor.name_en}? Record will stay in database.`);
    if (!confirmed) return;

    router.delete(route('admin.tutors.destroy', tutor.id), {
        preserveScroll: true,
    });
};

const onSheetPicked = (event) => {
    const [file] = event.target.files || [];
    importForm.sheet = file ?? null;
};

const submitImport = () => {
    importForm.post(route('admin.tutors.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            importForm.default_project_type = 'DEMI';
        },
    });
};
</script>

<template>
    <Head title="Tutors Data" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Tutors Data</h1>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="showImportModal = true">Upload Sheet</SecondaryButton>
                    <PrimaryButton type="button" @click="showCreateModal = true">New Tutor</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-4">
                <TextInput
                    v-model="search"
                    type="text"
                    placeholder="Search by tutor id/name/mentor"
                    class="block w-full"
                />

                <select
                    v-model="projectTypeFilter"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                >
                    <option value="">All Types</option>
                    <option value="DEMI">DEMI</option>
                    <option value="DECI">DECI</option>
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Dashboard Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </template>

                <tr v-for="tutor in tutors.data" :key="tutor.id">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ tutor.tutor_code }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ tutor.name_en }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ tutor.project_type || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ tutor.mentor_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ tutor.grade || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ tutor.dashboard_email || tutor.user?.email || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-medium"
                            :class="tutor.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ tutor.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] transition hover:bg-[var(--qd-sky-50)]"
                                @click="openEdit(tutor)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
                                @click="deleteTutor(tutor)"
                            >
                                Deactivate
                            </button>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="tutors.data.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No tutor records yet. Use <strong>Upload Sheet</strong> or <strong>New Tutor</strong>.
            </div>

            <PaginationLinks :links="tutors.links" />
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Create Tutor</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Dashboard email is auto-generated as <code>tutor_id@ischoolteams.com</code>.
                </p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="create_tutor_code" value="Tutor ID" />
                        <TextInput id="create_tutor_code" v-model="createForm.tutor_code" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.tutor_code" />
                    </div>

                    <div>
                        <InputLabel for="create_name_en" value="Name" />
                        <TextInput id="create_name_en" v-model="createForm.name_en" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.name_en" />
                    </div>

                    <div>
                        <InputLabel for="create_project_type" value="Project Type" />
                        <select
                            id="create_project_type"
                            v-model="createForm.project_type"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="DEMI">DEMI</option>
                            <option value="DECI">DECI</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.project_type" />
                    </div>

                    <div>
                        <InputLabel for="create_mentor_name" value="Mentor" />
                        <TextInput id="create_mentor_name" v-model="createForm.mentor_name" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.mentor_name" />
                    </div>

                    <div>
                        <InputLabel for="create_grade" value="Grade" />
                        <TextInput id="create_grade" v-model="createForm.grade" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.grade" />
                    </div>

                    <div>
                        <InputLabel for="create_zoom_email" value="Zoom Email" />
                        <TextInput id="create_zoom_email" v-model="createForm.zoom_email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.zoom_email" />
                    </div>

                    <div>
                        <InputLabel for="create_zoom_password" value="Zoom Password" />
                        <TextInput id="create_zoom_password" v-model="createForm.zoom_password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.zoom_password" />
                    </div>

                    <div>
                        <InputLabel for="create_dashboard_password" value="Dashboard Password (Optional)" />
                        <TextInput id="create_dashboard_password" v-model="createForm.dashboard_password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.dashboard_password" />
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input id="create_is_active" v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="create_is_active" class="text-sm text-slate-700">Active tutor</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="createForm.processing" @click="submitCreate">Save Tutor</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Edit Tutor</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="edit_tutor_code" value="Tutor ID" />
                        <TextInput id="edit_tutor_code" v-model="editForm.tutor_code" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.tutor_code" />
                    </div>

                    <div>
                        <InputLabel for="edit_name_en" value="Name" />
                        <TextInput id="edit_name_en" v-model="editForm.name_en" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.name_en" />
                    </div>

                    <div>
                        <InputLabel for="edit_project_type" value="Project Type" />
                        <select
                            id="edit_project_type"
                            v-model="editForm.project_type"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="DEMI">DEMI</option>
                            <option value="DECI">DECI</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.project_type" />
                    </div>

                    <div>
                        <InputLabel for="edit_mentor_name" value="Mentor" />
                        <TextInput id="edit_mentor_name" v-model="editForm.mentor_name" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.mentor_name" />
                    </div>

                    <div>
                        <InputLabel for="edit_grade" value="Grade" />
                        <TextInput id="edit_grade" v-model="editForm.grade" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.grade" />
                    </div>

                    <div>
                        <InputLabel for="edit_zoom_email" value="Zoom Email" />
                        <TextInput id="edit_zoom_email" v-model="editForm.zoom_email" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.zoom_email" />
                    </div>

                    <div>
                        <InputLabel for="edit_zoom_password" value="Zoom Password" />
                        <TextInput id="edit_zoom_password" v-model="editForm.zoom_password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.zoom_password" />
                    </div>

                    <div>
                        <InputLabel for="edit_dashboard_password" value="Dashboard Password (Optional)" />
                        <TextInput id="edit_dashboard_password" v-model="editForm.dashboard_password" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.dashboard_password" />
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input id="edit_is_active" v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <label for="edit_is_active" class="text-sm text-slate-700">Active tutor</label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showEditModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="editForm.processing" @click="submitEdit">Update Tutor</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showImportModal" @close="showImportModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Upload Tutors Sheet</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Upload CSV columns:
                    <code>Tutor ID,Name,Mentor,Grade,Zoom email,Zoom password,Dashboard Email,Dashboard Password,Status</code>
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Optional column: <code>Project Type</code> or <code>project_type</code>.
                </p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="default_project_type" value="Default Project Type" />
                        <select
                            id="default_project_type"
                            v-model="importForm.default_project_type"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="DEMI">DEMI</option>
                            <option value="DECI">DECI</option>
                        </select>
                        <InputError class="mt-2" :message="importForm.errors.default_project_type" />
                    </div>

                    <div>
                    <InputLabel for="sheet" value="CSV file" />
                    <input
                        id="sheet"
                        type="file"
                        accept=".csv,.txt"
                        class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm"
                        @change="onSheetPicked"
                    />
                    <InputError class="mt-2" :message="importForm.errors.sheet" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showImportModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="importForm.processing" @click="submitImport">Upload</PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
