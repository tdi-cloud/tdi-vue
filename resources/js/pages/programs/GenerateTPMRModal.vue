<script setup lang="ts">
/**
 * GenerateTPMRModal.vue
 *
 * Usage (sa parent page, hal. Programs/Index.vue):
 *
 *   <Button @click="showTPMR = true">Generate TPMR</Button>
 *   <GenerateTPMRModal v-model="showTPMR" />
 *
 * Note: gumagamit ito ng route('reports.tpmr') at route('employees.search')
 * helpers (Ziggy). Siguraduhing naka-register ang mga routes (see routes_snippet.php).
 */
import { Button } from '@/components/ui/button';
import { X, Search, ChevronRight, FileText, SlidersHorizontal, UserCheck, Stamp } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps<{
    modelValue: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const close = () => emit('update:modelValue', false);

/* ---------------- Form state ---------------- */

const regions = [
    'All Regions',
    'CO',
    'NCR',
    'CAR',
    'R1',
    'R2',
    'R3',
    'R4A',
    'R4B',
    'R5',
    'R6',
    'R7',
    'R8',
    'R9',
    'R10',
    'R11',
    'R12',
    'CARAGA',
    'NIR',
];

const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const form = reactive({
    region: 'CO',
    filter: 'monthly' as 'all' | 'monthly' | 'annual',
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear(),
    prepared: { name: '', position: '', date: '' },
    noted: { name: '', position: '', date: '' },
});

/* ---------------- Employee search (Prepared by / Noted by) ---------------- */

type Target = 'prepared' | 'noted' | null;

const employeeSearchTarget = ref<Target>(null);
const employeeQuery = ref('');
const employeeResults = ref<any[]>([]);
const searching = ref(false);

const openEmployeeSearch = (target: Target) => {
    employeeSearchTarget.value = target;
    employeeQuery.value = '';
    employeeResults.value = [];
    fetchEmployees('');
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
watch(employeeQuery, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchEmployees(val), 250);
});

const fetchEmployees = async (q: string) => {
    searching.value = true;
    try {
        const { data } = await axios.get(route('employees.search'), { params: { q } });
        employeeResults.value = data;
    } catch (e) {
        employeeResults.value = [];
    } finally {
        searching.value = false;
    }
};

const initials = (emp: any) => {
    const f = (emp.FIRSTNAME || '').charAt(0);
    const l = (emp.LASTNAME || '').charAt(0);
    return `${f}${l}`.toUpperCase();
};

const fullName = (emp: any) => {
    return [emp.FIRSTNAME, emp.MI, emp.LASTNAME]
        .filter(Boolean)
        .join(' ')
        .toUpperCase();
};

const positionLabel = (emp: any) => {
    const office = emp.OFFICE_DIVISION;
    return office ? `${emp.POSITION}, ${office}` : emp.POSITION;
};

const selectEmployee = (emp: any) => {
    if (!employeeSearchTarget.value) return;
    form[employeeSearchTarget.value].name = fullName(emp);
    form[employeeSearchTarget.value].position = positionLabel(emp);
    employeeSearchTarget.value = null;
};

