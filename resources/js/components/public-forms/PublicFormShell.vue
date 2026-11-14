<script setup lang="ts">
import PublicFormBackdrop from '@/components/public-forms/PublicFormBackdrop.vue';
import { onMounted } from 'vue';

/**
 * Page frame for the public forms: always light (like the public
 * registration form), the form's background picture under an animated
 * technology layer and a white wash, a centred column and the TESDA footer.
 */
withDefaults(
    defineProps<{
        /** Null shows the plain light gray page ("No background"). */
        backgroundUrl?: string | null;
        /** Centres a short card (closed and success pages) vertically. */
        centered?: boolean;
        /** md: a single short card. form: the form and the forms list, wider on big screens. */
        width?: 'md' | 'form';
    }>(),
    { backgroundUrl: null, centered: false, width: 'form' },
);

onMounted(() => {
    document.documentElement.classList.remove('dark');
});
</script>

<template>
    <div class="relative min-h-screen bg-gray-100 px-4 py-8 [color-scheme:light]" :class="{ 'flex items-center justify-center py-12': centered }">
        <!-- A fixed layer instead of background-attachment: fixed, which is slow (and ignored) on phones -->
        <template v-if="backgroundUrl">
            <div
                class="fixed inset-0 bg-cover bg-center"
                :style="{ backgroundImage: `url(${JSON.stringify(backgroundUrl)})` }"
                aria-hidden="true"
            ></div>
            <PublicFormBackdrop :focus-width="width" />
            <!-- White wash over the animation so the form keeps the focus -->
            <div class="fixed inset-0 bg-white/50" aria-hidden="true"></div>
        </template>

        <div class="relative mx-auto w-full space-y-4" :class="width === 'md' ? 'max-w-md' : 'max-w-2xl lg:max-w-3xl xl:max-w-4xl'">
            <slot />

            <footer class="flex items-center justify-center gap-2.5 pb-4 pt-2">
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-100">TESDA Development Institute</span>
            </footer>
        </div>
    </div>
</template>
