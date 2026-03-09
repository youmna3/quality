<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    assignment: {
        type: Object,
        required: true,
    },
    slotOptions: {
        type: Array,
        required: true,
    },
    criteriaGroups: {
        type: Array,
        required: true,
    },
    flagSubcategories: {
        type: Array,
        required: true,
    },
    groupMappings: {
        type: Array,
        required: true,
    },
    issueCandidates: {
        type: Array,
        required: true,
    },
    previousFlags: {
        type: Array,
        required: true,
    },
    tutorHistory: {
        type: Array,
        required: true,
    },
    negativeHistory: {
        type: Array,
        required: true,
    },
    repeatPolicy: {
        type: Object,
        required: true,
    },
    cycle: {
        type: Object,
        required: false,
        default: () => ({
            starts_at: null,
            deadline_at: null,
        }),
    },
    editState: {
        type: Object,
        required: false,
        default: () => ({
            locked: false,
            cycle_closed: false,
            reviewer_edit_count: 0,
            remaining_reviewer_edits: 2,
            max_reviewer_edits: 2,
            edit_limit_reached: false,
            can_request_admin_edit: false,
            latest_request: null,
        }),
    },
    formDefaults: {
        type: Object,
        required: true,
    },
});

const normalizeText = (value) => String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
const dateToWeekday = (value) => {
    if (!value) return null;

    const date = new Date(`${value}T12:00:00`);
    if (Number.isNaN(date.getTime())) return null;

    return date.getDay();
};

const flatCriteria = computed(() =>
    props.criteriaGroups.flatMap((group) =>
        group.criteria.map((criterion) => ({
            ...criterion,
            group_key: group.key,
            group_label: group.label,
        }))
    )
);

const criterionByKey = computed(() => {
    const map = {};
    flatCriteria.value.forEach((criterion) => {
        map[criterion.key] = criterion;
    });
    return map;
});

const criterionToGroup = computed(() => {
    const map = {};
    flatCriteria.value.forEach((criterion) => {
        map[criterion.key] = criterion.group_key;
    });
    return map;
});

const emptySelections = () => {
    const output = {};
    flatCriteria.value.forEach((criterion) => {
        output[criterion.key] = [];
    });
    return output;
};

const ensureMatrix = (source) => {
    const matrix = emptySelections();
    Object.entries(source ?? {}).forEach(([key, values]) => {
        if (!Object.prototype.hasOwnProperty.call(matrix, key) || !Array.isArray(values)) return;
        matrix[key] = [...new Set(values.filter((v) => String(v || '').trim() !== '').map((v) => String(v).trim()))];
    });
    return matrix;
};

const createEmptyGrouped = () => {
    const grouped = {};
    props.criteriaGroups.forEach((group) => {
        grouped[group.key] = {
            positive: [],
            negative: [],
        };
    });
    return grouped;
};

const createDraftInputs = () => {
    const drafts = {};
    props.criteriaGroups.forEach((group) => {
        drafts[group.key] = {
            positive: '',
            negative: '',
        };
    });
    return drafts;
};

const createDraftCriteria = () => {
    const drafts = {};
    props.criteriaGroups.forEach((group) => {
        const fallback = group.criteria[0]?.key || '';
        drafts[group.key] = {
            positive: fallback,
            negative: fallback,
        };
    });
    return drafts;
};

const createEmptyFlagEntry = () => ({
    type: 'none',
    subcategory: '',
    reason: '',
    duration_text: '',
    screenshot: null,
});

const normalizeFlagEntries = (flags) => {
    if (!Array.isArray(flags) || flags.length === 0) {
        return [createEmptyFlagEntry()];
    }

    const normalized = flags.map((flag) => ({
        type: ['none', 'yellow', 'red', 'both'].includes(String(flag?.type || '').toLowerCase())
            ? String(flag.type).toLowerCase()
            : 'none',
        subcategory: String(flag?.subcategory || ''),
        reason: String(flag?.reason || ''),
        duration_text: String(flag?.duration_text || ''),
        screenshot: null,
    }));

    return normalized.length > 0 ? normalized : [createEmptyFlagEntry()];
};

const buildGroupedFromDefaults = () => {
    const grouped = createEmptyGrouped();
    const positiveDefaults = ensureMatrix(props.formDefaults.positive_comments);
    const negativeDefaults = ensureMatrix(props.formDefaults.negative_comments);

    Object.entries(positiveDefaults).forEach(([criterionKey, comments]) => {
        const groupKey = criterionToGroup.value[criterionKey];
        if (!groupKey) return;
        comments.forEach((comment) => {
            const exists = grouped[groupKey].positive.some(
                (entry) => normalizeText(entry.text) === normalizeText(comment) && entry.criterion_key === criterionKey
            );
            if (!exists) grouped[groupKey].positive.push({ text: comment, criterion_key: criterionKey });
        });
    });

    Object.entries(negativeDefaults).forEach(([criterionKey, comments]) => {
        const groupKey = criterionToGroup.value[criterionKey];
        if (!groupKey) return;
        comments.forEach((comment) => {
            const exists = grouped[groupKey].negative.some(
                (entry) => normalizeText(entry.text) === normalizeText(comment) && entry.criterion_key === criterionKey
            );
            if (!exists) grouped[groupKey].negative.push({ text: comment, criterion_key: criterionKey });
        });
    });

    return grouped;
};

