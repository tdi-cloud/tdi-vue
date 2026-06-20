<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    ScrollText,
    Plus,
    Pencil,
    Trash2,
    ExternalLink,
    LoaderCircle,
    Save,
    FileSearch,
    FolderOpen,
    Inbox,
    CalendarDays,
    Hash,
    Building2,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';

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

interface Program {
    id: number;
    program_code: string;
    supporting_documents?: SupportingDocument[];
}

const props = defineProps<{
    program: Program;
}>();

const documents = computed(() => props.program.supporting_documents ?? []);

/* ---------- Modal state ---------- */
const showModal = ref(false);
const editingDoc = ref<SupportingDocument | null>(null);
const processing = ref(false);

const form = ref({
    document_type: '',
    subject: '',
    document_series: new Date().getFullYear(),
    origin: '',
    document_number: '',
    date_issued: '',
    link: '',
});

const errors = ref<Record<string, string>>({});

const DOCUMENT_TYPES = [
    'Memorandum',
    'Memorandum Circular',
    'TESDA Order',
    'Office Order',
    'Circular',
    'Bulletin',
    'Cluster Order',
    'Advisory',
];

const openCreate = () => {
    editingDoc.value = null;
    form.value = {
        document_type: '',
        subject: '',
        document_series: new Date().getFullYear(),
        origin: '',
        document_number: '',
        date_issued: '',
        link: '',
    };
    errors.value = {};
    showModal.value = true;
};

const openEdit = (doc: SupportingDocument) => {
    editingDoc.value = doc;
    form.value = {
        document_type: doc.document_type,
        subject: doc.subject,
        document_series: doc.document_series,
        origin: doc.origin ?? '',
        document_number: doc.document_number,
        date_issued: doc.date_issued ?? '',
        link: doc.link ?? '',
    };
    errors.value = {};
    showModal.value = true;
};

const submit = () => {
    errors.value = {};
    processing.value = true;

    const payload = {
        document_type: form.value.document_type,
        subject: form.value.subject,
        document_series: form.value.document_series,
        origin: form.value.origin || null,
        document_number: form.value.document_number,
        date_issued: form.value.date_issued || null,
        link: form.value.link || null,
    };

    if (editingDoc.value) {
        router.put(
            route('programs.supporting-documents.update', [props.program.id, editingDoc.value.id]),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showModal.value = false;
                },
                onError: (e) => {
                    errors.value = e as any;
                },
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    } else {
        router.post(
            route('programs.supporting-documents.store', props.program.id),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    showModal.value = false;
                },
                onError: (e) => {
                    errors.value = e as any;
                },
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    }
};

const destroy = (doc: SupportingDocument) => {
    if (!confirm(`Delete "${doc.subject}"? This cannot be undone.`)) return;

    router.delete(route('programs.supporting-documents.destroy', [props.program.id, doc.id]), {
        preserveScroll: true,
    });
};

/* ---------- Helpers ---------- */
const formatDate = (d: string | null) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
};

const docTypeColor = (type: string) => {
    const t = type.toLowerCase();
    if (t.includes('memo')) return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300';
    if (t.includes('order') || t.includes('o.o') || t.includes('office order')) return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    if (t.includes('circular')) return 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300';
    if (t.includes('certificate') || t.includes('cert')) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
};
</script>

