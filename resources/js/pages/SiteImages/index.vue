<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Images, Upload, RotateCcw, CheckCircle2, Loader2 } from 'lucide-vue-next';

interface ImageSlot {
    key: string;
    label: string;
    section: string;
    url: string;
    is_customized: boolean;
}

defineProps<{
    sections: Record<string, ImageSlot[]>;
}>();

const fileInputs = ref<Record<string, HTMLInputElement | null>>({});
const busyKey = ref<string | null>(null);

const triggerUpload = (key: string) => {
    fileInputs.value[key]?.click();
};

const handleFileChange = (key: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    busyKey.value = key;
    router.post(route('site-images.update', key), { image: file }, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            busyKey.value = null;
            input.value = '';
        },
    });
};

const resetImage = (key: string) => {
    if (!confirm('Reset this image back to its default?')) return;
    busyKey.value = key;
    router.delete(route('site-images.destroy', key), {
        preserveScroll: true,
        onFinish: () => { busyKey.value = null; },
    });
};
</script>

<template>
    <Head title="Homepage Images" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-5 p-4">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-fuchsia-600 shadow-md">
                    <Images class="h-5 w-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold leading-none">Homepage Images</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Customize every image shown on the public homepage — superadmin only</p>
                </div>
            </div>

            <!-- Sections -->
            <div v-for="(slots, sectionName) in sections" :key="sectionName" class="flex flex-col gap-3">
                <h2 class="text-sm font-bold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-400">{{ sectionName }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div
                        v-for="slot in slots"
                        :key="slot.key"
                        class="rounded-2xl border overflow-hidden shadow-sm bg-background flex flex-col"
                    >
                        <div class="relative aspect-video bg-muted/40">
                            <img :src="slot.url" :alt="slot.label" class="w-full h-full object-cover" />
                            <span
                                v-if="slot.is_customized"
                                class="absolute top-2 right-2 inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-600 text-white shadow"
                            >
                                <CheckCircle2 class="h-3 w-3" /> Customized
                            </span>
                            <div v-if="busyKey === slot.key" class="absolute inset-0 flex items-center justify-center bg-black/40">
                                <Loader2 class="h-6 w-6 text-white animate-spin" />
                            </div>
                        </div>

                        <div class="p-3 flex flex-col gap-2 flex-1">
                            <p class="text-xs font-semibold leading-tight">{{ slot.label }}</p>

                            <div class="mt-auto flex items-center gap-1.5">
                                <input
                                    :ref="(el) => { fileInputs[slot.key] = el as HTMLInputElement }"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleFileChange(slot.key, $event)"
                                />
                                <button
                                    type="button"
                                    :disabled="busyKey === slot.key"
                                    class="flex-1 inline-flex items-center justify-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg bg-fuchsia-600 hover:bg-fuchsia-700 text-white transition-colors disabled:opacity-50"
                                    @click="triggerUpload(slot.key)"
                                >
                                    <Upload class="h-3.5 w-3.5" /> Replace
                                </button>
                                <button
                                    v-if="slot.is_customized"
                                    type="button"
                                    :disabled="busyKey === slot.key"
                                    title="Reset to default"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg border text-muted-foreground hover:text-red-600 hover:border-red-300 hover:bg-red-50 transition-colors disabled:opacity-50"
                                    @click="resetImage(slot.key)"
                                >
                                    <RotateCcw class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