const groupedSelections = ref(buildGroupedFromDefaults());
const draftInputs = ref(createDraftInputs());
const draftCriteria = ref(createDraftCriteria());
const showPreviousFlags = ref(false);
const showTutorHistory = ref(false);
const groupReviewHistory = ref([]);
const groupLookupLoading = ref(false);
let groupLookupTimer = null;

const buildInitialFormState = () => ({
    tutor_role: props.formDefaults.tutor_role ?? 'main',
    session_date: props.formDefaults.session_date ?? '',
    slot: props.formDefaults.slot ?? '',
    group_code: props.formDefaults.group_code ?? '',
    recorded_link: props.formDefaults.recorded_link ?? '',
    issue_text: props.formDefaults.issue_text ?? '',
    flags: normalizeFlagEntries(props.formDefaults.flags),
    positive_comments: ensureMatrix(props.formDefaults.positive_comments),
    negative_comments: ensureMatrix(props.formDefaults.negative_comments),
});

const form = useForm(buildInitialFormState());

const resetReviewerFormState = () => {
    const initialState = buildInitialFormState();

    form.tutor_role = initialState.tutor_role;
    form.session_date = initialState.session_date;
    form.slot = initialState.slot;
    form.group_code = initialState.group_code;
    form.recorded_link = initialState.recorded_link;
    form.issue_text = initialState.issue_text;
    form.flags = initialState.flags;
    form.positive_comments = initialState.positive_comments;
    form.negative_comments = initialState.negative_comments;
    form.clearErrors();

    groupedSelections.value = buildGroupedFromDefaults();
    draftInputs.value = createDraftInputs();
    draftCriteria.value = createDraftCriteria();
    showPreviousFlags.value = false;
    showTutorHistory.value = false;
    groupReviewHistory.value = [];
    groupLookupLoading.value = false;
};

const addFlagEntry = () => {
    form.flags.push(createEmptyFlagEntry());
};

const removeFlagEntry = (index) => {
    if (form.flags.length === 1) {
        form.flags[0] = createEmptyFlagEntry();
        return;
    }

    form.flags.splice(index, 1);
};

const onFlagTypeChange = (index) => {
    const flag = form.flags[index];
    if (!flag) return;

    if (flag.type === 'none') {
        flag.subcategory = '';
        flag.reason = '';
        flag.duration_text = '';
        flag.screenshot = null;
    }
};

const toDateOrNull = (value) => {
    if (!value) return null;
    const date = new Date(String(value));
    return Number.isNaN(date.getTime()) ? null : date;
};

const repeatThresholdDays = computed(() => Number(props.repeatPolicy?.min_days_for_red ?? 3));
const cycleStartDate = computed(() => props.cycle?.starts_at || null);
const cycleDeadlineDate = computed(() => props.cycle?.deadline_at || null);
const isReviewLocked = computed(() => !!props.editState?.locked);
const selectedSessionWeekday = computed(() => dateToWeekday(form.session_date));
const filteredSlotOptions = computed(() => {
    if (!form.session_date) {
        return props.slotOptions;
    }

    if (selectedSessionWeekday.value === 5) {
        return props.slotOptions.filter((slot) => String(slot).startsWith('Fri'));
    }

    if (selectedSessionWeekday.value === 6) {
        return props.slotOptions.filter((slot) => String(slot).startsWith('Sat'));
    }

    return [];
});
const slotValidationHint = computed(() => {
    if (!form.session_date) {
        return 'Select the session date first so the valid slots can be filtered automatically.';
    }

    if (selectedSessionWeekday.value === 5) {
        return 'Friday date selected. Only Friday slots are allowed.';
    }

    if (selectedSessionWeekday.value === 6) {
        return 'Saturday date selected. Only Saturday slots are allowed.';
    }

    return 'Only Friday and Saturday session dates are valid for reviewer slots.';
});

const formatRelativeFlagAge = (createdAt) => {
    const date = toDateOrNull(createdAt);
    if (!date) return '-';

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.max(0, Math.floor(diffMs / (1000 * 60 * 60 * 24)));

    if (diffDays === 0) return 'today';
    if (diffDays === 1) return '1 day ago';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 14) return 'last week';
    return `${Math.floor(diffDays / 7)} weeks ago`;
};

