<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import axios from 'axios';
import { Building2, Check, LoaderCircle, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Sponsor {
    id: number;
    name: string;
    full_name: string | null;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', name: string): void;
    (e: 'updated'): void;
}>();

const { confirmDialog } = useConfirm();

const sponsors = ref<Sponsor[]>([]);
const loading = ref(false);
const newName = ref('');
const newFullName = ref('');
const adding = ref(false);
const error = ref('');
const deletingId = ref<number | null>(null);

const editingId = ref<number | null>(null);
const editFullName = ref('');
const savingId = ref<number | null>(null);

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
        const { data } = await axios.post(route('organizing-sponsors.store'), {
            name,
            full_name: newFullName.value.trim() || null,
        });
        sponsors.value = [...sponsors.value, data].sort((a, b) => a.name.localeCompare(b.name));
        newName.value = '';
        newFullName.value = '';
        emit('updated');
    } catch (err: any) {
        error.value = err.response?.data?.errors?.name?.[0] ?? 'Failed to add sponsor.';
    } finally {
        adding.value = false;
    }
};

const deleteSponsor = async (sponsor: Sponsor) => {
    if (!(await confirmDialog(`Remove "${sponsor.name}" from the list?`))) return;
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

const startEditFullName = (sponsor: Sponsor) => {
    editingId.value = sponsor.id;
    editFullName.value = sponsor.full_name ?? '';
};

const cancelEditFullName = () => {
    editingId.value = null;
    editFullName.value = '';
};

const saveFullName = async (sponsor: Sponsor) => {
    savingId.value = sponsor.id;
    try {
        const { data } = await axios.put(route('organizing-sponsors.update', sponsor.id), {
            full_name: editFullName.value.trim() || null,
        });
        sponsors.value = sponsors.value.map((s) => (s.id === sponsor.id ? data : s));
        emit('updated');
        editingId.value = null;
    } catch {
        alert('Failed to update full name.');
    } finally {
        savingId.value = null;
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
        <div class="flex max-h-[80vh] w-full max-w-md flex-col rounded-2xl bg-background shadow-2xl">
            <!-- Header -->
            <div class="flex shrink-0 items-center gap-3 border-b px-5 py-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-600">
                    <Building2 class="h-4 w-4 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-extrabold leading-none">Organizing Sponsors</h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">Manage the list of sponsors</p>
                </div>
                <button class="ml-auto text-muted-foreground transition-colors hover:text-foreground" @click="emit('close')">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Add new -->
            <div class="shrink-0 border-b px-5 py-3">
                <div class="flex flex-col gap-2">
                    <input
                        v-model="newName"
                        type="text"
                        placeholder="Abbreviation, e.g. JICA"
                        class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keydown.enter="addSponsor"
                    />
                    <input
                        v-model="newFullName"
                        type="text"
                        placeholder="Full name, e.g. Japan International Cooperation Agency (optional)"
                        class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keydown.enter="addSponsor"
                    />
                    <button
                        :disabled="adding || !newName.trim()"
                        class="flex items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="addSponsor"
                    >
                        <LoaderCircle v-if="adding" class="h-3.5 w-3.5 animate-spin" />
                        <Plus v-else class="h-3.5 w-3.5" />
                        Add Sponsor
                    </button>
                </div>
                <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto px-5 py-3">
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <LoaderCircle class="h-5 w-5 animate-spin text-blue-500" />
                </div>
                <div v-else-if="!sponsors.length" class="flex flex-col items-center justify-center gap-2 py-10 text-center text-muted-foreground">
                    <Building2 class="h-8 w-8 text-slate-300" />
                    <p class="text-xs font-semibold">No sponsors yet.</p>
                    <p class="text-xs">Add one above to get started.</p>
                </div>
                <div v-else class="divide-y overflow-hidden rounded-xl border">
                    <div v-for="sponsor in sponsors" :key="sponsor.id" class="group px-3 py-2.5 transition-colors hover:bg-muted/40">
                        <div class="flex items-center justify-between gap-2">
                            <button
                                type="button"
                                class="flex-1 text-left text-sm font-semibold transition-colors hover:text-blue-600"
                                @click="selectSponsor(sponsor.name)"
                            >
                                {{ sponsor.name }}
                            </button>
                            <button
                                v-if="editingId !== sponsor.id"
                                type="button"
                                class="rounded-md p-1 text-muted-foreground opacity-0 transition-colors hover:text-blue-600 group-hover:opacity-100"
                                title="Edit full name"
                                @click="startEditFullName(sponsor)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                :disabled="deletingId === sponsor.id"
                                class="rounded-md p-1 text-muted-foreground opacity-0 transition-colors hover:text-red-500 group-hover:opacity-100"
                                @click="deleteSponsor(sponsor)"
                            >
                                <LoaderCircle v-if="deletingId === sponsor.id" class="h-3.5 w-3.5 animate-spin" />
                                <Trash2 v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Full name: display -->
                        <p v-if="editingId !== sponsor.id" class="mt-0.5 text-xs text-muted-foreground" :class="{ italic: !sponsor.full_name }">
                            {{ sponsor.full_name ?? 'No full name set' }}
                        </p>

                        <!-- Full name: inline edit -->
                        <div v-else class="mt-1.5 flex items-center gap-1.5">
                            <input
                                v-model="editFullName"
                                type="text"
                                placeholder="Full name"
                                class="flex-1 rounded-lg border px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                                @keydown.enter="saveFullName(sponsor)"
                                @keydown.escape="cancelEditFullName"
                            />
                            <button
                                type="button"
                                :disabled="savingId === sponsor.id"
                                class="rounded-md p-1 text-emerald-600 transition-colors hover:text-emerald-700"
                                title="Save"
                                @click="saveFullName(sponsor)"
                            >
                                <LoaderCircle v-if="savingId === sponsor.id" class="h-3.5 w-3.5 animate-spin" />
                                <Check v-else class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                class="rounded-md p-1 text-muted-foreground transition-colors hover:text-foreground"
                                title="Cancel"
                                @click="cancelEditFullName"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t px-5 py-3 text-right">
                <p class="text-xs text-muted-foreground">Click a sponsor name to select it.</p>
            </div>
        </div>
    </div>
</template>
