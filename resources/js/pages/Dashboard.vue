<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useLiteMode } from '@/composables/useLiteMode';
import { useMotionLevel } from '@/composables/useMotionLevel';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { BarChart3, Feather } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import DashboardSummaryBanner from '../components/DashboardSummaryBanner.vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import DirectoryAmbience from '../components/position-directory/DirectoryAmbience.vue';
import ReapComplianceCard from '../components/ReapComplianceCard.vue';
import ReportsModal from '../components/ReportsModal.vue';
import SupervisoryComplianceCard from '../components/SupervisoryComplianceCard.vue';
import TdorComplianceCard from '../components/TdorComplianceCard.vue';
import TrainingComplianceCard from '../components/TrainingComplianceCard.vue';
import TreapComplianceCard from '../components/TreapComplianceCard.vue';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

defineProps<{
    // Deferred prop (loaded after the first paint).
    summary?: {
        employees: number;
        programs: number;
        active_batches: number;
        pending_submissions: number;
    };
    years: number[];
    firstName: string | null;
}>();

/* ===================== SHARED FILTERS ===================== */

const REGIONS = ['ALL', 'CO', 'NCR', 'R1', 'R2', 'R3', 'R4A', 'R4B', 'R5', 'NIR', 'R6', 'R7', 'R8', 'R9', 'R10', 'R11', 'R12', 'CAR', 'CARAGA'];

const STATUS_OPTIONS = ['PERMANENT', 'JOB ORDER', 'CONTRACTUAL', 'CTI'];

const target = ref<'Nationwide' | 'OPCR'>('Nationwide');
const region = ref('ALL');
// Naka-uncheck by default ang JOB ORDER — pero kapag manual na na-check ito
// ng user, sinasama na ito sa bilang (parehong dinaanan ng shared filter
// props kaya awtomatikong na-a-apply sa lahat ng compliance card).
const selectedStatuses = ref<string[]>(STATUS_OPTIONS.filter((s) => s !== 'JOB ORDER'));

// Lite mode (slow connections): skip the chart cards and their data requests until asked.
const { isLiteMode, setLiteMode } = useLiteMode();

// Background animation follows the same rules as the Employees page (calm in lite mode, still for reduced motion).
const motionLevel = useMotionLevel();

const office = ref('ALL');
const officeOptions = ref<string[]>([]);

// Default: kasalukuyang taon.
const year = ref<string>(String(new Date().getFullYear()));

const toggleStatus = (status: string) => {
    if (selectedStatuses.value.includes(status)) {
        selectedStatuses.value = selectedStatuses.value.filter((s) => s !== status);
    } else {
        selectedStatuses.value = [...selectedStatuses.value, status];
    }
};

// Goes through axios so failures get the app-wide friendly error toast (lib/http.ts).
const fetchOffices = async () => {
    const { data } = await axios.get(route('dashboard.offices'), { params: { region: region.value } });
    officeOptions.value = data;
    office.value = 'ALL';
};

onMounted(fetchOffices);

