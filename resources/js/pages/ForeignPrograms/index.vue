<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Plus, Earth, Calendar, Users, Building2, Search, X,
    ChevronLeft, ChevronRight, Globe, MapPin, Clock, Banknote,
    Tag, FileText, CalendarDays, Building, Hash, AlignLeft,
    CheckCircle2, SlidersHorizontal, Trash2, Eye, Pencil,BarChart3, Settings
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import ForeignProgramsDashboardModal from '@/components/ForeignProgramsDashboardModal.vue';
import OrganizingSponsorModal from '@/components/OrganizingSponsorModal.vue';
import SponsorConfigModal from '@/components/SponsorConfigModal.vue';

const showDashboard = ref(false);
const showFormSettingsDropdown = ref(false);
function handleClickOutside(event: MouseEvent) {
    showFormSettingsDropdown.value = false;
}

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: 'in-person' | 'online' | 'hybrid';
    organizing_sponsor: string;
    status: string;
    nominees_count: number;
    submission_date?: string;
    embassy_deadline?: string;
    interview_date?: string;
    attached_agency?: string;
    invited_agencies?: string;
    category?: string;
    description?: string;
    online_start?: string;
    online_end?: string;
    inperson_start?: string;
    inperson_end?: string;
    program_cost?: string;
    fund_source?: string;
}

interface PaginatedPrograms {
    data: ForeignProgram[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    programs: PaginatedPrograms;
    years: number[];
    filters: {
        search?: string;
        status?: string;
        year?: string;
        semester?: string;
        organization?: string;
        category?: string;
        embassy_deadline?: string;
        interview_date?: string;
    };
}>();

// --- Filters state ---
const search           = ref(props.filters.search ?? '');
const filterStatus     = ref(props.filters.status ?? '');
const filterYear       = ref(props.filters.year ?? '');
const filterSemester   = ref(props.filters.semester ?? '');
const filterOrg        = ref(props.filters.organization ?? '');
const filterCategory   = ref(props.filters.category ?? '');
const filterEmbassy    = ref(props.filters.embassy_deadline ?? '');
const filterInterview  = ref(props.filters.interview_date ?? '');

const hasActiveFilters = computed(() =>
    [search.value, filterStatus.value, filterYear.value, filterSemester.value,
     filterOrg.value, filterCategory.value, filterEmbassy.value, filterInterview.value]
    .some(v => v !== '')
);

const applyFilters = () => {
    router.get(route('foreign-programs.index'), {
        search:           search.value || undefined,
        status:           filterStatus.value || undefined,
        year:             filterYear.value || undefined,
        semester:         filterSemester.value || undefined,
        organization:     filterOrg.value || undefined,
        category:         filterCategory.value || undefined,
        embassy_deadline: filterEmbassy.value || undefined,
        interview_date:   filterInterview.value || undefined,
    }, { preserveScroll: true, replace: true });
};

let debounce: ReturnType<typeof setTimeout>;
watch(
    [search, filterStatus, filterYear, filterSemester, filterOrg, filterCategory, filterEmbassy, filterInterview],
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilters, 350);
    }
);

const clearFilters = () => {
    search.value = '';
    filterStatus.value = '';
    filterYear.value = '';
    filterSemester.value = '';
    filterOrg.value = '';
    filterCategory.value = '';
    filterEmbassy.value = '';
    filterInterview.value = '';
};

// --- Quick View Modal ---
const viewProgram = ref<ForeignProgram | null>(null);
const openView = (program: ForeignProgram) => { viewProgram.value = program; };
const closeView = () => { viewProgram.value = null; };

// --- Edit Modal ---
const showEditModal = ref(false);

const editForm = useForm({
    program_title: '',
    description: '',
    program_start: '',
    program_end: '',
    slots: 1,
    modality: 'in-person' as 'in-person' | 'online' | 'hybrid',
    online_start: '',
    online_end: '',
    inperson_start: '',
    inperson_end: '',
    program_cost: '',
    fund_source: '',
    category: 'Foreign',
    organizing_sponsor: '',
    status: 'for_dissemination',
    submission_date: '',
    embassy_deadline: '',
    interview_date: '',
    invited_agencies: '',
    attached_agency: '',
});

