<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X, Plus, Trash2, Building2, Loader2 } from 'lucide-vue-next';
import axios from 'axios';

interface Agency {
    id: number;
    name: string;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', name: string): void;
    (e: 'updated'): void;
}>();

const agencies = ref<Agency[]>([]);
const newName  = ref('');
const loading  = ref(false);
const adding   = ref(false);
const error    = ref('');

async function fetchAgencies() {
    loading.value = true;
    try {
        const res      = await axios.get(route('foreign-agencies.index'));
        agencies.value = res.data;
    } finally {
        loading.value = false;
    }
}

async function addAgency() {
    if (!newName.value.trim()) return;
    error.value  = '';
    adding.value = true;
    try {
        await axios.post(route('foreign-agencies.store'), {
            name: newName.value.trim(),
        });
        newName.value = '';
        await fetchAgencies();
        emit('updated');
    } catch (err: any) {
        error.value = err.response?.data?.errors?.name?.[0] ?? 'Failed to add agency.';
    } finally {
        adding.value = false;
    }
}

async function removeAgency(agency: Agency) {
    if (!confirm(`Remove "${agency.name}"?`)) return;
    try {
        await axios.delete(route('foreign-agencies.destroy', agency.id));
        await fetchAgencies();
        emit('updated');
    } catch {
        // silent
    }
}

function select(agency: Agency) {
    emit('select', agency.name);
    emit('close');
}

onMounted(fetchAgencies);
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @click.self="emit('close')"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md flex flex-col max-h-[85vh] overflow-hidden">

                <!-- Header -->
                <div class="flex items-center gap-3 px-5 py-4 border-b shrink-0">
                    <div class="h-9 w-9 rounded-xl bg-blue-600 flex items-center justify-center shrink-0">
                        <Building2 class="h-4 w-4 text-white" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-sm font-bold leading-none">Manage Agencies</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Add or remove agencies for foreign participants</p>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Add new -->
                <div class="px-5 py-3 border-b shrink-0">
                    <div class="flex gap-2">
                        <Input
                            v-model="newName"
                            placeholder="e.g. TESDA"
                            class="h-9 text-sm flex-1"
                            @keydown.enter="addAgency"
                        />
                        <Button
                            size="sm"
                            class="h-9 bg-blue-600 hover:bg-blue-700 text-white shrink-0"
                            :disabled="adding || !newName.trim()"
                            @click="addAgency"
                        >
                            <Loader2 v-if="adding" class="h-3.5 w-3.5 animate-spin" />
                            <Plus v-else class="h-3.5 w-3.5" />
                            Add
                        </Button>
                    </div>
                    <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
                </div>

                <!-- List -->
                <div class="flex-1 overflow-y-auto px-5 py-3 flex flex-col gap-1">
                    <div v-if="loading" class="flex justify-center py-8">
                        <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                    </div>

                    <div v-else-if="agencies.length === 0" class="text-center py-8 text-xs text-muted-foreground">
                        No agencies yet. Add one above.
                    </div>

                    <div
                        v-for="agency in agencies"
                        :key="agency.id"
                        class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2 hover:bg-muted/40 group transition-colors cursor-pointer"
                        @click="select(agency)"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                            <span class="text-sm truncate">{{ agency.name }}</span>
                        </div>
                        <button
                            class="shrink-0 p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-muted-foreground hover:text-red-600 opacity-0 group-hover:opacity-100 transition-all"
                            title="Remove"
                            @click.stop="removeAgency(agency)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 border-t shrink-0">
                    <Button variant="outline" class="w-full" size="sm" @click="emit('close')">Close</Button>
                </div>

            </div>
        </div>
    </Teleport>
</template>