<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import {
    Calendar,
    Search,
    X,
    FileText,
    UserRound,
    Building2,
    Loader2,
} from 'lucide-vue-next';

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

const selectedDate   = ref('');
const generating     = ref(false);

// Prepared By
const preparedSearch    = ref('');
const preparedResults   = ref<EmployeeResult[]>([]);
const preparedLoading   = ref(false);
const preparedSignatory = ref<Signatory | null>(null);

// Noted By
const notedSearch    = ref('');
const notedResults   = ref<EmployeeResult[]>([]);
const notedLoading   = ref(false);
const notedSignatory = ref<Signatory | null>(null);

// ─── Computed ────────────────────────────────────────────────────────────────

// Generate list of dates between date_start and date_end
const availableDates = computed(() => {
    if (!props.batch?.date_start || !props.batch?.date_end) return [];

    const dates: string[] = [];
    const start = new Date(props.batch.date_start + 'T00:00:00');
    const end   = new Date(props.batch.date_end   + 'T00:00:00');
    const cur   = new Date(start);

    while (cur <= end) {
        dates.push(cur.toISOString().split('T')[0]);
        cur.setDate(cur.getDate() + 1);
    }

    return dates;
});

const canGenerate = computed(() =>
    selectedDate.value &&
    preparedSignatory.value &&
    notedSignatory.value
);

// ─── Reset on open ───────────────────────────────────────────────────────────

watch(() => props.open, (val) => {
    if (val) {
        selectedDate.value      = availableDates.value[0] ?? '';
        preparedSearch.value    = '';
        preparedResults.value   = [];
        preparedSignatory.value = null;
        notedSearch.value       = '';
        notedResults.value      = [];
        notedSignatory.value    = null;
    }
});

// ─── Employee search ─────────────────────────────────────────────────────────

let preparedDebounce: ReturnType<typeof setTimeout>;
let notedDebounce:    ReturnType<typeof setTimeout>;

