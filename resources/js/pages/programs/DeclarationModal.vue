<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Search, LoaderCircle, FileBadge2, AlertTriangle, Download, CheckCircle2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';

interface SignatoryOption {
    empcode: string;
    name: string;
    position: string;
}

const props = defineProps<{
    open: boolean;
    batch: any | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const completedCount = computed(() =>
    (props.batch?.participants ?? []).filter((p: any) => p.attendance === 'Complete').length
);

const query = ref('');
const results = ref<SignatoryOption[]>([]);
const searching = ref(false);
const showDropdown = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const selected = ref<SignatoryOption | null>(null);
const generating = ref(false);

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        results.value = [];
        selected.value = null;
        showDropdown.value = false;
        generating.value = false;
    }
});

watch(query, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);

    if (!val || val.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }

    debounceTimer = setTimeout(async () => {
        searching.value = true;
        try {
            const url = route('declarations.signatories.search', { q: val });
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            results.value = await res.json();
            showDropdown.value = true;
        } catch (e) {
            results.value = [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

const selectSignatory = (s: SignatoryOption) => {
    selected.value = s;
    query.value = '';
    results.value = [];
    showDropdown.value = false;
};

const generate = () => {
    if (!props.batch || !selected.value) return;

    generating.value = true;
    const url = route('batches.declaration', props.batch.id)
        + `?signatory_empcode=${encodeURIComponent(selected.value.empcode)}`;
    window.open(url, '_blank');

    setTimeout(() => { generating.value = false; }, 1200);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md !rounded-2xl">

            <DialogHeader>
                <DialogTitle>
                    <span class="flex gap-2 items-center">
                        <FileBadge2 class="h-5 w-5 text-blue-600" /> Generate Declaration of Completers
                    </span>
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    {{ batch?.batch }}
                </DialogDescription>
            </DialogHeader>

            <!-- Wala pang Complete -->
            <div v-if="completedCount === 0" class="flex flex-col items-center text-center gap-2 py-6">
                <AlertTriangle class="h-9 w-9 text-amber-500" />
                <p class="text-sm font-bold text-slate-600">Cannot generate yet</p>
                <p class="text-xs text-slate-400 max-w-xs">
                    No participants have "Complete" attendance for this batch yet.
                    Set at least one participant's attendance in the Participants tab
                    before generating the Declaration of Completers.
                </p>
                <Button variant="outline" size="sm" class="mt-2" @click="emit('update:open', false)">Close</Button>
            </div>

            <!-- May Complete na -->
            <div v-else class="flex flex-col gap-3">
                <p class="text-xs text-slate-500 flex items-center gap-1">
                    <CheckCircle2 class="h-3.5 w-3.5 text-emerald-600" />
                    {{ completedCount }} participant(s) marked "Complete" will be included in the list.
                </p>

                <div class="grid gap-1">
                    <label class="text-xs font-bold">Signatory <span class="text-red-500">*</span></label>

                    <div v-if="!selected" class="relative">
                        <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                        <Input
                            v-model="query"
                            class="text-xs h-8 pl-7"
                            placeholder="Search employee to sign (name or empcode)..."
                        />
                        <LoaderCircle
                            v-if="searching"
                            class="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-muted-foreground"
                        />

                        <div
                            v-if="showDropdown && results.length"
                            class="absolute z-50 mt-1 w-full rounded-lg border bg-popover shadow-md max-h-48 overflow-y-auto"
                        >
                            <button
                                v-for="emp in results"
                                :key="emp.empcode"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-accent"
                                @click="selectSignatory(emp)"
                            >
                                <span>
                                    <span class="font-semibold block">{{ emp.name }}</span>
                                    <span class="text-muted-foreground">{{ emp.position }}</span>
                                </span>
                                <span class="text-muted-foreground">{{ emp.empcode }}</span>
                            </button>
                        </div>

                        <div
                            v-else-if="showDropdown && !searching && query.length >= 2"
                            class="absolute z-50 mt-1 w-full rounded-lg border bg-popover shadow-md px-3 py-2 text-xs text-muted-foreground"
                        >
                            No employees found.
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-between rounded-lg border px-3 py-2">
                        <div>
                            <p class="text-xs font-bold">{{ selected.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ selected.position }} · {{ selected.empcode }}</p>
                        </div>
                        <Badge variant="secondary" class="cursor-pointer text-[10px]" @click="selected = null">
                            Change
                        </Badge>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t mt-1">
                    <Button variant="outline" size="sm" @click="emit('update:open', false)">Cancel</Button>
                    <Button
                        size="sm"
                        class="bg-blue-600 hover:bg-blue-700 dark:text-white"
                        :disabled="!selected || generating"
                        @click="generate"
                    >
                        <LoaderCircle v-if="generating" class="h-3 w-3 animate-spin mr-1" />
                        <Download v-else class="h-3.5 w-3.5" />
                        Generate PDF
                    </Button>
                </div>
            </div>

        </DialogContent>
    </Dialog>
</template>     