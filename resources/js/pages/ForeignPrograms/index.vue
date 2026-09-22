<script setup lang="ts">
import ForeignProgramsDashboardModal from '@/components/ForeignProgramsDashboardModal.vue';
import NominationHistoryModal from '@/components/NominationHistoryModal.vue';
import OrganizingSponsorModal from '@/components/OrganizingSponsorModal.vue';
import SponsorConfigModal from '@/components/SponsorConfigModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useConfirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    DEADLINE_URGENCY_CLASS,
    deadlineUrgency,
    formatProgramDate,
    modalityMeta,
    PROGRAM_STATUS_OPTIONS,
    programStatusMeta,
} from '@/lib/foreignPrograms';
import EditProgramModal from '@/pages/ForeignPrograms/EditProgramModal.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlignLeft,
    Banknote,
    BarChart3,
    Building,
    Building2,
    Calendar,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Earth,
    Eye,
    FileText,
    Globe,
    Hash,
    History,
    ListFilter,
    MapPin,
    Pencil,
    PlayCircle,
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Foreign Programs', href: route('foreign-programs.index') }];

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

interface ProgramStats {
    total: number;
    active: number;
    for_nomination: number;
    completed: number;
}

const props = defineProps<{
    programs: PaginatedPrograms;
    years: number[];
    sponsorOptions: string[];
    stats: ProgramStats;
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

// --- Stat cards (real backend data, no hardcoded numbers) ---
const percentOfTotal = (value: number) => (props.stats.total ? `${Math.round((value / props.stats.total) * 100)}% of total` : 'No programs yet');

const statCards = computed(() => [
    {
        key: 'total',
        label: 'Total Programs',
        value: props.stats.total,
        sub: 'All foreign programs',
        icon: Globe,
        iconBg: 'bg-blue-100 dark:bg-blue-950/40',
        iconColor: 'text-blue-600 dark:text-blue-400',
    },
    {
        key: 'active',
        label: 'Active Programs',
        value: props.stats.active,
        sub: percentOfTotal(props.stats.active),
        icon: PlayCircle,
        iconBg: 'bg-emerald-100 dark:bg-emerald-950/40',
        iconColor: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        key: 'for_nomination',
        label: 'For Nomination',
        value: props.stats.for_nomination,
        sub: percentOfTotal(props.stats.for_nomination),
        icon: Clock,
        iconBg: 'bg-amber-100 dark:bg-amber-950/40',
        iconColor: 'text-amber-600 dark:text-amber-400',
    },
    {
        key: 'completed',
        label: 'Completed Programs',
        value: props.stats.completed,
        sub: percentOfTotal(props.stats.completed),
        icon: CheckCircle2,
        iconBg: 'bg-violet-100 dark:bg-violet-950/40',
        iconColor: 'text-violet-600 dark:text-violet-400',
    },
]);

// --- Filters state ---
// Selects use the 'all' sentinel (native <Select> can't carry an empty-string
// value) and are translated back to "no filter" when the query is built.
const search = ref(props.filters.search ?? '');
const filterStatus = ref(props.filters.status || 'all');
const filterYear = ref(props.filters.year || 'all');
const filterSemester = ref(props.filters.semester || 'all');
const filterOrg = ref(props.filters.organization || 'all');
const filterCategory = ref(props.filters.category || 'all');
const filterEmbassy = ref(props.filters.embassy_deadline ?? '');
const filterInterview = ref(props.filters.interview_date ?? '');
const showFilters = ref(false);
const showAdvancedFilters = ref(false);

const hasActiveFilters = computed(() =>
    [search.value !== '', filterStatus.value !== 'all', filterYear.value !== 'all', filterSemester.value !== 'all', filterOrg.value !== 'all', filterCategory.value !== 'all', filterEmbassy.value !== '', filterInterview.value !== ''].some(
        Boolean,
    ),
);

const activeFilterCount = computed(
    () =>
        [
            filterStatus.value !== 'all',
            filterYear.value !== 'all',
            filterSemester.value !== 'all',
            filterOrg.value !== 'all',
            filterCategory.value !== 'all',
            filterEmbassy.value !== '',
            filterInterview.value !== '',
        ].filter(Boolean).length,
);

const applyFilters = () => {
    router.get(
        route('foreign-programs.index'),
        {
            search: search.value || undefined,
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            year: filterYear.value === 'all' ? undefined : filterYear.value,
            semester: filterSemester.value === 'all' ? undefined : filterSemester.value,
            organization: filterOrg.value === 'all' ? undefined : filterOrg.value,
            category: filterCategory.value === 'all' ? undefined : filterCategory.value,
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
    filterStatus.value = 'all';
    filterYear.value = 'all';
    filterSemester.value = 'all';
    filterOrg.value = 'all';
    filterCategory.value = 'all';
    filterEmbassy.value = '';
    filterInterview.value = '';
};

const semesterLabel = (value: string) => (value === '1' ? '1st Semester (Jan–Jun)' : value === '2' ? '2nd Semester (Jul–Dec)' : value);

const activeFilterChips = computed(() => {
    const chips: { key: string; label: string; remove: () => void }[] = [];
    if (filterStatus.value !== 'all') {
        chips.push({ key: 'status', label: programStatusMeta(filterStatus.value).label, remove: () => (filterStatus.value = 'all') });
    }
    if (filterYear.value !== 'all') {
        chips.push({ key: 'year', label: `Year: ${filterYear.value}`, remove: () => (filterYear.value = 'all') });
    }
    if (filterSemester.value !== 'all') {
        chips.push({ key: 'semester', label: semesterLabel(filterSemester.value), remove: () => (filterSemester.value = 'all') });
    }
    if (filterCategory.value !== 'all') {
        chips.push({ key: 'category', label: filterCategory.value, remove: () => (filterCategory.value = 'all') });
    }
    if (filterOrg.value !== 'all') {
        chips.push({ key: 'organization', label: filterOrg.value, remove: () => (filterOrg.value = 'all') });
    }
    if (filterEmbassy.value !== '') {
        chips.push({
            key: 'embassy_deadline',
            label: `Embassy: ${formatProgramDate(filterEmbassy.value)}`,
            remove: () => (filterEmbassy.value = ''),
        });
    }
    if (filterInterview.value !== '') {
        chips.push({
            key: 'interview_date',
            label: `Interview: ${formatProgramDate(filterInterview.value)}`,
            remove: () => (filterInterview.value = ''),
        });
    }
    return chips;
});

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
        preserveState: true,
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const confirmDelete = async (id: number) => {
    if (await confirmDialog('Are you sure you want to delete this program?')) {
        router.delete(route('foreign-programs.destroy', id), { preserveScroll: true, preserveState: true });
    }
};

// --- Multiple selection (bulk delete) ---
const selectedIds = ref<Set<number>>(new Set());

const allVisibleSelected = computed(
    () => props.programs.data.length > 0 && props.programs.data.every((p) => selectedIds.value.has(p.id)),
);

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        props.programs.data.forEach((p) => selectedIds.value.delete(p.id));
    } else {
        props.programs.data.forEach((p) => selectedIds.value.add(p.id));
    }
};

const toggleSelect = (id: number) => {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
};

const clearSelection = () => selectedIds.value.clear();

// Hindi na tugma sa view ang dating pinili kapag nagbago ang filter, page, o
// pagkatapos ng bulk delete — i-reset na lang ang selection sa bawat bagong list.
watch(
    () => props.programs.data,
    () => clearSelection(),
);

const bulkDeleting = ref(false);
const confirmBulkDelete = async () => {
    const count = selectedIds.value.size;
    if (!count) return;
    if (!(await confirmDialog(`Delete ${count} selected program(s)? This cannot be undone.`))) return;

    bulkDeleting.value = true;
    router.delete(route('foreign-programs.bulk-destroy'), {
        data: { ids: Array.from(selectedIds.value) },
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            bulkDeleting.value = false;
        },
    });
};

