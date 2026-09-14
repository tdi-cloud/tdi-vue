<script setup lang="ts">
import ForeignProgramsDashboardModal from '@/components/ForeignProgramsDashboardModal.vue';
import NominationHistoryModal from '@/components/NominationHistoryModal.vue';
import OrganizingSponsorModal from '@/components/OrganizingSponsorModal.vue';
import SponsorConfigModal from '@/components/SponsorConfigModal.vue';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import EditProgramModal from '@/pages/ForeignPrograms/EditProgramModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlignLeft,
    Banknote,
    BarChart3,
    Building,
    Building2,
    Calendar,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Earth,
    Eye,
    FileText,
    Globe,
    Hash,
    History,
    MapPin,
    Pencil,
    Plus,
    Search,
    Settings,
    SlidersHorizontal,
    Tag,
    Trash2,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const { confirmDialog } = useConfirm();
const showDashboard = ref(false);
const showNominationHistory = ref(false);
const showFormSettingsDropdown = ref(false);

interface ForeignProgram {
    id: number;
    program_title: string;
    program_start: string;
    program_end: string;
    slots: number;
    modality: 'in-person' | 'online' | 'hybrid';
    organizing_sponsor: string;
    sponsor: { full_name: string | null } | null;
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
    created_by_empcode?: string | null;
    created_by_name?: string | null;
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
    sponsorOptions: string[];
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
const search = ref(props.filters.search ?? '');
const filterStatus = ref(props.filters.status ?? '');
const filterYear = ref(props.filters.year ?? '');
const filterSemester = ref(props.filters.semester ?? '');
const filterOrg = ref(props.filters.organization ?? '');
const filterCategory = ref(props.filters.category ?? '');
const filterEmbassy = ref(props.filters.embassy_deadline ?? '');
const filterInterview = ref(props.filters.interview_date ?? '');

const hasActiveFilters = computed(() =>
    [
        search.value,
        filterStatus.value,
        filterYear.value,
        filterSemester.value,
        filterOrg.value,
        filterCategory.value,
        filterEmbassy.value,
        filterInterview.value,
    ].some((v) => v !== ''),
);

const applyFilters = () => {
    router.get(
        route('foreign-programs.index'),
        {
            search: search.value || undefined,
            status: filterStatus.value || undefined,
            year: filterYear.value || undefined,
            semester: filterSemester.value || undefined,
            organization: filterOrg.value || undefined,
            category: filterCategory.value || undefined,
            embassy_deadline: filterEmbassy.value || undefined,
            interview_date: filterInterview.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

let debounce: ReturnType<typeof setTimeout>;
watch([search, filterStatus, filterYear, filterSemester, filterOrg, filterCategory, filterEmbassy, filterInterview], () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 350);
});

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

function sponsorDisplay(program: ForeignProgram) {
    const fullName = program.sponsor?.full_name;
    return fullName ? `${fullName} (${program.organizing_sponsor})` : program.organizing_sponsor;
}

// --- Quick View Modal ---
const viewProgram = ref<ForeignProgram | null>(null);
const openView = (program: ForeignProgram) => {
    viewProgram.value = program;
};
const closeView = () => {
    viewProgram.value = null;
};

// --- Edit Modal --- (form fields/date handling live inside EditProgramModal.vue)
const showEditModal = ref(false);
const editingProgram = ref<ForeignProgram | null>(null);

const openEdit = (program: ForeignProgram) => {
    editingProgram.value = program;
    showEditModal.value = true;
};

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

const showOnlineDates = computed(() => form.modality === 'online' || form.modality === 'hybrid');

watch(
    () => form.modality,
    (val) => {
        if (val === 'in-person') {
            form.online_start = '';
            form.online_end = '';
        }
    },
);

const submit = () => {
    form.attached_agency = '';
    form.post(route('foreign-programs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const confirmDelete = async (id: number) => {
    if (await confirmDialog('Are you sure you want to delete this program?')) {
        router.delete(route('foreign-programs.destroy', id), { preserveScroll: true });
    }
};

// --- Lookups ---
const statusLabels: Record<string, string> = {
    for_dissemination: 'For Dissemination',
    waiting_for_nominees: 'Waiting for Nominees',
    for_interview: 'For Interview',
    for_endorsement: 'For Endorsement',
    no_nominee: 'No Nominee',
    waiting_for_result: 'Waiting for Result',
    ongoing: 'Ongoing',
    concluded: 'Concluded',
    not_nfp_concern: 'Not NFP Concern',
};

const statusColors: Record<string, string> = {
    for_dissemination: 'bg-slate-100 text-slate-700',
    waiting_for_nominees: 'bg-amber-100 text-amber-700',
    for_interview: 'bg-blue-100 text-blue-700',
    for_endorsement: 'bg-violet-100 text-violet-700',
    no_nominee: 'bg-red-100 text-red-700',
    waiting_for_result: 'bg-cyan-100 text-cyan-700',
    ongoing: 'bg-emerald-100 text-emerald-700',
    concluded: 'bg-gray-200 text-gray-600',
    not_nfp_concern: 'bg-neutral-200 text-neutral-500',
};

const modalityColors: Record<string, string> = {
    'in-person': 'bg-emerald-100 text-emerald-700',
    online: 'bg-purple-100 text-purple-700',
    hybrid: 'bg-blue-100 text-blue-700',
};

const modalityIcons: Record<string, string> = {
    'in-person': '🏢',
    online: '💻',
    hybrid: '🔀',
};

const formatDate = (date?: string) => {
    if (!date) return '—';
    // If already has time component (ISO from DB), use as-is
    // If plain date YYYY-MM-DD, append time to avoid UTC shift
    const d = date.includes('T') ? new Date(date) : new Date(date + 'T00:00:00');
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

// Organizing Sponsors (Add Program form only — EditProgramModal.vue manages its own)
const showSponsorModal = ref(false);
const sponsors = ref<string[]>([]);

const fetchSponsors = async () => {
    const res = await fetch(route('organizing-sponsors.index'), {
        headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    sponsors.value = data.map((s: { id: number; name: string }) => s.name);
};

const openSponsorModal = () => {
    showSponsorModal.value = true;
};

const onSponsorSelected = (name: string) => {
    form.organizing_sponsor = name;
};

// Initial load
fetchSponsors();

const configModalOpen = ref(false);
const selectedSponsor = ref('');

function openConfigModal(sponsor: string) {
    selectedSponsor.value = sponsor;
    configModalOpen.value = true;
}

function onConfigSaved() {
    // Huwag i-close ang modal dito — ang Programs tab ay auto-save na ngayon sa
    // bawat select/deselect, kaya kung isasara natin ito sa bawat 'saved' event,
    // maisasara ang modal sa gitna mismo ng pag-setup ng admin. Ang X button /
    // pag-click sa labas ng modal na lang ang magsasara nito.
}
</script>

<template>
    <Head title="Foreign Programs" />
    <ForeignProgramsDashboardModal v-if="showDashboard" @close="showDashboard = false" />
    <NominationHistoryModal v-if="showNominationHistory" @close="showNominationHistory = false" />
    <OrganizingSponsorModal v-if="showSponsorModal" @close="showSponsorModal = false" @select="onSponsorSelected" @updated="fetchSponsors" />
    <SponsorConfigModal :open="configModalOpen" :organizing-sponsor="selectedSponsor" @close="configModalOpen = false" @saved="onConfigSaved" />

    <div v-if="showFormSettingsDropdown" class="fixed inset-0 z-40" @click="showFormSettingsDropdown = false" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-md">
                        <Earth class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-none">Foreign Programs</h1>
                        <p class="mt-0.5 text-sm text-muted-foreground">Manage nominations for foreign training programs</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="border-indigo-200 text-indigo-700 shadow-sm hover:bg-indigo-50" @click="showDashboard = true">
                        <BarChart3 class="mr-1 h-4 w-4" /> Dashboard
                    </Button>
                    <Button
                        variant="outline"
                        class="border-violet-200 text-violet-700 shadow-sm hover:bg-violet-50"
                        @click="showNominationHistory = true"
                    >
                        <History class="mr-1 h-4 w-4" /> Nomination History
                    </Button>
                    <div class="relative">
                        <Button
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-sm hover:bg-blue-50"
                            @click="showFormSettingsDropdown = !showFormSettingsDropdown"
                        >
                            <Settings class="mr-1 h-4 w-4" /> Requirements Form Settings
                        </Button>

                        <!-- Dropdown ng sponsors -->
                        <div
                            v-if="showFormSettingsDropdown"
                            class="absolute right-0 top-full z-50 mt-1 min-w-48 rounded-xl border bg-background py-1 shadow-lg"
                        >
                            <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Select Organizing Sponsor</p>
                            <button
                                v-for="sponsor in sponsors"
                                :key="sponsor"
                                class="w-full px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                @click="
                                    openConfigModal(sponsor);
                                    showFormSettingsDropdown = false;
                                "
                            >
                                {{ sponsor }}
                            </button>
                            <p v-if="sponsors.length === 0" class="px-3 py-2 text-xs text-muted-foreground">No sponsors found.</p>
                        </div>
                    </div>
                    <Button class="bg-blue-600 shadow-sm hover:bg-blue-700 dark:text-white" @click="showModal = true">
                        <Plus class="mr-1 h-4 w-4" /> Add Program
                    </Button>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <div class="relative max-w-sm flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search programs..."
                            class="w-full rounded-lg border bg-background py-2 pl-9 pr-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex items-center gap-1.5 rounded-lg border bg-muted/30 px-2 py-1.5 text-xs text-muted-foreground">
                        <SlidersHorizontal class="h-3.5 w-3.5" />
                        <span>Filters</span>
                    </div>
                    <Button v-if="hasActiveFilters" variant="ghost" class="gap-1 text-xs text-muted-foreground" @click="clearFilters">
                        <X class="h-3.5 w-3.5" /> Clear all
                    </Button>
                </div>

                <div class="grid grid-cols-2 gap-2 rounded-xl border bg-muted/30 p-3 md:grid-cols-4 lg:grid-cols-7">
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <CheckCircle2 class="h-3 w-3" /> Status
                        </label>
                        <select v-model="filterStatus" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="">All</option>
                            <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <CalendarDays class="h-3 w-3" /> Year
                        </label>
                        <select v-model="filterYear" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="">All</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <Clock class="h-3 w-3" /> Semester
                        </label>
                        <select v-model="filterSemester" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="">All</option>
                            <option value="1">1st (Jan–Jun)</option>
                            <option value="2">2nd (Jul–Dec)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <Tag class="h-3 w-3" /> Category
                        </label>
                        <select v-model="filterCategory" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="">All</option>
                            <option value="Foreign">Foreign</option>
                            <option value="Bilateral">Bilateral</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <Building class="h-3 w-3" /> Organization
                        </label>
                        <select v-model="filterOrg" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm">
                            <option value="">All</option>
                            <option v-for="s in props.sponsorOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <MapPin class="h-3 w-3" /> Embassy Deadline
                        </label>
                        <input v-model="filterEmbassy" type="date" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <Users class="h-3 w-3" /> Interview Date
                        </label>
                        <input v-model="filterInterview" type="date" class="rounded-lg border bg-background px-2 py-1.5 text-xs shadow-sm" />
                    </div>
                </div>

                <p class="text-xs text-muted-foreground">
                    Showing {{ programs.from ?? 0 }}–{{ programs.to ?? 0 }} of {{ programs.total }} program(s)
                </p>
            </div>

            <!-- List -->
            <div class="overflow-hidden rounded-2xl border shadow-sm">
                <table v-if="programs.data.length" class="w-full table-fixed border-collapse text-sm">
                    <colgroup>
                        <col style="width: 32%" />
                        <col style="width: 8%" />
                        <col style="width: 19%" />
                        <col style="width: 11%" />
                        <col style="width: 11%" />
                        <col style="width: 11%" />
                        <col style="width: 8%" />
                        <col style="width: 112px" />
                    </colgroup>
                    <thead>
                        <tr class="border-b bg-muted/50 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-2.5 text-left font-semibold">Program</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Sponsor</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Schedule</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Modality</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Interview Date</th>
                            <th class="px-4 py-2.5 text-left font-semibold">Embassy Deadline</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Slots</th>
                            <th class="px-4 py-2.5 text-center font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="program in programs.data"
                            :key="program.id"
                            class="group border-b transition-colors last:border-b-0 hover:bg-blue-50/50 dark:hover:bg-blue-950/20"
                        >
                            <td class="px-4 py-3 align-middle">
                                <Link :href="route('foreign-programs.show', program.id)" class="flex min-w-0 flex-col gap-0.5">
                                    <span class="truncate font-semibold leading-snug transition-colors group-hover:text-blue-600">{{
                                        program.program_title
                                    }}</span>
                                    <span class="w-fit rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusColors[program.status]">
                                        {{ statusLabels[program.status] }}
                                    </span>
                                    <span
                                        v-if="program.created_by_empcode"
                                        class="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground"
                                        :title="`Added by ${program.created_by_name} (${program.created_by_empcode})`"
                                    >
                                        <UserRound class="h-2.5 w-2.5 shrink-0" /> {{ program.created_by_empcode }}
                                    </span>
                                </Link>
                            </td>

                            <td class="px-4 py-3 align-middle text-xs text-muted-foreground">
                                <div class="flex items-center gap-1.5 truncate" :title="sponsorDisplay(program)">
                                    <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                    <span class="truncate">{{ program.organizing_sponsor }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-middle text-xs text-muted-foreground">
                                <div class="flex items-center gap-1.5 whitespace-nowrap">
                                    <Calendar class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                    <span>{{ formatDate(program.program_start) }} – {{ formatDate(program.program_end) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-middle text-xs capitalize text-muted-foreground">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ modalityIcons[program.modality] }}</span>
                                    <span>{{ program.modality }}</span>
                                </div>
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 align-middle text-xs"
                                :class="program.interview_date ? 'text-violet-600' : 'text-muted-foreground'"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Users class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ formatDate(program.interview_date) }}</span>
                                </div>
                            </td>
                            <td
                                class="whitespace-nowrap px-4 py-3 align-middle text-xs"
                                :class="program.embassy_deadline ? 'text-red-600' : 'text-muted-foreground'"
                            >
                                <div class="flex items-center gap-1.5">
                                    <MapPin class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ formatDate(program.embassy_deadline) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right align-middle text-muted-foreground">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Users class="h-3.5 w-3.5 text-blue-400" />
                                    <span>
                                        <span class="font-semibold text-foreground">{{ program.nominees_count }}</span>
                                        <span> / {{ program.slots }}</span>
                                    </span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 align-middle">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        @click="openView(program)"
                                        class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-950/30"
                                        title="View details"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="openEdit(program)"
                                        class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-950/30"
                                        title="Edit program"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button
                                        @click="confirmDelete(program.id)"
                                        class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30"
                                        title="Delete program"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="programs.data.length === 0" class="flex flex-col items-center justify-center gap-3 py-24 text-muted-foreground">
                    <div class="relative">
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950/30">
                            <Globe class="h-10 w-10 text-blue-300" />
                        </div>
                        <div
                            class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-background bg-muted"
                        >
                            <Search class="h-3.5 w-3.5 text-muted-foreground" />
                        </div>
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-semibold">No programs found.</p>
                        <p class="mt-1 text-xs">Try adjusting your search or filters.</p>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="programs.last_page > 1" class="flex items-center justify-between text-sm">
                <p class="text-xs text-muted-foreground">Page {{ programs.current_page }} of {{ programs.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="link in programs.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs transition-colors"
                            :class="link.active ? 'border-blue-600 bg-blue-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                        >
                            <ChevronLeft v-if="link.label.includes('Previous')" class="h-3.5 w-3.5" />
                            <ChevronRight v-else-if="link.label.includes('Next')" class="h-3.5 w-3.5" />
                            <span v-else v-html="link.label" />
                        </Link>
                        <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs text-muted-foreground opacity-40">
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
            <div class="w-full max-w-lg rounded-2xl bg-background shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b px-6 pb-4 pt-6">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Program Details</p>
                        <h2 class="text-base font-bold leading-snug">{{ viewProgram.program_title }}</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ sponsorDisplay(viewProgram) }}</p>
                    </div>
                    <button @click="closeView" class="mt-0.5 shrink-0 text-muted-foreground transition-colors hover:text-foreground">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="flex flex-col gap-4 px-6 py-5">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Period</p>
                            <p class="font-medium">{{ formatDate(viewProgram.program_start) }} – {{ formatDate(viewProgram.program_end) }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Slots</p>
                            <p class="font-medium">{{ viewProgram.nominees_count }} / {{ viewProgram.slots }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Modality</p>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="modalityColors[viewProgram.modality]"
                            >
                                {{ modalityIcons[viewProgram.modality] }} {{ viewProgram.modality }}
                            </span>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Status</p>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusColors[viewProgram.status]">
                                {{ statusLabels[viewProgram.status] }}
                            </span>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Submission Date</p>
                            <p class="font-medium">{{ formatDate(viewProgram.submission_date) }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Embassy Deadline</p>
                            <p class="font-medium" :class="viewProgram.embassy_deadline ? 'text-red-600' : ''">
                                {{ formatDate(viewProgram.embassy_deadline) }}
                            </p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Interview Date</p>
                            <p class="font-medium" :class="viewProgram.interview_date ? 'text-violet-600' : ''">
                                {{ formatDate(viewProgram.interview_date) }}
                            </p>
                        </div>
                        <div>
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Attached Agency</p>
                            <p class="font-medium">{{ viewProgram.attached_agency || '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Invited Agencies</p>
                            <p class="font-medium">{{ viewProgram.invited_agencies || '—' }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Added By</p>
                            <p class="font-medium">
                                <template v-if="viewProgram.created_by_empcode">
                                    {{ viewProgram.created_by_name }}
                                    <span class="font-mono text-xs text-muted-foreground">({{ viewProgram.created_by_empcode }})</span>
                                </template>
                                <template v-else>—</template>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t px-6 py-4">
                    <Link
                        :href="route('foreign-programs.show', viewProgram.id)"
                        class="flex items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700"
                    >
                        <Users class="h-4 w-4" /> View Participants
                    </Link>
                    <Button variant="outline" @click="closeView">Close</Button>
                </div>
            </div>
        </div>

        <EditProgramModal :open="showEditModal" :program="editingProgram" @update:open="showEditModal = $event" />

        <!-- ===== Add Program Modal ===== -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showModal = false">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-background shadow-2xl">
                <div class="sticky top-0 z-10 flex items-center gap-3 rounded-t-2xl border-b bg-background px-6 py-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 shadow">
                        <Globe class="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold leading-none">Add Foreign Program</h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">Fill in the details for the new program</p>
                    </div>
                    <button @click="showModal = false" class="ml-auto text-muted-foreground transition-colors hover:text-foreground">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex flex-col gap-6 p-6">
                    <!-- Basic Info -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1 md:col-span-2">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.program_title"
                                    type="text"
                                    placeholder="e.g. JICA Training on Public Administration"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <span v-if="form.errors.program_title" class="text-xs text-red-500">{{ form.errors.program_title }}</span>
                            </div>
                            <div class="flex flex-col gap-1 md:col-span-2">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="resize-none rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Optional — brief overview of the program"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <CalendarDays class="h-3.5 w-3.5" /> <span>Schedule</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.program_start"
                                    type="date"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.program_end"
                                    type="date"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.slots"
                                    type="number"
                                    min="1"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.modality"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="in-person">🏢 In-person</option>
                                    <option value="online">💻 Online</option>
                                    <option value="hybrid">🔀 Hybrid</option>
                                </select>
                            </div>
                        </div>

                        <template v-if="showOnlineDates">
                            <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-950/30">
                                <p class="mb-3 text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400">
                                    💻 Online Schedule
                                </p>
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online Start</label>
                                        <input
                                            v-model="form.online_start"
                                            type="date"
                                            class="rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 dark:bg-background"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-xs font-semibold">Online End</label>
                                        <input
                                            v-model="form.online_end"
                                            type="date"
                                            class="rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400 dark:bg-background"
                                        />
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
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="form.category"
                                        class="rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-background"
                                    >
                                        <option value="Foreign">🌐 Foreign</option>
                                        <option value="Bilateral">🤝 Bilateral</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                    </label>
                                    <input
                                        v-model="form.program_cost"
                                        type="text"
                                        class="rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-background"
                                        placeholder="e.g. 50,000"
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                    </label>
                                    <select
                                        v-model="form.fund_source"
                                        class="rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-background"
                                    >
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
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <select
                                        v-model="form.organizing_sponsor"
                                        class="flex-1 rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">— Select sponsor —</option>
                                        <option v-for="s in sponsors" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                    <button
                                        type="button"
                                        class="whitespace-nowrap rounded-lg border px-3 py-2 text-xs font-semibold text-blue-600 transition-colors hover:bg-blue-50"
                                        @click="openSponsorModal()"
                                    >
                                        + Manage
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                                </label>
                                <select
                                    v-model="form.status"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
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
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                                </label>
                                <input
                                    v-model="form.submission_date"
                                    type="date"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                                </label>
                                <input
                                    v-model="form.interview_date"
                                    type="date"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                                </label>
                                <input
                                    v-model="form.embassy_deadline"
                                    type="date"
                                    class="rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Agencies -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                            <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="flex items-center gap-1.5 text-xs font-semibold">
                                <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                            </label>
                            <textarea
                                v-model="form.invited_agencies"
                                rows="2"
                                class="resize-none rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Comma-separated, e.g. DILG, DBM, CSC"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div class="sticky bottom-0 flex justify-end gap-2 rounded-b-2xl border-t bg-background px-6 py-4">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="form.processing" @click="submit">
                        <Plus v-if="!form.processing" class="mr-1 h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Save Program' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