watch(region, fetchOffices);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="tdi-dashboard pd-theme is-ambient flex h-full flex-1 flex-col rounded-xl">
            <!-- Same ambient background as the Employees page (lights, beams, circuit lines, particles) -->
            <DirectoryAmbience :motion-level="motionLevel" :tone="0" />

            <div class="relative z-[1] flex flex-1 flex-col gap-4 p-4">
                <!-- ===================== SUMMARY BANNER ===================== -->
                <DashboardSummaryBanner :summary="summary" :first-name="firstName" />

                <!-- ===================== SHARED FILTER BAR ===================== -->
                <div
                    class="tdi-card tdi-accent-navy tdi-accent-to-sky relative isolate flex flex-wrap items-center gap-3 overflow-hidden rounded-xl border px-5 py-3"
                >
                    <!-- Target filter -->
                    <Select v-model="target">
                        <SelectTrigger class="h-9 w-44 text-xs font-semibold">
                            <SelectValue placeholder="Select target" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="Nationwide">Nationwide Target</SelectItem>
                            <SelectItem class="text-xs" value="OPCR">OPCR Target</SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Region filter -->
                    <Select v-model="region">
                        <SelectTrigger class="h-9 w-36 text-xs font-semibold">
                            <SelectValue placeholder="Select region" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in REGIONS" :key="r" class="text-xs" :value="r">
                                {{ r === 'ALL' ? 'All Regions' : r }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <Select v-model="office">
                        <SelectTrigger class="h-9 w-48 text-xs font-semibold">
                            <SelectValue placeholder="All Offices" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="ALL">All Offices</SelectItem>
                            <SelectItem v-for="o in officeOptions" :key="o" class="text-xs" :value="o">
                                {{ o }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Year filter -->
                    <Select v-model="year">
                        <SelectTrigger class="h-9 w-28 text-xs font-semibold">
                            <SelectValue placeholder="Year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem class="text-xs" value="ALL">All Years</SelectItem>
                            <SelectItem v-for="y in years" :key="y" class="text-xs" :value="String(y)">
                                {{ y }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Divider -->
                    <div class="h-5 w-px bg-border"></div>

                    <!-- Plantilla status checkboxes -->
                    <div class="flex flex-wrap items-center gap-3">
                        <label v-for="status in STATUS_OPTIONS" :key="status" class="flex cursor-pointer select-none items-center gap-1.5">
                            <input
                                type="checkbox"
                                class="h-4 w-4 cursor-pointer rounded-full accent-amber-500"
                                :checked="selectedStatuses.includes(status)"
                                @change="toggleStatus(status)"
                            />
                            <span class="text-[11px] font-bold tracking-wide text-muted-foreground">{{ status }}</span>
                        </label>
                    </div>

                    <!-- Reports — pumili ng Report Time Range, tapos Download CSV (programs + batches + participants + attendance) -->
                    <div class="ml-auto flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-9 text-xs font-semibold"
                            :aria-pressed="isLiteMode"
                            @click="setLiteMode(!isLiteMode)"
                        >
                            <BarChart3 v-if="isLiteMode" class="h-4 w-4" />
                            <Feather v-else class="h-4 w-4" />
                            {{ isLiteMode ? 'Show charts' : 'Load without charts' }}
                        </Button>
                        <ReportsModal :region="region" :office="office" :target="target" :selected-statuses="selectedStatuses" />
                    </div>
                </div>

                <!-- ===================== LITE MODE ===================== -->
                <div v-if="isLiteMode" role="status" class="tdi-card flex flex-col items-center gap-2 rounded-xl border px-6 py-10 text-center">
                    <Feather class="h-6 w-6 text-muted-foreground" aria-hidden="true" />
                    <p class="text-sm font-semibold">Charts are hidden to save data</p>
                    <p class="max-w-md text-xs text-muted-foreground">
                        Lite mode is on, so compliance charts aren't loaded. Your choice is remembered on this browser.
                    </p>
                    <Button size="sm" class="mt-1" @click="setLiteMode(false)"><BarChart3 class="h-4 w-4" /> Show charts</Button>
                </div>

                <template v-else>
                    <!-- ===================== TWO CARDS SIDE BY SIDE ===================== -->
                    <div class="grid gap-4 md:grid-cols-2">
                        <TrainingComplianceCard
                            :target="target"
                            :region="region"
                            :selected-statuses="selectedStatuses"
                            :office="office"
                            :year="year"
                        />
                        <SupervisoryComplianceCard
                            :target="target"
                            :region="region"
                            :selected-statuses="selectedStatuses"
                            :office="office"
                            :year="year"
                        />
                    </div>

                    <!-- ===================== TREAP PANEL ===================== -->
                    <TreapComplianceCard :target="target" :region="region" :selected-statuses="selectedStatuses" :office="office" :year="year" />

                    <ReapComplianceCard :target="target" :region="region" :selected-statuses="selectedStatuses" :office="office" :year="year" />

                    <!-- ===================== TDOR PANEL ===================== -->
                    <TdorComplianceCard :target="target" :region="region" :selected-statuses="selectedStatuses" :office="office" :year="year" />

                    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                        <div class="tdi-card relative isolate aspect-video overflow-hidden rounded-xl border">
                            <PlaceholderPattern />
                        </div>
                        <div class="tdi-card relative isolate aspect-video overflow-hidden rounded-xl border">
                            <PlaceholderPattern />
                        </div>
                        <div class="tdi-card relative isolate aspect-video overflow-hidden rounded-xl border">
                            <PlaceholderPattern />
                        </div>
                    </div>

                    <div class="tdi-card relative isolate min-h-[100vh] flex-1 overflow-hidden rounded-xl border md:min-h-min">
                        <PlaceholderPattern />
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/*
 * Use the Employees page background here: its pearl (or deep navy)
 * base instead of the dashboard's dotted one, with the ambient layer on top.
 */
.tdi-dashboard.is-ambient {
    position: relative;
    background-color: var(--pd-page-bg);
    background-image: none;
}
</style>