const getRepeatMatches = (flagEntry) => {
    const sessionDate = toDateOrNull(form.session_date ? `${form.session_date}T23:59:59` : null);
    if (!sessionDate || !flagEntry?.subcategory) return [];

    const threshold = repeatThresholdDays.value;
    const normalizedSubcategory = normalizeText(flagEntry.subcategory);

    return props.previousFlags.filter((previousFlag) => {
        if (!previousFlag || previousFlag.status === 'removed') return false;
        if (normalizeText(previousFlag.subcategory) !== normalizedSubcategory) return false;

        const issuedAt = toDateOrNull(previousFlag.created_at);
        if (!issuedAt) return false;

        const diffDays = (sessionDate.getTime() - issuedAt.getTime()) / (1000 * 60 * 60 * 24);
        return diffDays >= threshold;
    });
};

const suggestionBank = (group, side) => {
    const output = [];
    group.criteria.forEach((criterion) => {
        const list = Array.isArray(criterion[side]) ? criterion[side] : [];
        list.forEach((text) => {
            output.push({
                text,
                criterion_key: criterion.key,
            });
        });
    });
    return output;
};

const bankOptionsByGroup = computed(() => {
    const output = {};
    props.criteriaGroups.forEach((group) => {
        output[group.key] = {
            positive: suggestionBank(group, 'positive'),
            negative: suggestionBank(group, 'negative'),
        };
    });
    return output;
});

const commentIdentifier = (criterionKey, text) => `${criterionKey}|${normalizeText(text)}`;

const isCommentSelected = (groupKey, side, item) =>
    groupedSelections.value[groupKey][side].some(
        (entry) => commentIdentifier(entry.criterion_key, entry.text) === commentIdentifier(item.criterion_key, item.text)
    );

const removeCommentByValue = (groupKey, side, text, criterionKey) => {
    groupedSelections.value[groupKey][side] = groupedSelections.value[groupKey][side].filter(
        (entry) => !(entry.criterion_key === criterionKey && normalizeText(entry.text) === normalizeText(text))
    );
};

const toggleBankComment = (groupKey, side, item) => {
    if (isCommentSelected(groupKey, side, item)) {
        removeCommentByValue(groupKey, side, item.text, item.criterion_key);
        return;
    }

    addCommentEntry(groupKey, side, item.text, item.criterion_key);
};

const getCustomEntries = (groupKey, side) => {
    const bankKeys = new Set(
        (bankOptionsByGroup.value[groupKey]?.[side] ?? []).map((entry) => commentIdentifier(entry.criterion_key, entry.text))
    );

    return groupedSelections.value[groupKey][side].filter(
        (entry) => !bankKeys.has(commentIdentifier(entry.criterion_key, entry.text))
    );
};

const getSuggestions = (group, side) => {
    const keyword = normalizeText(draftInputs.value[group.key]?.[side]);
    if (!keyword) return [];

    const selectedKeys = new Set(
        groupedSelections.value[group.key][side].map(
            (entry) => `${entry.criterion_key}|${normalizeText(entry.text)}`
        )
    );

    return suggestionBank(group, side)
        .filter((entry) => normalizeText(entry.text).includes(keyword))
        .filter((entry) => !selectedKeys.has(`${entry.criterion_key}|${normalizeText(entry.text)}`))
        .slice(0, 8);
};

const addCommentEntry = (groupKey, side, text, criterionKey) => {
    const clean = String(text || '').trim();
    if (!clean) return;

    const resolvedCriterionKey =
        criterionKey && criterionByKey.value[criterionKey]
            ? criterionKey
            : draftCriteria.value[groupKey]?.[side];

    if (!resolvedCriterionKey || !criterionByKey.value[resolvedCriterionKey]) return;

    const entries = groupedSelections.value[groupKey][side];
    const exists = entries.some(
        (entry) =>
            entry.criterion_key === resolvedCriterionKey &&
            normalizeText(entry.text) === normalizeText(clean)
    );

    if (!exists) {
        entries.push({
            text: clean,
            criterion_key: resolvedCriterionKey,
        });
    }
};

const addSuggestion = (groupKey, side, suggestion) => {
    addCommentEntry(groupKey, side, suggestion.text, suggestion.criterion_key);
    draftInputs.value[groupKey][side] = '';
};

const addCustomComment = (groupKey, side) => {
    addCommentEntry(
        groupKey,
        side,
        draftInputs.value[groupKey]?.[side] || '',
        draftCriteria.value[groupKey]?.[side] || ''
    );
    draftInputs.value[groupKey][side] = '';
};

const removeComment = (groupKey, side, index) => {
    groupedSelections.value[groupKey][side].splice(index, 1);
};

