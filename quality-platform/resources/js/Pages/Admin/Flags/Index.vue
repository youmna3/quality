<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    flags: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    colorOptions: {
        type: Array,
        required: true,
    },
});

const search = ref(props.filters.search ?? '');
const colorFilter = ref(props.filters.color ?? '');
const statusFilter = ref(props.filters.status ?? '');

const applyFilters = () => {
    router.get(
        route('admin.flags.index'),
        {
            search: search.value || null,
            color: colorFilter.value || null,
            status: statusFilter.value || null,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    colorFilter.value = '';
    statusFilter.value = '';
    router.get(route('admin.flags.index'), {}, { preserveState: true, replace: true });
};

const updateStatus = (flagId, status) => {
    router.put(
        route('admin.flags.update', flagId),
        { status },
        { preserveScroll: true, preserveState: true }
    );
};

const updateObjection = (flagId, objectionStatus) => {
    const objectionResponse = window.prompt(
        objectionStatus === 'accepted' ? 'Optional admin note for accepted objection:' : 'Optional admin note for rejected objection:',
        ''
    );

    router.put(
        route('admin.flags.objection.update', flagId),
        {
            objection_status: objectionStatus,
            objection_response: objectionResponse || null,
        },
        { preserveScroll: true, preserveState: true }
    );
};
</script>

<template>
    <Head title="Flags" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Flags</h1>
                <p class="text-sm text-slate-500">Yellow / Red / Both flags submitted by reviewers.</p>
            </div>
        </template>

        <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-5">
                <TextInput v-model="search" type="text" placeholder="Search tutor/reason/subcategory" class="block w-full md:col-span-2" />

                <select
                    v-model="colorFilter"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                >
                    <option value="">All Colors</option>
                    <option v-for="color in colorOptions" :key="color" :value="color">{{ color }}</option>
                </select>

                <select
                    v-model="statusFilter"
                    class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                >
                    <option value="">All Statuses</option>
                    <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tutor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Color</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Flag</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session Link</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Objection</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Screenshot</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </template>

                <tr v-for="flag in flags.data" :key="flag.id">
                    <td class="px-4 py-3 text-sm">
                        <p class="font-semibold text-slate-800">{{ flag.tutor?.tutor_code || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ flag.tutor?.name_en || '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p>{{ flag.review?.session_date || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ flag.review?.slot || '-' }} | {{ flag.review?.group_code || '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">{{ flag.review?.reviewer_name || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span
                            class="rounded-full px-2 py-1 text-xs font-semibold"
                            :class="flag.color === 'yellow' ? 'bg-amber-100 text-amber-700' : (flag.color === 'red' ? 'bg-rose-100 text-rose-700' : 'bg-orange-100 text-orange-700')"
                        >
                            {{ flag.color }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p class="font-semibold">{{ flag.subcategory || '-' }}</p>
                        <p class="text-xs text-slate-500">{{ flag.reason || '-' }}</p>
                        <p class="text-xs text-slate-500">Duration: {{ flag.duration_text || '-' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700 break-all">
                        <a v-if="flag.review?.recorded_link" :href="flag.review.recorded_link" target="_blank" class="font-semibold text-[var(--qd-blue-700)] hover:underline">Open</a>
                        <span v-else>-</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <p class="text-xs">
                            <span class="font-semibold">Status:</span> {{ flag.objection_status || 'none' }}
                        </p>
                        <p v-if="flag.objection_text" class="mt-1 text-xs text-slate-500 whitespace-pre-line">{{ flag.objection_text }}</p>
                        <p v-if="flag.objection_response" class="mt-1 text-xs text-slate-500">Admin Note: {{ flag.objection_response }}</p>
                        <div v-if="flag.objection_status === 'pending'" class="mt-2 flex gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-emerald-200 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                @click="updateObjection(flag.id, 'accepted')"
                            >
                                Accept
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                @click="updateObjection(flag.id, 'rejected')"
                            >
                                Reject
                            </button>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <a v-if="flag.screenshot_url" :href="flag.screenshot_url" target="_blank" class="font-semibold text-[var(--qd-blue-700)] hover:underline">Open</a>
                        <span v-else>-</span>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <select
                            :value="flag.status"
                            class="rounded-lg border border-[var(--qd-blue-100)] px-2 py-1 text-xs text-slate-700"
                            @change="updateStatus(flag.id, $event.target.value)"
                        >
                            <option v-for="status in statusOptions" :key="status" :value="status">{{ status }}</option>
                        </select>
                    </td>
                </tr>
            </DataTable>

            <div
                v-if="flags.data.length === 0"
                class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
            >
                No flags found.
            </div>

            <PaginationLinks :links="flags.links" />
        </div>
    </AuthenticatedLayout>
</template>