async function searchEmployees(q: string, role: 'prepared' | 'noted') {
    if (!q.trim()) {
        if (role === 'prepared') preparedResults.value = [];
        else                     notedResults.value    = [];
        return;
    }

    if (role === 'prepared') preparedLoading.value = true;
    else                     notedLoading.value    = true;

    try {
        const res  = await fetch(`/employees/search-signatory?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (role === 'prepared') preparedResults.value = data;
        else                     notedResults.value    = data;
    } finally {
        if (role === 'prepared') preparedLoading.value = false;
        else                     notedLoading.value    = false;
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
    preparedSearch.value    = '';
    preparedResults.value   = [];
}

function selectNoted(emp: EmployeeResult) {
    notedSignatory.value = emp;
    notedSearch.value    = '';
    notedResults.value   = [];
}

function clearPrepared() {
    preparedSignatory.value = null;
    preparedSearch.value    = '';
    preparedResults.value   = [];
}

function clearNoted() {
    notedSignatory.value = null;
    notedSearch.value    = '';
    notedResults.value   = [];
}

// ─── Format helpers ──────────────────────────────────────────────────────────

function formatDateLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-PH', {
        weekday: 'short', month: 'long', day: 'numeric', year: 'numeric',
    });
}

// ─── Generate PDF ────────────────────────────────────────────────────────────

function generate() {
    if (!canGenerate.value || !props.batch) return;

    const params = new URLSearchParams({
        batch_id:               String(props.batch.id),
        date:                   selectedDate.value,
        prepared_name:          preparedSignatory.value!.name,
        prepared_position:      preparedSignatory.value!.position,
        prepared_office:        preparedSignatory.value!.office_division,
        noted_name:             notedSignatory.value!.name,
        noted_position:         notedSignatory.value!.position,
        noted_office:           notedSignatory.value!.office_division,
    });

    window.open(`/attendance/generate?${params.toString()}`, '_blank');
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-lg !rounded-2xl flex flex-col max-h-[90vh] overflow-hidden">

            <DialogHeader class="shrink-0">
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-4 w-4 text-violet-600" />
                    Generate Attendance Sheet
                </DialogTitle>
                <DialogDescription class="text-xs">
                    {{ batch?.batch }} · {{ program?.title }}
                </DialogDescription>
            </DialogHeader>

            <div class="overflow-y-auto flex-1 flex flex-col gap-5 py-2 px-1">

                <!-- ── Step 1: Select Date ─────────────────────────────── -->
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                        <Calendar class="h-3.5 w-3.5 text-blue-500" /> Select Date
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="d in availableDates"
                            :key="d"
                            class="rounded-lg border px-3 py-2 text-xs font-semibold text-left transition-all"
                            :class="selectedDate === d
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'hover:bg-muted border-border text-muted-foreground'"
                            @click="selectedDate = d"
                        >
                            {{ formatDateLabel(d) }}
                        </button>
                    </div>
                </div>

                <!-- ── Step 2: Prepared By ─────────────────────────────── -->
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                        <UserRound class="h-3.5 w-3.5 text-emerald-500" /> Prepared By
                    </p>

                    <!-- Selected signatory chip -->
                    <div v-if="preparedSignatory" class="flex items-start justify-between gap-2 rounded-lg border bg-emerald-50 dark:bg-emerald-950/30 px-3 py-2.5">
                        <div>
                            <p class="text-xs font-bold">{{ preparedSignatory.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ preparedSignatory.position }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ preparedSignatory.office_division }}</p>
                        </div>
                        <button class="text-muted-foreground hover:text-red-500 mt-0.5 shrink-0" @click="clearPrepared">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Search input -->
                    <div v-else class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                        <Input
                            v-model="preparedSearch"
                            placeholder="Search employee name or code…"
                            class="pl-9 h-9 text-sm"
                        />
                        <Loader2 v-if="preparedLoading" class="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-muted-foreground" />

                        <!-- Results dropdown -->
                        <div
                            v-if="preparedResults.length"
                            class="absolute z-20 top-full mt-1 left-0 right-0 rounded-lg border bg-background shadow-lg overflow-hidden"
                        >
                            <button
                                v-for="emp in preparedResults"
                                :key="emp.empcode"
                                class="w-full text-left px-3 py-2 hover:bg-muted transition-colors"
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
                    <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                        <Building2 class="h-3.5 w-3.5 text-violet-500" /> Noted By
                    </p>

                    <div v-if="notedSignatory" class="flex items-start justify-between gap-2 rounded-lg border bg-violet-50 dark:bg-violet-950/30 px-3 py-2.5">
                        <div>
                            <p class="text-xs font-bold">{{ notedSignatory.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ notedSignatory.position }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ notedSignatory.office_division }}</p>
                        </div>
                        <button class="text-muted-foreground hover:text-red-500 mt-0.5 shrink-0" @click="clearNoted">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div v-else class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
                        <Input
                            v-model="notedSearch"
                            placeholder="Search employee name or code…"
                            class="pl-9 h-9 text-sm"
                        />
                        <Loader2 v-if="notedLoading" class="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-muted-foreground" />

                        <div
                            v-if="notedResults.length"
                            class="absolute z-20 top-full mt-1 left-0 right-0 rounded-lg border bg-background shadow-lg overflow-hidden"
                        >
                            <button
                                v-for="emp in notedResults"
                                :key="emp.empcode"
                                class="w-full text-left px-3 py-2 hover:bg-muted transition-colors"
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
            <div class="shrink-0 flex justify-end gap-2 pt-3 border-t mt-1">
                <Button variant="outline" size="sm" @click="emit('update:open', false)">Cancel</Button>
                <Button
                    size="sm"
                    class="bg-violet-600 hover:bg-violet-700 text-white"
                    :disabled="!canGenerate || generating"
                    @click="generate"
                >
                    <FileText class="h-3.5 w-3.5 mr-1.5" />
                    {{ generating ? 'Generating…' : 'Generate PDF' }}
                </Button>
            </div>

        </DialogContent>
    </Dialog>
</template>
