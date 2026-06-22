<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { X, Plus, Trash2, Building2, LoaderCircle } from 'lucide-vue-next';
import axios from 'axios';

interface Sponsor {
    id: number;
    name: string;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', name: string): void;
    (e: 'updated'): void;
}>();

const sponsors   = ref<Sponsor[]>([]);
const loading    = ref(false);
const newName    = ref('');
const adding     = ref(false);
const error      = ref('');
const deletingId = ref<number | null>(null);

const fetchSponsors = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('organizing-sponsors.index'));
        sponsors.value = data;
    } finally {
        loading.value = false;
    }
};

const addSponsor = async () => {
    error.value = '';
    const name = newName.value.trim();
    if (!name) return;

    adding.value = true;
    try {
        const { data } = await axios.post(route('organizing-sponsors.store'), { name });
        sponsors.value = [...sponsors.value, data].sort((a, b) => a.name.localeCompare(b.name));
        newName.value = '';
        emit('updated');
    } catch (err: any) {
        error.value = err.response?.data?.errors?.name?.[0] ?? 'Failed to add sponsor.';
    } finally {
        adding.value = false;
    }
};

const deleteSponsor = async (sponsor: Sponsor) => {
    if (!confirm(`Remove "${sponsor.name}" from the list?`)) return;
    deletingId.value = sponsor.id;
    try {
        await axios.delete(route('organizing-sponsors.destroy', sponsor.id));
        sponsors.value = sponsors.value.filter((s) => s.id !== sponsor.id);
        emit('updated');
    } catch {
        alert('Failed to delete sponsor.');
    } finally {
        deletingId.value = null;
    }
};

const selectSponsor = (name: string) => {
    emit('select', name);
    emit('close');
};

onMounted(fetchSponsors);
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
        <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[80vh]">

            <!-- Header -->
            <div class="flex items-center gap-3 px-5 py-4 border-b shrink-0">
                <div class="flex items-center justify-center h-8 w-8 rounded-xl bg-blue-600">
                    <Building2 class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold leading-none">Organizing Sponsors</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Manage the list of sponsors</p>
                </div>
                <button class="ml-auto text-muted-foreground hover:text-foreground transition-colors" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Add new -->
            <div class="px-5 py-3 border-b shrink-0">
                <div class="flex gap-2">
                    <input
                        v-model="newName"
                        type="text"
                        placeholder="e.g. JICA, World Bank..."
                        class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keydown.enter="addSponsor"
                    />
                    <button
                        :disabled="adding || !newName.trim()"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        @click="addSponsor"
                    >
                        <LoaderCircle v-if="adding" class="h-3.5 w-3.5 animate-spin" />
                        <Plus v-else class="h-3.5 w-3.5" />
                        Add
                    </button>
                </div>
                <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto px-5 py-3">
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <LoaderCircle class="h-5 w-5 animate-spin text-blue-500" />
                </div>
                <div v-else-if="!sponsors.length" class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground gap-2">
                    <Building2 class="h-8 w-8 text-slate-300" />
                    <p class="text-xs font-semibold">No sponsors yet.</p>
                    <p class="text-xs">Add one above to get started.</p>
                </div>
                <div v-else class="divide-y rounded-xl border overflow-hidden">
                    <div
                        v-for="sponsor in sponsors"
                        :key="sponsor.id"
                        class="flex items-center justify-between px-3 py-2.5 hover:bg-muted/40 transition-colors group"
                    >
                        <button
                            type="button"
                            class="text-sm font-semibold text-left flex-1 hover:text-blue-600 transition-colors"
                            @click="selectSponsor(sponsor.name)"
                        >
                            {{ sponsor.name }}
                        </button>
                        <button
                            type="button"
                            :disabled="deletingId === sponsor.id"
                            class="text-muted-foreground hover:text-red-500 transition-colors p-1 rounded-md opacity-0 group-hover:opacity-100"
                            @click="deleteSponsor(sponsor)"
                        >
                            <LoaderCircle v-if="deletingId === sponsor.id" class="h-3.5 w-3.5 animate-spin" />
                            <Trash2 v-else class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3 border-t shrink-0 text-right">
                <p class="text-xs text-muted-foreground">Click a sponsor name to select it.</p>
            </div>

        </div>
    </div>
</template>