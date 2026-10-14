<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import BackToTop from './BackToTop.vue';
import SignatoryPicker from './SignatoryPicker.vue';
import TnaBackdrop from './TnaBackdrop.vue';

const props = defineProps({
    assessment: { type: Object, required: true }, // { id, subordinate_name, subordinate_position }
    fasd: { type: Object, default: () => ({}) }, // { empcode, name, position, office }
    noted: { type: Object, default: null }, // { empcode, name, position, office }
    notedDefault: { type: Object, default: null }, // the supervisor
});

const preparedBy = ref(props.fasd?.empcode ? props.fasd : null);
// null means "use the default" (the supervisor)
const notedBy = ref(props.noted?.empcode && props.noted.empcode !== props.notedDefault?.empcode ? props.noted : null);

const form = useForm({
    fasd_empcode: props.fasd?.empcode ?? '',
    fasd_name: props.fasd?.name ?? '',
    fasd_position: props.fasd?.position ?? '',
    fasd_office: props.fasd?.office ?? '',
    noted_empcode: notedBy.value?.empcode ?? '',
    noted_name: notedBy.value?.name ?? '',
    noted_position: notedBy.value?.position ?? '',
    noted_office: notedBy.value?.office ?? '',
});

watch(preparedBy, (s) => {
    form.fasd_empcode = s?.empcode ?? '';
    form.fasd_name = s?.name ?? '';
    form.fasd_position = s?.position ?? '';
    form.fasd_office = s?.office ?? '';
});

watch(notedBy, (s) => {
    form.noted_empcode = s?.empcode ?? '';
    form.noted_name = s?.name ?? '';
    form.noted_position = s?.position ?? '';
    form.noted_office = s?.office ?? '';
});

const submit = () => {
    form.patch(route('tna.supervisory.fasd.update', props.assessment.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Change TNA Result Signatories" />

    <div class="tna-page relative min-h-screen px-4 py-8">
        <TnaBackdrop />
        <div class="relative z-10 mx-auto max-w-2xl space-y-6">
            <Link
                :href="route('tna.supervisory.index')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to list
            </Link>

            <div class="rounded-2xl bg-white p-8 shadow-xl">
                <h1 class="text-xl font-bold text-gray-900">Change TNA Result Signatories</h1>
                <p class="mt-1 text-sm text-gray-500">
                    TNA Result for {{ assessment.subordinate_name }} ·
                    {{ assessment.subordinate_position }}
                </p>

                <div class="mt-6 space-y-8">
                    <SignatoryPicker
                        v-model="preparedBy"
                        title="Prepared by"
                        description="The FASD who prepares the TNA Result."
                        :error="form.errors.fasd_empcode"
                    />
                    <SignatoryPicker
                        v-model="notedBy"
                        title="Noted by"
                        description="You are the &quot;Noted by&quot; signatory by default. Change it only if someone else will sign."
                        :default-signatory="notedDefault"
                        :error="form.errors.noted_name"
                    />
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button
                        type="button"
                        :disabled="form.processing || !form.fasd_empcode"
                        class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit"
                    >
                        {{ form.processing ? 'Saving…' : 'Save Signatories' }}
                    </button>
                </div>
            </div>
        </div>
        <BackToTop />
    </div>
</template>
