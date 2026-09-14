<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Loader2, Mail, Plus, Send, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Participant {
    empcode: string;
    employee_name: string | null;
    employee_email: string | null;
}

const props = defineProps<{
    open: boolean;
    programId: number | null;
    batchId: number | null;
    requirementId: number | null;
    batchName: string;
    programTitle: string;
    requirementTitle: string;
    requirementName: string;
    dueDate: string | null;
    participants: Participant[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

/* ===================== TO ===================== */
const toList = ref<string[]>([]);
const noEmailList = ref<Participant[]>([]);
const newEmail = ref('');
const showNoEmail = ref(false);

const addEmail = () => {
    const e = newEmail.value.trim();
    if (!e || toList.value.includes(e)) return;
    toList.value.push(e);
    newEmail.value = '';
};
const removeEmail = (e: string) => {
    toList.value = toList.value.filter((x) => x !== e);
};

/* ===================== SUBJECT ===================== */
const subject = ref('');
const buildSubject = () => `📌 REMINDER | Post-Training Requirements – ${props.programTitle}`;

/* ===================== QUILL ===================== */
let quill: any = null;
let sigQuill: any = null;
const quillContainer = ref<HTMLElement | null>(null);
const sigContainer = ref<HTMLElement | null>(null);

const formatDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '');

const buildBody = () => {
    const due = props.dueDate ? `on or before <strong>${formatDate(props.dueDate)}</strong>` : 'as soon as possible';
    return `<p>Good day,</p>
<p>Thank you for your participation in the <strong>${props.programTitle}</strong>. We sincerely appreciate your active engagement and commitment to continuous learning and professional development.</p>
<p>This is a gentle reminder regarding the submission of your post-training requirement. As provided in the corresponding TESDA Order, participants are required to submit their <strong>${props.requirementName} (${props.requirementTitle})</strong> to the TESDA Development Institute (TDI) ${due}.</p>
<p>For proper submission and monitoring, kindly use the official link below:</p>
<p><a href="https://bit.ly/TDI-TREAP">🔗 bit.ly/TDI-TREAP</a></p>
<p>Your timely compliance is highly appreciated and will greatly assist in the completion of the required post-training documentation and monitoring activities.</p>
<p>Please <strong>disregard this reminder if you have already submitted</strong> your ${props.requirementTitle}.</p>
<p>If you were <strong>unable to attend or did not complete the training</strong>, you may disregard the requirement above. Kindly submit your justification for non-attendance through the link below instead:</p>
<p><a href="https://bit.ly/Training-Non-Attendance">🔗 bit.ly/Training-Non-Attendance</a></p>
<p>Thank you for your cooperation and continued support of TESDA's learning and development initiatives.</p>`;
};

const buildSig = () =>
    `Regards,

TESDA Development Institute
Technical Education and Skills Development Authority (TESDA)
Office of the Deputy Director General for Administration and Innovation`;

const loadScript = (src: string): Promise<void> =>
    new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => resolve();
        s.onerror = () => reject();
        document.head.appendChild(s);
    });

const loadLink = (href: string, id: string) => {
    if (document.getElementById(id)) return;
    const l = document.createElement('link');
    l.id = id;
    l.rel = 'stylesheet';
    l.href = href;
    document.head.appendChild(l);
};

