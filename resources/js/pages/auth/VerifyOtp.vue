<script setup lang="ts">
import OtpVerify from '@/components/OtpVerify.vue';
import { updateTheme } from '@/composables/useAppearance';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    email: string;
    resendAvailableIn: number;
}>();

// ── Force light mode on the OTP page ────────────────────────────────────────
onMounted(() => {
    document.documentElement.classList.remove('dark');
});

onBeforeUnmount(() => {
    updateTheme((localStorage.getItem('appearance') as 'light' | 'dark' | 'system' | null) || 'system');
});

const page = usePage<SharedData>();
const isCelebrating = ref(false);

// The layout card's border and outer glow turn green once the code is accepted.
const cardClass = computed(() => [
    'transition-[border-color,box-shadow] duration-300',
    isCelebrating.value &&
        (page.props.authBackground
            ? 'border-green-400/70 shadow-[0_0_48px_-4px] shadow-green-400/50'
            : 'border-green-600/60 shadow-[0_0_40px_-6px] shadow-green-600/30'),
]);

const goToRedirect = (url: string) => {
    router.visit(url);
};
</script>

<template>
    <AuthBase title="" description="" :card-class="cardClass">
        <Head title="Verify OTP" />

        <OtpVerify :email="email" :resend-available-in="resendAvailableIn" @celebrating="isCelebrating = $event" @verified="goToRedirect" />
    </AuthBase>
</template>
