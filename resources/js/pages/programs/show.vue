<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { 
    ArrowLeft, 
    Trash2, 
    Info, 
    Users, 
    FileText, 
    Pencil, 
    Lightbulb, 
    Plus, 
    X, 
    FilePenLine, 
    ScrollText, 
    Settings2, 
    Presentation, 
    House, 
    Play, 
    Handshake, 
    Coins,
    HandCoins,
    Flag,
    Megaphone 
} from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import BatchList from '@/pages/programs/BatchList.vue';
import CompetencyModal from '@/pages/programs/CompetencyModal.vue';
import RequirementList from '@/pages/programs/RequirementList.vue'; 
import SubmissionList from '@/pages/programs/SubmissionList.vue';
import SupportingDocuments from '@/pages/programs/SupportingDocuments.vue';
import ResourceSpeakers from '@/pages/programs/ResourceSpeakers.vue';
import CoverPagePanel from '@/pages/programs/CoverPagePanel.vue';


interface Requirement {
    id: number;
    batch_id: number;
    title: string;
    name: string;
    due_date: string;
    is_required: boolean;
    note: string | null;
}

interface Batch {
    id: number;
    sort_order: number;
    program_code: string;
    batch: string;
    status: string;
    modality: string;
    venue: string | null;
    date_start: string;
    date_end: string;
    time_start: string;
    time_end: string;
    days: string;
    hours: string;
    requirements?: Requirement[]; 
}

interface Competency {
    id: number;
    domain: string;
    competency: string;
}

interface SupportingDocument {
    id: number;
    program_id: number;
    program_code: string | null;
    document_type: string;
    subject: string;
    document_series: number;
    origin: string | null;
    document_number: string;
    date_issued: string | null;
    link: string | null;
}

interface ResourceSpeaker {
    id: number;
    program_id: number;
    program_code: string | null;
    name: string;
    designation: string | null;
    affiliation: string | null;
    topic: string | null;
    expertise: string | null;
    email: string | null;
    contact_number: string | null;
    date_engaged: string | null;
    remarks: string | null;
}

interface Program {
    id: number;
    program_code: string;
    title: string;
    description: string;
    modality: string;
    pax: string;
    category: string;
    type: string;
    initiated: string;
    provider: string;
    cost: string;
    fund: string;
    origin: string;
    created_at: string;
    batches?: Batch[];
    competencies?: Competency[];
    supporting_documents?: SupportingDocument[];
    resource_speakers?: ResourceSpeaker[];
    cover_page?: { id: number; image: string; image_url: string | null } | null;
}

