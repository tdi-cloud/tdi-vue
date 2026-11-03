<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Link, useForm } from '@inertiajs/vue3';
import { MessageSquareWarning } from 'lucide-vue-next';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        buttonClass?: string;
        // When set, renders a text button (e.g. on the Error page) instead of the icon-only one.
        label?: string;
    }>(),
    {
        buttonClass: 'text-foreground/70 hover:bg-accent hover:text-accent-foreground',
    },
);

const open = ref(false);

const form = useForm({
    description: '',
    page_url: '',
});

const submit = () => {
    form.page_url = window.location.href;

    form.post(route('problem-reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
};
</script>

<template>
    <button
        type="button"
        class="relative inline-flex h-9 items-center justify-center gap-2 rounded-lg transition"
        :class="[buttonClass, label ? 'px-3 text-sm font-medium' : 'w-9']"
        :aria-label="label ? undefined : 'Report a problem'"
        :title="label ? undefined : 'Report a problem'"
        @click="open = true"
    >
        <MessageSquareWarning class="h-5 w-5" />
        <span v-if="label">{{ label }}</span>
    </button>

    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="!max-w-md !rounded-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"> <MessageSquareWarning class="h-5 w-5 text-amber-500" /> Report a Problem </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Describe the issue you encountered. This will be sent directly to our team.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="problem-description">What went wrong?</Label>
                    <textarea
                        id="problem-description"
                        v-model="form.description"
                        rows="5"
                        required
                        placeholder="Tell us what happened, what you expected, and any steps to reproduce it..."
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <!-- Earlier reports and the super admins' replies -->
                    <Link
                        :href="route('problem-reports.mine')"
                        class="mr-auto text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        @click="open = false"
                    >
                        My reports &amp; replies
                    </Link>
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <SubmitButton
                        class="bg-amber-600 hover:bg-amber-700"
                        :processing="form.processing"
                        processing-label="Sending…"
                        :disabled="!form.description.trim()"
                    >
                        Submit Report
                    </SubmitButton>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
