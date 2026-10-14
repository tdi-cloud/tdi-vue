<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    modelValue: { type: Object, default: null }, // { empcode, name, position, office }
    defaultSignatory: { type: Object, default: null }, // shown when nothing is picked; can be restored
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const open = ref(false);
const searching = ref(false);
let timer = null;

const shown = computed(() => props.modelValue ?? props.defaultSignatory);
const isDefault = computed(() => !props.modelValue && !!props.defaultSignatory);

watch(query, (val) => {
    clearTimeout(timer);
    if (val.trim().length < 2) {
        results.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(runSearch, 300);
});

async function runSearch() {
    loading.value = true;
    open.value = true;
    try {
        const res = await fetch(route('tna.fasd.search') + '?q=' + encodeURIComponent(query.value), { headers: { Accept: 'application/json' } });
        results.value = res.ok ? await res.json() : [];
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
}

function pick(s) {
    emit('update:modelValue', s);
    query.value = '';
    results.value = [];
    open.value = false;
    searching.value = false;
}

// Editing the position turns the shown signatory (even the default) into an explicit pick
function updatePosition(event) {
    emit('update:modelValue', { ...shown.value, position: event.target.value });
}

function change() {
    searching.value = true;
}

function useDefault() {
    emit('update:modelValue', null);
    query.value = '';
    results.value = [];
    open.value = false;
    searching.value = false;
}
</script>

<template>
    <div>
        <h2 class="text-lg font-bold text-gray-900">{{ title }}</h2>
        <p v-if="description" class="mb-4 text-sm text-gray-500">{{ description }}</p>

        <!-- Selected chip -->
        <div v-if="shown && !searching" class="flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-green-700">
                    Selected{{ isDefault ? ' (default)' : '' }}
                </p>
                <p class="text-sm font-semibold text-gray-900">{{ shown.name }}</p>
                <p class="text-xs text-gray-500">{{ shown.position }}<span v-if="shown.office"> · {{ shown.office }}</span></p>
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <button
                    v-if="defaultSignatory && !isDefault"
                    type="button"
                    class="text-xs font-semibold text-gray-600 hover:underline"
                    @click="useDefault"
                >
                    Use default
                </button>
                <button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="change">Change</button>
            </div>
        </div>

        <div v-if="shown && !searching" class="mt-3 max-w-xl">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Position (as shown on the TNA Result)</label>
            <input
                :value="shown.position"
                type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                @input="updatePosition"
            />
        </div>

        <template v-else>
            <div class="relative max-w-xl">
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search by name or empcode…"
                    autocomplete="off"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                    @focus="results.length && (open = true)"
                />

                <!-- Dropdown -->
                <div
                    v-if="open && (loading || results.length)"
                    class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                >
                    <div v-if="loading" class="px-4 py-3 text-sm text-gray-400">Searching…</div>
                    <button
                        v-for="s in results"
                        :key="s.empcode"
                        type="button"
                        class="flex w-full flex-col items-start border-b border-gray-50 px-4 py-2.5 text-left last:border-0 hover:bg-blue-50"
                        @click="pick(s)"
                    >
                        <span class="text-sm font-semibold text-gray-900">{{ s.name }}</span>
                        <span class="text-xs text-gray-500">{{ s.position }} · {{ s.office }}</span>
                    </button>
                </div>
                <div
                    v-else-if="open && !loading && query.length >= 2"
                    class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-400 shadow-lg"
                >
                    No matching results found.
                </div>
            </div>
            <button v-if="searching && shown" type="button" class="mt-2 text-xs font-semibold text-gray-600 hover:underline" @click="searching = false">
                Cancel
            </button>
            <p v-else-if="!shown" class="mt-2 text-xs text-gray-500">No signatory selected yet.</p>
        </template>

        <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
