<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

const props = defineProps<{ email: string }>();

const form = useForm({
    email: props.email,
    otp: '',
});

const digits = ref(['', '', '', '', '', '']);
const inputs = ref<HTMLInputElement[]>([]);

const updateOtp = () => {
    form.otp = digits.value.join('');
};

const onInput = (index: number) => {
    updateOtp();
    if (digits.value[index] && index < 5) {
        inputs.value[index + 1]?.focus();
    }
};

const onKeydown = (index: number, e: KeyboardEvent) => {
    if (e.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputs.value[index - 1]?.focus();
    }
};

const onPaste = (e: ClipboardEvent) => {
    const pasted = e.clipboardData?.getData('text').trim().slice(0, 6) ?? '';
    if (/^\d{6}$/.test(pasted)) {
        digits.value = pasted.split('');
        updateOtp();
        inputs.value[5]?.focus();
        e.preventDefault();
    }
};

const submit = () => {
    form.post(route('register'));
};

onMounted(() => {
    inputs.value[0]?.focus();
});
</script>

<template>
    <AuthBase title="" description="">
        <Head title="Verify OTP" />

        <div class="flex flex-col items-center gap-6">
            <!-- Icon -->
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50">
                <Mail class="h-8 w-8 text-blue-500" />
            </div>

            <!-- Heading -->
            <div class="text-center">
                <h1 class="text-2xl font-bold text-foreground">Verify Your Email</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Enter the OTP sent to <strong>{{ email }}</strong>
                </p>
            </div>

            <form @submit.prevent="submit" class="flex w-full flex-col items-center gap-6">
                <!-- 6-digit inputs -->
                <div class="flex flex-col items-center gap-2 w-full">
                    <label class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                        One-Time Password
                    </label>
                    <div class="flex gap-3" @paste="onPaste">
                        <Input
                            v-for="(_, i) in digits"
                            :key="i"
                            :ref="el => { if (el) inputs[i] = (el as any).$el ?? el }"
                            type="text"
                            inputmode="numeric"
                            maxlength="1"
                            v-model="digits[i]"
                            @input="onInput(i)"
                            @keydown="onKeydown(i, $event)"
                            class="h-14 w-12 text-center text-xl font-bold tracking-widest"
                        />
                    </div>
                    <p v-if="form.errors.otp" class="text-sm text-destructive">{{ form.errors.otp }}</p>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || form.otp.length < 6"
                >
                    <LoaderCircle v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Verify OTP
                </Button>
            </form>
        </div>
    </AuthBase>
</template>