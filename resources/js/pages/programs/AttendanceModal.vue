<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Building2, Calendar, FileText, Loader2, Search, UserRound, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Batch {
    id: number;
    batch: string;
    date_start: string;
    date_end: string;
    venue: string | null;
    program_code: string;
    participants?: any[];
}

interface Program {
    title: string;
}

interface EmployeeResult {
    empcode: string;
    name: string;
    position: string;
    office_division: string;
}

interface Signatory {
    empcode: string;
    name: string;
    position: string;
    office_division: string;
}

const props = defineProps<{
    open: boolean;
    batch: Batch | null;
    program: Program | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

// ─── State ───────────────────────────────────────────────────────────────────

const selectedDate = ref('');
const generating = ref(false);

// Prepared By
const preparedSearch = ref('');
const preparedResults = ref<EmployeeResult[]>([]);
const preparedLoading = ref(false);
const preparedSignatory = ref<Signatory | null>(null);

// Noted By
const notedSearch = ref('');
const notedResults = ref<EmployeeResult[]>([]);
const notedLoading = ref(false);
const notedSignatory = ref<Signatory | null>(null);

// ─── Computed ────────────────────────────────────────────────────────────────

// Generate list of dates between date_start and date_end
const availableDates = computed(() => {
    if (!props.batch?.date_start || !props.batch?.date_end) return [];

    const dates: string[] = [];
    const start = new Date(props.batch.date_start + 'T00:00:00');
    const end = new Date(props.batch.date_end + 'T00:00:00');
    const cur = new Date(start);

    while (cur <= end) {
        dates.push(cur.toISOString().split('T')[0]);
        cur.setDate(cur.getDate() + 1);
    }

    return dates;
});

const canGenerate = computed(() => selectedDate.value && preparedSignatory.value && notedSignatory.value);

// ─── Reset on open ───────────────────────────────────────────────────────────

watch(
    () => props.open,
    (val) => {
        if (val) {
            selectedDate.value = availableDates.value[0] ?? '';
            preparedSearch.value = '';
            preparedResults.value = [];
            preparedSignatory.value = null;
            notedSearch.value = '';
            notedResults.value = [];
            notedSignatory.value = null;
        }
    },
);

// ─── Employee search ─────────────────────────────────────────────────────────

let preparedDebounce: ReturnType<typeof setTimeout>;
let notedDebounce: ReturnType<typeof setTimeout>;

async function searchEmployees(q: string, role: 'prepared' | 'noted') {
    if (!q.trim()) {
        if (role === 'prepared') preparedResults.value = [];
        else notedResults.value = [];
        return;
    }

    if (role === 'prepared') preparedLoading.value = true;
    else notedLoading.value = true;

    try {
        const res = await fetch(`/employees/search-signatory?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (role === 'prepared') preparedResults.value = data;
        else notedResults.value = data;
    } finally {
        if (role === 'prepared') preparedLoading.value = false;
        else notedLoading.value = false;
    }
}

watch(preparedSearch, (val) => {
    clearTimeout(preparedDebounce);
    preparedDebounce = setTimeout(() => searchEmployees(val, 'prepared'), 300);
});

watch(notedSearch, (val) => {
    clearTimeout(notedDebounce);
    notedDebounce = setTimeout(() => searchEmployees(val, 'noted'), 300);
});

function selectPrepared(emp: EmployeeResult) {
    preparedSignatory.value = emp;
    preparedSearch.value = '';
    preparedResults.value = [];
}

function selectNoted(emp: EmployeeResult) {
    notedSignatory.value = emp;
    notedSearch.value = '';
    notedResults.value = [];
}

function clearPrepared() {
    preparedSignatory.value = null;
    preparedSearch.value = '';
    preparedResults.value = [];
}

function clearNoted() {
    notedSignatory.value = null;
    notedSearch.value = '';
    notedResults.value = [];
}

// ─── Format helpers ──────────────────────────────────────────────────────────

function formatDateLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', {
        weekday: 'short',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

// ─── Generate PDF ────────────────────────────────────────────────────────────

function generate() {
    if (!canGenerate.value || !props.batch) return;

    const params = new URLSearchParams({
        batch_id: String(props.batch.id),
        date: selectedDate.value,
        prepared_name: preparedSignatory.value!.name,
        prepared_position: preparedSignatory.value!.position,
        prepared_office: preparedSignatory.value!.office_division,
        noted_name: notedSignatory.value!.name,
        noted_position: notedSignatory.value!.position,
        noted_office: notedSignatory.value!.office_division,
    });

    window.open(`/attendance/generate?${params.toString()}`, '_blank');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="flex max-h-[90vh] max-w-lg flex-col overflow-hidden !rounded-2xl">
            <DialogHeader class="shrink-0">
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-4 w-4 text-violet-600" />
                    Generate Attendance Sheet
                </DialogTitle>
                <DialogDescription class="text-xs"> {{ batch?.batch }} · {{ program?.title }} </DialogDescription>
            </DialogHeader>

            <div class="flex flex-1 flex-col gap-5 overflow-y-auto px-1 py-2">
                <!-- ── Step 1: Select Date ─────────────────────────────── -->
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-muted-foreground">
                        <Calendar class="h-3.5 w-3.5 text-blue-500" /> Select Date
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="d in availableDates"
                            :key="d"
                            class="rounded-lg border px-3 py-2 text-left text-xs font-semibold transition-all"
                            :class="
                                selectedDate === d ? 'border-blue-600 bg-blue-600 text-white' : 'border-border text-muted-foreground hover:bg-muted'
                            "
                            @click="selectedDate = d"
                        >
                            {{ formatDateLabel(d) }}
                        </button>
                    </div>
                </div>

                <!-- ── Step 2: Prepared By ─────────────────────────────── -->
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-muted-foreground">
                        <UserRound class="h-3.5 w-3.5 text-emerald-500" /> Prepared By
                    </p>

                    <!-- Selected signatory chip -->
                    <div
                        v-if="preparedSignatory"
                        class="flex items-start justify-between gap-2 rounded-lg border bg-emerald-50 px-3 py-2.5 dark:bg-emerald-950/30"
                    >
                        <div>
                            <p class="text-xs font-bold">{{ preparedSignatory.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ preparedSignatory.position }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ preparedSignatory.office_division }}</p>
                        </div>
                        <button class="mt-0.5 shrink-0 text-muted-foreground hover:text-red-500" @click="clearPrepared">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Search input -->
                    <div v-else class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="preparedSearch" placeholder="Search employee name or code…" class="h-9 pl-9 text-sm" />
                        <Loader2
                            v-if="preparedLoading"
                            class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-muted-foreground"
                        />

                        <!-- Results dropdown -->
                        <div
                            v-if="preparedResults.length"
                            class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-lg border bg-background shadow-lg"
                        >
                            <button
                                v-for="emp in preparedResults"
                                :key="emp.empcode"
                                class="w-full px-3 py-2 text-left transition-colors hover:bg-muted"
                                @click="selectPrepared(emp)"
                            >
                                <p class="text-xs font-semibold">{{ emp.name }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ emp.position }} · {{ emp.office_division }}</p>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Step 3: Noted By ────────────────────────────────── -->
                <div class="flex flex-col gap-2">
                    <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-muted-foreground">
                        <Building2 class="h-3.5 w-3.5 text-violet-500" /> Noted By
                    </p>

                    <div
                        v-if="notedSignatory"
                        class="flex items-start justify-between gap-2 rounded-lg border bg-violet-50 px-3 py-2.5 dark:bg-violet-950/30"
                    >
                        <div>
                            <p class="text-xs font-bold">{{ notedSignatory.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ notedSignatory.position }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ notedSignatory.office_division }}</p>
                        </div>
                        <button class="mt-0.5 shrink-0 text-muted-foreground hover:text-red-500" @click="clearNoted">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div v-else class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="notedSearch" placeholder="Search employee name or code…" class="h-9 pl-9 text-sm" />
                        <Loader2
                            v-if="notedLoading"
                            class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-muted-foreground"
                        />

                        <div
                            v-if="notedResults.length"
                            class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-lg border bg-background shadow-lg"
                        >
                            <button
                                v-for="emp in notedResults"
                                :key="emp.empcode"
                                class="w-full px-3 py-2 text-left transition-colors hover:bg-muted"
                                @click="selectNoted(emp)"
                            >
                                <p class="text-xs font-semibold">{{ emp.name }}</p>
                                <p class="text-[11px] text-muted-foreground">{{ emp.position }} · {{ emp.office_division }}</p>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-1 flex shrink-0 justify-end gap-2 border-t pt-3">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">Cancel</Button>
                <Button size="sm" class="bg-violet-600 text-white hover:bg-violet-700" :disabled="!canGenerate || generating" @click="generate">
                    <FileText class="mr-1.5 h-3.5 w-3.5" />
                    {{ generating ? 'Generating…' : 'Generate PDF' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
