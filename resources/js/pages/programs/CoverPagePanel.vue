<script setup lang="ts">
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { ImagePlus, Trash2, Upload, Loader2 } from 'lucide-vue-next';

interface CoverPage {
    id: number;
    image: string;
    image_url: string | null;
}

const props = defineProps<{
    programId: number;
    coverPage?: CoverPage | null;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const uploading = ref(false);

// Ipapakita: bagong preview kung may pinili, kung wala, yung naka-save na
const displayImage = computed(() => previewUrl.value ?? props.coverPage?.image_url ?? null);

const triggerFileSelect = () => fileInput.value?.click();

const handleFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    // Client-side validation
    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        alert('Image too large. Max 2MB.');
        return;
    }

    // Instant local preview
    previewUrl.value = URL.createObjectURL(file);

    // Auto-upload
    uploadFile(file);
};

const uploadFile = (file: File) => {
    uploading.value = true;

    const form = useForm({
        program_id: props.programId,
        image: file,
    });

    form.post(route('programs.cover.upload'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            previewUrl.value = null; // gamitin na yung galing server
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

const removeCover = () => {
    if (!confirm('Remove this cover page?')) return;
    router.delete(route('programs.cover.destroy', props.programId), {
        preserveScroll: true,
        onSuccess: () => {
            previewUrl.value = null;
        },
    });
};
</script>

<template>
    <div class="rounded-2xl border shadow-sm overflow-hidden">
        <!-- May image -->
        <div v-if="displayImage" class="relative group">
            <img
                :src="displayImage"
                alt="Program cover"
                class="w-full h-48 md:h-56 object-cover"
            />

            <!-- Loading overlay -->
            <div
                v-if="uploading"
                class="absolute inset-0 bg-black/50 flex items-center justify-center"
            >
                <Loader2 class="h-6 w-6 text-white animate-spin" />
            </div>

            <!-- Action buttons (hover) -->
            <div
                class="absolute top-3 right-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity"
            >
                <Button size="sm" variant="secondary" @click="triggerFileSelect" :disabled="uploading">
                    <Upload class="h-3.5 w-3.5 mr-1" /> Change
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
            class="w-full h-48 md:h-56 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-muted-foreground/30 bg-muted/30 hover:bg-muted/50 transition-colors text-muted-foreground"
        >
            <Loader2 v-if="uploading" class="h-8 w-8 animate-spin" />
            <template v-else>
                <ImagePlus class="h-8 w-8" />
                <p class="text-sm font-semibold">Add Cover Page</p>
                <p class="text-[11px]">Click to upload (JPG, PNG, WEBP · max 2MB)</p>
            </template>
        </button>

        <!-- Hidden file input -->
        <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            class="hidden"
            @change="handleFileChange"
        />
    </div>
</template>