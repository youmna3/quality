<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    review: {
        type: Object,
        required: true,
    },
    criteriaGroups: {
        type: Array,
        required: true,
    },
    slotOptions: {
        type: Array,
        required: true,
    },
    pendingEditRequests: {
        type: Array,
        required: true,
    },
    flagsIndexUrl: {
        type: String,
        required: true,
    },
});

const form = useForm({
    tutor_role: props.review.tutor_role || 'main',
    session_date: props.review.session_date || '',
    slot: props.review.slot || '',
    group_code: props.review.group_code || '',
    recorded_link: props.review.recorded_link || '',
    issue_text: props.review.issue_text || '',
    positive_lines: props.review.positive_lines === '-' ? '' : (props.review.positive_lines || ''),
    negative_lines: props.review.negative_lines === '-' ? '' : (props.review.negative_lines || ''),
    criterion_scores: { ...(props.review.criterion_scores || {}) },
});

const submit = () => {
    form.put(route('admin.reports.update', props.review.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Edit Report - ${review.tutor?.tutor_code || review.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Edit Report</h1>
                    <p class="text-sm text-slate-500">
                        Week {{ review.week_number || '-' }} |
                        {{ review.tutor?.tutor_code || '-' }} - {{ review.tutor?.name_en || '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="flagsIndexUrl"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Manage Flags
                    </Link>
                    <Link
                        :href="route('admin.reports.index', { week: review.week_number || 1 })"
                        class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                    >
                        Back to Reports
                    </Link>
                </div>
            </div>
        </template>

        <div class="grid gap-6 xl:grid-cols-[1.6fr,1fr]">
            <div class="space-y-6">
                <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Session Data</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="tutor_role" value="Tutor Role" />
                            <select
                                id="tutor_role"
                                v-model="form.tutor_role"
                                class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                            >
                                <option value="main">Main</option>
                                <option value="cover">Cover</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.tutor_role" />
                        </div>
                        <div>
                            <InputLabel for="session_date" value="Session Date" />
                            <TextInput id="session_date" v-model="form.session_date" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.session_date" />
                        </div>
                        <div>
                            <InputLabel for="slot" value="Slot" />
                            <select
                                id="slot"
                                v-model="form.slot"
                                class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                            >
                                <option v-for="slot in slotOptions" :key="slot" :value="slot">{{ slot }}</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.slot" />
                        </div>
                        <div>
                            <InputLabel for="group_code" value="Group ID" />
                            <TextInput id="group_code" v-model="form.group_code" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.group_code" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="recorded_link" value="Recorded Link" />
                            <TextInput id="recorded_link" v-model="form.recorded_link" type="url" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.recorded_link" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="issue_text" value="Issue Text" />
                            <textarea
                                id="issue_text"
                                v-model="form.issue_text"
                                rows="4"
                                class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                            />
                            <InputError class="mt-2" :message="form.errors.issue_text" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Comments</h2>
                            <p class="mt-1 text-xs text-slate-500">Keep comments in the approved prefixed format so analytics and repeated-comment tracking stay accurate.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 xl:grid-cols-2">
                        <div>
                            <InputLabel for="positive_lines" value="Positive Comments" />
                            <textarea
                                id="positive_lines"
                                v-model="form.positive_lines"
                                rows="14"
                                class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                            />
                            <InputError class="mt-2" :message="form.errors.positive_lines" />
                        </div>
                        <div>
                            <InputLabel for="negative_lines" value="Negative Comments" />
                            <textarea
                                id="negative_lines"
                                v-model="form.negative_lines"
                                rows="14"
                                class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] px-3 py-2 text-sm text-slate-700"
                            />
                            <InputError class="mt-2" :message="form.errors.negative_lines" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Score Control</h2>
                    <p class="mt-1 text-xs text-slate-500">Editing criterion scores recalculates group percentages and the report total score.</p>

                    <div class="mt-4 space-y-4">
                        <div
                            v-for="group in criteriaGroups"
                            :key="group.key"
                            class="rounded-2xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4"
                        >
                            <h3 class="text-sm font-bold uppercase tracking-wide text-[var(--qd-blue-800)]">{{ group.label }}</h3>
                            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <div v-for="criterion in group.criteria" :key="criterion.key">
                                    <InputLabel :for="criterion.key" :value="criterion.label" />
                                    <TextInput
                                        :id="criterion.key"
                                        v-model="form.criterion_scores[criterion.key]"
                                        type="number"
                                        min="0"
                                        max="5"
                                        class="mt-1 block w-full"
                                    />
                                    <InputError class="mt-2" :message="form.errors[`criterion_scores.${criterion.key}`]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div
                    v-if="pendingEditRequests.length > 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm"
                >
                    <h2 class="text-lg font-bold text-amber-900">Pending Edit Requests</h2>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="request in pendingEditRequests"
                            :key="request.id"
                            class="rounded-2xl border border-amber-200 bg-white p-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ request.requester_name }}</p>
                            <p class="mt-2 text-sm text-slate-700">{{ request.message }}</p>
                            <p class="mt-2 text-xs text-slate-500">{{ request.created_at }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Current Flags</h2>
                    <p class="mt-1 text-xs text-slate-500">Removed flags are excluded automatically from tutor reports.</p>
                    <div v-if="review.flags.length > 0" class="mt-4 space-y-3">
                        <div
                            v-for="(flag, index) in review.flags"
                            :key="`${flag.color}-${flag.subcategory}-${index}`"
                            class="rounded-2xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--qd-blue-700)]">{{ flag.color }} | {{ flag.status }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ flag.subcategory }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ flag.reason }}</p>
                            <p class="mt-2 text-xs text-slate-500">Duration: {{ flag.duration_text || '-' }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">No active flags on this report.</p>
                </div>

                <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Report Actions</h2>
                    <p class="mt-1 text-sm text-slate-500">Saving here updates the tutor-facing report and closes pending reviewer edit requests for this review.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <Link
                            :href="route('admin.reports.index', { week: review.week_number || 1 })"
                            class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton type="button" :disabled="form.processing" @click="submit">
                            Save Report
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
