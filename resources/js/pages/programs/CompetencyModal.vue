<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Lightbulb, Search, Plus, TriangleAlert, X, LoaderCircle } from 'lucide-vue-next';

interface CompetencyItem {
    domain: string;
    competency: string;
}

const props = defineProps<{
    open: boolean;
    programId: number;
    /** Competency names na naka-attach na sa program (para hindi na lumabas sa choices) */
    existing: string[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const COMPETENCIES: CompetencyItem[] = [
    { domain: 'Leadership', competency: 'Practice Strategic and Critical Thinking (PSCT)' },
    { domain: 'Leadership', competency: 'Drive Performance for Integrity and Service (DPIS)' },
    { domain: 'Leadership', competency: 'Establish Linkages and Networking for Programs and Services (ELN)' },
    { domain: 'Leadership', competency: 'Plan and Organize for Greater Impact (POGI)' },
    { domain: 'Leadership', competency: 'Lead in a Continuously Changing Environment (LCCE)' },
    { domain: 'Leadership', competency: 'Develop and Empower Others to Establish Collective Accountability for Results (DEO)' },
    { domain: 'Core', competency: 'Exemplify Integrity' },
    { domain: 'Core', competency: 'Deliver Service Excellence (DSE)' },
    { domain: 'Core', competency: 'Solve Problems and Make Decisions (SPMD)' },
    { domain: 'Core', competency: 'Work Effectively in TVET (WETE)' },
    { domain: 'Organizational', competency: 'Deliver Programs and Services' },
    { domain: 'Organizational', competency: 'Develop Lifelong Learning and Career Development Interventions (DLLCDI)' },
    { domain: 'Organizational', competency: 'Write Effectively (WE)' },
    { domain: 'Organizational', competency: 'Speak Effectively (SE)' },
    { domain: 'Organizational', competency: 'Promote Learning and Innovation (PLI)' },
    { domain: 'Organizational', competency: 'Establish Teamwork (ET)' },
    { domain: 'Technical', competency: 'Financial Management - Accounting Competencies' },
    { domain: 'Technical', competency: 'Financial Management - Budgeting Competencies' },
    { domain: 'Technical', competency: 'Financial Management - Cash Management Competencies' },
    { domain: 'Technical', competency: 'Financial Management - Procurement Competencies' },
    { domain: 'Technical', competency: 'Financial Management - Financial Reporting and Analysis' },
    { domain: 'Technical', competency: 'HRM - Training and Development Competencies' },
    { domain: 'Technical', competency: 'HRM - Performance Management Competencies' },
    { domain: 'Technical', competency: 'HRM - Talent Acquisition Competencies' },
    { domain: 'Technical', competency: 'HRM - Presentation Skills' },
    { domain: 'Technical', competency: 'Information Technology' },
    { domain: 'Technical', competency: 'Effective Partnerships and Networking' },
    { domain: 'Technical', competency: 'Planning and Execution Competencies' },
    { domain: 'Technical', competency: 'Program Development and Management' },
    { domain: 'Technical', competency: 'Quality Management and Assurance' },
    { domain: 'Technical', competency: 'Standards Development' },
    { domain: 'TTI', competency: 'Conduct competency assessment' },
    { domain: 'TTI', competency: 'Develop learning materials' },
    { domain: 'TTI', competency: 'Develop learning materials for e-learning' },
    { domain: 'TTI', competency: 'Develop training curriculum' },
    { domain: 'TTI', competency: 'Implement enrolment systems and procedures' },
    { domain: 'TTI', competency: 'Evaluate training/learning effectiveness' },
    { domain: 'TTI', competency: 'Facilitate development of competency standards' },
    { domain: 'TTI', competency: 'Formulate institutional policies, guidelines and procedures' },
    { domain: 'TTI', competency: 'Facilitate learning sessions' },
    { domain: 'TTI', competency: 'Apply facilitation skills' },
    { domain: 'TTI', competency: 'Perform guidance services' },
    { domain: 'TTI', competency: 'Implement workplace health, safety, security practices and environmental requirements' },
    { domain: 'TTI', competency: 'Manage library' },
    { domain: 'TTI', competency: 'Manage training institution' },
    { domain: 'TTI', competency: 'Apply planning, organizing and delivering skills' },
    { domain: 'TTI', competency: 'Plan training sessions' },
    { domain: 'TTI', competency: 'Apply presentation skills' },
    { domain: 'TTI', competency: 'Generate resources' },
    { domain: 'TTI', competency: 'Supervise work-based learning' },
    { domain: 'TTI', competency: 'Conduct training needs assessment' },
];

const DOMAIN_ORDER = ['Leadership', 'Core', 'Organizational', 'Technical', 'TTI'];

const DOMAIN_COLORS: Record<string, string> = {
    Leadership: 'text-purple-500',
    Core: 'text-blue-500',
    Organizational: 'text-emerald-500',
    Technical: 'text-orange-500',
    TTI: 'text-rose-500',
};

const search = ref('');
const selected = ref<CompetencyItem[]>([]);
const processing = ref(false);

// I-reset ang state tuwing bubuksan ang modal
watch(() => props.open, (val) => {
    if (val) {
        search.value = '';
        selected.value = [];
    }
});

// Choices: grouped by domain, tinatanggal ang naka-add na at ang hindi tugma sa search
const grouped = computed(() => {
    const q = search.value.trim().toLowerCase();
    return DOMAIN_ORDER
        .map((domain) => ({
            domain,
            items: COMPETENCIES.filter(
                (c) =>
                    c.domain === domain &&
                    !props.existing.includes(c.competency) &&
                    (q === '' || c.competency.toLowerCase().includes(q) || c.domain.toLowerCase().includes(q)),
            ),
        }))
        .filter((g) => g.items.length > 0);
});

// Selected items, grouped din para sa "ADDED" panel
const selectedGrouped = computed(() =>
    DOMAIN_ORDER
        .map((domain) => ({
            domain,
            items: selected.value.filter((c) => c.domain === domain),
        }))
        .filter((g) => g.items.length > 0),
);

const isSelected = (item: CompetencyItem) =>
    selected.value.some((s) => s.competency === item.competency);

const toggle = (item: CompetencyItem) => {
    if (isSelected(item)) {
        selected.value = selected.value.filter((s) => s.competency !== item.competency);
    } else {
        selected.value = [...selected.value, item];
    }
};

const remove = (item: CompetencyItem) => {
    selected.value = selected.value.filter((s) => s.competency !== item.competency);
};

const submit = () => {
    if (selected.value.length === 0 || processing.value) return;
    processing.value = true;

    router.post(
        route('programs.competencies.store', props.programId),
        { competencies: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                search.value = '';
                emit('update:open', false);
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="!max-w-3xl p-0 overflow-hidden !rounded-2xl gap-0">

            <!-- Header -->
            <DialogHeader class="px-5 py-4 border-b shrink-0">
                <DialogTitle>
                    <span class="flex items-center gap-2">
                        <Lightbulb class="h-5 w-5 text-yellow-500" /> Competencies
                    </span>
                </DialogTitle>
            </DialogHeader>

            <!-- Body: choices (left) + added preview (right) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 h-[480px]">

                <!-- LEFT: search + grouped choices + footer -->
                <div class="flex flex-col border-r min-h-0">

                    <!-- Search -->
                    <div class="p-3 shrink-0">
                        <div class="relative">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                            <Input
                                v-model="search"
                                class="text-xs h-9 pl-8 rounded-xl"
                                placeholder="Search..."
                            />
                        </div>
                    </div>

                    <!-- Choices list -->
                    <div class="flex-1 overflow-y-auto px-3 pb-3 space-y-4 min-h-0">
                        <div v-for="group in grouped" :key="group.domain">
                            <p
                                class="text-[11px] font-extrabold uppercase tracking-wide mb-1.5"
                                :class="DOMAIN_COLORS[group.domain]"
                            >
                                {{ group.domain }}
                            </p>
                            <label
                                v-for="item in group.items"
                                :key="item.competency"
                                class="flex items-start gap-2.5 px-1.5 py-1.5 rounded-lg cursor-pointer hover:bg-muted/60 transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-input accent-blue-600 cursor-pointer"
                                    :checked="isSelected(item)"
                                    @change="toggle(item)"
                                />
                                <span class="text-xs leading-snug">{{ item.competency }}</span>
                            </label>
                        </div>

                        <div
                            v-if="grouped.length === 0"
                            class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
                        >
                            <p class="text-xs font-semibold">No competencies found.</p>
                            <p class="text-[11px] mt-1">Try a different search, or everything has been added already.</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="shrink-0 flex items-center justify-between border-t px-4 py-3">
                        <p class="text-xs text-muted-foreground font-medium">{{ selected.length }} selected</p>
                        <Button
                            size="sm"
                            class="bg-blue-600 hover:bg-blue-700 dark:text-white rounded-full px-5"
                            :disabled="selected.length === 0 || processing"
                            @click="submit"
                        >
                            <LoaderCircle v-if="processing" class="h-3 w-3 animate-spin mr-1" />
                            <Plus v-else class="h-4 w-4 mr-1" /> Add
                        </Button>
                    </div>
                </div>

                <!-- RIGHT: "ADDED" preview ng mga napili -->
                <div class="hidden sm:flex flex-col min-h-0">
                    <div class="px-4 py-3 shrink-0">
                        <p class="text-xs font-extrabold tracking-wide text-muted-foreground">
                            ADDED <span class="text-blue-600 ml-1">{{ selected.length }}</span>
                        </p>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="selected.length === 0"
                        class="flex-1 flex flex-col items-center justify-center text-center px-6"
                    >
                        <span class="flex items-center gap-2 text-muted-foreground">
                            <TriangleAlert class="h-4 w-4 text-red-500" />
                            <span class="text-sm">Nothing added yet.</span>
                        </span>
                    </div>

                    <!-- Selected list -->
                    <div v-else class="flex-1 overflow-y-auto px-4 pb-4 space-y-4 min-h-0">
                        <div v-for="group in selectedGrouped" :key="group.domain">
                            <p
                                class="text-[11px] font-extrabold uppercase tracking-wide mb-1.5"
                                :class="DOMAIN_COLORS[group.domain]"
                            >
                                {{ group.domain }}
                            </p>
                            <div
                                v-for="item in group.items"
                                :key="item.competency"
                                class="flex items-start justify-between gap-2 rounded-lg border px-2.5 py-1.5 mb-1.5"
                            >
                                <span class="text-xs leading-snug">{{ item.competency }}</span>
                                <button
                                    type="button"
                                    class="shrink-0 mt-0.5 text-muted-foreground hover:text-red-500 transition-colors"
                                    @click="remove(item)"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </DialogContent>
    </Dialog>
</template>