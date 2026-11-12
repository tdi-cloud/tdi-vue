<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { type EditorSection } from '@/lib/publicForms';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { watch } from 'vue';

/**
 * Add or edit a section of a public form. Each section is one step of the
 * form, and can be limited to employees with a minimum salary grade.
 */
const props = defineProps<{
    formSlug: string;
    /** Null to add a new section. */
    section: EditorSection | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    title: '',
    step_label: '',
    description: '',
    min_salary_grade: '' as string | number,
});

// Fill the boxes each time the dialog opens.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.defaults({
        title: props.section?.title ?? '',
        step_label: props.section?.step_label ?? '',
        description: props.section?.description ?? '',
        min_salary_grade: props.section?.min_salary_grade ?? '',
    });
    form.reset();
    form.clearErrors();
});

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    form.transform((data) => ({
        ...data,
        min_salary_grade: data.min_salary_grade === '' || data.min_salary_grade === null ? null : Number(data.min_salary_grade),
    }));

    if (props.section) {
        form.put(route('public-form-sections.update', props.section.id), options);
    } else {
        form.post(route('public-forms.admin.sections.store', props.formSlug), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto !rounded-2xl">
            <DialogHeader>
                <DialogTitle>{{ section ? 'Edit section' : 'Add a section' }}</DialogTitle>
                <DialogDescription class="text-xs">Each section is one step of the form, with its own questions.</DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="save">
                <div class="grid gap-1">
                    <label for="section-title" class="text-xs font-semibold">Title <span class="text-red-500">*</span></label>
                    <Input id="section-title" v-model="form.title" class="h-9 text-sm" maxlength="150" placeholder="e.g. Your CY 2026 Experience" />
                    <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <div class="grid gap-1">
                    <label for="section-step-label" class="text-xs font-semibold">Short name <span class="font-normal">(optional)</span></label>
                    <Input
                        id="section-step-label"
                        v-model="form.step_label"
                        class="h-9 text-sm"
                        maxlength="30"
                        :placeholder="form.title ? form.title.slice(0, 16) : 'e.g. Experience'"
                    />
                    <p class="text-[11px] text-muted-foreground">Shown under the step number in the form's progress bar.</p>
                    <p v-if="form.errors.step_label" class="text-xs text-red-500">{{ form.errors.step_label }}</p>
                </div>

                <div class="grid gap-1">
                    <label for="section-description" class="text-xs font-semibold">Description <span class="font-normal">(optional)</span></label>
                    <Textarea id="section-description" v-model="form.description" rows="2" class="text-sm" maxlength="500" />
                    <p v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</p>
                </div>

                <div class="grid gap-1">
                    <label for="section-min-sg" class="text-xs font-semibold"
                        >Only for salary grade <span class="font-normal">(optional)</span></label
                    >
                    <div class="flex items-center gap-2">
                        <Input id="section-min-sg" v-model="form.min_salary_grade" type="number" min="1" max="33" class="h-9 w-24 text-sm" />
                        <span class="text-xs text-muted-foreground">and above</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Leave it empty so everyone answers this section. With a number, only employees with that salary grade or higher (from HR's
                        list) see it.
                    </p>
                    <p v-if="form.errors.min_salary_grade" class="text-xs text-red-500">{{ form.errors.min_salary_grade }}</p>
                </div>

                <div class="flex justify-end gap-2 border-t pt-3">
                    <Button type="button" variant="outline" size="sm" @click="open = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-blue-600 hover:bg-blue-700 dark:text-white" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="mr-1 h-3 w-3 animate-spin" />
                        {{ section ? 'Save Section' : 'Add Section' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
