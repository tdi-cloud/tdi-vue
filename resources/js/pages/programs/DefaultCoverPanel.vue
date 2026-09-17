<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Loader2, Maximize2, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const { confirmDialog } = useConfirm();

interface DefaultCover {
    id: number;
    image: string;
    image_url: string | null;
}

const props = defineProps<{
    defaultCover?: DefaultCover | null;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const uploading = ref(false);
const showLightbox = ref(false);

// Ipapakita: bagong preview kung may pinili, kung wala, yung naka-save na
const displayImage = computed(() => previewUrl.value ?? props.defaultCover?.image_url ?? null);

const triggerFileSelect = () => fileInput.value?.click();

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Image too large. Max 2MB.');
        return;
    }

    previewUrl.value = URL.createObjectURL(file);
    uploadFile(file);
};

const uploadFile = (file: File) => {
    uploading.value = true;

    const form = useForm({
        image: file,
    });

    form.post(route('programs.default-cover.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            previewUrl.value = null;
        },
        onError: () => {
            previewUrl.value = null;
            alert('Upload failed. Please try again.');
        },
        onFinish: () => {
            uploading.value = false;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const removeCover = async () => {
    if (!(await confirmDialog('Remove the default program cover? Programs without their own cover will show no image again.'))) return;
    router.delete(route('programs.default-cover.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            previewUrl.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-2">
        <p class="text-xs text-muted-foreground">
            Shown on a program's page whenever it doesn't have its own cover page uploaded yet.
        </p>
        <div class="overflow-hidden rounded-2xl border shadow-sm">
            <!-- May image -->
            <div v-if="displayImage" class="group relative">
                <img :src="displayImage" alt="Default program cover" class="h-48 w-full cursor-zoom-in object-cover md:h-56" @click="showLightbox = true" />

                <div v-if="uploading" class="absolute inset-0 flex items-center justify-center bg-black/50">
                    <Loader2 class="h-6 w-6 animate-spin text-white" />
                </div>

                <div class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100">
                    <span class="flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur">
                        <Maximize2 class="h-3.5 w-3.5" /> View larger
                    </span>
                </div>

                <div class="absolute right-3 top-3 flex gap-2 opacity-0 transition-opacity group-hover:opacity-100">
                    <Button size="sm" variant="secondary" @click="triggerFileSelect" :disabled="uploading">
                        <Upload class="mr-1 h-3.5 w-3.5" /> Change
                    </Button>
                    <Button size="sm" variant="destructive" @click="removeCover" :disabled="uploading">
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>

            <!-- Walang image: upload placeholder -->
            <button
                v-else
                type="button"
                @click="triggerFileSelect"
                :disabled="uploading"
                class="flex h-48 w-full flex-col items-center justify-center gap-2 border-2 border-dashed border-muted-foreground/30 bg-muted/30 text-muted-foreground transition-colors hover:bg-muted/50 md:h-56"
            >
                <Loader2 v-if="uploading" class="h-8 w-8 animate-spin" />
                <template v-else>
                    <ImagePlus class="h-8 w-8" />
                    <p class="text-sm font-semibold">Set a Default Cover</p>
                    <p class="text-[11px]">Click to upload (JPG, PNG, WEBP · max 2MB)</p>
                </template>
            </button>

            <input ref="fileInput" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden" @change="handleFileChange" />

            <Teleport to="body">
                <div
                    v-if="showLightbox && displayImage"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
                    @click.self="showLightbox = false"
                >
                    <button
                        type="button"
                        class="absolute right-4 top-4 text-white/80 transition-colors hover:text-white"
                        aria-label="Close preview"
                        @click="showLightbox = false"
                    >
                        <X class="h-7 w-7" />
                    </button>
                    <img :src="displayImage" alt="Default program cover" class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl" />
                </div>
            </Teleport>
        </div>
    </div>
</template>
