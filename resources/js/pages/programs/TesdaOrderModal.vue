<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    X, Bold, Italic, Underline, Strikethrough,
    AlignLeft, AlignCenter, AlignRight, AlignJustify,
    List, ListOrdered, Indent, Outdent,
    Link, RemoveFormatting, Search, Loader2, UserCheck, Save,
    FileText, Users, PenLine, Stamp,
    ChevronUp, ChevronDown, GripVertical,
} from 'lucide-vue-next';

interface Program {
    id: number;
    program_code: string;
    title: string;
}

interface Participant {
    id: number;
    office: string;
    name: string;
    position: string;
}

interface BatchGroup {
    id: number;
    batch: string | null;
    participants: Participant[];
}

const props = defineProps<{
    open: boolean;
    program: Program;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'generated'): void;
}>();

const DEFAULT_BODY_TEMPLATE = (title: string) =>
    `<p>In the interest of the service, the following TESDA officials and personnel are hereby authorized to attend the ${title} to be held on [DATE] at [VENUE]:</p>`;

const DEFAULT_CLOSURE = `<p>The abovementioned officials and personnel shall attend the program on official time. The registration fee, accommodation, transportation, and other related costs shall be charged against the available funds of their respective offices, subject to existing government accounting, budgeting, and auditing rules and regulations.</p><p>Further, in accordance with the Learning and Development Guidelines, within five (5) working days after the completion of the program, they are required to submit the following post-training requirements to the TESDA Development Institute through this link: bit.ly/TDI-TREAP:</p><ol><li>Copy of their certificate of attendance and all the learning materials; and</li><li>Terminal and Re-Entry Action Plan (TREAP) on the application of knowledge/ skills acquired from the training programme in the workplace;</li></ol><p>This Order shall take effect as indicated and shall serve as the Travel Order of the participant in attending the said program.</p>`;

const form = ref({
    subject:               '',
    date_issued:            '',
    effectivity:             '',
    supersedes:              '',
    series_year:             new Date().getFullYear(),
    include_participants:    true,   // ← auto-checked by default
    include_batch_data:      false,
    signatory_empcode:       '',
    signatory_name:          '',
    signatory_position:      '',
});

const bodyRef    = ref<HTMLElement | null>(null);
const closureRef = ref<HTMLElement | null>(null);
const processing = ref(false);
const errors     = ref<Record<string, string>>({});

// ── Participants reorder state ─────────────────────────────────────────────
const orderedBatches      = ref<BatchGroup[]>([]);
const loadingParticipants = ref(false);

async function fetchParticipants() {
    loadingParticipants.value = true;
    try {
        const res = await fetch(route('tesda-orders.participants', props.program.id));
        orderedBatches.value = await res.json();
    } finally {
        loadingParticipants.value = false;
    }
}

// Kapag nag-uncheck tapos nag-recheck ang user, i-fetch ulit kung wala pang data
watch(() => form.value.include_participants, (val) => {
    if (val && orderedBatches.value.length === 0) fetchParticipants();
});

// ── Batch reorder ──────────────────────────────────────────────────────────
function moveBatch(index: number, dir: -1 | 1) {
    const target = index + dir;
    if (target < 0 || target >= orderedBatches.value.length) return;
    const arr = [...orderedBatches.value];
    [arr[index], arr[target]] = [arr[target], arr[index]];
    orderedBatches.value = arr;
}

// ── Participant reorder within a batch ────────────────────────────────────
function moveParticipant(batchIdx: number, pIdx: number, dir: -1 | 1) {
    const target = pIdx + dir;
    const rows = [...orderedBatches.value[batchIdx].participants];
    if (target < 0 || target >= rows.length) return;
    [rows[pIdx], rows[target]] = [rows[target], rows[pIdx]];
    orderedBatches.value[batchIdx].participants = rows;
}

