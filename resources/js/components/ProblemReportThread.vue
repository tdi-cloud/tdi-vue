<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Label } from '@/components/ui/label';
import { useForm, usePage } from '@inertiajs/vue3';
import { Send, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

export interface ProblemReportReply {
    id: number;
    user_id: number | null;
    body: string;
    created_at: string;
    user: { id: number; name: string } | null;
}

/**
 * The conversation under a problem report plus a reply box. Super admins see it
 * on the Problem Reports page; reporters on their "My problem reports" page.
 */
const props = withDefaults(
    defineProps<{
        report: { id: number; user_id: number; replies: ProblemReportReply[] };
        emptyText?: string;
    }>(),
    { emptyText: 'No replies yet.' },
);

const page = usePage();
const viewerId = computed(() => (page.props.auth as any)?.user?.id as number | undefined);

const isOwn = (reply: ProblemReportReply) => reply.user_id !== null && reply.user_id === viewerId.value;
// Only super admins can answer someone else's report, so any author other than the reporter is one.
const isFromSuperAdmin = (reply: ProblemReportReply) => reply.user_id !== props.report.user_id;
const authorName = (reply: ProblemReportReply) => (isOwn(reply) ? 'You' : (reply.user?.name ?? 'Former user'));

const formatDateTime = (d: string) =>
    new Date(d).toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

const fieldId = computed(() => `problem-report-reply-${props.report.id}`);

const form = useForm({ body: '' });

const submit = () => {
    form.post(route('problem-reports.replies.store', { problemReport: props.report.id }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <div class="flex flex-col gap-3">
        <ol v-if="report.replies.length" class="flex flex-col gap-2.5" aria-label="Conversation">
            <li v-for="reply in report.replies" :key="reply.id" class="flex" :class="isOwn(reply) ? 'justify-end' : 'justify-start'">
                <div
                    class="max-w-[90%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm ring-1 ring-inset sm:max-w-[80%]"
                    :class="
                        isFromSuperAdmin(reply)
                            ? 'bg-amber-50 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-400/20'
                            : 'bg-white ring-slate-200 dark:bg-slate-900 dark:ring-slate-700'
                    "
                >
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                        <span class="font-semibold text-foreground">{{ authorName(reply) }}</span>
                        <span
                            v-if="isFromSuperAdmin(reply)"
                            class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-px text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
                        >
                            <ShieldCheck class="h-3 w-3" aria-hidden="true" /> Super admin
                        </span>
                        <time :datetime="reply.created_at" class="text-muted-foreground">{{ formatDateTime(reply.created_at) }}</time>
                    </p>
                    <p class="mt-1 whitespace-pre-line break-words text-slate-800 dark:text-slate-100">{{ reply.body }}</p>
                </div>
            </li>
        </ol>
        <p v-else class="text-xs text-muted-foreground">{{ emptyText }}</p>

        <form class="grid gap-2" @submit.prevent="submit">
            <Label :for="fieldId" class="text-xs">Write a reply</Label>
            <textarea
                :id="fieldId"
                v-model="form.body"
                rows="3"
                maxlength="2000"
                required
                placeholder="Type your message…"
                class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <InputError :message="form.errors.body" />
            <div class="flex justify-end">
                <SubmitButton
                    size="sm"
                    class="bg-amber-600 text-white hover:bg-amber-700"
                    :icon="Send"
                    :processing="form.processing"
                    processing-label="Sending…"
                    :disabled="!form.body.trim()"
                >
                    Send reply
                </SubmitButton>
            </div>
        </form>
    </div>
</template>