<template>
    <div class="flex flex-col gap-4">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold flex items-center gap-1.5">
                    <ScrollText class="h-4 w-4 text-blue-600" /> Supporting Documents
                </h2>
                <p class="text-xs font-semibold text-slate-400">
                    {{ documents.length }} document(s) attached to {{ program.program_code }}
                </p>
            </div>

            <Button size="sm" class="bg-blue-600 font-extrabold rounded-lg hover:bg-blue-500 dark:text-white" @click="openCreate">
                <Plus class="h-4 w-4" /> Add Document
            </Button>
        </div>

        <!-- Empty state with illustration -->
        <div v-if="!documents.length" class="flex flex-col items-center justify-center rounded-2xl border border-dashed py-16 px-6 text-center gap-3">
            <!-- Folder illustration -->
            <svg viewBox="0 0 200 160" class="h-40 w-auto" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="100" cy="142" rx="70" ry="8" fill="currentColor" class="text-slate-100 dark:text-slate-800" />
                <path d="M30 50 H80 L95 65 H170 V125 H30 Z" fill="currentColor" class="text-blue-100 dark:text-blue-900/40" />
                <path d="M30 50 H80 L95 65 H170 V70 H30 Z" fill="currentColor" class="text-blue-200 dark:text-blue-800/60" />
                <rect x="45" y="85" width="60" height="6" rx="3" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                <rect x="45" y="98" width="90" height="6" rx="3" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                <rect x="45" y="111" width="75" height="6" rx="3" fill="currentColor" class="text-blue-300 dark:text-blue-700/60" />
                <circle cx="155" cy="40" r="18" fill="currentColor" class="text-amber-100 dark:text-amber-900/40" />
                <path d="M148 40 l5 5 l9 -10" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" class="text-amber-500" />
            </svg>
            <p class="text-sm font-bold text-slate-500">No supporting documents yet</p>
            <p class="text-xs text-slate-400 max-w-xs">
                Memos, office orders, circulars, certificates — attach official documents related to this program here.
            </p>
            <Button size="sm" variant="outline" class="mt-1" @click="openCreate">
                <Plus class="h-3.5 w-3.5 mr-1" /> Add your first document
            </Button>
        </div>

        <!-- Document list -->
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="rounded-xl border bg-card p-4 shadow-sm transition hover:shadow-md flex flex-col gap-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <Badge :class="docTypeColor(doc.document_type)" class="text-[10px] font-bold border-0">
                        {{ doc.document_type }}
                    </Badge>
                    <span class="text-[11px] font-bold text-slate-400">S.Y. {{ doc.document_series }}</span>
                </div>

                <h3 class="text-sm font-extrabold leading-5">{{ doc.subject }}</h3>

                <div class="flex flex-col gap-1 text-xs text-slate-500 dark:text-slate-400">
                    <p class="flex items-center gap-1.5">
                        <Hash class="h-3.5 w-3.5 shrink-0" />
                        {{ doc.document_number }}
                    </p>
                    <p v-if="doc.origin" class="flex items-center gap-1.5">
                        <Building2 class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ doc.origin }}</span>
                    </p>
                    <p class="flex items-center gap-1.5">
                        <CalendarDays class="h-3.5 w-3.5 shrink-0" />
                        {{ formatDate(doc.date_issued) }}
                    </p>
                </div>

                
                <a v-if="doc.link"
                    :href="doc.link"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:underline mt-1"
                >
                    <ExternalLink class="h-3.5 w-3.5" /> View document
                </a>

                <div class="mt-2 flex justify-end gap-1 border-t pt-2">
                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(doc)">
                        <Pencil class="h-3 w-3" /> Edit
                    </Button>
                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-red-500 hover:text-red-600" @click="destroy(doc)">
                        <Trash2 class="h-3 w-3" /> Delete
                    </Button>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Dialog :open="showModal" @update:open="showModal = $event">
            <DialogContent class="max-w-xl flex flex-col max-h-[90vh] overflow-hidden !rounded-2xl">
                <DialogHeader class="shrink-0">
                    <DialogTitle>
                        <span class="flex gap-2 items-center">
                            <ScrollText class="h-5 w-5 text-blue-600" />
                            {{ editingDoc ? 'Edit Supporting Document' : 'Add Supporting Document' }}
                        </span>
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {{ editingDoc
                            ? 'Update the details of this document.'
                            : `Attach a memo, order, circular, or certificate to ${program.program_code}.` }}
                    </DialogDescription>
                </DialogHeader>

                <div class="overflow-y-auto flex-1 px-1">
                    <form id="supporting-doc-form" @submit.prevent="submit" class="grid grid-cols-2 gap-4 py-2">

                        <!-- Document Type -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Document Type <span class="text-red-500">*</span></Label>
                            <Select v-model="form.document_type">
                                <SelectTrigger class="text-xs h-8">
                                    <SelectValue placeholder="— Select type —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="type in DOCUMENT_TYPES"
                                        :key="type"
                                        :value="type"
                                        class="text-xs"
                                    >
                                        {{ type }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-red-500">{{ errors.document_type }}</p>
                        </div>

                        <!-- Document Series -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Document Series (Year) <span class="text-red-500">*</span></Label>
                            <Input type="number" class="text-xs h-8" v-model="form.document_series" placeholder="e.g. 2026" />
                            <p class="text-xs text-red-500">{{ errors.document_series }}</p>
                        </div>

                        <!-- Subject (full width) -->
                        <div class="grid gap-1 col-span-2">
                            <Label class="text-xs">Subject <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.subject" placeholder="e.g. Conduct of ISO 9001:2015 Awareness Training" />
                            <p class="text-xs text-red-500">{{ errors.subject }}</p>
                        </div>

                        <!-- Document Number -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Document Number <span class="text-red-500">*</span></Label>
                            <Input class="text-xs h-8" v-model="form.document_number" placeholder="e.g. 2026-045" />
                            <p class="text-xs text-red-500">{{ errors.document_number }}</p>
                        </div>

                        <!-- Origin -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Origin</Label>
                            <Input class="text-xs h-8" v-model="form.origin" placeholder="e.g. Office of the Director" />
                            <p class="text-xs text-red-500">{{ errors.origin }}</p>
                        </div>

                        <!-- Date Issued -->
                        <div class="grid gap-1">
                            <Label class="text-xs">Date Issued</Label>
                            <Input type="date" class="text-xs h-8" v-model="form.date_issued" />
                            <p class="text-xs text-red-500">{{ errors.date_issued }}</p>
                        </div>

                        <!-- Link (full width) -->
                        <div class="grid gap-1 col-span-2">
                            <Label class="text-xs">Link (Google Drive, etc.)</Label>
                            <Input class="text-xs h-8" v-model="form.link" placeholder="https://drive.google.com/..." />
                            <p class="text-xs text-red-500">{{ errors.link }}</p>
                        </div>

                    </form>
                </div>

                <div class="shrink-0 flex justify-end gap-2 pt-3 border-t mt-2">
                    <Button type="button" variant="outline" size="sm" @click="showModal = false">Cancel</Button>
                    <Button type="submit" class="bg-blue-600 hover:bg-blue-700 dark:text-white" form="supporting-doc-form" size="sm" :disabled="processing">
                        <LoaderCircle v-if="processing" class="h-3 w-3 animate-spin mr-1" />
                        <Save v-else class="h-3.5 w-3.5" />
                        {{ editingDoc ? 'Update Document' : 'Save Document' }}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

    </div>
</template>