const initQuill = async () => {
    if (!quillContainer.value) return;

    // Wait for Quill to be available (should already be loaded via onMounted)
    let attempts = 0;
    while (!(window as any).Quill && attempts < 20) {
        await new Promise<void>((r) => setTimeout(r, 100));
        attempts++;
    }

    if (!(window as any).Quill) return; // timeout

    const Quill = (window as any).Quill;

    // ── Body editor ──
    if (quill) {
        quill = null;
        quillContainer.value.innerHTML = '';
    }

    quill = new Quill(quillContainer.value, {
        theme: 'snow',
        placeholder: 'Write your message here…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ align: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ indent: '-1' }, { indent: '+1' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    quill.clipboard.dangerouslyPasteHTML(buildBody());
    quill.setSelection(0, 0);

    // ── Signature editor ──
    if (sigContainer.value) {
        if (sigQuill) {
            sigQuill = null;
            sigContainer.value.innerHTML = '';
        }

        sigQuill = new Quill(sigContainer.value, {
            theme: 'snow',
            placeholder: 'Your signature…',
            modules: {
                toolbar: [['bold', 'italic', 'underline'], [{ color: [] }], ['clean']],
            },
        });

        sigQuill.clipboard.dangerouslyPasteHTML(buildSig().replace(/\n/g, '<br>'));
        sigQuill.setSelection(0, 0);
    }
};

const destroyQuill = () => {
    if (quill) {
        quill = null;
        if (quillContainer.value) quillContainer.value.innerHTML = '';
    }
    if (sigQuill) {
        sigQuill = null;
        if (sigContainer.value) sigContainer.value.innerHTML = '';
    }
};

// Pre-load Quill as soon as the component mounts so it's ready instantly
onMounted(async () => {
    loadLink('https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css', 'quill-css');
    if (!(window as any).Quill) {
        await loadScript('https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js');
    }
});

watch(
    () => props.open,
    async (val) => {
        if (val) {
            quillReady.value = false;
            toList.value = props.participants.filter((p) => p.employee_email).map((p) => p.employee_email!);
            noEmailList.value = props.participants.filter((p) => !p.employee_email);
            subject.value = buildSubject();
            // Wait for DOM to be ready
            await nextTick();
            await initQuill();
            quillReady.value = true;
        } else {
            quillReady.value = false;
            destroyQuill();
        }
    },
);

/* ===================== SEND ===================== */
const quillReady = ref(false);
const sending = ref(false);
const sent = ref(false);
const errorMsg = ref('');

const canSend = computed(() => toList.value.length > 0 && subject.value.trim());

const send = () => {
    if (!canSend.value) return;
    sending.value = true;
    errorMsg.value = '';

    const bodyHtml = quill?.root?.innerHTML ?? '';
    const sigText = sigQuill?.root?.innerHTML ?? '';

    // I-match ang bawat email sa kilalang participant (empcode/name) para may
    // makikitang detalye sa reminder history; kung manually-added lang ang
    // email (wala sa listahan ng participants), null na lang ang empcode/name.
    const recipients = toList.value.map((email) => {
        const participant = props.participants.find((p) => p.employee_email === email);
        return {
            empcode: participant?.empcode ?? null,
            name: participant?.employee_name ?? null,
            email,
        };
    });

    router.post(
        route('email-reminder.send'),
        {
            to: toList.value,
            subject: subject.value,
            body: bodyHtml,
            signature: sigText,
            program_id: props.programId,
            batch_id: props.batchId,
            requirement_id: props.requirementId,
            recipients,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                sent.value = true;
                setTimeout(() => {
                    sent.value = false;
                    emit('update:open', false);
                }, 2000);
            },
            onError: (errors) => {
                errorMsg.value = Object.values(errors).join(' ');
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
};

const close = () => {
    if (!sending.value) emit('update:open', false);
};

onUnmounted(() => destroyQuill());
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center">
                <div class="absolute inset-0 bg-black/80" @click="close" />

                <div
                    class="relative z-10 mx-4 w-full max-w-2xl rounded-2xl border bg-background shadow-xl"
                    style="display: flex; flex-direction: column; height: 92vh; max-height: 92vh"
                >
                    <!-- Header -->
                    <div class="flex shrink-0 items-center justify-between border-b px-6 py-4">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/40">
                                <Mail class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold leading-none">Email Reminder</h2>
                                <p class="mt-0.5 text-[11px] text-muted-foreground">{{ batchName }} · {{ requirementTitle }}</p>
                            </div>
                        </div>
                        <button type="button" class="opacity-70 transition-opacity hover:opacity-100" @click="close">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Scrollable body -->
                    <div style="flex: 1; min-height: 0; overflow-y: auto">
                        <div class="flex flex-col gap-4 px-6 py-4">
                            <!-- TO -->
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <Label class="text-xs font-semibold">
                                        To
                                        <span class="ml-1 font-normal text-muted-foreground"
                                            >({{ toList.length }} recipient{{ toList.length !== 1 ? 's' : '' }})</span
                                        >
                                    </Label>
                                    <button
                                        v-if="noEmailList.length"
                                        type="button"
                                        class="flex items-center gap-1 text-[11px] text-amber-600 hover:underline dark:text-amber-400"
                                        @click="showNoEmail = !showNoEmail"
                                    >
                                        ⚠ {{ noEmailList.length }} without email
                                        <ChevronDown v-if="!showNoEmail" class="h-3 w-3" />
                                        <ChevronUp v-else class="h-3 w-3" />
                                    </button>
                                </div>

                                <div
                                    v-if="showNoEmail && noEmailList.length"
                                    class="flex flex-col gap-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800 dark:bg-amber-900/20"
                                >
                                    <p class="mb-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300">No email on file:</p>
                                    <p v-for="p in noEmailList" :key="p.empcode" class="text-[11px] text-amber-700 dark:text-amber-400">
                                        {{ p.employee_name ?? p.empcode }} ({{ p.empcode }})
                                    </p>
                                </div>

                                <div class="flex min-h-[40px] flex-wrap gap-1.5 rounded-xl border px-3 py-2">
                                    <span
                                        v-for="email in toList"
                                        :key="email"
                                        class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        {{ email }}
                                        <button type="button" @click="removeEmail(email)" class="transition-colors hover:text-red-500">
                                            <X class="h-3 w-3" />
                                        </button>
                                    </span>
                                </div>

                                <div class="flex gap-2">
                                    <Input
                                        v-model="newEmail"
                                        class="h-8 flex-1 text-xs"
                                        type="email"
                                        placeholder="Add email address…"
                                        @keydown.enter.prevent="addEmail"
                                    />
                                    <Button variant="outline" size="sm" class="h-8 text-xs" @click="addEmail">
                                        <Plus class="mr-1 h-3.5 w-3.5" /> Add
                                    </Button>
                                </div>
                            </div>

                            <!-- SUBJECT -->
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-semibold">Subject</Label>
                                <Input v-model="subject" class="h-8 text-xs" />
                            </div>

                            <!-- BODY — Quill editor -->
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-semibold">Body</Label>
                                <div class="quill-wrapper relative overflow-hidden rounded-xl border">
                                    <!-- Loading overlay while Quill initializes -->
                                    <div
                                        v-if="!quillReady"
                                        class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-background/80"
                                    >
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Loader2 class="h-4 w-4 animate-spin" />
                                            Loading editor…
                                        </div>
                                    </div>
                                    <div ref="quillContainer" style="min-height: 220px" />
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="flex items-center gap-2">
                                <div class="flex-1 border-t border-dashed" />
                                <span class="px-2 text-[11px] text-muted-foreground">Signature</span>
                                <div class="flex-1 border-t border-dashed" />
                            </div>

                            <!-- SIGNATURE — Quill with minimal toolbar -->
                            <div class="quill-wrapper quill-sig overflow-hidden rounded-xl border">
                                <div ref="sigContainer" style="min-height: 80px" />
                            </div>

                            <p v-if="errorMsg" class="text-xs text-red-600 dark:text-red-400">{{ errorMsg }}</p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex shrink-0 items-center justify-between gap-2 border-t px-6 py-3">
                        <p class="text-[11px] text-muted-foreground">From: <span class="font-semibold">tdi.noreply@tesda.gov.ph</span></p>
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="sm" @click="close" :disabled="sending"> <X class="mr-1 h-3.5 w-3.5" /> Cancel </Button>
                            <Button size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="!canSend || sending" @click="send">
                                <Loader2 v-if="sending" class="mr-1 h-3.5 w-3.5 animate-spin" />
                                <Send v-else-if="!sent" class="mr-1 h-3.5 w-3.5" />
                                {{ sent ? '✓ Sent!' : sending ? 'Sending…' : `Send to ${toList.length}` }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
/* Quill snow theme overrides para mag-fit sa ating design */
.quill-wrapper .ql-toolbar {
    border: none;
    border-bottom: 1px solid hsl(var(--border));
    background: hsl(var(--muted) / 0.3);
    padding: 6px 8px;
    flex-wrap: wrap;
}
.quill-wrapper .ql-container {
    border: none;
    font-size: 14px;
    font-family: inherit;
}
.quill-wrapper .ql-editor {
    min-height: 200px;
    padding: 12px 16px;
    direction: ltr;
    line-height: 1.4;
}
.quill-wrapper .ql-editor p {
    margin-bottom: 10px;
} /* ← space between paragraphs */
.quill-wrapper .ql-editor ul,
.quill-wrapper .ql-editor ol {
    padding-left: 1.5em;
    margin-bottom: 8px;
}
.quill-wrapper .ql-editor li {
    margin-bottom: 4px;
} /* ← space between list items */
.quill-wrapper .ql-editor.ql-blank::before {
    color: hsl(var(--muted-foreground));
    font-style: normal;
}
.quill-sig .ql-editor {
    min-height: 80px;
    font-size: 12px;
    padding: 10px 16px;
    line-height: 1.3;
}
.quill-sig .ql-editor p {
    margin-bottom: 4px;
} /* ← space between sig lines */
.quill-sig .ql-toolbar {
    padding: 4px 6px;
}
</style>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