const toCriterionMatrix = () => {
    const positive = emptySelections();
    const negative = emptySelections();

    Object.entries(groupedSelections.value).forEach(([groupKey, sides]) => {
        ['positive', 'negative'].forEach((side) => {
            const target = side === 'positive' ? positive : negative;
            (sides[side] ?? []).forEach((entry) => {
                const criterionKey = entry.criterion_key;
                if (!criterionKey || !Object.prototype.hasOwnProperty.call(target, criterionKey)) return;
                const cleanText = String(entry.text || '').trim();
                if (!cleanText) return;
                target[criterionKey].push(cleanText);
            });
        });
    });

    Object.keys(positive).forEach((key) => {
        positive[key] = [...new Set(positive[key])];
        negative[key] = [...new Set(negative[key])];
    });

    return { positive, negative };
};

const criterionScores = computed(() => {
    const { negative } = toCriterionMatrix();
    const output = {};
    flatCriteria.value.forEach((criterion) => {
        const negatives = (negative[criterion.key] ?? []).length;
        output[criterion.key] = Math.max(0, 5 - negatives);
    });
    return output;
});

const groupPercentages = computed(() => {
    const output = {};
    props.criteriaGroups.forEach((group) => {
        const total = group.criteria.reduce((sum, criterion) => sum + Number(criterionScores.value[criterion.key] ?? 0), 0);
        const max = group.criteria.length * 5;
        output[group.key] = max > 0 ? ((total / max) * 100).toFixed(1) : '0.0';
    });
    return output;
});

const totalScore = computed(() =>
    Object.values(criterionScores.value).reduce((sum, value) => sum + Number(value), 0)
);

const findIssueCandidate = () => {
    if (!form.slot || !form.session_date) {
        return null;
    }

    return props.issueCandidates.find(
        (row) => row.slot === form.slot && row.session_date === form.session_date
    ) ?? null;
};

const autofillGroupCode = () => {
    if (form.slot) {
        const exactGroup = props.groupMappings.find(
            (row) => row.slot === form.slot && row.session_date === form.session_date
        );
        const fallbackGroup = props.groupMappings.find(
            (row) => row.slot === form.slot && (!row.session_date || row.session_date === '')
        );
        const group = exactGroup ?? fallbackGroup;
        if (group) form.group_code = group.group_code;
    }
};

const refreshIssueText = () => {
    const issue = findIssueCandidate();
    form.issue_text = issue?.issue_text ?? '';
};

const fetchGroupReviewHistory = async (groupCode) => {
    groupLookupLoading.value = true;

    try {
        const response = await window.axios.get(route('reviewer.reviews.group-history', props.assignment.id), {
            params: { group_code: groupCode },
        });
        groupReviewHistory.value = Array.isArray(response?.data?.data) ? response.data.data : [];
    } catch (error) {
        groupReviewHistory.value = [];
    } finally {
        groupLookupLoading.value = false;
    }
};

watch(
    () => [form.slot, form.session_date],
    () => {
        if (form.slot && !filteredSlotOptions.value.includes(form.slot)) {
            form.slot = '';
        }
        autofillGroupCode();
        refreshIssueText();
    }
);

watch(
    () => props.assignment.id,
    () => {
        resetReviewerFormState();
    }
);

watch(
    () => form.group_code,
    (value) => {
        if (groupLookupTimer) {
            clearTimeout(groupLookupTimer);
        }

        const normalizedValue = String(value || '').trim();
        if (normalizedValue === '') {
            groupReviewHistory.value = [];
            groupLookupLoading.value = false;
            return;
        }

        groupLookupTimer = window.setTimeout(() => {
            fetchGroupReviewHistory(normalizedValue);
        }, 300);
    }
);

onBeforeUnmount(() => {
    if (groupLookupTimer) {
        clearTimeout(groupLookupTimer);
    }
});