const editingId = ref<number | null>(null);

    const toDateInput = (date?: string | null): string => {
    if (!date) return '';
    return date.slice(0, 10); // takes "2026-06-15" from "2026-06-15T00:00:00.000000Z"
};

const openEdit = (program: ForeignProgram) => {
    editingId.value = program.id;
    editForm.program_title      = program.program_title;
    editForm.description        = program.description ?? '';
    editForm.program_start      = toDateInput(program.program_start);
    editForm.program_end        = toDateInput(program.program_end);
    editForm.slots              = program.slots;
    editForm.modality           = program.modality;
    editForm.online_start       = toDateInput(program.online_start);
    editForm.online_end         = toDateInput(program.online_end);
    editForm.inperson_start     = toDateInput(program.inperson_start);
    editForm.inperson_end       = toDateInput(program.inperson_end);
    editForm.program_cost       = program.program_cost ?? '';
    editForm.fund_source        = program.fund_source ?? '';
    editForm.category           = program.category ?? 'Foreign';
    editForm.organizing_sponsor = program.organizing_sponsor;
    editForm.status             = program.status;
    editForm.submission_date    = toDateInput(program.submission_date);
    editForm.embassy_deadline   = toDateInput(program.embassy_deadline);
    editForm.interview_date     = toDateInput(program.interview_date);
    editForm.invited_agencies   = program.invited_agencies ?? '';
    editForm.attached_agency    = program.attached_agency ?? '';
    showEditModal.value = true;
};

const closeEdit = () => {
    showEditModal.value = false;
    editingId.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (!editingId.value) return;
    editForm.put(route('foreign-programs.update', editingId.value), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const showEditOnlineDates   = computed(() => editForm.modality === 'online'    || editForm.modality === 'hybrid');
const showEditInpersonDates = computed(() => editForm.modality === 'in-person' || editForm.modality === 'hybrid');

watch(() => editForm.modality, (val) => {
    if (val === 'in-person') { editForm.online_start = '';   editForm.online_end = '';   }
    if (val === 'online')    { editForm.inperson_start = ''; editForm.inperson_end = ''; }
});

// --- Add Modal & Form ---
const showModal = ref(false);

const form = useForm({
    program_title: '',
    description: '',
    program_start: '',
    program_end: '',
    slots: 1,
    modality: 'in-person',
    online_start: '',
    online_end: '',
    inperson_start: '',
    inperson_end: '',
    program_cost: '',
    fund_source: '',
    category: 'Foreign',
    organizing_sponsor: '',
    status: 'for_dissemination',
    submission_date: '',
    embassy_deadline: '',
    interview_date: '',
    invited_agencies: '',
    attached_agency: '',
});

const showOnlineDates   = computed(() => form.modality === 'online'    || form.modality === 'hybrid');
const showInpersonDates = computed(() => form.modality === 'in-person' || form.modality === 'hybrid');

watch(() => form.modality, (val) => {
    if (val === 'in-person') { form.online_start = '';   form.online_end = '';   }
    if (val === 'online')    { form.inperson_start = ''; form.inperson_end = ''; }
});

const submit = () => {
    form.attached_agency = '';
    form.post(route('foreign-programs.store'), {
        preserveScroll: true,
        onSuccess: () => { showModal.value = false; form.reset(); },
    });
};

const confirmDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this program?')) {
        router.delete(route('foreign-programs.destroy', id), { preserveScroll: true });
    }
};

// --- Lookups ---
const statusLabels: Record<string, string> = {
    for_dissemination:    'For Dissemination',
    waiting_for_nominees: 'Waiting for Nominees',
    for_interview:        'For Interview',
    for_endorsement:      'For Endorsement',
    no_nominee:           'No Nominee',
    waiting_for_result:   'Waiting for Result',
    ongoing:              'Ongoing',
    concluded:            'Concluded',
};

