<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import { onMounted } from 'vue';

interface EvaluationFormData {
    title: string;
    batch: {
        batch: string;
        program: { title: string };
    };
}

defineProps<{ form: EvaluationFormData }>();

onMounted(() => {
    document.documentElement.classList.remove('dark');
});
</script>

<template>
    <Head :title="form.title" />

    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4 py-12 [color-scheme:light]">
        <div class="w-full max-w-md space-y-4">
            <div class="overflow-hidden rounded-2xl shadow-md">
                <div class="bg-gradient-to-br from-rose-700 via-red-700 to-orange-600 px-6 py-5">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-rose-200">Program Evaluation</p>
                    <h1 class="text-xl font-extrabold text-white">{{ form.batch.program.title }}</h1>
                    <p class="mt-0.5 text-xs text-white/80">{{ form.batch.batch }}</p>
                </div>
                <div class="border-t-4 border-rose-500 bg-white px-6 py-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <Lock class="h-7 w-7 text-gray-500" />
                    </div>
                    <h2 class="mb-2 text-xl font-extrabold text-gray-800">Evaluation Closed</h2>
                    <p class="text-sm leading-relaxed text-gray-500">
                        This evaluation form for <strong class="text-gray-700">{{ form.batch.program.title }}</strong> is no longer accepting
                        responses. If you believe this is a mistake, please get in touch with the program organizer.
                    </p>
                </div>
            </div>

            <footer class="flex items-center justify-center gap-2.5 pt-2">
                <img
                    src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png"
                    alt="TESDA Development Institute"
                    class="h-7 w-7 object-contain"
                />
                <span class="text-sm font-semibold text-gray-600">TESDA Development Institute</span>
            </footer>
        </div>
    </div>
</template>
