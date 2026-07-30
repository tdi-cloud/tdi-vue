<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { MessageSquareWarning, LoaderCircle } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        buttonClass?: string;
    }>(),
    {
        buttonClass: 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
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
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg transition"
        :class="buttonClass"
        aria-label="Report a problem"
        title="Report a problem"
        @click="open = true"
    >
        <MessageSquareWarning class="h-5 w-5" />
    </button>

    <Dialog :open="open" @update:open="open = $event">
        <DialogContent class="!max-w-md !rounded-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <MessageSquareWarning class="h-5 w-5 text-amber-500" /> Report a Problem
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground">
                    Describe the issue you encountered. This will be sent directly to the super admin.
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

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <Button type="submit" class="bg-amber-600 hover:bg-amber-700" :disabled="form.processing || !form.description.trim()">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Submit Report
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