const statusColors: Record<string, string> = {
    for_dissemination:    'bg-slate-100 text-slate-700',
    waiting_for_nominees: 'bg-amber-100 text-amber-700',
    for_interview:        'bg-blue-100 text-blue-700',
    for_endorsement:      'bg-violet-100 text-violet-700',
    no_nominee:           'bg-red-100 text-red-700',
    waiting_for_result:   'bg-cyan-100 text-cyan-700',
    ongoing:              'bg-emerald-100 text-emerald-700',
    concluded:            'bg-gray-200 text-gray-600',
};

const modalityColors: Record<string, string> = {
    'in-person': 'bg-emerald-100 text-emerald-700',
    'online':    'bg-purple-100 text-purple-700',
    'hybrid':    'bg-blue-100 text-blue-700',
};

const modalityIcons: Record<string, string> = {
    'in-person': '🏢',
    'online':    '💻',
    'hybrid':    '🔀',
};

const formatDate = (date?: string) => {
    if (!date) return '—';
    // If already has time component (ISO from DB), use as-is
    // If plain date YYYY-MM-DD, append time to avoid UTC shift
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
};


// Organizing Sponsors
const showSponsorModal    = ref(false);
const sponsors            = ref<string[]>([]);
const sponsorsForEditModal = ref(false); // true = para sa edit form, false = para sa add form

