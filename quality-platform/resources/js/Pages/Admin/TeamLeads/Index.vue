<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    teamLeads: {
        type: Array,
        required: true,
    },
    mentors: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const editingTeamLeadId = ref(null);
const search = ref('');

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    is_active: true,
});

const mappingForm = useForm({
    assignments: props.mentors.map((row) => ({
        mentor_name: row.mentor_name,
        tutors_count: row.tutors_count ?? 0,
        team_lead_user_id: row.team_lead_user_id ? String(row.team_lead_user_id) : '',
    })),
});

const filteredRows = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (!keyword) return mappingForm.assignments;
    return mappingForm.assignments.filter((row) => row.mentor_name.toLowerCase().includes(keyword));
});

const teamLeadLookup = computed(() => new Map(props.teamLeads.map((item) => [String(item.id), item])));

const mappedCount = computed(() => mappingForm.assignments.filter((row) => row.team_lead_user_id !== '').length);

const submitCreate = () => {
    createForm.post(route('admin.team-leads.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.is_active = true;
            showCreateModal.value = false;
        },
    });
};

const openEdit = (teamLead) => {
    editingTeamLeadId.value = teamLead.id;
    editForm.name = teamLead.name;
    editForm.email = teamLead.email;
    editForm.password = '';
    editForm.is_active = Boolean(teamLead.is_active);
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingTeamLeadId.value) return;
    editForm.patch(route('admin.team-leads.update', editingTeamLeadId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingTeamLeadId.value = null;
            editForm.reset();
            editForm.is_active = true;
        },
    });
};

const saveMappings = () => {
    mappingForm
        .transform((payload) => ({
            assignments: payload.assignments.map((row) => ({
                mentor_name: row.mentor_name,
                team_lead_user_id: row.team_lead_user_id === '' ? null : Number(row.team_lead_user_id),
            })),
        }))
        .put(route('admin.team-leads.mappings.update'), {
            preserveScroll: true,
        });
};
</script>

<template>
    <Head title="Team Leads" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Team Leads</h1>
                    <p class="text-sm text-slate-500">Create team lead accounts and assign mentors to each lead.</p>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton type="button" @click="showCreateModal = true">New Team Lead</SecondaryButton>
                    <PrimaryButton type="button" :disabled="mappingForm.processing" @click="saveMappings">Save Mentor Mapping</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Team Leads</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.total_team_leads }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.total_mentors }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mapped Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ mappedCount }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Unmapped Mentors</p>
                <p class="mt-2 text-2xl font-extrabold text-[var(--qd-blue-900)]">{{ stats.unmapped_mentors }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Team Lead Accounts</h2>
                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                        </tr>
                    </template>

                    <tr v-for="teamLead in teamLeads" :key="teamLead.id">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ teamLead.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ teamLead.email }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="teamLead.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'"
                            >
                                {{ teamLead.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <button
                                type="button"
                                class="rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] transition hover:bg-[var(--qd-sky-50)]"
                                @click="openEdit(teamLead)"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                </DataTable>
            </div>

            <div>
                <h2 class="mb-3 text-lg font-bold text-[var(--qd-blue-900)]">Mentor Mapping</h2>
                <div class="mb-3">
                    <TextInput v-model="search" type="text" placeholder="Search mentor..." class="block w-full" />
                </div>

                <DataTable>
                    <template #head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mentor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutors</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Team Lead</th>
                        </tr>
                    </template>

                    <tr v-for="row in filteredRows" :key="row.mentor_name">
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ row.mentor_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ row.tutors_count }}</td>
                        <td class="px-4 py-3 text-sm">
                            <select
                                v-model="row.team_lead_user_id"
                                class="block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-800 shadow-sm outline-none transition focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                            >
                                <option value="">Unassigned</option>
                                <option v-for="teamLead in teamLeads" :key="teamLead.id" :value="String(teamLead.id)">
                                    {{ teamLead.name }} - {{ teamLead.email }}
                                </option>
                            </select>
                            <p v-if="row.team_lead_user_id" class="mt-1 text-xs text-slate-500">
                                {{ teamLeadLookup.get(row.team_lead_user_id)?.is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </td>
                    </tr>
                </DataTable>
            </div>
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-slate-900">Create Team Lead Account</h2>

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
                <h2 class="text-lg font-semibold text-slate-900">Edit Team Lead Account</h2>

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