// ── Open modal — auto-check participants and auto-fetch ───────────────────
watch(() => props.open, async (val) => {
    if (!val) return;
    form.value = {
        subject:            props.program.title,
        date_issued:         '',
        effectivity:          '',
        supersedes:           '',
        series_year:          new Date().getFullYear(),
        include_participants: true,   // ← auto-checked
        include_batch_data:   false,
        signatory_empcode:    '',
        signatory_name:       '',
        signatory_position:   '',
    };
    orderedBatches.value = [];
    errors.value         = {};

    await nextTick();
    requestAnimationFrame(() => {
        if (bodyRef.value)    bodyRef.value.innerHTML    = DEFAULT_BODY_TEMPLATE(props.program.title);
        if (closureRef.value) closureRef.value.innerHTML = DEFAULT_CLOSURE;
    });

    // Auto-fetch participants immediately since include_participants is auto-checked
    fetchParticipants();

    signatoryQuery.value   = '';
    signatoryResults.value = [];
});

// ── Rich text commands ─────────────────────────────────────────────────────
function exec(cmd: string, value?: string) {
    document.execCommand(cmd, false, value ?? undefined);
}
function applyBlock(tag: string) { exec('formatBlock', tag); }
function insertLink() {
    const url = prompt('Enter URL:');
    if (url) exec('createLink', url);
}

const textColors      = ['#000000','#1a2744','#1d3fc4','#0CA678','#e67700','#c92a2a','#6741d9','#868e96'];
const highlightColors = ['#fff59d','#a5d6a7','#90caf9','#ef9a9a','#ce93d8','#ffffff'];

// ── Signatory search ───────────────────────────────────────────────────────
const signatoryQuery   = ref('');
const signatoryResults = ref<{ empcode: string; name: string; position: string }[]>([]);
let searchTimeout: ReturnType<typeof setTimeout>;

watch(signatoryQuery, (val) => {
    clearTimeout(searchTimeout);
    if (!val.trim()) { signatoryResults.value = []; return; }
    searchTimeout = setTimeout(async () => {
        const res = await fetch(route('tesda-orders.search-signatory') + `?q=${encodeURIComponent(val)}`);
        signatoryResults.value = await res.json();
    }, 300);
});

function selectSignatory(emp: { empcode: string; name: string; position: string }) {
    form.value.signatory_empcode  = emp.empcode;
    form.value.signatory_name     = emp.name;
    form.value.signatory_position = emp.position;
    signatoryQuery.value   = '';
    signatoryResults.value = [];
}