const fetchSponsors = async () => {
    const res = await fetch(route('organizing-sponsors.index'), {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    sponsors.value = data.map((s: { id: number; name: string }) => s.name);
};

const openSponsorModal = (forEdit = false) => {
    sponsorsForEditModal.value = forEdit;
    showSponsorModal.value = true;
};

const onSponsorSelected = (name: string) => {
    if (sponsorsForEditModal.value) {
        editForm.organizing_sponsor = name;
    } else {
        form.organizing_sponsor = name;
    }
};

// Initial load
fetchSponsors();

const configModalOpen  = ref(false);
const selectedSponsor  = ref('');

function openConfigModal(sponsor: string) {
    selectedSponsor.value = sponsor;
    configModalOpen.value = true;
}

function onConfigSaved() {
    // Optional: pwedeng mag-refresh ng page or mag-show ng toast
    configModalOpen.value = false;
}
</script>

<template>
    <Head title="Foreign Programs" />
    <ForeignProgramsDashboardModal v-if="showDashboard" @close="showDashboard = false" />
    <OrganizingSponsorModal
        v-if="showSponsorModal"
        @close="showSponsorModal = false"
        @select="onSponsorSelected"
        @updated="fetchSponsors"
    />
    <SponsorConfigModal
        :open="configModalOpen"
        :organizing-sponsor="selectedSponsor"
        @close="configModalOpen = false"
        @saved="onConfigSaved"
    />

    <div
        v-if="showFormSettingsDropdown"
        class="fixed inset-0 z-40"
        @click="showFormSettingsDropdown = false"
    />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-blue-600 shadow-md">
                        <Earth class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-none">Foreign Programs</h1>
                        <p class="text-sm text-muted-foreground mt-0.5">Manage nominations for foreign training programs</p>
                    </div>
                </div>

                <div class="flex gap-2">

                    <Button variant="outline" class="border-indigo-200 text-indigo-700 hover:bg-indigo-50 shadow-sm" @click="showDashboard = true">
                    <BarChart3 class="h-4 w-4 mr-1" /> Dashboard
                    </Button>
                    <div class="relative">
                        <Button
                            variant="outline"
                            class="border-blue-200 text-blue-700 hover:bg-blue-50 shadow-sm"
                            @click="showFormSettingsDropdown = !showFormSettingsDropdown"
                        >
                            <Settings class="h-4 w-4 mr-1" /> Requirements Form Settings
                        </Button>

                        <!-- Dropdown ng sponsors -->
                        <div
                            v-if="showFormSettingsDropdown"
                            class="absolute right-0 top-full mt-1 z-50 bg-background border rounded-xl shadow-lg py-1 min-w-48"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground px-3 py-1.5">
                                Select Organizing Sponsor
                            </p>
                            <button
                                v-for="sponsor in sponsors"
                                :key="sponsor"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-muted transition-colors"
                                @click="openConfigModal(sponsor); showFormSettingsDropdown = false"
                            >
                                {{ sponsor }}
                            </button>
                            <p v-if="sponsors.length === 0" class="px-3 py-2 text-xs text-muted-foreground">
                                No sponsors found.
                            </p>
                        </div>
                    </div>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white shadow-sm" @click="showModal = true">
                        <Plus class="h-4 w-4 mr-1" /> Add Program
                    </Button>

                </div>

                
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search programs..."
                            class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-background"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground px-2 py-1.5 rounded-lg border bg-muted/30">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <Button v-if="hasActiveFilters" variant="ghost" class="text-xs text-muted-foreground gap-1" @click="clearFilters">
                        <X class="h-3.5 w-3.5" /> Clear all
                    </Button>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2 p-3 rounded-xl border bg-muted/30">
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <CheckCircle2 class="h-3 w-3" /> Status
                        </label>
                        <select v-model="filterStatus" class="border rounded-lg px-2 py-1.5 text-xs bg-background">
                            <option value="">All</option>
                            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <CalendarDays class="h-3 w-3" /> Year
                        </label>
                        <select v-model="filterYear" class="border rounded-lg px-2 py-1.5 text-xs bg-background">
                            <option value="">All</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <Clock class="h-3 w-3" /> Semester
                        </label>
                        <select v-model="filterSemester" class="border rounded-lg px-2 py-1.5 text-xs bg-background">
                            <option value="">All</option>
                            <option value="1">1st (Jan–Jun)</option>
                            <option value="2">2nd (Jul–Dec)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <Tag class="h-3 w-3" /> Category
                        </label>
                        <select v-model="filterCategory" class="border rounded-lg px-2 py-1.5 text-xs bg-background">
                            <option value="">All</option>
                            <option value="Foreign">Foreign</option>
                            <option value="Bilateral">Bilateral</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <Building class="h-3 w-3" /> Organization
                        </label>
                        <input v-model="filterOrg" type="text" placeholder="e.g. JICA" class="border rounded-lg px-2 py-1.5 text-xs bg-background" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <MapPin class="h-3 w-3" /> Embassy Deadline
                        </label>
                        <input v-model="filterEmbassy" type="date" class="border rounded-lg px-2 py-1.5 text-xs bg-background" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                            <Users class="h-3 w-3" /> Interview Date
                        </label>
                        <input v-model="filterInterview" type="date" class="border rounded-lg px-2 py-1.5 text-xs bg-background" />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ programs.from ?? 0 }}–{{ programs.to ?? 0 }} of {{ programs.total }} program(s)
                </p>
            </div>

            <!-- List -->
            <div class="rounded-2xl border overflow-hidden shadow-sm">
                <div class="grid grid-cols-[2fr_1.5fr_1fr_1fr_1fr_auto] gap-4 px-4 py-2.5 bg-muted/50 border-b text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                    <span>Program</span>
                    <span>Sponsor</span>
                    <span>Schedule</span>
                    <span>Modality</span>
                    <span class="text-right">Slots</span>
                    <span class="text-center w-28">Actions</span>
                </div>

                <div
                    v-for="program in programs.data"
                    :key="program.id"
                    class="grid grid-cols-[2fr_1.5fr_1fr_1fr_1fr_auto] gap-4 px-4 py-3 items-center border-b last:border-b-0 hover:bg-blue-50/50 dark:hover:bg-blue-950/20 transition-colors text-sm group"
                >
                  
                    <Link :href="route('foreign-programs.show', program.id)" class="flex flex-col gap-0.5 min-w-0">
                        <span class="font-semibold leading-snug truncate group-hover:text-blue-600 transition-colors">{{ program.program_title }}</span>
                        <span class="w-fit text-[10px] font-semibold px-2 py-0.5 rounded-full" :class="statusColors[program.status]">
                            {{ statusLabels[program.status] }}
                        </span>
                    </Link>
               

                    <div class="flex items-center gap-1.5 text-muted-foreground truncate">
                        <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                        <span class="truncate">{{ program.organizing_sponsor }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-muted-foreground">
                        <Calendar class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                        <span class="whitespace-nowrap">{{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-muted-foreground capitalize">
                        <span>{{ modalityIcons[program.modality] }}</span>
                        <span>{{ program.modality }}</span>
                    </div>
                    <div class="flex items-center justify-end gap-1.5 text-muted-foreground">
                        <Users class="h-3.5 w-3.5 text-blue-400" />
                        <span>
                            <span class="font-semibold text-foreground">{{ program.nominees_count }}</span>
                            <span> / {{ program.slots }}</span>
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-center gap-1 w-28">
                        <button @click="openView(program)" class="p-1.5 rounded-lg text-muted-foreground hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 transition-colors" title="View details">
                            <Eye class="h-4 w-4" />
                        </button>
                        <button @click="openEdit(program)" class="p-1.5 rounded-lg text-muted-foreground hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors" title="Edit program">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button @click="confirmDelete(program.id)" class="p-1.5 rounded-lg text-muted-foreground hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors" title="Delete program">
                            <Trash2 class="h-4 w-4" />
                        </button>
                       
                    </div>
                </div>

                <div v-if="programs.data.length === 0" class="flex flex-col items-center justify-center py-24 text-muted-foreground gap-3">
                    <div class="relative">
                        <div class="h-20 w-20 rounded-full bg-blue-50 dark:bg-blue-950/30 flex items-center justify-center">
                            <Globe class="h-10 w-10 text-blue-300" />
                        </div>
                        <div class="absolute -bottom-1 -right-1 h-7 w-7 rounded-full bg-muted flex items-center justify-center border-2 border-background">
                            <Search class="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-semibold">No programs found.</p>
                        <p class="text-xs mt-1">Try adjusting your search or filters.</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="programs.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-muted-foreground text-xs">Page {{ programs.current_page }} of {{ programs.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in programs.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-muted text-muted-foreground'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </Link>
                        <span v-else class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-xs text-muted-foreground opacity-40">
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </span>
                    </template>
                </div>
            </div>

        </div>

        <!-- ===== Quick View Modal ===== -->
        <div v-if="viewProgram" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeView">
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-lg">
                <div class="flex items-start justify-between px-6 pt-6 pb-4 border-b gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-1">Program Details</p>
                        <h2 class="text-base font-bold leading-snug">{{ viewProgram.program_title }}</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ viewProgram.organizing_sponsor }}</p>
                    </div>
                    <button @click="closeView" class="text-muted-foreground hover:text-foreground transition-colors shrink-0 mt-0.5">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Period</p>
                            <p class="font-medium">{{ formatDate(viewProgram.program_start) }} – {{ formatDate(viewProgram.program_end) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Slots</p>
                            <p class="font-medium">{{ viewProgram.nominees_count }} / {{ viewProgram.slots }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Modality</p>
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full" :class="modalityColors[viewProgram.modality]">
                                {{ modalityIcons[viewProgram.modality] }} {{ viewProgram.modality }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Status</p>
                            <span class="inline-flex text-xs font-semibold px-2.5 py-1 rounded-full" :class="statusColors[viewProgram.status]">
                                {{ statusLabels[viewProgram.status] }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Submission Date</p>
                            <p class="font-medium">{{ formatDate(viewProgram.submission_date) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Embassy Deadline</p>
                            <p class="font-medium" :class="viewProgram.embassy_deadline ? 'text-red-600' : ''">{{ formatDate(viewProgram.embassy_deadline) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Interview Date</p>
                            <p class="font-medium" :class="viewProgram.interview_date ? 'text-violet-600' : ''">{{ formatDate(viewProgram.interview_date) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Attached Agency</p>
                            <p class="font-medium">{{ viewProgram.attached_agency || '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">Invited Agencies</p>
                            <p class="font-medium">{{ viewProgram.invited_agencies || '—' }}</p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t flex items-center justify-between">
                    <Link :href="route('foreign-programs.show', viewProgram.id)" class="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors">
                        <Users class="h-4 w-4" /> View Participants
                    </Link>
                    <Button variant="outline" @click="closeView">Close</Button>
                </div>
            </div>
        </div>

        <!-- ===== Edit Modal ===== -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeEdit">
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

                <!-- Header -->
                <div class="sticky top-0 z-10 bg-background border-b px-6 py-4 rounded-t-2xl flex items-center gap-3">
                    <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-amber-500 shadow">
                        <Pencil class="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold leading-none">Edit Program</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Update the details of this program</p>
                    </div>
                    <button @click="closeEdit" class="ml-auto text-muted-foreground hover:text-foreground transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6">

                    <!-- Basic Info -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2 flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                                </label>
                                <input v-model="editForm.program_title" type="text" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                                <span v-if="editForm.errors.program_title" class="text-xs text-red-500">{{ editForm.errors.program_title }}</span>
                            </div>
                            <div class="md:col-span-2 flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                                </label>
                                <textarea v-model="editForm.description" rows="3" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none" placeholder="Optional"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Schedule</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                                </label>
                                <input v-model="editForm.program_start" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                                </label>
                                <input v-model="editForm.program_end" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                                </label>
                                <input v-model="editForm.slots" type="number" min="1" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                                </label>
                                <select v-model="editForm.modality" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <option value="in-person">🏢 In-person</option>
                                    <option value="online">💻 Online</option>
                                    <option value="hybrid">🔀 Hybrid</option>
                                </select>
                            </div>
                        </div>

                        <template v-if="showEditOnlineDates">
                            <div class="rounded-xl bg-purple-50 border border-purple-200 p-4 dark:bg-purple-950/30 dark:border-purple-900">
                                <p class="text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400 mb-3">💻 Online Schedule</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online Start</label>
                                        <input v-model="editForm.online_start" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online End</label>
                                        <input v-model="editForm.online_end" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-if="showEditInpersonDates">
                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 dark:bg-emerald-950/30 dark:border-emerald-900">
                                <p class="text-xs font-extrabold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 mb-3">🏢 In-person Schedule</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">In-person Start</label>
                                        <input v-model="editForm.inperson_start" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-emerald-400" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">In-person End</label>
                                        <input v-model="editForm.inperson_end" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-emerald-400" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Classification & Funding -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Banknote class="h-3.5 w-3.5" /> <span>Classification & Funding</span>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 dark:bg-amber-950/30 dark:border-amber-900">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                    </label>
                                    <select v-model="editForm.category" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        <option value="Foreign">🌐 Foreign</option>
                                        <option value="Bilateral">🤝 Bilateral</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                    </label>
                                    <input v-model="editForm.program_cost" type="text" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500" placeholder="e.g. 50,000" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                    </label>
                                    <select v-model="editForm.fund_source" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-amber-500">
                                        <option value="">— Select —</option>
                                        <option value="SDP">SDP</option>
                                        <option value="Other Office">Other Office</option>
                                        <option value="Sponsoring Organization">Sponsoring Organization</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organizer & Status -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Building2 class="h-3.5 w-3.5" /> <span>Organizer & Status</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <select
                                        v-model="editForm.organizing_sponsor"
                                        class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    >
                                        <option value="">— Select sponsor —</option>
                                        <option v-for="s in sponsors" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="px-3 py-2 rounded-lg border text-xs font-semibold text-amber-600 hover:bg-amber-50 transition-colors whitespace-nowrap"
                                        @click="openSponsorModal(true)"
                                    >
                                        + Manage
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                                </label>
                                <select v-model="editForm.status" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Key Dates -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Key Dates</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                                </label>
                                <input v-model="editForm.submission_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                                </label>
                                <input v-model="editForm.embassy_deadline" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                                </label>
                                <input v-model="editForm.interview_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Agencies -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                            <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                            </label>
                            <textarea v-model="editForm.invited_agencies" rows="2" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 resize-none" placeholder="Comma-separated, e.g. DILG, DBM, CSC"></textarea>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="sticky bottom-0 bg-background border-t px-6 py-4 rounded-b-2xl flex justify-end gap-2">
                    <Button variant="outline" @click="closeEdit">Cancel</Button>
                    <Button class="bg-amber-500 hover:bg-amber-600 text-white" :disabled="editForm.processing" @click="submitEdit">
                        <Pencil v-if="!editForm.processing" class="h-4 w-4 mr-1" />
                        {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- ===== Add Program Modal ===== -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

                <div class="sticky top-0 z-10 bg-background border-b px-6 py-4 rounded-t-2xl flex items-center gap-3">
                    <div class="flex items-center justify-center h-9 w-9 rounded-xl bg-blue-600 shadow">
                        <Globe class="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold leading-none">Add Foreign Program</h2>
                        <p class="text-xs text-muted-foreground mt-0.5">Fill in the details for the new program</p>
                    </div>
                    <button @click="showModal = false" class="ml-auto text-muted-foreground hover:text-foreground transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="p-6 flex flex-col gap-6">

                    <!-- Basic Info -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2 flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.program_title" type="text" placeholder="e.g. JICA Training on Public Administration" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                <span v-if="form.errors.program_title" class="text-xs text-red-500">{{ form.errors.program_title }}</span>
                            </div>
                            <div class="md:col-span-2 flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                                </label>
                                <textarea v-model="form.description" rows="3" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Optional — brief overview of the program"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Schedule</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.program_start" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.program_end" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.slots" type="number" min="1" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.modality" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="in-person">🏢 In-person</option>
                                    <option value="online">💻 Online</option>
                                    <option value="hybrid">🔀 Hybrid</option>
                                </select>
                            </div>
                        </div>

                        <template v-if="showOnlineDates">
                            <div class="rounded-xl bg-purple-50 border border-purple-200 p-4 dark:bg-purple-950/30 dark:border-purple-900">
                                <p class="text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400 mb-3">💻 Online Schedule</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online Start</label>
                                        <input v-model="form.online_start" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online End</label>
                                        <input v-model="form.online_end" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-purple-400" />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-if="showInpersonDates">
                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 dark:bg-emerald-950/30 dark:border-emerald-900">
                                <p class="text-xs font-extrabold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 mb-3">🏢 In-person Schedule</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">In-person Start</label>
                                        <input v-model="form.inperson_start" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-emerald-400" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">In-person End</label>
                                        <input v-model="form.inperson_end" type="date" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-emerald-400" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Classification & Funding -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <Banknote class="h-3.5 w-3.5" /> <span>Classification & Funding</span>
                        </div>
                        <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 dark:bg-blue-950/30 dark:border-blue-900">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                    </label>
                                    <select v-model="form.category" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Foreign">🌐 Foreign</option>
                                        <option value="Bilateral">🤝 Bilateral</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                    </label>
                                    <input v-model="form.program_cost" type="text" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="e.g. 50,000" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold flex items-center gap-1.5">
                                        <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                    </label>
                                    <select v-model="form.fund_source" class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-background focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">— Select —</option>
                                        <option value="SDP">SDP</option>
                                        <option value="Other Office">Other Office</option>
                                        <option value="Sponsoring Organization">Sponsoring Organization</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organizer & Status -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <Building2 class="h-3.5 w-3.5" /> <span>Organizer & Status</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <select
                                        v-model="form.organizing_sponsor"
                                        class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">— Select sponsor —</option>
                                        <option v-for="s in sponsors" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="px-3 py-2 rounded-lg border text-xs font-semibold text-blue-600 hover:bg-blue-50 transition-colors whitespace-nowrap"
                                        @click="openSponsorModal(false)"
                                    >
                                        + Manage
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.status" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Key Dates -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Key Dates</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                                </label>
                                <input v-model="form.submission_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                                </label>
                                <input v-model="form.embassy_deadline" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold flex items-center gap-1.5">
                                    <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                                </label>
                                <input v-model="form.interview_date" type="date" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Agencies -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs font-semibold flex items-center gap-1.5">
                                <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                            </label>
                            <textarea v-model="form.invited_agencies" rows="2" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" placeholder="Comma-separated, e.g. DILG, DBM, CSC"></textarea>
                        </div>
                    </div>

                </div>

                <div class="sticky bottom-0 bg-background border-t px-6 py-4 rounded-b-2xl flex justify-end gap-2">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="form.processing" @click="submit">
                        <Plus v-if="!form.processing" class="h-4 w-4 mr-1" />
                        {{ form.processing ? 'Saving...' : 'Save Program' }}
                    </Button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>