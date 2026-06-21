<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, LoaderCircle, Save } from 'lucide-vue-next';
import { watch } from 'vue';

interface Program {
    id: number;
    program_code: string;
    title: string;
    description: string;
    modality: string;
    pax: string;
    category: string;
    type: string;
    initiated: string;
    provider: string;
    cost: string;
    fund: string;
    origin: string;
}

const props = defineProps<{
    program: Program;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Programs', href: '/programs' },
    { title: props.program.program_code, href: `/programs/${props.program.id}` },
    { title: 'Edit', href: `/programs/${props.program.id}/edit` },
];

const form = useForm({
    title: props.program.title,
    description: props.program.description,
    modality: props.program.modality,
    pax: props.program.pax,
    category: props.program.category,
    type: props.program.type,
    initiated: props.program.initiated,
    provider: props.program.provider,
    cost: props.program.cost,
    fund: props.program.fund,
    origin: props.program.origin,
});

watch(() => form.initiated, (val) => {
    if (val === 'TDI') {
        form.provider = 'TESDA Development Institute (TDI)';
    } else if (val === 'NTTA') {
        form.provider = 'National TVET Trainors Academy (NTTA)';
    } else if (!['TDI', 'NTTA'].includes(props.program.initiated)) {
        form.provider = '';
    }
});

const submit = () => {
    form.put(route('programs.update', props.program.id));
};
</script>

<template>
    <Head :title="`Edit - ${program.program_code}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4">

            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b px-6 pb-4 pt-6">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" @click="router.visit(route('programs.show', program.id))">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div>
                        <p class="text-xs text-slate-400 font-semibold">{{ program.program_code }}</p>
                        <h1 class="text-xl font-extrabold leading-tight">Edit Program</h1>
                    </div>
                </div>
                <Button class="bg-blue-600 hover:bg-blue-700" size="sm" :disabled="form.processing" @click="submit">
                    <LoaderCircle v-if="form.processing" class="h-3 w-3 animate-spin mr-1" />
                    <Save class="h-4 w-4 mr-1" /> Save Changes
                </Button>
            </div>

            <!-- Form -->
            <div class="px-6 pb-6">
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-4 py-2">

                    <!-- Title -->
                    <div class="col-span-2 grid gap-1">
                        <Label class="text-xs">Title <span class="text-red-500">*</span></Label>
                        <Input class="text-xs h-8" v-model="form.title" placeholder="Program title" />
                        <p class="text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <!-- Description -->
                    <div class="col-span-2 grid gap-1">
                        <Label class="text-xs">Description</Label>
                        <Textarea class="text-xs min-h-[100px] resize-y" v-model="form.description" placeholder="Enter program description..." />
                        <p class="text-xs text-red-500">{{ form.errors.description }}</p>
                    </div>

                    <!-- Modality -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Modality <span class="text-red-500">*</span></Label>
                        <Select v-model="form.modality">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select modality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="In-person">In-person</SelectItem>
                                <SelectItem class="text-xs" value="Online/Virtual">Online/Virtual</SelectItem>
                                <SelectItem class="text-xs" value="Hybrid">Hybrid</SelectItem>
                                <SelectItem class="text-xs" value="Self-Paced">Self-Paced</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.modality }}</p>
                    </div>

                    <!-- Pax -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Pax <span class="text-red-500">*</span></Label>
                        <Input class="text-xs h-8" v-model="form.pax" placeholder="Number of participants" />
                        <p class="text-xs text-red-500">{{ form.errors.pax }}</p>
                    </div>

                    <!-- Category -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Category <span class="text-red-500">*</span></Label>
                        <Select v-model="form.category">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="Benchmarking">Benchmarking</SelectItem>
                                <SelectItem class="text-xs" value="Capability Building">Capability Building</SelectItem>
                                <SelectItem class="text-xs" value="Executive-Office">Executive-Office</SelectItem>
                                <SelectItem class="text-xs" value="Foreign-Bilateral">Foreign-Bilateral</SelectItem>
                                <SelectItem class="text-xs" value="Foreign-FSTP">Foreign-FSTP</SelectItem>
                                <SelectItem class="text-xs" value="Local-In-House">Local-In-House</SelectItem>
                                <SelectItem class="text-xs" value="Local-Public">Local-Public</SelectItem>
                                <SelectItem class="text-xs" value="Other-Foreign">Other Foreign Program</SelectItem>
                                <SelectItem class="text-xs" value="Regional">Regional</SelectItem>
                                <SelectItem class="text-xs" value="Team-Building">Team-Building</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.category }}</p>
                    </div>

                    <!-- Program Type -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Program Type <span class="text-red-500">*</span></Label>
                        <Select v-model="form.type">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="ADMIN">ADMIN</SelectItem>
                                <SelectItem class="text-xs" value="TECHNICAL">TECHNICAL</SelectItem>
                                <SelectItem class="text-xs" value="SUPERVISORY/MANAGERIAL">SUPERVISORY/MANAGERIAL</SelectItem>
                                <SelectItem class="text-xs" value="TEAM-BUILDING">TEAM-BUILDING</SelectItem>
                                <SelectItem class="text-xs" value="OTHER">OTHER</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.type }}</p>
                    </div>

                    <!-- Office Initiated -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Office Initiated <span class="text-red-500">*</span></Label>
                        <Select v-model="form.initiated">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select office" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="TDI">TESDA Development Institute (TDI)</SelectItem>
                                <SelectItem class="text-xs" value="NTTA">National TVET Trainors Academy (NTTA)</SelectItem>
                                <SelectItem class="text-xs" value="Other Executive Office">Other Executive Office</SelectItem>
                                <SelectItem class="text-xs" value="Other Training Provider">Other Training Provider</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.initiated }}</p>
                    </div>

                    <!-- Provider -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Provider</Label>
                        <Input
                            class="text-xs h-8"
                            v-model="form.provider"
                            placeholder="Training provider"
                        />
                        <p class="text-xs text-red-500">{{ form.errors.provider }}</p>
                    </div>

                    <!-- Cost -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Cost <span class="text-red-500">*</span></Label>
                        <Input class="text-xs h-8" v-model="form.cost" placeholder="e.g. 5000" />
                        <p class="text-xs text-red-500">{{ form.errors.cost }}</p>
                    </div>

                    <!-- Fund Source -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Fund Source <span class="text-red-500">*</span></Label>
                        <Select v-model="form.fund">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select fund source" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="Central Office - SDP">Central Office - SDP</SelectItem>
                                <SelectItem class="text-xs" value="Regional Office - SDP">Regional Office - SDP</SelectItem>
                                <SelectItem class="text-xs" value="Other Office">Other Office</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.fund }}</p>
                    </div>

                    <!-- Origin -->
                    <div class="grid gap-1">
                        <Label class="text-xs">Origin <span class="text-red-500">*</span></Label>
                        <Select v-model="form.origin">
                            <SelectTrigger class="text-xs h-8">
                                <SelectValue placeholder="Select origin" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem class="text-xs" value="Local">Local</SelectItem>
                                <SelectItem class="text-xs" value="Foreign">Foreign</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-red-500">{{ form.errors.origin }}</p>
                    </div>

                </form>
            </div>

        </div>
    </AppLayout>
</template>