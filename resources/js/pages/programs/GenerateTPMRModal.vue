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
import { X, Search, ChevronRight, FileText } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
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
    'Region I',
    'Region II',
    'Region III',
    'Region IV-A',
    'Region IV-B',
    'Region V',
    'Region VI',
    'Region VII',
    'Region VIII',
    'Region IX',
    'Region X',
    'Region XI',
    'Region XII',
    'Region XIII',
    'BARMM',
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
        <div class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl bg-card shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-5 border-b">
                <h2 class="text-lg font-bold">Generate TPMR</h2>
                <button class="text-muted-foreground hover:text-foreground" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 flex flex-col gap-5">
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

                <hr class="border-t" />

                <!-- Prepared by -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold tracking-wide">PREPARED BY</span>
                        <Button variant="outline" size="sm" class="h-7 text-xs" @click="openEmployeeSearch('prepared')">
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

                <hr class="border-t" />

                <!-- Noted by -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-bold tracking-wide">NOTED BY</span>
                        <Button variant="outline" size="sm" class="h-7 text-xs" @click="openEmployeeSearch('noted')">
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

            <!-- Footer -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t">
                <Button variant="ghost" @click="close">Cancel</Button>
                <Button :disabled="generating" @click="generate" class="bg-blue-500 hover:bg-blue-400">
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
                        class="w-full rounded-md border px-3 py-2 text-sm bg-background"
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
                        <span class="h-9 w-9 shrink-0 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">
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