/* ---------------- Accent theming ---------------- */
// Prepared by = blue, Noted by = violet — carries through to the shared employee search sub-modal.
const searchAccent = computed(() =>
    employeeSearchTarget.value === 'noted'
        ? { ring: 'focus:ring-violet-500', avatar: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300' }
        : { ring: 'focus:ring-blue-500', avatar: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' },
);

/* ---------------- Generate ---------------- */

const generating = ref(false);

const generate = () => {
    generating.value = true;

    const params = new URLSearchParams();
    params.append('region', form.region === 'All Regions' ? 'all' : form.region);
    params.append('filter', form.filter);

    if (form.filter === 'monthly') {
        params.append('month', String(form.month));
        params.append('year', String(form.year));
    } else if (form.filter === 'annual') {
        params.append('year', String(form.year));
    }

    params.append('prepared_name', form.prepared.name);
    params.append('prepared_position', form.prepared.position);
    params.append('prepared_date', form.prepared.date);
    params.append('noted_name', form.noted.name);
    params.append('noted_position', form.noted.position);
    params.append('noted_date', form.noted.date);

    window.open(`${route('reports.tpmr')}?${params.toString()}`, '_blank');

    generating.value = false;
};
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg lg:max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-card shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-5 bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
                        <FileText class="h-4.5 w-4.5 text-white" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white leading-tight">Generate TPMR</h2>
                        <p class="text-xs text-white/70">Training Program Monitoring Report</p>
                    </div>
                </div>
                <button class="text-white/70 hover:text-white" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 flex flex-col gap-5">
                <!-- Region / Filter / Month / Year -->
                <div class="flex items-center gap-1.5 -mb-1">
                    <div class="h-6 w-6 rounded-md bg-teal-100 dark:bg-teal-950/50 flex items-center justify-center shrink-0">
                        <SlidersHorizontal class="h-3.5 w-3.5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wide text-teal-700 dark:text-teal-400">Report Filters</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Region -->
                    <div>
                        <label class="block text-sm font-bold mb-1">Region</label>
                        <select v-model="form.region" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <!-- Filter -->
                    <div>
                        <label class="block text-sm font-bold mb-1">Filter</label>
                        <select v-model="form.filter" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option value="all">All</option>
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>

                    <!-- Month (monthly only) -->
                    <div v-if="form.filter === 'monthly'">
                        <label class="block text-sm font-bold mb-1">Month</label>
                        <select v-model.number="form.month" class="w-full rounded-md border px-3 py-2 text-sm bg-background">
                            <option v-for="(m, i) in months" :key="m" :value="i + 1">{{ m }}</option>
                        </select>
                    </div>

                    <!-- Year (monthly + annual) -->
                    <div v-if="form.filter !== 'all'">
                        <label class="block text-sm font-bold mb-1">Year</label>
                        <input
                            v-model.number="form.year"
                            type="number"
                            class="w-full rounded-md border px-3 py-2 text-sm bg-background"
                        />
                    </div>
                </div>

                <hr class="border-t" />

                <!-- Prepared by / Noted by -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Prepared by -->
                    <div class="lg:border-r lg:pr-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="flex items-center gap-1.5">
                                <span class="h-6 w-6 rounded-md bg-blue-100 dark:bg-blue-950/50 flex items-center justify-center shrink-0">
                                    <UserCheck class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                                </span>
                                <span class="text-sm font-bold tracking-wide text-blue-700 dark:text-blue-400">PREPARED BY</span>
                            </span>
                            <Button variant="outline" size="sm" class="h-7 text-xs border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800/40 dark:text-blue-400 dark:hover:bg-blue-950/30" @click="openEmployeeSearch('prepared')">
                                <Search class="h-3 w-3 mr-1" /> Select Employee
                            </Button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-muted-foreground mb-1">Name</label>
                                <input v-model="form.prepared.name" placeholder="Full name" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                            </div>
                            <div>
                                <label class="block text-xs text-muted-foreground mb-1">Position</label>
                                <input v-model="form.prepared.position" placeholder="Position/title" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs text-muted-foreground mb-1">Date</label>
                            <input v-model="form.prepared.date" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                        </div>
                    </div>

                    <!-- Noted by -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="flex items-center gap-1.5">
                                <span class="h-6 w-6 rounded-md bg-violet-100 dark:bg-violet-950/50 flex items-center justify-center shrink-0">
                                    <Stamp class="h-3.5 w-3.5 text-violet-600 dark:text-violet-400" />
                                </span>
                                <span class="text-sm font-bold tracking-wide text-violet-700 dark:text-violet-400">NOTED BY</span>
                            </span>
                            <Button variant="outline" size="sm" class="h-7 text-xs border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800/40 dark:text-violet-400 dark:hover:bg-violet-950/30" @click="openEmployeeSearch('noted')">
                                <Search class="h-3 w-3 mr-1" /> Select Employee
                            </Button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-muted-foreground mb-1">Name</label>
                                <input v-model="form.noted.name" placeholder="Full name" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                            </div>
                            <div>
                                <label class="block text-xs text-muted-foreground mb-1">Position</label>
                                <input v-model="form.noted.position" placeholder="Position/title" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs text-muted-foreground mb-1">Date</label>
                            <input v-model="form.noted.date" type="date" class="w-full rounded-md border px-3 py-2 text-sm bg-muted/30" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t">
                <Button variant="ghost" @click="close">Cancel</Button>
                <Button :disabled="generating" @click="generate" class="bg-gradient-to-r from-blue-600 to-violet-600 hover:from-blue-500 hover:to-violet-500 text-white">
                    <FileText class="h-4 w-4 mr-2" />
                    Generate TPMR PDF
                </Button>
            </div>
        </div>

        <!-- Employee Search Sub-modal -->
        <div v-if="employeeSearchTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm max-h-[80vh] flex flex-col rounded-2xl bg-card shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b">
                    <h3 class="text-base font-bold">Select Employee</h3>
                    <button class="text-muted-foreground hover:text-foreground" @click="employeeSearchTarget = null">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-5 py-3 border-b">
                    <input
                        v-model="employeeQuery"
                        type="text"
                        placeholder="Search employees..."
                        class="w-full rounded-md border px-3 py-2 text-sm bg-background outline-none focus:ring-2"
                        :class="searchAccent.ring"
                    />
                </div>
                <div class="overflow-y-auto flex-1 divide-y">
                    <div v-if="searching" class="px-5 py-6 text-center text-sm text-muted-foreground">Searching...</div>
                    <div v-else-if="employeeResults.length === 0" class="px-5 py-6 text-center text-sm text-muted-foreground">
                        No employees found.
                    </div>
                    <button
                        v-for="emp in employeeResults"
                        :key="emp.EMPCODE"
                        class="w-full flex items-center gap-3 px-5 py-3 hover:bg-muted/50 text-left"
                        @click="selectEmployee(emp)"
                    >
                        <span class="h-9 w-9 shrink-0 rounded-full flex items-center justify-center text-xs font-bold" :class="searchAccent.avatar">
                            {{ initials(emp) }}
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-bold truncate">{{ fullName(emp) }}</span>
                            <span class="block text-xs text-muted-foreground truncate">{{ positionLabel(emp) }}</span>
                        </span>
                        <ChevronRight class="h-4 w-4 text-muted-foreground/50 shrink-0" />
                    </button>
                </div>
                <div class="px-5 py-2 border-t text-center text-[11px] text-muted-foreground">
                    Data loaded from employees
                </div>
            </div>
        </div>
    </div>
</template>