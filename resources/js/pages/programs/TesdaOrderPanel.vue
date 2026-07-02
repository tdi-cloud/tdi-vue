<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    FileSignature, Download, Trash2, Plus, Loader2,
    Calendar, Layers, UserCheck, Stamp,
} from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import TesdaOrderModal from '@/pages/programs/TesdaOrderModal.vue';

interface TesdaOrder {
    id: number;
    subject: string;
    series_year: number;
    total_pages: number;
    pdf_path: string | null;
    signatory_name: string;
    generated_by: string;
    created_at: string;
}

interface Program {
    id: number;
    program_code: string;
    title: string;
}

const props = defineProps<{
    program: Program;
    tesdaOrders?: TesdaOrder[];
}>();

const orders     = ref<TesdaOrder[]>(props.tesdaOrders ?? []);
const showModal  = ref(false);
const loading    = ref(false);

async function refreshOrders() {
    loading.value = true;
    const res = await fetch(route('tesda-orders.index', props.program.id));
    orders.value = await res.json();
    loading.value = false;
}

onMounted(() => {
    refreshOrders();
});

function deleteOrder(order: TesdaOrder) {
    if (!confirm(`Delete TESDA Order "${order.subject}"?`)) return;
    router.delete(route('tesda-orders.destroy', order.id), {
        onSuccess: () => refreshOrders(),
        preserveScroll: true,
    });
}

async function onGenerated() {
    showModal.value = false;
    await refreshOrders();
    // Ang PDF ay binubuksan na ng modal mismo (synchronously sa onSuccess)
    // para hindi ma-block ng popup blocker ng browser.
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Bahagyang nagbabago ang accent color ng bawat card depende sa series_year,
// para may visual variety kahit maraming cards na ang nakikita.
const accentPalette = [
    { ring: 'ring-blue-100',    bg: 'from-blue-600 to-indigo-600',   chip: 'bg-blue-50 text-blue-700' },
    { ring: 'ring-emerald-100', bg: 'from-emerald-600 to-teal-600',  chip: 'bg-emerald-50 text-emerald-700' },
    { ring: 'ring-amber-100',   bg: 'from-amber-500 to-orange-600',  chip: 'bg-amber-50 text-amber-700' },
    { ring: 'ring-purple-100',  bg: 'from-purple-600 to-fuchsia-600', chip: 'bg-purple-50 text-purple-700' },
];
function accentFor(id: number) {
    return accentPalette[id % accentPalette.length];
}
</script>

<template>
    <div class="flex flex-col gap-4">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold flex items-center gap-1.5">
                    <FileSignature class="h-4 w-4 text-blue-600" /> TESDA Orders
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Generate an official TESDA Order authorizing participants of this program.
                </p>
            </div>
            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" @click="showModal = true">
                <Plus class="h-3.5 w-3.5 mr-1" /> Generate TESDA Order
            </Button>
        </div>

        <!-- Loading -->
        <div v-if="loading && orders.length === 0" class="flex justify-center py-16">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>

        <!-- Empty state -->
        <div
            v-else-if="orders.length === 0"
            class="flex flex-col items-center justify-center py-16 text-center text-muted-foreground rounded-2xl border border-dashed"
        >
            <div class="relative mb-3">
                <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center rotate-[-6deg] shadow-sm">
                    <FileSignature class="h-6 w-6 text-blue-500" />
                </div>
                <div class="absolute -bottom-1 -right-2 h-7 w-7 rounded-full bg-amber-100 flex items-center justify-center rotate-[8deg] shadow-sm">
                    <Stamp class="h-3.5 w-3.5 text-amber-600" />
                </div>
            </div>
            <p class="text-sm font-semibold">No TESDA Orders generated yet.</p>
            <p class="text-xs mt-1">Click "Generate TESDA Order" to create one.</p>
        </div>

        <!-- Card grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div
                v-for="order in orders"
                :key="order.id"
                class="group relative rounded-2xl border bg-card overflow-hidden shadow-sm hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5"
            >
                <!-- Colored header strip with document illustration -->
                <div
                    class="relative h-20 bg-gradient-to-br px-4 py-3 flex items-start justify-between overflow-hidden"
                    :class="accentFor(order.id).bg"
                >
                    <!-- Decorative stacked "paper" illustration -->
                    <div class="absolute -right-3 -top-3 h-16 w-16 rounded-xl bg-white/15 rotate-12"></div>
                    <div class="absolute -right-1 -top-5 h-16 w-16 rounded-xl bg-white/10 rotate-[24deg]"></div>

                    <div class="relative h-9 w-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center ring-1 ring-white/30">
                        <FileSignature class="h-4.5 w-4.5 text-white" />
                    </div>

                    <span class="relative text-[10px] font-bold text-white/90 bg-black/15 rounded-full px-2 py-1 backdrop-blur-sm">
                        Series {{ order.series_year }}
                    </span>
                </div>

                <!-- Body -->
                <div class="p-4 flex flex-col gap-3">
                    <p class="text-sm font-bold leading-snug line-clamp-2 min-h-[2.5rem]">
                        {{ order.subject }}
                    </p>

                    <div class="flex flex-col gap-1.5 text-[11px] text-muted-foreground">
                        <div class="flex items-center gap-1.5">
                            <Layers class="h-3 w-3 shrink-0" />
                            <span>{{ order.total_pages }} page{{ order.total_pages === 1 ? '' : 's' }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <UserCheck class="h-3 w-3 shrink-0" />
                            <span class="truncate">Signed by {{ order.signatory_name }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Calendar class="h-3 w-3 shrink-0" />
                            <span>{{ formatDate(order.created_at) }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1 border-t">
                        <a
                            v-if="order.pdf_path"
                            :href="route('tesda-orders.download', order.id)"
                            target="_blank"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg text-xs font-bold py-2 transition-colors"
                            :class="accentFor(order.id).chip + ' hover:brightness-95'"
                        >
                            <Download class="h-3.5 w-3.5" /> View PDF
                        </a>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 shrink-0 text-muted-foreground hover:text-red-500 hover:bg-red-50"
                            @click="deleteOrder(order)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <TesdaOrderModal
            :open="showModal"
            :program="program"
            @close="showModal = false"
            @generated="onGenerated"
        />
    </div>
</template>