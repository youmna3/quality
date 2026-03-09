<script setup>
import { reactive, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['apply', 'reset']);

const state = reactive({
    search: props.filters.search ?? '',
    project_id: props.filters.project_id ?? '',
    is_active: props.filters.is_active === null || props.filters.is_active === undefined
        ? ''
        : String(Number(Boolean(props.filters.is_active))),
});

watch(
    () => props.filters,
    (newFilters) => {
        state.search = newFilters.search ?? '';
        state.project_id = newFilters.project_id ?? '';
        state.is_active = newFilters.is_active === null || newFilters.is_active === undefined
            ? ''
            : String(Number(Boolean(newFilters.is_active)));
    },
    { deep: true }
);

const apply = () => {
    emit('apply', {
        search: state.search,
        project_id: state.project_id || null,
        is_active: state.is_active === '' ? null : Number(state.is_active),
    });
};

const reset = () => {
    state.search = '';
    state.project_id = '';
    state.is_active = '';
    emit('reset');
};
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <input
                v-model="state.search"
                type="text"
                placeholder="Search by tutor code"
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            <select
                v-model="state.project_id"
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">All Projects</option>
                <option v-for="project in projects" :key="project.id" :value="project.id">
                    {{ project.code }} - {{ project.name }}
                </option>
            </select>

            <select
                v-model="state.is_active"
                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option value="">All Statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>

            <div class="flex items-center gap-2">
                <PrimaryButton type="button" @click="apply">Apply</PrimaryButton>
                <SecondaryButton type="button" @click="reset">Reset</SecondaryButton>
            </div>
        </div>
    </div>
</template>
