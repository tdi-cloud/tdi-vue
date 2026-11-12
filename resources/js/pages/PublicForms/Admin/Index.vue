<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PublicLinkField from '@/components/public-forms/PublicLinkField.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type PublicFormSummary } from '@/lib/publicForms';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, FilePlus2, FileText, Globe, Inbox, ListChecks, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface AdminPublicForm extends PublicFormSummary {
    id: number;
    public_url: string;
    question_count: number;
    is_open: boolean;
    responses_count: number;
    latest_response_at: string | null;
    created_by_name: string | null;
}

const props = defineProps<{
    forms: AdminPublicForm[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Public Forms', href: '/public-forms' }];

const formsListUrl = route('public-forms.index');

/* ── New form ─────────────────────────────────────────────────────────────── */

const newFormOpen = ref(false);
const newForm = useForm({ title: '', copy_from: '' as number | '' });

function openNewForm(): void {
    newForm.reset();
    newForm.clearErrors();
    newFormOpen.value = true;
}

function createForm(): void {
    newForm
        .transform((data) => ({ ...data, copy_from: data.copy_from === '' ? null : data.copy_from }))
        .post(route('public-forms.admin.store'), {
            onSuccess: () => {
                newFormOpen.value = false;
            },
        });
}

const fieldClass = 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head title="Public Forms" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-800 via-blue-700 to-sky-600 p-6 text-white shadow-xl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -right-8 -top-8 h-48 w-48 rounded-full bg-white/5" />
                    <div class="absolute -bottom-12 -right-4 h-64 w-64 rounded-full bg-white/5" />
                </div>
                <div class="relative flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="flex flex-col gap-2">
                        <span
                            class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide"
                        >
                            <Globe class="h-3.5 w-3.5" /> Public Forms
                        </span>
                        <h1 class="text-xl font-bold leading-tight md:text-2xl">Forms anyone can answer without logging in</h1>
                        <p class="max-w-2xl text-sm text-white/85">
                            Build a survey, share its link, and employees answer after finding their name in the employee list. Each one gets a
                            confirmation email.
                        </p>
                    </div>
                    <Button class="w-fit shrink-0 bg-white text-blue-700 hover:bg-blue-50" @click="openNewForm">
                        <FilePlus2 class="mr-1.5 h-4 w-4" /> New Form
                    </Button>
                </div>
            </div>

            <div class="rounded-2xl border bg-background p-4">
                <p class="mb-1.5 text-xs font-semibold">Page that lists every open public form</p>
                <PublicLinkField :url="formsListUrl" label="Link to the public forms list" />
            </div>

            <EmptyState
                v-if="!props.forms.length"
                :icon="FileText"
                class="rounded-2xl border border-dashed"
                title="No public forms yet"
                description="Create one with New Form."
            />

            <!-- Forms -->
            <div v-else class="grid gap-4 lg:grid-cols-2">
                <article v-for="form in forms" :key="form.slug" class="flex flex-col gap-4 rounded-2xl border bg-background p-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950/40">
                            <FileText class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <h2 class="text-base font-bold">{{ form.title }}</h2>
                                <Badge
                                    v-if="!form.closed_reason"
                                    variant="outline"
                                    class="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                                >
                                    Open
                                </Badge>
                                <Badge v-else variant="outline" class="text-[10px] text-muted-foreground">
                                    {{ form.closed_reason === 'deadline' ? 'Closed (deadline passed)' : 'Not open' }}
                                </Badge>
                            </div>
                            <p v-if="form.description" class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{{ form.description }}</p>
                            <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                <span class="flex items-center gap-1"><ListChecks class="h-3.5 w-3.5" /> {{ form.question_count }} questions</span>
                                <span v-if="form.closes_at_label">
                                    {{ form.closed_reason === 'deadline' ? 'Closed on' : 'Open until' }} {{ form.closes_at_label }}
                                </span>
                                <span v-if="form.created_by_name">Created by {{ form.created_by_name }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-muted/40 px-4 py-3">
                        <Inbox class="h-5 w-5 text-muted-foreground" />
                        <div>
                            <p class="text-xl font-extrabold tabular-nums leading-none">{{ form.responses_count.toLocaleString() }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ form.responses_count === 1 ? 'response' : 'responses' }}
                                <template v-if="form.latest_response_at"> · latest {{ form.latest_response_at }}</template>
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-semibold">Public link</p>
                        <PublicLinkField :url="form.public_url" :label="`Public link of ${form.title}`" />
                    </div>

                    <Button as-child size="sm" class="mt-auto w-full bg-blue-600 hover:bg-blue-700 dark:text-white sm:w-fit">
                        <Link :href="route('public-forms.admin.show', form.slug)">
                            Responses, questions and settings <ArrowRight class="ml-1 h-3.5 w-3.5" />
                        </Link>
                    </Button>
                </article>
            </div>
        </div>

        <!-- New form -->
        <Dialog v-model:open="newFormOpen">
            <DialogContent class="max-w-md !rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2"><FilePlus2 class="h-4 w-4" /> New public form</DialogTitle>
                    <DialogDescription class="text-xs">
                        It starts closed, so you can add its questions and preview it before opening it to employees.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="createForm">
                    <div class="grid gap-1">
                        <label for="new-form-title" class="text-xs font-semibold">Title <span class="text-red-500">*</span></label>
                        <Input
                            id="new-form-title"
                            v-model="newForm.title"
                            class="h-9 text-sm"
                            maxlength="150"
                            placeholder="e.g. Training Needs Survey 2027"
                        />
                        <p v-if="newForm.errors.title" class="text-xs text-red-500">{{ newForm.errors.title }}</p>
                    </div>

                    <div class="grid gap-1">
                        <label for="new-form-copy" class="text-xs font-semibold">Start from</label>
                        <select id="new-form-copy" v-model="newForm.copy_from" :class="fieldClass">
                            <option value="">A blank form</option>
                            <option v-for="form in forms" :key="form.id" :value="form.id">A copy of "{{ form.title }}"</option>
                        </select>
                        <p class="text-[11px] text-muted-foreground">A copy gets the same sections and questions, but none of the responses.</p>
                        <p v-if="newForm.errors.copy_from" class="text-xs text-red-500">{{ newForm.errors.copy_from }}</p>
                    </div>

                    <div class="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" size="sm" @click="newFormOpen = false">Cancel</Button>
                        <Button type="submit" size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="newForm.processing">
                            <LoaderCircle v-if="newForm.processing" class="mr-1 h-3 w-3 animate-spin" /> Create Form
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