const props = defineProps<{
    program: Program;
    submissions: any[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Programs', href: '/programs' },
    { title: props.program.program_code, href: `/programs/${props.program.id}` },
];

const deleteProgram = () => {
    if (confirm('Are you sure you want to delete this program?')) {
        router.delete(route('programs.destroy', props.program.id), {
            onSuccess: () => router.visit(route('programs.index')),
        });
    }
};

/* ===================== COMPETENCIES ===================== */

const showCompetencyModal = ref(false);

const DOMAIN_ORDER = ['Leadership', 'Core', 'Organizational', 'Technical', 'TTI'];

const DOMAIN_COLORS: Record<string, string> = {
    Leadership: 'text-purple-500',
    Core: 'text-blue-500',
    Organizational: 'text-emerald-500',
    Technical: 'text-orange-500',
    TTI: 'text-rose-500',
};

// Grouped per domain para sa sidebar display
const groupedCompetencies = computed(() => {
    const list = props.program.competencies ?? [];
    return DOMAIN_ORDER
        .map((domain) => ({
            domain,
            items: list.filter((c) => c.domain === domain),
        }))
        .filter((g) => g.items.length > 0);
});

// Names ng naka-add na, para hindi na lumabas sa choices ng modal
const existingCompetencyNames = computed(() =>
    (props.program.competencies ?? []).map((c) => c.competency),
);

const removeCompetency = (competency: Competency) => {
    if (confirm('Remove this competency from the program?')) {
        router.delete(route('programs.competencies.destroy', [props.program.id, competency.id]), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head :title="program.program_code" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b px-6 pb-4 pt-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="router.visit(route('programs.index'))">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <!-- <p class="text-xs text-slate-400 font-semibold">{{ program.program_code }}</p> -->
                        <h1 class="text-xl font-extrabold dark:text-yellow-500 leading-tight text-sky-900">{{ program.title }}</h1>
                    </div>
                </div>
                <!-- Header buttons -->
                <div class="flex items-center gap-2">
                    <Link :href="route('programs.edit', program.id)">
                        <Button variant="outline" size="sm">
                            <Pencil class="h-4 w-4 mr-1" /> Edit
                        </Button>
                    </Link>
                    <Button variant="destructive" size="sm" @click="deleteProgram">
                        <Trash2 class="h-4 w-4 mr-1" /> Delete
                    </Button>
                </div>
            </div>

            <!-- Tabs -->
            <Tabs default-value="details" class="flex flex-col flex-1">

                <div class="border-b px-6">
                    <TabsList class="bg-transparent p-0 h-auto gap-0 rounded-none">
                        
                        <TabsTrigger
                            value="details"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                        <span class="flex items-center gap-1"><Info class="h-4 w-4" /> Details</span>

                        </TabsTrigger>
                        <TabsTrigger
                            value="participants"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                            <span class="flex items-center gap-1"><Users class="h-4 w-4" /> Participants</span>
                        </TabsTrigger>
                        <TabsTrigger
                            value="submissions"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                            <span class="flex items-center gap-1"><FileText class="h-4 w-4" /> Submissions</span>
                        </TabsTrigger>

                        <TabsTrigger
                            value="requirements"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                            <span class="flex items-center gap-1"><FilePenLine class="h-4 w-4" /> Requirements</span>
                        </TabsTrigger>

                        <TabsTrigger
                            value="Supporting"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                            <span class="flex items-center gap-1"><ScrollText class="h-4 w-4" /> Supporting Docs</span>
                        </TabsTrigger>

                        <TabsTrigger
                            value="resource"
                            class="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:shadow-none px-4 pb-3 pt-1 text-sm font-medium"
                        >
                            <span class="flex items-center gap-1"><Megaphone class="h-4 w-4" /> Resource Speaker</span>
                        </TabsTrigger>

                    </TabsList>
                </div>

                <!-- Details Tab -->
                <TabsContent value="details" class="flex flex-col gap-4 px-6 py-4 mt-0">

                    <!-- HINATI: details sa kaliwa, competencies sa kanan -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

                        <!-- LEFT: Program Details (2/3 ng lapad) -->
                        <div class="lg:col-span-2 flex flex-col gap-4">
                            <h1 class="font-bold">Program Details</h1>

                            <CoverPagePanel
                                :program-id="program.id"
                                :cover-page="program.cover_page"
                            />

                            <!-- Description -->
                            <div class="rounded-2xl border p-4 shadow-sm">
                                <p class="text-xs font-semibold text-muted-foreground mb-1">Description</p>
                                <p class="text-sm leading-relaxed">{{ program.description || 'No description provided.' }}</p>
                            </div>

                            

                            <!-- Details Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Modality</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Presentation class="w-4 text-blue-500"/> {{ program.modality }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Category</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><House class="w-4 text-indigo-500" />{{ program.category }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Program Type</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Settings2 class="w-5" /> {{ program.type }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground"> Target Pax</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Users class="w-5 text-purple-500"/> {{ program.pax }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Cost</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Coins class="w-5 text-yellow-500" />{{ Number(program.cost).toLocaleString() }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Fund Source</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><HandCoins class="w-5 text-green-500" />{{ program.fund }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Office Initiated</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Play class="w-5 text-orange-500" /> {{ program.initiated }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Provider</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Handshake class="w-8 text-blue-500" />{{ program.provider || '—' }}</p>
                                </div>
                                <div class="rounded-2xl border p-4 flex flex-col gap-1">
                                    <p class="text-xs text-muted-foreground">Origin</p>
                                    <p class="text-sm font-bold flex items-center gap-2"><Flag class="w-5 text-emerald-500" />{{ program.origin }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: Competencies sidebar (1/3 ng lapad) -->
                        <div class="rounded-2xl border p-4 shadow-sm flex flex-col gap-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold flex items-center gap-1.5">
                                    <Lightbulb class="h-4 w-4 text-yellow-500" /> Competencies
                                </p>
                                <Button
                                    size="sm"
                                    class="h-7 text-xs bg-blue-600 hover:bg-blue-700 dark:text-white"
                                    @click="showCompetencyModal = true"
                                >
                                    <Plus class="h-3.5 w-3.5 mr-0.5" /> Add Competency
                                </Button>
                            </div>

                            <!-- Grouped list -->
                            <template v-if="groupedCompetencies.length">
                                <div
                                    v-for="group in groupedCompetencies"
                                    :key="group.domain"
                                    class="flex flex-col gap-1.5"
                                >
                                    <p
                                        class="text-[11px] font-extrabold uppercase tracking-wide"
                                        :class="DOMAIN_COLORS[group.domain]"
                                    >
                                        {{ group.domain }}
                                    </p>
                                    <div
                                        v-for="c in group.items"
                                        :key="c.id"
                                        class="group flex items-start justify-between gap-2 rounded-lg border px-2.5 py-1.5"
                                    >
                                        <p class="text-xs leading-snug">{{ c.competency }}</p>
                                        <button
                                            type="button"
                                            class="shrink-0 mt-0.5 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-red-500 transition-all"
                                            @click="removeCompetency(c)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty state -->
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
                            >
                                <p class="text-xs font-semibold">No competencies yet.</p>
                                <p class="text-[11px] mt-1">Click "Add Competency" to attach competencies to this program.</p>
                            </div>
                        </div>

                    </div>

                    <!-- Competency picker modal -->
                    <CompetencyModal
                        :open="showCompetencyModal"
                        :program-id="program.id"
                        :existing="existingCompetencyNames"
                        @update:open="showCompetencyModal = $event"
                    />

                </TabsContent>

                <!-- Participants Tab -->
                <TabsContent value="participants" class="flex flex-col gap-4 px-6  mt-0">

                    <!-- BATCH SECTION: list ng batches + Add Batch modal -->
                    <BatchList :program="program" :batches="program.batches ?? []" />

                </TabsContent>

                <!-- Submissions Tab -->
                <TabsContent value="submissions" class="flex flex-col gap-4 px-6 py-4 mt-0">
                    <SubmissionList :program="program" :submissions="submissions" />
                </TabsContent>

                <!-- Requirements Tab -->
                <TabsContent value="requirements" class="flex flex-col gap-4 px-6 py-4 mt-0">
                    <RequirementList :program="program" />
                </TabsContent>

                <!-- Supporting Documents Tab -->
                <TabsContent value="Supporting" class="flex flex-col gap-4 px-6 py-4 mt-0">
                    <SupportingDocuments :program="program" />
                </TabsContent>

                <!-- Resource Speakers Tab -->
                <TabsContent value="resource" class="flex flex-col gap-4 px-6 py-4 mt-0">
                    <ResourceSpeakers :program="program" />
                </TabsContent>

            </Tabs>

        </div>
    </AppLayout>
</template>