// ── Submit ─────────────────────────────────────────────────────────────────
function submit() {
    if (!form.value.subject.trim()) { alert('Subject is required.'); return; }
    if (!form.value.signatory_name.trim() || !form.value.signatory_position.trim()) {
        alert('Please select or enter a signatory.'); return;
    }
    processing.value = true;
    errors.value     = {};

    // Open a blank window immediately (synchronous) so the popup blocker doesn't block it.
    // We'll set the real URL once the server returns the new order ID.
    const pdfWindow = window.open('', '_blank');

    router.post(route('tesda-orders.store', props.program.id), {
        ...form.value,
        body:            bodyRef.value?.innerHTML ?? '',
        closure:         closureRef.value?.innerHTML ?? '',
        ordered_batches: form.value.include_participants && orderedBatches.value.length
            ? JSON.stringify(orderedBatches.value)
            : null,
    }, {
        onSuccess: (page) => {
            // Get new_order_id from Inertia flash data
            const newOrderId = (page.props as any).flash?.new_order_id;
            if (pdfWindow && newOrderId) {
                pdfWindow.location.href = route('tesda-orders.download', newOrderId);
            } else if (pdfWindow) {
                pdfWindow.close(); // Close blank window if no order ID
            }
            emit('generated');
        },
        onError: (errs) => {
            // Close blank window on error
            if (pdfWindow) pdfWindow.close();
            errors.value = errs;
        },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            @click.self="$emit('close')"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col max-h-[92vh] overflow-hidden border border-border">

                <!-- ── Modal Header ── -->
                <div class="flex items-center justify-between px-6 py-4 border-b shrink-0 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-lg bg-white/20 flex items-center justify-center">
                            <FileText class="h-4 w-4 text-white" />
                        </div>
                        <div>
                            <p class="font-bold text-sm text-white">Generate TESDA Order</p>
                            <p class="text-[11px] text-blue-100 truncate max-w-[400px]">{{ program.title }}</p>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="h-7 w-7 rounded-lg bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors">
                        <X class="h-3.5 w-3.5 text-white" />
                    </button>
                </div>

                <div class="overflow-y-auto flex-1 p-5 space-y-5">

                    <!-- ── Error display ── -->
                    <div v-if="Object.keys(errors).length"
                        class="rounded-xl border border-red-200 bg-red-50 p-4 space-y-1.5">
                        <p class="text-xs font-bold text-red-700 flex items-center gap-1.5">
                            <span class="inline-block h-4 w-4 rounded-full bg-red-600 text-white text-[10px] flex items-center justify-center font-bold">!</span>
                            Please fix the following errors before generating:
                        </p>
                        <ul class="space-y-0.5 pl-2">
                            <li v-for="(msg, field) in errors" :key="field"
                                class="text-xs text-red-600">
                                <span class="font-semibold capitalize">{{ field.replace(/_/g, ' ') }}:</span> {{ msg }}
                            </li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-blue-200 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2 bg-blue-100 border-b border-blue-200">
                            <FileText class="h-3.5 w-3.5 text-blue-600" />
                            <p class="text-xs font-bold text-blue-700 uppercase tracking-wide">Document Header</p>
                        </div>
                        <div class="p-4 space-y-3 bg-blue-50/40">
                            <div>
                                <label class="block text-xs font-semibold text-muted-foreground mb-1">Subject <span class="text-red-500">*</span></label>
                                <input v-model="form.subject" type="text"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition" />
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Date Issued</label>
                                    <input v-model="form.date_issued" type="date"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-blue-400 transition" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Effectivity</label>
                                    <input v-model="form.effectivity" type="text" placeholder="As indicated"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-blue-400 transition" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Supersedes</label>
                                    <input v-model="form.supersedes" type="text"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-blue-400 transition" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Series Year</label>
                                    <input v-model.number="form.series_year" type="number" :min="2000" :max="2099"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-blue-400 transition" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Shared Toolbar ── -->
                    <div class="rounded-xl border border-border bg-background px-2 py-1.5 flex items-center gap-0.5 flex-wrap sticky top-0 z-10 shadow-md">
                        <select class="text-xs rounded-lg border border-border bg-muted px-2 py-1 outline-none mr-1 cursor-pointer"
                            @change="applyBlock(($event.target as HTMLSelectElement).value)">
                            <option value="p">Normal</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="blockquote">Quote</option>
                        </select>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <button type="button" title="Bold" class="toolbar-btn" @mousedown.prevent="exec('bold')"><Bold class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Italic" class="toolbar-btn" @mousedown.prevent="exec('italic')"><Italic class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Underline" class="toolbar-btn" @mousedown.prevent="exec('underline')"><Underline class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Strikethrough" class="toolbar-btn" @mousedown.prevent="exec('strikeThrough')"><Strikethrough class="h-3.5 w-3.5" /></button>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <div class="flex items-center gap-0.5">
                            <span class="text-[10px] font-bold text-muted-foreground mr-0.5">A</span>
                            <button v-for="c in textColors" :key="'tc-'+c" type="button"
                                class="h-3.5 w-3.5 rounded-sm border border-border/50 hover:scale-110 transition-transform"
                                :style="{ background: c }" @mousedown.prevent="exec('foreColor', c)"></button>
                        </div>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <div class="flex items-center gap-0.5">
                            <span class="text-[10px] font-bold text-muted-foreground mr-0.5 bg-yellow-200 px-0.5 rounded">A</span>
                            <button v-for="c in highlightColors" :key="'hc-'+c" type="button"
                                class="h-3.5 w-3.5 rounded-sm border border-border/50 hover:scale-110 transition-transform"
                                :style="{ background: c }" @mousedown.prevent="exec('hiliteColor', c)"></button>
                        </div>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <button type="button" title="Align left" class="toolbar-btn" @mousedown.prevent="exec('justifyLeft')"><AlignLeft class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Align center" class="toolbar-btn" @mousedown.prevent="exec('justifyCenter')"><AlignCenter class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Align right" class="toolbar-btn" @mousedown.prevent="exec('justifyRight')"><AlignRight class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Justify" class="toolbar-btn" @mousedown.prevent="exec('justifyFull')"><AlignJustify class="h-3.5 w-3.5" /></button>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <button type="button" title="Bullet list" class="toolbar-btn" @mousedown.prevent="exec('insertUnorderedList')"><List class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Numbered list" class="toolbar-btn" @mousedown.prevent="exec('insertOrderedList')"><ListOrdered class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Indent" class="toolbar-btn" @mousedown.prevent="exec('indent')"><Indent class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Outdent" class="toolbar-btn" @mousedown.prevent="exec('outdent')"><Outdent class="h-3.5 w-3.5" /></button>
                        <span class="w-px h-4 bg-border mx-1"></span>
                        <button type="button" title="Insert link" class="toolbar-btn" @mousedown.prevent="insertLink"><Link class="h-3.5 w-3.5" /></button>
                        <button type="button" title="Clear formatting" class="toolbar-btn" @mousedown.prevent="exec('removeFormat')"><RemoveFormatting class="h-3.5 w-3.5" /></button>
                    </div>

                    <!-- ── Section 2: Body ── -->
                    <div class="rounded-xl border border-emerald-200 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2 bg-emerald-100 border-b border-emerald-200">
                            <PenLine class="h-3.5 w-3.5 text-emerald-600" />
                            <p class="text-xs font-bold text-emerald-700 uppercase tracking-wide">Body</p>
                            <span class="ml-auto text-[10px] text-emerald-500">Main content of the order</span>
                        </div>
                        <div class="p-3 bg-emerald-50/40">
                            <div ref="bodyRef" contenteditable="true"
                                class="rich-editor min-h-[110px] rounded-lg border border-emerald-200 bg-background px-3 py-2.5 text-sm outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100 transition"></div>
                        </div>
                    </div>

                    <!-- ── Section 3: Participants ── -->
                    <div class="rounded-xl border border-violet-200 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2 bg-violet-100 border-b border-violet-200">
                            <Users class="h-3.5 w-3.5 text-violet-600" />
                            <p class="text-xs font-bold text-violet-700 uppercase tracking-wide">Participants Table</p>
                            <span class="ml-auto text-[10px] text-violet-400">Uncheck to exclude</span>
                        </div>
                        <div class="p-4 space-y-3 bg-violet-50/40">

                            <!-- Toggles -->
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                    <input v-model="form.include_participants" type="checkbox" class="h-4 w-4 rounded accent-violet-600" />
                                    <span class="text-sm group-hover:text-violet-600 transition-colors">Include list of participants (by Office, Name, Position)</span>
                                </label>
                                <label v-if="form.include_participants" class="flex items-center gap-2.5 cursor-pointer group ml-6">
                                    <input v-model="form.include_batch_data" type="checkbox" class="h-4 w-4 rounded accent-violet-600" />
                                    <span class="text-sm group-hover:text-violet-600 transition-colors">Group and label participants by batch</span>
                                </label>
                            </div>

                            <!-- Loading -->
                            <div v-if="form.include_participants && loadingParticipants"
                                class="flex items-center justify-center py-6 gap-2 text-xs text-muted-foreground">
                                <Loader2 class="h-4 w-4 animate-spin text-violet-500" />
                                Loading participants…
                            </div>

                            <!-- Participant reorder list -->
                            <div v-if="form.include_participants && !loadingParticipants && orderedBatches.length"
                                class="space-y-3">
                                <div v-for="(batch, bIdx) in orderedBatches" :key="batch.id"
                                    class="rounded-xl border border-violet-200 bg-background overflow-hidden">

                                    <!-- Batch header + reorder -->
                                    <div class="flex items-center gap-2 px-3 py-2 bg-violet-50 border-b border-violet-100">
                                        <GripVertical class="h-3.5 w-3.5 text-violet-300 shrink-0" />
                                        <span class="text-xs font-bold text-violet-700 flex-1 truncate">
                                            {{ batch.batch || 'Batch ' + (bIdx + 1) }}
                                            <span class="font-normal text-violet-400 ml-1">({{ batch.participants.length }} participants)</span>
                                        </span>
                                        <div v-if="orderedBatches.length > 1" class="flex gap-0.5 shrink-0">
                                            <button type="button"
                                                class="h-6 w-6 rounded flex items-center justify-center hover:bg-violet-200 transition-colors disabled:opacity-30"
                                                :disabled="bIdx === 0"
                                                @click="moveBatch(bIdx, -1)">
                                                <ChevronUp class="h-3.5 w-3.5 text-violet-600" />
                                            </button>
                                            <button type="button"
                                                class="h-6 w-6 rounded flex items-center justify-center hover:bg-violet-200 transition-colors disabled:opacity-30"
                                                :disabled="bIdx === orderedBatches.length - 1"
                                                @click="moveBatch(bIdx, 1)">
                                                <ChevronDown class="h-3.5 w-3.5 text-violet-600" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Participants -->
                                    <div class="divide-y divide-border">
                                        <div v-for="(p, pIdx) in batch.participants" :key="p.id"
                                            class="flex items-center gap-2 px-3 py-2 hover:bg-muted/40 transition-colors">
                                            <GripVertical class="h-3.5 w-3.5 text-muted-foreground/30 shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-semibold truncate">{{ p.name }}</p>
                                                <p class="text-[10px] text-muted-foreground truncate">{{ p.office }} · {{ p.position }}</p>
                                            </div>
                                            <div class="flex gap-0.5 shrink-0">
                                                <button type="button"
                                                    class="h-5 w-5 rounded flex items-center justify-center hover:bg-muted transition-colors disabled:opacity-30"
                                                    :disabled="pIdx === 0"
                                                    @click="moveParticipant(bIdx, pIdx, -1)">
                                                    <ChevronUp class="h-3 w-3 text-muted-foreground" />
                                                </button>
                                                <button type="button"
                                                    class="h-5 w-5 rounded flex items-center justify-center hover:bg-muted transition-colors disabled:opacity-30"
                                                    :disabled="pIdx === batch.participants.length - 1"
                                                    @click="moveParticipant(bIdx, pIdx, 1)">
                                                    <ChevronDown class="h-3 w-3 text-muted-foreground" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-if="form.include_participants && !loadingParticipants && orderedBatches.length === 0"
                                class="text-xs text-muted-foreground text-center py-4 rounded-lg border border-dashed border-violet-200">
                                No participants found for this program.
                            </div>

                        </div>
                    </div>

                    <!-- ── Section 4: Closure ── -->
                    <div class="rounded-xl border border-amber-200 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2 bg-amber-100 border-b border-amber-200">
                            <PenLine class="h-3.5 w-3.5 text-amber-600" />
                            <p class="text-xs font-bold text-amber-700 uppercase tracking-wide">Closure</p>
                            <span class="ml-auto text-[10px] text-amber-500">Closing paragraph &amp; effectivity</span>
                        </div>
                        <div class="p-3 bg-amber-50/40">
                            <div ref="closureRef" contenteditable="true"
                                class="rich-editor min-h-[150px] rounded-lg border border-amber-200 bg-background px-3 py-2.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100 transition"></div>
                        </div>
                    </div>

                    <!-- ── Section 5: Signatory ── -->
                    <div class="rounded-xl border border-rose-200 overflow-hidden">
                        <div class="flex items-center gap-2 px-4 py-2 bg-rose-100 border-b border-rose-200">
                            <Stamp class="h-3.5 w-3.5 text-rose-600" />
                            <p class="text-xs font-bold text-rose-700 uppercase tracking-wide">Signatory</p>
                            <span class="ml-auto text-[10px] text-rose-400">Authorizing official</span>
                        </div>
                        <div class="p-4 space-y-3 bg-rose-50/40">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                <input v-model="signatoryQuery" type="text"
                                    placeholder="Search employee by name or empcode…"
                                    class="w-full rounded-xl border border-border bg-background pl-9 pr-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100 transition" />
                                <div v-if="signatoryResults.length"
                                    class="absolute z-10 mt-1 w-full rounded-xl border bg-background shadow-lg max-h-48 overflow-y-auto">
                                    <button v-for="emp in signatoryResults" :key="emp.empcode" type="button"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-muted flex items-center justify-between gap-2"
                                        @click="selectSignatory(emp)">
                                        <span>{{ emp.name }}</span>
                                        <span class="text-xs text-muted-foreground">{{ emp.position }}</span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="form.signatory_name"
                                class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-700">
                                <UserCheck class="h-3.5 w-3.5" />
                                <span>Selected: <strong>{{ form.signatory_name }}</strong> — {{ form.signatory_position }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Signatory Name <span class="text-red-500">*</span></label>
                                    <input v-model="form.signatory_name" type="text"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100 transition" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-muted-foreground mb-1">Position <span class="text-red-500">*</span></label>
                                    <input v-model="form.signatory_position" type="text"
                                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100 transition" />
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ── Footer ── -->
                <div class="border-t px-5 py-4 shrink-0 bg-muted/30">
                    <button :disabled="processing"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 disabled:opacity-60 text-white font-bold py-2.5 text-sm transition shadow-md hover:shadow-lg"
                        @click="submit">
                        <Loader2 v-if="processing" class="h-3.5 w-3.5 animate-spin" />
                        <Save v-else class="h-3.5 w-3.5" />
                        {{ processing ? 'Generating PDF…' : 'Generate TESDA Order PDF' }}
                    </button>
                </div>

            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.toolbar-btn {
    padding: 0.375rem;
    border-radius: 0.375rem;
    cursor: pointer;
    color: currentColor;
    transition: background 0.15s;
}
.toolbar-btn:hover { background: hsl(var(--muted)); }

.rich-editor :deep(p) { margin: 0 0 0.5rem !important; text-indent: 2rem !important; }
.rich-editor :deep(ul) { list-style-type: disc !important; padding-left: 1.5rem !important; margin: 0.4rem 0 !important; }
.rich-editor :deep(ol) { list-style-type: decimal !important; padding-left: 1.5rem !important; margin: 0.4rem 0 !important; }
.rich-editor :deep(li) { display: list-item !important; margin: 0.15rem 0 !important; text-indent: 0 !important; }
.rich-editor :deep(blockquote) { margin: 0 0 0 2rem !important; border-left: none !important; padding-left: 0 !important; text-indent: 0 !important; }
.rich-editor :deep(h1) { font-size: 1.4rem; font-weight: 800; margin: 0.5rem 0; text-indent: 0; }
.rich-editor :deep(h2) { font-size: 1.2rem; font-weight: 700; margin: 0.5rem 0; text-indent: 0; }
.rich-editor :deep(h3) { font-size: 1rem;   font-weight: 700; margin: 0.5rem 0; text-indent: 0; }
.rich-editor :deep(a)  { color: #1d3fc4; text-decoration: underline; }
</style>