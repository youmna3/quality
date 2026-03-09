<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import DataTable from '@/Components/DataTable.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';

const props = defineProps({
    projects: {
        type: Object,
        required: true,
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingProjectId = ref(null);

const createForm = useForm({
    code: 'DEMI',
    name: '',
});

const editForm = useForm({
    code: '',
    name: '',
});

const submitCreate = () => {
    createForm.post(route('admin.projects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset('name');
            createForm.code = 'DEMI';
        },
    });
};

const openEdit = (project) => {
    editingProjectId.value = project.id;
    editForm.code = project.code;
    editForm.name = project.name;
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('admin.projects.update', editingProjectId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
};
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900">Projects</h1>
                <PrimaryButton type="button" @click="showCreateModal = true">New Project</PrimaryButton>
            </div>
        </template>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tutors</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                </tr>
            </template>

            <tr v-for="project in projects.data" :key="project.id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ project.code }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ project.name }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ project.tutors_count }}</td>
                <td class="px-4 py-3 text-right">
                    <SecondaryButton type="button" @click="openEdit(project)">Edit</SecondaryButton>
                </td>
            </tr>
        </DataTable>

        <PaginationLinks :links="projects.links" />

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Create Project</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="project_code" value="Project Code" />
                        <select
                            id="project_code"
                            v-model="createForm.code"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="DEMI">DEMI</option>
                            <option value="DECI">DECI</option>
                        </select>
                        <InputError class="mt-2" :message="createForm.errors.code" />
                    </div>

                    <div>
                        <InputLabel for="project_name" value="Project Name" />
                        <TextInput id="project_name" v-model="createForm.name" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="createForm.errors.name" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = false">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="createForm.processing" @click="submitCreate">Save</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Edit Project</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="edit_project_code" value="Project Code" />
                        <select
                            id="edit_project_code"
                            v-model="editForm.code"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="DEMI">DEMI</option>
                            <option value="DECI">DECI</option>
                        </select>
                        <InputError class="mt-2" :message="editForm.errors.code" />
                    </div>

                    <div>
                        <InputLabel for="edit_project_name" value="Project Name" />
                        <TextInput id="edit_project_name" v-model="editForm.name" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="editForm.errors.name" />
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
