<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import axios from 'axios';
import { Building2, Loader2, Plus, Trash2, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Agency {
    id: number;
    name: string;
}

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select', name: string): void;
    (e: 'updated'): void;
}>();

const { confirmDialog } = useConfirm();
const agencies = ref<Agency[]>([]);
const newName = ref('');
const loading = ref(false);
const adding = ref(false);
const error = ref('');

async function fetchAgencies() {
    loading.value = true;
    try {
        const res = await axios.get(route('foreign-agencies.index'));
        agencies.value = res.data;
    } finally {
        loading.value = false;
    }
}

async function addAgency() {
    if (!newName.value.trim()) return;
    error.value = '';
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
    if (!(await confirmDialog(`Remove "${agency.name}"?`))) return;
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
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
            <div class="flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-2xl bg-background shadow-2xl">
                <!-- Header -->
                <div class="flex shrink-0 items-center gap-3 border-b px-5 py-4">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-600">
                        <Building2 class="h-4 w-4 text-white" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-sm font-bold leading-none">Manage Agencies</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Add or remove agencies for foreign participants</p>
                    </div>
                    <button class="text-muted-foreground hover:text-foreground" @click="emit('close')">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Add new -->
                <div class="shrink-0 border-b px-5 py-3">
                    <div class="flex gap-2">
                        <Input v-model="newName" placeholder="e.g. TESDA" class="h-9 flex-1 text-sm" @keydown.enter="addAgency" />
                        <Button
                            size="sm"
                            class="h-9 shrink-0 bg-blue-600 text-white hover:bg-blue-700"
                            :disabled="adding || !newName.trim()"
                            @click="addAgency"
                        >
                            <Loader2 v-if="adding" class="h-3.5 w-3.5 animate-spin" />
                            <Plus v-else class="h-3.5 w-3.5" />
                            Add
                        </Button>
                    </div>
                    <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
                </div>

                <!-- List -->
                <div class="flex flex-1 flex-col gap-1 overflow-y-auto px-5 py-3">
                    <div v-if="loading" class="flex justify-center py-8">
                        <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                    </div>

                    <div v-else-if="agencies.length === 0" class="py-8 text-center text-xs text-muted-foreground">
                        No agencies yet. Add one above.
                    </div>

                    <div
                        v-for="agency in agencies"
                        :key="agency.id"
                        class="group flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 transition-colors hover:bg-muted/40"
                        @click="select(agency)"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                            <span class="truncate text-sm">{{ agency.name }}</span>
                        </div>
                        <button
                            class="shrink-0 rounded p-1 text-muted-foreground opacity-0 transition-all hover:bg-red-100 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-900/30"
                            title="Remove"
                            @click.stop="removeAgency(agency)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="shrink-0 border-t px-5 py-3">
                    <Button variant="outline" class="w-full" size="sm" @click="emit('close')">Close</Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
