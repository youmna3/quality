<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PaginationLinks from '@/Components/PaginationLinks.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    flags: {
        type: Object,
        required: true,
    },
});

const showObjectionModal = ref(false);
const selectedFlag = ref(null);
const objectionForm = useForm({
    objection_text: '',
});

const selectedFlagTitle = computed(() => {
    if (!selectedFlag.value) return 'Session Objection';
    return `Objection - ${selectedFlag.value.subcategory || 'Flag'} (${selectedFlag.value.color || 'none'})`;
});

const openObjectionModal = (flag) => {
    selectedFlag.value = flag;
    objectionForm.objection_text = flag.objection_text || '';
    objectionForm.clearErrors();
    showObjectionModal.value = true;
};

const closeObjectionModal = () => {
    showObjectionModal.value = false;
    selectedFlag.value = null;
    objectionForm.reset();
};

const submitObjection = () => {
    if (!selectedFlag.value) return;

    objectionForm.post(route('tutor.flags.objection.store', selectedFlag.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => closeObjectionModal(),
    });
};
</script>

<template>
    <Head title="My Flags" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">My Flags</h1>
                <p class="text-sm text-slate-500">All flags raised in your reviewed sessions with session details.</p>
            </div>
        </template>

        <DataTable>
            <template #head>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Color</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Details</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Session Link</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Objection</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Screenshot</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                </tr>
            </template>

            <tr v-for="flag in flags.data" :key="flag.id">
                <td class="px-4 py-3 text-sm text-slate-700">
                    <p>{{ flag.review?.session_date || '-' }}</p>
                    <p class="text-xs text-slate-500">{{ flag.review?.slot || '-' }} | {{ flag.review?.group_code || '-' }}</p>
                </td>
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
                        Status:
                        <span
                            class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="flag.objection_status === 'pending'
                                ? 'bg-amber-100 text-amber-700'
                                : (flag.objection_status === 'accepted'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : (flag.objection_status === 'rejected'
                                        ? 'bg-rose-100 text-rose-700'
                                        : 'bg-slate-100 text-slate-700'))"
                        >
                            {{ flag.objection_status || 'none' }}
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Objection closes: {{ flag.objection_deadline_at || '-' }}
                    </p>
                    <p v-if="flag.objection_response" class="mt-1 text-xs text-slate-500">Admin Note: {{ flag.objection_response }}</p>
                    <button
                        type="button"
                        class="mt-2 rounded-lg border px-2 py-1 text-xs font-semibold"
                        :class="flag.objection_closed
                            ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-500'
                            : 'border-[var(--qd-blue-100)] text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]'"
                        :disabled="flag.objection_closed"
                        @click="openObjectionModal(flag)"
                    >
                        {{ flag.objection_closed ? 'Objection Closed' : (flag.objection_status === 'pending' ? 'Edit Objection' : 'Submit Objection') }}
                    </button>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">
                    <a v-if="flag.screenshot_url" :href="flag.screenshot_url" target="_blank" class="font-semibold text-[var(--qd-blue-700)] hover:underline">Open</a>
                    <span v-else>-</span>
                </td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ flag.status }}</td>
            </tr>
        </DataTable>

        <div
            v-if="flags.data.length === 0"
            class="mt-3 rounded-xl border border-dashed border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4 text-sm text-slate-600"
        >
            No flags recorded for your sessions yet.
        </div>

        <PaginationLinks :links="flags.links" />

        <Modal :show="showObjectionModal" @close="closeObjectionModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">{{ selectedFlagTitle }}</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Explain why this flag should be reviewed. Your objection will be sent to the admin team.
                </p>

                <div class="mt-4">
                    <InputLabel for="objection_text" value="Objection Details" />
                    <textarea
                        id="objection_text"
                        v-model="objectionForm.objection_text"
                        rows="6"
                        class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        placeholder="Write a clear objection with session context and evidence."
                    />
                    <InputError class="mt-2" :message="objectionForm.errors.objection_text" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="closeObjectionModal">Cancel</SecondaryButton>
                    <PrimaryButton type="button" :disabled="objectionForm.processing" @click="submitObjection">
                        Submit Objection
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