const submit = () => {
    if (isReviewLocked.value) {
        return;
    }

    const matrix = toCriterionMatrix();
    form.positive_comments = matrix.positive;
    form.negative_comments = matrix.negative;

    form.post(route('reviewer.reviews.store', props.assignment.id), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Start Review" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-[var(--qd-blue-900)]">Start Review Session</h1>
                    <p class="text-sm text-slate-500">Week {{ assignment.week_number }} | Assignment #{{ assignment.id }}</p>
                </div>
                <Link
                    :href="route('reviewer.home', { week: assignment.week_number })"
                    class="inline-flex items-center rounded-xl border border-[var(--qd-blue-100)] bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-[var(--qd-blue-700)] shadow-sm"
                >
                    Back to Dashboard
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <div
                v-if="isReviewLocked"
                class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm"
            >
                <span v-if="editState.edit_limit_reached">
                    Reviewer edit limit reached ({{ editState.max_reviewer_edits }} edits). Use the dashboard request flow for any further correction.
                </span>
                <span v-else>
                    Reviewer editing is closed for this cycle. Use the dashboard request flow if this submitted review still needs an admin correction.
                </span>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide">
                    <span class="rounded-full bg-[var(--qd-sky-50)] px-3 py-1 text-[var(--qd-blue-700)]">
                        Reviewer Edits: {{ editState.reviewer_edit_count }}/{{ editState.max_reviewer_edits }}
                    </span>
                    <span
                        class="rounded-full px-3 py-1"
                        :class="editState.remaining_reviewer_edits > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'"
                    >
                        Remaining: {{ editState.remaining_reviewer_edits }}
                    </span>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <InputLabel value="Tutor ID (Prefilled)" />
                        <TextInput :model-value="assignment.tutor_id" disabled class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Tutor Name" />
                        <TextInput :model-value="assignment.tutor_name" disabled class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Reviewer Name (Prefilled)" />
                        <TextInput :model-value="assignment.reviewer_name" disabled class="mt-1 block w-full" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Session Data</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <InputLabel for="tutor_role" value="Tutor Role" />
                        <select
                            id="tutor_role"
                            v-model="form.tutor_role"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="main">Main</option>
                            <option value="cover">Cover</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.tutor_role" />
                    </div>

                    <div>
                        <InputLabel for="session_date" value="Session Date" />
                        <TextInput
                            id="session_date"
                            v-model="form.session_date"
                            type="date"
                            :min="cycleStartDate || undefined"
                            :max="cycleDeadlineDate || undefined"
                            class="mt-1 block w-full"
                        />
                        <p v-if="cycleStartDate || cycleDeadlineDate" class="mt-2 text-xs text-slate-500">
                            Allowed cycle range:
                            {{ cycleStartDate || '-' }}
                            to
                            {{ cycleDeadlineDate || '-' }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.session_date" />
                    </div>

                    <div>
                        <InputLabel for="slot" value="Slot" />
                        <select
                            id="slot"
                            v-model="form.slot"
                            class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                        >
                            <option value="">Select Slot</option>
                            <option v-for="slot in filteredSlotOptions" :key="slot" :value="slot">{{ slot }}</option>
                        </select>
                        <p class="mt-2 text-xs text-slate-500">{{ slotValidationHint }}</p>
                        <InputError class="mt-2" :message="form.errors.slot" />
                    </div>

                    <div>
                        <InputLabel for="group_code" value="Group ID" />
                        <TextInput id="group_code" v-model="form.group_code" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.group_code" />
                        <p v-if="groupLookupLoading" class="mt-2 text-xs font-semibold text-[var(--qd-blue-700)]">
                            Checking previous QC reviews for this group ID...
                        </p>
                        <div
                            v-else-if="groupReviewHistory.length > 0"
                            class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">
                                This group ID was previously reviewed by another QC
                            </p>
                            <div class="mt-1 space-y-1 text-sm text-amber-800">
                                <p v-for="entry in groupReviewHistory" :key="`group-history-${entry.id}`">
                                    {{ entry.reviewer_name || 'Unknown Reviewer' }} reviewed {{ entry.tutor_code || '-' }}
                                    in Week {{ entry.week_number || '-' }} on {{ entry.session_date || '-' }} ({{ entry.slot || '-' }}).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="recorded_link" value="Recorded Link" />
                        <TextInput id="recorded_link" v-model="form.recorded_link" type="url" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.recorded_link" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="issue_text" value="State the Issue (manual or auto-filled from Session/Student Issue Forms)" />
                    <textarea
                        id="issue_text"
                        v-model="form.issue_text"
                        rows="4"
                        class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                    />
                    <p class="mt-2 text-xs text-slate-500">Auto-fill now uses exact Session Date + Slot only. Changing either field refreshes this box.</p>
                    <InputError class="mt-2" :message="form.errors.issue_text" />
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Previous Flags Detection</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]"
                            @click="showPreviousFlags = !showPreviousFlags"
                        >
                            {{ showPreviousFlags ? 'Hide Previous Flags' : 'Show Previous Flags' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-[var(--qd-blue-100)] px-3 py-1.5 text-xs font-semibold text-[var(--qd-blue-700)] hover:bg-[var(--qd-sky-50)]"
                            @click="showTutorHistory = !showTutorHistory"
                        >
                            {{ showTutorHistory ? 'Hide Tutor History' : 'Show Tutor History' }}
                        </button>
                    </div>
                </div>

                <p class="mt-1 text-xs text-slate-500">
                    System checks repeated issues automatically. If a matching issue was already flagged at least {{ repeatThresholdDays }} days before this session date, RED is recommended.
                </p>

                <div v-if="showPreviousFlags" class="mt-3 space-y-2">
                    <div
                        v-for="flag in previousFlags"
                        :key="`prev-flag-${flag.id}`"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] px-3 py-2"
                    >
                        <p class="text-sm font-semibold text-slate-800">
                            {{ (flag.color || 'none').toUpperCase() }} - {{ flag.subcategory || 'General' }}
                            <span class="text-xs font-medium text-slate-500">({{ formatRelativeFlagAge(flag.created_at) }})</span>
                        </p>
                        <p class="text-xs text-slate-600">
                            Session: {{ flag.session_date || '-' }} | Slot: {{ flag.slot || '-' }} | Status: {{ flag.status || '-' }}
                        </p>
                    </div>
                    <p v-if="previousFlags.length === 0" class="text-sm text-slate-500">No previous flags found for this tutor.</p>
                </div>

                <div v-if="showTutorHistory" class="mt-4 border-t border-[var(--qd-blue-100)] pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Full Tutor Review History</p>
                    <div v-if="tutorHistory.length > 0" class="mt-2 space-y-3">
                        <div
                            v-for="entry in tutorHistory"
                            :key="`tutor-history-${entry.id}`"
                            class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-[var(--qd-blue-800)]">
                                    Week {{ entry.week_number || '-' }} | {{ entry.session_date || '-' }} | {{ entry.slot || '-' }} | Group {{ entry.group_code || '-' }}
                                </p>
                                <p class="text-xs font-bold text-slate-800">Score: {{ entry.score ?? '-' }}</p>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Reviewer: {{ entry.reviewer_name || '-' }} | Submitted: {{ entry.submitted_at || '-' }}</p>
                            <div class="mt-2 grid gap-3 md:grid-cols-2">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Positive Comments</p>
                                    <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-slate-700">
                                        <li v-for="(comment, idx) in entry.positive_comments" :key="`pos-history-${entry.id}-${idx}`">
                                            {{ comment }}
                                        </li>
                                        <li v-if="entry.positive_comments.length === 0" class="list-none pl-0 text-slate-500">No positive comments saved.</li>
                                    </ul>
                                </div>
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Negative Comments</p>
                                    <ul class="mt-1 list-disc space-y-1 pl-5 text-xs text-slate-700">
                                        <li v-for="(comment, idx) in entry.negative_comments" :key="`neg-history-${entry.id}-${idx}`">
                                            {{ comment }}
                                        </li>
                                        <li v-if="entry.negative_comments.length === 0" class="list-none pl-0 text-slate-500">No negative comments saved.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mt-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Flags</p>
                                <div v-if="entry.flags.length > 0" class="mt-1 space-y-1 text-xs text-slate-700">
                                    <p v-for="(flag, idx) in entry.flags" :key="`flag-history-${entry.id}-${idx}`">
                                        {{ (flag.color || 'none').toUpperCase() }} - {{ flag.subcategory || 'General' }}:
                                        {{ flag.reason || '-' }} [{{ flag.duration_text || '-' }}] | Status: {{ flag.status || '-' }}
                                    </p>
                                </div>
                                <p v-else class="mt-1 text-xs text-slate-500">No flags in this review.</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-slate-500">No past review history found for this tutor.</p>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Flags</h2>
                        <p class="text-xs text-slate-500">Add one or more flags per review when needed.</p>
                    </div>
                    <PrimaryButton type="button" class="text-xs" @click="addFlagEntry">Add Another Flag</PrimaryButton>
                </div>

                <InputError class="mt-2" :message="form.errors.flags" />

                <div class="mt-4 space-y-4">
                    <div
                        v-for="(flag, flagIndex) in form.flags"
                        :key="`flag-${flagIndex}`"
                        class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4"
                    >
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-[var(--qd-blue-900)]">Flag {{ flagIndex + 1 }}</p>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                @click="removeFlagEntry(flagIndex)"
                            >
                                Remove
                            </button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel :for="`flags_${flagIndex}_type`" value="Flag Type" />
                                <select
                                    :id="`flags_${flagIndex}_type`"
                                    v-model="flag.type"
                                    class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                                    @change="onFlagTypeChange(flagIndex)"
                                >
                                    <option value="none">None</option>
                                    <option value="yellow">Yellow</option>
                                    <option value="red">Red</option>
                                    <option value="both">Both</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors[`flags.${flagIndex}.type`]" />
                            </div>
                        </div>

                        <div v-if="flag.type !== 'none'" class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <InputLabel :for="`flags_${flagIndex}_subcategory`" value="Flag Subcategory" />
                                <select
                                    :id="`flags_${flagIndex}_subcategory`"
                                    v-model="flag.subcategory"
                                    class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                                >
                                    <option value="">Select Subcategory</option>
                                    <option v-for="subcategory in flagSubcategories" :key="subcategory" :value="subcategory">
                                        {{ subcategory }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors[`flags.${flagIndex}.subcategory`]" />
                            </div>
                            <div>
                                <InputLabel :for="`flags_${flagIndex}_duration_text`" value="Duration (Optional)" />
                                <TextInput :id="`flags_${flagIndex}_duration_text`" v-model="flag.duration_text" class="mt-1 block w-full" />
                                <InputError class="mt-2" :message="form.errors[`flags.${flagIndex}.duration_text`]" />
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel :for="`flags_${flagIndex}_reason`" value="Reason" />
                                <textarea
                                    :id="`flags_${flagIndex}_reason`"
                                    v-model="flag.reason"
                                    rows="3"
                                    class="mt-1 block w-full rounded-xl border border-[var(--qd-blue-100)] bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-[var(--qd-blue-500)] focus:ring-2 focus:ring-[var(--qd-blue-100)]"
                                />
                                <InputError class="mt-2" :message="form.errors[`flags.${flagIndex}.reason`]" />
                            </div>
                            <div
                                v-if="getRepeatMatches(flag).length > 0"
                                class="md:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Repeated Issue Detected</p>
                                <p class="mt-1 text-sm text-amber-700">
                                    {{ getRepeatMatches(flag).length }} previous matching flag(s) found before this session date with the required time gap.
                                    RED flag is recommended.
                                </p>
                                <button
                                    type="button"
                                    class="mt-2 rounded-lg border border-amber-300 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                    @click="flag.type = 'red'"
                                >
                                    Apply RED
                                </button>
                            </div>
                            <div class="md:col-span-2">
                                <InputLabel :for="`flags_${flagIndex}_screenshot`" value="Flag Screenshot (optional)" />
                                <input
                                    :id="`flags_${flagIndex}_screenshot`"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                                    class="mt-1 block w-full text-sm text-slate-700"
                                    @change="flag.screenshot = $event.target.files[0] ?? null"
                                />
                                <InputError class="mt-2" :message="form.errors[`flags.${flagIndex}.screenshot`]" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--qd-blue-100)] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-[var(--qd-blue-900)]">Comments & Score</h2>
                    <div class="rounded-xl bg-[var(--qd-sky-50)] px-3 py-2 text-sm font-semibold text-[var(--qd-blue-700)]">Row Score Preview: {{ totalScore }} / 100</div>
                </div>
                <p class="mt-1 text-xs text-slate-500">Use the 5 main categories only. Type to get hidden-bank suggestions, then add selected comments.</p>
                <InputError class="mt-2" :message="form.errors.positive_comments" />

                <div class="mt-4 space-y-5">
                    <div v-for="group in criteriaGroups" :key="group.key" class="rounded-xl border border-[var(--qd-blue-100)] bg-[var(--qd-sky-50)] p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-base font-bold text-[var(--qd-blue-900)]">{{ group.label }}</h3>
                            <span class="text-sm font-semibold text-[var(--qd-blue-700)]">{{ groupPercentages[group.key] }}%</span>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-xl border border-emerald-100 bg-white p-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">Positive checkboxes (choose at least one per main category)</p>

                                <div class="max-h-72 space-y-2 overflow-auto rounded-xl border border-emerald-100 bg-emerald-50/40 p-2">
                                    <label
                                        v-for="(item, idx) in bankOptionsByGroup[group.key]?.positive ?? []"
                                        :key="`pos-bank-${group.key}-${idx}`"
                                        class="flex items-start gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-700 hover:bg-white"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                            :checked="isCommentSelected(group.key, 'positive', item)"
                                            @change="toggleBankComment(group.key, 'positive', item)"
                                        />
                                        <span>
                                            {{ item.text }}
                                            <span class="text-slate-500">({{ criterionByKey[item.criterion_key]?.label || 'Subcategory' }})</span>
                                        </span>
                                    </label>
                                </div>

                                <div v-if="getCustomEntries(group.key, 'positive').length > 0" class="mt-3 space-y-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Other positive comments</p>
                                    <div
                                        v-for="(entry, idx) in getCustomEntries(group.key, 'positive')"
                                        :key="`pos-custom-${group.key}-${idx}-${entry.text}`"
                                        class="flex items-start justify-between gap-2 rounded-lg bg-emerald-50 px-2 py-1.5 text-xs text-slate-700"
                                    >
                                        <span>
                                            {{ entry.text }}
                                            <span class="text-slate-500">({{ criterionByKey[entry.criterion_key]?.label || 'Subcategory' }})</span>
                                        </span>
                                        <button
                                            type="button"
                                            class="text-rose-600"
                                            @click="removeCommentByValue(group.key, 'positive', entry.text, entry.criterion_key)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-5">
                                    <TextInput
                                        v-model="draftInputs[group.key].positive"
                                        type="text"
                                        placeholder="Other positive comment..."
                                        class="md:col-span-3"
                                    />
                                    <select
                                        v-model="draftCriteria[group.key].positive"
                                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-2 py-2 text-xs text-slate-700 md:col-span-1"
                                    >
                                        <option v-for="criterion in group.criteria" :key="criterion.key" :value="criterion.key">
                                            {{ criterion.label }}
                                        </option>
                                    </select>
                                    <PrimaryButton type="button" class="justify-center md:col-span-1" @click="addCustomComment(group.key, 'positive')">Add</PrimaryButton>
                                </div>

                                <div v-if="getSuggestions(group, 'positive').length > 0" class="mt-2 max-h-40 overflow-auto rounded-xl border border-[var(--qd-blue-100)] bg-white">
                                    <button
                                        v-for="(item, idx) in getSuggestions(group, 'positive')"
                                        :key="`sug-pos-${group.key}-${idx}-${item.text}`"
                                        type="button"
                                        class="block w-full border-b border-[var(--qd-blue-50)] px-3 py-2 text-left text-xs text-slate-700 last:border-b-0 hover:bg-[var(--qd-sky-50)]"
                                        @click="addSuggestion(group.key, 'positive', item)"
                                    >
                                        {{ item.text }}
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-xl border border-rose-100 bg-white p-3">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-rose-700">Negative checkboxes</p>

                                <div class="max-h-72 space-y-2 overflow-auto rounded-xl border border-rose-100 bg-rose-50/40 p-2">
                                    <label
                                        v-for="(item, idx) in bankOptionsByGroup[group.key]?.negative ?? []"
                                        :key="`neg-bank-${group.key}-${idx}`"
                                        class="flex items-start gap-2 rounded-lg px-2 py-1.5 text-xs text-slate-700 hover:bg-white"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                            :checked="isCommentSelected(group.key, 'negative', item)"
                                            @change="toggleBankComment(group.key, 'negative', item)"
                                        />
                                        <span>
                                            {{ item.text }}
                                            <span class="text-slate-500">({{ criterionByKey[item.criterion_key]?.label || 'Subcategory' }})</span>
                                        </span>
                                    </label>
                                </div>

                                <div v-if="getCustomEntries(group.key, 'negative').length > 0" class="mt-3 space-y-2">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Other negative comments</p>
                                    <div
                                        v-for="(entry, idx) in getCustomEntries(group.key, 'negative')"
                                        :key="`neg-custom-${group.key}-${idx}-${entry.text}`"
                                        class="flex items-start justify-between gap-2 rounded-lg bg-rose-50 px-2 py-1.5 text-xs text-slate-700"
                                    >
                                        <span>
                                            {{ entry.text }}
                                            <span class="text-slate-500">({{ criterionByKey[entry.criterion_key]?.label || 'Subcategory' }})</span>
                                        </span>
                                        <button
                                            type="button"
                                            class="text-rose-600"
                                            @click="removeCommentByValue(group.key, 'negative', entry.text, entry.criterion_key)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-5">
                                    <TextInput
                                        v-model="draftInputs[group.key].negative"
                                        type="text"
                                        placeholder="Other negative comment..."
                                        class="md:col-span-3"
                                    />
                                    <select
                                        v-model="draftCriteria[group.key].negative"
                                        class="rounded-xl border border-[var(--qd-blue-100)] bg-white px-2 py-2 text-xs text-slate-700 md:col-span-1"
                                    >
                                        <option v-for="criterion in group.criteria" :key="criterion.key" :value="criterion.key">
                                            {{ criterion.label }}
                                        </option>
                                    </select>
                                    <PrimaryButton type="button" class="justify-center md:col-span-1" @click="addCustomComment(group.key, 'negative')">Add</PrimaryButton>
                                </div>

                                <div v-if="getSuggestions(group, 'negative').length > 0" class="mt-2 max-h-40 overflow-auto rounded-xl border border-[var(--qd-blue-100)] bg-white">
                                    <button
                                        v-for="(item, idx) in getSuggestions(group, 'negative')"
                                        :key="`sug-neg-${group.key}-${idx}-${item.text}`"
                                        type="button"
                                        class="block w-full border-b border-[var(--qd-blue-50)] px-3 py-2 text-left text-xs text-slate-700 last:border-b-0 hover:bg-[var(--qd-sky-50)]"
                                        @click="addSuggestion(group.key, 'negative', item)"
                                    >
                                        {{ item.text }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <SecondaryButton type="button" :disabled="form.processing" @click="$inertia.get(route('reviewer.home', { week: assignment.week_number }))">Cancel</SecondaryButton>
                <PrimaryButton type="button" :disabled="form.processing || isReviewLocked" @click="submit">
                    {{ isReviewLocked ? 'Cycle Closed' : 'Submit Review' }}
                </PrimaryButton>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