// --- Organizing Sponsors (Add Program form only — EditProgramModal.vue manages its own) ---
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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 shadow-md">
                        <Earth class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold leading-none">Foreign Programs</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Manage and monitor foreign training opportunities, nominations, and assessment progress.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
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

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card v-for="stat in statCards" :key="stat.key" class="shadow-sm">
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="stat.iconBg">
                            <component :is="stat.icon" class="h-5 w-5" :class="stat.iconColor" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-2xl font-bold leading-none">{{ stat.value }}</p>
                            <p class="mt-1.5 truncate text-xs font-semibold text-muted-foreground">{{ stat.label }}</p>
                            <p class="mt-0.5 truncate text-[11px] text-muted-foreground">{{ stat.sub }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Search & Filter Toolbar -->
            <div class="flex flex-col gap-2.5">
                <div class="flex flex-wrap items-center gap-2 rounded-xl border bg-card px-4 py-3 shadow-sm">
                    <div class="relative min-w-[220px] flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" class="h-9 w-full pl-9 pr-8 text-sm shadow-none" placeholder="Search programs by title..." />
                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                            aria-label="Clear search"
                            @click="search = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <Sheet v-model:open="showFilters">
                        <SheetTrigger as-child>
                            <Button variant="outline" class="h-9 shrink-0 gap-1.5 text-sm font-semibold">
                                <SlidersHorizontal class="h-4 w-4" /> Filters
                                <span
                                    v-if="activeFilterCount"
                                    class="ml-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-[11px] font-bold text-white"
                                >
                                    {{ activeFilterCount }}
                                </span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-sm">
                            <SheetHeader class="border-b px-5 py-4 text-left">
                                <SheetTitle class="flex items-center gap-2 text-base font-bold">
                                    <SlidersHorizontal class="h-4 w-4" /> Filters
                                </SheetTitle>
                            </SheetHeader>

                            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                                <div class="grid gap-1.5">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <CheckCircle2 class="h-3.5 w-3.5" /> Status
                                    </Label>
                                    <Select v-model="filterStatus">
                                        <SelectTrigger class="h-9 w-full text-xs">
                                            <SelectValue placeholder="All statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem class="text-xs" value="all">All statuses</SelectItem>
                                            <SelectItem v-for="opt in PROGRAM_STATUS_OPTIONS" :key="opt.value" :value="opt.value" class="text-xs">
                                                {{ opt.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="grid gap-1.5">
                                        <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                            <CalendarDays class="h-3.5 w-3.5" /> Year
                                        </Label>
                                        <Select v-model="filterYear">
                                            <SelectTrigger class="h-9 w-full text-xs">
                                                <SelectValue placeholder="All years" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem class="text-xs" value="all">All years</SelectItem>
                                                <SelectItem v-for="y in years" :key="y" :value="String(y)" class="text-xs">{{ y }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                            <Clock class="h-3.5 w-3.5" /> Semester
                                        </Label>
                                        <Select v-model="filterSemester">
                                            <SelectTrigger class="h-9 w-full text-xs">
                                                <SelectValue placeholder="All" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem class="text-xs" value="all">All</SelectItem>
                                                <SelectItem class="text-xs" value="1">1st (Jan–Jun)</SelectItem>
                                                <SelectItem class="text-xs" value="2">2nd (Jul–Dec)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Tag class="h-3.5 w-3.5" /> Category
                                    </Label>
                                    <Select v-model="filterCategory">
                                        <SelectTrigger class="h-9 w-full text-xs">
                                            <SelectValue placeholder="All categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem class="text-xs" value="all">All categories</SelectItem>
                                            <SelectItem class="text-xs" value="Foreign">Foreign</SelectItem>
                                            <SelectItem class="text-xs" value="Bilateral">Bilateral</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="grid gap-1.5">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                        <Building class="h-3.5 w-3.5" /> Organization
                                    </Label>
                                    <Select v-model="filterOrg">
                                        <SelectTrigger class="h-9 w-full text-xs">
                                            <SelectValue placeholder="All organizations" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem class="text-xs" value="all">All organizations</SelectItem>
                                            <SelectItem v-for="s in props.sponsorOptions" :key="s" :value="s" class="text-xs">{{ s }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Advanced filters: less frequently used, tucked away -->
                                <Collapsible v-model:open="showAdvancedFilters" class="rounded-lg border">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold text-muted-foreground"
                                    >
                                        <span class="flex items-center gap-1.5"><ListFilter class="h-3.5 w-3.5" /> Advanced Filters</span>
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="showAdvancedFilters ? 'rotate-180' : ''" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 border-t px-3 pb-3 pt-3">
                                        <div class="grid gap-1.5">
                                            <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                                <MapPin class="h-3.5 w-3.5" /> Embassy Deadline
                                            </Label>
                                            <Input v-model="filterEmbassy" type="date" class="h-9 w-full text-xs" />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                                <Users class="h-3.5 w-3.5" /> Interview Date
                                            </Label>
                                            <Input v-model="filterInterview" type="date" class="h-9 w-full text-xs" />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>

                            <div class="flex shrink-0 items-center justify-between border-t px-5 py-3">
                                <button
                                    type="button"
                                    class="text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!hasActiveFilters"
                                    @click="clearFilters"
                                >
                                    Clear all filters
                                </button>
                                <Button size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" @click="showFilters = false">Done</Button>
                            </div>
                        </SheetContent>
                    </Sheet>

                    <Button v-if="hasActiveFilters" variant="ghost" class="h-9 gap-1 text-xs text-muted-foreground" @click="clearFilters">
                        <X class="h-3.5 w-3.5" /> Clear all
                    </Button>
                </div>

                <!-- Active filter chips -->
                <div v-if="activeFilterChips.length" class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
                    >
                        {{ chip.label }}
                        <button
                            type="button"
                            class="transition-colors hover:text-blue-900 dark:hover:text-blue-100"
                            :aria-label="`Remove ${chip.label} filter`"
                            @click="chip.remove()"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </span>
                </div>

                <p class="text-xs font-medium text-muted-foreground">
                    Showing {{ programs.from ?? 0 }}–{{ programs.to ?? 0 }} of {{ programs.total }} program(s)
                </p>

                <!-- Bulk selection action bar -->
                <div
                    v-if="selectedIds.size"
                    class="flex items-center justify-between gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 dark:border-red-900 dark:bg-red-950/30"
                >
                    <p class="text-xs font-semibold text-red-700 dark:text-red-300">{{ selectedIds.size }} program(s) selected</p>
                    <div class="flex items-center gap-2">
                        <Button size="sm" variant="ghost" class="h-7 text-xs" @click="clearSelection"> Clear </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="h-7 text-xs"
                            :disabled="bulkDeleting"
                            @click="confirmBulkDelete"
                        >
                            <Trash2 class="mr-1 h-3.5 w-3.5" /> Delete Selected
                        </Button>
                    </div>
                </div>
            </div>

            <!-- List: table on md+, stacked cards on small screens -->
            <div class="overflow-hidden rounded-2xl border shadow-sm">
                <table v-if="programs.data.length" class="hidden w-full table-fixed border-collapse text-sm md:table">
                    <colgroup>
                        <col style="width: 36px" />
                        <col style="width: 28%" />
                        <col style="width: 8%" />
                        <col style="width: 16%" />
                        <col style="width: 10%" />
                        <col style="width: 11%" />
                        <col style="width: 11%" />
                        <col style="width: 8%" />
                        <col style="width: 112px" />
                    </colgroup>
                    <thead>
                        <tr class="border-b bg-muted/50 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <th class="px-3 py-2.5">
                                <Checkbox
                                    :checked="allVisibleSelected"
                                    aria-label="Select all programs on this page"
                                    @update:checked="toggleSelectAll"
                                />
                            </th>
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
                            :class="selectedIds.has(program.id) ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''"
                        >
                            <td class="px-3 py-3 align-middle">
                                <Checkbox
                                    :checked="selectedIds.has(program.id)"
                                    :aria-label="`Select ${program.program_title}`"
                                    @update:checked="toggleSelect(program.id)"
                                />
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <Link :href="route('foreign-programs.show', program.id)" class="flex min-w-0 flex-col gap-1">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <span class="truncate font-semibold leading-snug transition-colors group-hover:text-blue-600">{{
                                                program.program_title
                                            }}</span>
                                        </TooltipTrigger>
                                        <TooltipContent>{{ program.program_title }}</TooltipContent>
                                    </Tooltip>
                                    <Badge variant="outline" class="w-fit gap-1" :class="programStatusMeta(program.status).badgeClass">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="programStatusMeta(program.status).dotClass" />
                                        {{ programStatusMeta(program.status).label }}
                                    </Badge>
                                    <span
                                        v-if="program.created_by_empcode"
                                        class="flex items-center gap-1 text-[10px] text-muted-foreground"
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
                                    <span>{{ formatProgramDate(program.program_start) }} – {{ formatProgramDate(program.program_end) }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-middle text-xs">
                                <Badge variant="outline" class="gap-1" :class="modalityMeta(program.modality).badgeClass">
                                    <component :is="modalityMeta(program.modality).icon" class="h-3 w-3" />
                                    {{ modalityMeta(program.modality).label }}
                                </Badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-xs" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(program.interview_date) ?? 'normal']">
                                <div class="flex items-center gap-1.5">
                                    <Users class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ formatProgramDate(program.interview_date) }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-middle text-xs" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(program.embassy_deadline) ?? 'normal']">
                                <div class="flex items-center gap-1.5">
                                    <MapPin class="h-3.5 w-3.5 shrink-0" />
                                    <span>{{ formatProgramDate(program.embassy_deadline) }}</span>
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

                <!-- Stacked cards for small screens -->
                <div v-if="programs.data.length" class="flex flex-col divide-y md:hidden">
                    <div
                        v-for="program in programs.data"
                        :key="program.id"
                        class="flex flex-col gap-3 p-4"
                        :class="selectedIds.has(program.id) ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-2.5">
                                <Checkbox
                                    class="mt-0.5 shrink-0"
                                    :checked="selectedIds.has(program.id)"
                                    :aria-label="`Select ${program.program_title}`"
                                    @update:checked="toggleSelect(program.id)"
                                />
                                <Link :href="route('foreign-programs.show', program.id)" class="flex min-w-0 flex-col gap-1.5">
                                    <span class="font-semibold leading-snug">{{ program.program_title }}</span>
                                    <Badge variant="outline" class="w-fit gap-1" :class="programStatusMeta(program.status).badgeClass">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="programStatusMeta(program.status).dotClass" />
                                        {{ programStatusMeta(program.status).label }}
                                    </Badge>
                                </Link>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
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
                        </div>

                        <div class="flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                            <Building2 class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                            <span class="truncate">{{ sponsorDisplay(program) }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5 shrink-0 text-blue-400" />
                                <span>{{ formatProgramDate(program.program_start) }} – {{ formatProgramDate(program.program_end) }}</span>
                            </div>
                            <Badge variant="outline" class="gap-1" :class="modalityMeta(program.modality).badgeClass">
                                <component :is="modalityMeta(program.modality).icon" class="h-3 w-3" />
                                {{ modalityMeta(program.modality).label }}
                            </Badge>
                        </div>

                        <div class="grid grid-cols-3 gap-2 rounded-lg bg-muted/30 p-2.5 text-xs">
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-muted-foreground">Slots</p>
                                <p class="mt-0.5 font-semibold">{{ program.nominees_count }} / {{ program.slots }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-muted-foreground">Interview</p>
                                <p class="mt-0.5 font-medium" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(program.interview_date) ?? 'normal']">
                                    {{ formatProgramDate(program.interview_date) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-muted-foreground">Embassy</p>
                                <p class="mt-0.5 font-medium" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(program.embassy_deadline) ?? 'normal']">
                                    {{ formatProgramDate(program.embassy_deadline) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

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
                        <p class="mt-1 text-xs">
                            {{ hasActiveFilters ? 'Try adjusting your search or filters.' : 'Get started by adding your first foreign program.' }}
                        </p>
                    </div>
                    <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearFilters">Clear all filters</Button>
                    <Button v-else class="bg-blue-600 hover:bg-blue-700 dark:text-white" size="sm" @click="showModal = true">
                        <Plus class="mr-1 h-4 w-4" /> Add Program
                    </Button>
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

        <!-- ===== Quick View Dialog ===== -->
        <Dialog :open="!!viewProgram" @update:open="(v) => !v && closeView()">
            <DialogContent v-if="viewProgram" class="flex max-h-[85vh] max-w-2xl flex-col overflow-hidden !rounded-2xl p-0">
                <DialogHeader class="shrink-0 border-b px-6 pb-4 pt-6 text-left">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted-foreground">Program Details</p>
                    <DialogTitle class="text-base font-bold leading-snug">{{ viewProgram.program_title }}</DialogTitle>
                    <DialogDescription class="mt-0.5 text-xs">{{ sponsorDisplay(viewProgram) }}</DialogDescription>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <Badge variant="outline" class="gap-1" :class="programStatusMeta(viewProgram.status).badgeClass">
                            <span class="h-1.5 w-1.5 rounded-full" :class="programStatusMeta(viewProgram.status).dotClass" />
                            {{ programStatusMeta(viewProgram.status).label }}
                        </Badge>
                        <Badge variant="outline" class="gap-1" :class="modalityMeta(viewProgram.modality).badgeClass">
                            <component :is="modalityMeta(viewProgram.modality).icon" class="h-3 w-3" />
                            {{ modalityMeta(viewProgram.modality).label }}
                        </Badge>
                        <Badge v-if="viewProgram.category" variant="outline">{{ viewProgram.category }}</Badge>
                    </div>
                </DialogHeader>

                <Tabs default-value="overview" class="flex min-h-0 flex-1 flex-col">
                    <div class="shrink-0 overflow-x-auto border-b px-4">
                        <TabsList class="h-auto w-max gap-0 rounded-none bg-transparent p-0">
                            <TabsTrigger
                                value="overview"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Overview
                            </TabsTrigger>
                            <TabsTrigger
                                value="schedule"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Schedule
                            </TabsTrigger>
                            <TabsTrigger
                                value="funding"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Classification &amp; Funding
                            </TabsTrigger>
                            <TabsTrigger
                                value="organizer"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Organizer &amp; Agencies
                            </TabsTrigger>
                            <TabsTrigger
                                value="dates"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Key Dates
                            </TabsTrigger>
                            <TabsTrigger
                                value="nominees"
                                class="rounded-none border-b-2 border-transparent px-3 pb-2.5 pt-1 text-xs font-semibold data-[state=active]:border-blue-600 data-[state=active]:bg-transparent data-[state=active]:text-blue-600 data-[state=active]:shadow-none"
                            >
                                Nominees
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <TabsContent value="overview" class="mt-0 flex flex-col gap-4">
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Description</p>
                                <p class="text-sm">{{ viewProgram.description || 'No description provided.' }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Period</p>
                                    <p class="font-medium">
                                        {{ formatProgramDate(viewProgram.program_start) }} – {{ formatProgramDate(viewProgram.program_end) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Slots</p>
                                    <p class="font-medium">{{ viewProgram.nominees_count }} / {{ viewProgram.slots }} filled</p>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="schedule" class="mt-0 flex flex-col gap-4 text-sm">
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Program Start</p>
                                    <p class="font-medium">{{ formatProgramDate(viewProgram.program_start) }}</p>
                                </div>
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Program End</p>
                                    <p class="font-medium">{{ formatProgramDate(viewProgram.program_end) }}</p>
                                </div>
                                <template v-if="viewProgram.modality !== 'in-person'">
                                    <div>
                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Online Start</p>
                                        <p class="font-medium">{{ formatProgramDate(viewProgram.online_start) }}</p>
                                    </div>
                                    <div>
                                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Online End</p>
                                        <p class="font-medium">{{ formatProgramDate(viewProgram.online_end) }}</p>
                                    </div>
                                </template>
                            </div>
                        </TabsContent>

                        <TabsContent value="funding" class="mt-0 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Category</p>
                                <p class="font-medium">{{ viewProgram.category || '—' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Fund Source</p>
                                <p class="font-medium">{{ viewProgram.fund_source || '—' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Program Cost</p>
                                <p class="font-medium">{{ viewProgram.program_cost || '—' }}</p>
                            </div>
                        </TabsContent>

                        <TabsContent value="organizer" class="mt-0 flex flex-col gap-4 text-sm">
                            <div class="grid grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Organizing Sponsor</p>
                                    <p class="font-medium">{{ sponsorDisplay(viewProgram) }}</p>
                                </div>
                                <div>
                                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Attached Agency</p>
                                    <p class="font-medium">{{ viewProgram.attached_agency || '—' }}</p>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Invited Agencies</p>
                                <p class="font-medium">{{ viewProgram.invited_agencies || '—' }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Added By</p>
                                <p class="font-medium">
                                    <template v-if="viewProgram.created_by_empcode">
                                        {{ viewProgram.created_by_name }}
                                        <span class="font-mono text-xs text-muted-foreground">({{ viewProgram.created_by_empcode }})</span>
                                    </template>
                                    <template v-else>—</template>
                                </p>
                            </div>
                        </TabsContent>

                        <TabsContent value="dates" class="mt-0 grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Submission Date</p>
                                <p class="font-medium">{{ formatProgramDate(viewProgram.submission_date) }}</p>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Embassy Deadline</p>
                                <p class="font-medium" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(viewProgram.embassy_deadline) ?? 'normal']">
                                    {{ formatProgramDate(viewProgram.embassy_deadline) }}
                                </p>
                            </div>
                            <div>
                                <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Interview Date</p>
                                <p class="font-medium" :class="DEADLINE_URGENCY_CLASS[deadlineUrgency(viewProgram.interview_date) ?? 'normal']">
                                    {{ formatProgramDate(viewProgram.interview_date) }}
                                </p>
                            </div>
                        </TabsContent>

                        <TabsContent value="nominees" class="mt-0 flex flex-col gap-4">
                            <div class="flex items-center gap-4 rounded-xl border bg-muted/30 p-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-950/40">
                                    <Users class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-lg font-bold leading-none">{{ viewProgram.nominees_count }} / {{ viewProgram.slots }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Nominees submitted against available slots</p>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Full nominee list, requirement submissions, and assessment status are managed on the program's detail page.
                            </p>
                            <Link
                                :href="route('foreign-programs.show', viewProgram.id)"
                                class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700"
                            >
                                <Users class="h-4 w-4" /> Manage Nominees & Requirements
                            </Link>
                        </TabsContent>
                    </div>
                </Tabs>

                <DialogFooter class="shrink-0 border-t px-6 py-4 sm:justify-between">
                    <Link
                        :href="route('foreign-programs.show', viewProgram.id)"
                        class="flex items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700"
                    >
                        <Users class="h-4 w-4" /> View Full Program Page
                    </Link>
                    <Button variant="outline" @click="closeView">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <EditProgramModal :open="showEditModal" :program="editingProgram" @update:open="showEditModal = $event" />

        <!-- ===== Add Program Modal ===== -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden !rounded-2xl p-0">
                <DialogHeader class="shrink-0 flex-row items-center gap-3 space-y-0 border-b px-6 py-4 text-left">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 shadow">
                        <Globe class="h-4 w-4 text-white" />
                    </div>
                    <div>
                        <DialogTitle class="text-base font-bold leading-none">Add Foreign Program</DialogTitle>
                        <DialogDescription class="mt-0.5 text-xs">Fill in the details for the new program</DialogDescription>
                    </div>
                </DialogHeader>

                <div class="flex-1 overflow-y-auto px-6 py-6">
                    <div class="flex flex-col gap-6">
                        <!-- Basic Info -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                                <FileText class="h-3.5 w-3.5" /> <span>Basic Information</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-1 md:col-span-2">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <AlignLeft class="h-3.5 w-3.5 text-muted-foreground" /> Program Title <span class="text-red-500">*</span>
                                    </Label>
                                    <Input v-model="form.program_title" type="text" placeholder="e.g. JICA Training on Public Administration" />
                                    <span v-if="form.errors.program_title" class="text-xs text-red-500">{{ form.errors.program_title }}</span>
                                </div>
                                <div class="flex flex-col gap-1 md:col-span-2">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Description
                                    </Label>
                                    <Textarea v-model="form.description" rows="3" placeholder="Optional — brief overview of the program" />
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
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program Start <span class="text-red-500">*</span>
                                    </Label>
                                    <Input v-model="form.program_start" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Program End <span class="text-red-500">*</span>
                                    </Label>
                                    <Input v-model="form.program_end" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Hash class="h-3.5 w-3.5 text-muted-foreground" /> Slots <span class="text-red-500">*</span>
                                    </Label>
                                    <Input v-model="form.slots" type="number" min="1" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Globe class="h-3.5 w-3.5 text-muted-foreground" /> Modality <span class="text-red-500">*</span>
                                    </Label>
                                    <Select v-model="form.modality">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="in-person">In-person</SelectItem>
                                            <SelectItem value="online">Online</SelectItem>
                                            <SelectItem value="hybrid">Hybrid</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <template v-if="showOnlineDates">
                                <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-950/30">
                                    <p class="mb-3 text-xs font-extrabold uppercase tracking-wide text-purple-600 dark:text-purple-400">
                                        Online Schedule
                                    </p>
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div class="flex flex-col gap-1">
                                            <Label class="text-xs font-semibold">Online Start</Label>
                                            <Input v-model="form.online_start" type="date" class="bg-white dark:bg-background" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <Label class="text-xs font-semibold">Online End</Label>
                                            <Input v-model="form.online_end" type="date" class="bg-white dark:bg-background" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Classification & Funding -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                                <Banknote class="h-3.5 w-3.5" /> <span>Classification &amp; Funding</span>
                            </div>
                            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div class="flex flex-col gap-1">
                                        <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                            <Tag class="h-3.5 w-3.5 text-muted-foreground" /> Category <span class="text-red-500">*</span>
                                        </Label>
                                        <Select v-model="form.category">
                                            <SelectTrigger class="w-full bg-white dark:bg-background"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Foreign">Foreign</SelectItem>
                                                <SelectItem value="Bilateral">Bilateral</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                            <Banknote class="h-3.5 w-3.5 text-muted-foreground" /> Program Cost
                                        </Label>
                                        <Input v-model="form.program_cost" type="text" class="bg-white dark:bg-background" placeholder="e.g. 50,000" />
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                            <FileText class="h-3.5 w-3.5 text-muted-foreground" /> Fund Source
                                        </Label>
                                        <Select v-model="form.fund_source">
                                            <SelectTrigger class="w-full bg-white dark:bg-background"><SelectValue placeholder="— Select —" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="SDP">SDP</SelectItem>
                                                <SelectItem value="Other Office">Other Office</SelectItem>
                                                <SelectItem value="Sponsoring Organization">Sponsoring Organization</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Organizer & Status -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                                <Building2 class="h-3.5 w-3.5" /> <span>Organizer &amp; Status</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Building2 class="h-3.5 w-3.5 text-muted-foreground" /> Organizing Sponsor <span class="text-red-500">*</span>
                                    </Label>
                                    <div class="flex gap-2">
                                        <Select v-model="form.organizing_sponsor">
                                            <SelectTrigger class="w-full flex-1"><SelectValue placeholder="— Select sponsor —" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="s in sponsors" :key="s" :value="s">{{ s }}</SelectItem>
                                            </SelectContent>
                                        </Select>
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
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <CheckCircle2 class="h-3.5 w-3.5 text-muted-foreground" /> Status <span class="text-red-500">*</span>
                                    </Label>
                                    <Select v-model="form.status">
                                        <SelectTrigger class="w-full"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="opt in PROGRAM_STATUS_OPTIONS" :key="opt.value" :value="opt.value">
                                                {{ opt.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        <!-- Key Dates -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                                <CalendarClock class="h-3.5 w-3.5" /> <span>Key Dates</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Calendar class="h-3.5 w-3.5 text-muted-foreground" /> Submission Date
                                    </Label>
                                    <Input v-model="form.submission_date" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <Users class="h-3.5 w-3.5 text-muted-foreground" /> Interview Date
                                    </Label>
                                    <Input v-model="form.interview_date" type="date" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                        <MapPin class="h-3.5 w-3.5 text-muted-foreground" /> Embassy Deadline
                                    </Label>
                                    <Input v-model="form.embassy_deadline" type="date" />
                                </div>
                            </div>
                        </div>

                        <!-- Agencies -->
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                                <Building class="h-3.5 w-3.5" /> <span>Invited Agencies</span>
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="flex items-center gap-1.5 text-xs font-semibold">
                                    <Building class="h-3.5 w-3.5 text-muted-foreground" /> Agencies
                                </Label>
                                <Textarea v-model="form.invited_agencies" rows="2" placeholder="Comma-separated, e.g. DILG, DBM, CSC" />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="shrink-0 border-t px-6 py-4">
                    <Button variant="outline" @click="showModal = false">Cancel</Button>
                    <Button class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="form.processing" @click="submit">
                        <Plus v-if="!form.processing" class="mr-1 h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Save Program' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
