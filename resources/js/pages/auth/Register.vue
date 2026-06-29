<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { LoaderCircle, ShieldCheck, User, Hash, Building2, MailCheck, Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';

const form = useForm({
    name: '',
    empcode: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const showConfirmModal = ref(false);
const isVerifying = ref(false);
const isSendingOtp = ref(false);
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const employeeInfo = ref<{ fullname: string; empcode: string; office_division: string } | null>(null);
const verifyErrors = ref<Record<string, string>>({});

// Step 1: Validate and fetch employee info
const verify = async () => {
    isVerifying.value = true;
    verifyErrors.value = {};
    try {
        const res = await axios.post(route('register.verify'), {
            name: form.name,
            empcode: form.empcode,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });
        employeeInfo.value = res.data.employee;
        showConfirmModal.value = true;
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const raw = err.response.data.errors;
            verifyErrors.value = Object.fromEntries(
                Object.entries(raw).map(([key, val]) => [
                    key,
                    Array.isArray(val) ? val[0] : val,
                ])
            ) as Record<string, string>;
        }
    } finally {
        isVerifying.value = false;
    }
};

// Step 2: Send OTP then redirect to OTP page
const confirmAndSendOtp = async () => {
    isSendingOtp.value = true;
    try {
        await axios.post(route('register.send-otp'), {
            name: form.name,
            empcode: form.empcode,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });
        showConfirmModal.value = false;
        router.visit(route('register.otp-page', { email: form.email }));
    } catch (err: any) {
        console.error('FULL ERROR:', err.response?.data);
    } finally {
        isSendingOtp.value = false;
    }
};

// Build initials for the avatar
const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((n) => n[0]?.toUpperCase())
        .join('');
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <form @submit.prevent="verify" class="flex flex-col gap-6">
            <div class="grid gap-6">

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" type="text" required autofocus tabindex="1" autocomplete="name" v-model="form.name" placeholder="Full name" />
                    <InputError :message="verifyErrors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="empcode">Employee Code</Label>
                    <Input id="empcode" type="text" required tabindex="2" autocomplete="off" v-model="form.empcode" placeholder="Employee code" />
                    <InputError :message="verifyErrors.empcode" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required tabindex="3" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="verifyErrors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            tabindex="-1"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition-colors hover:text-foreground focus:outline-none"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="verifyErrors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            :type="showPasswordConfirm ? 'text' : 'password'"
                            required
                            tabindex="5"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirm password"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            tabindex="-1"
                            :aria-label="showPasswordConfirm ? 'Hide password' : 'Show password'"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground transition-colors hover:text-foreground focus:outline-none"
                            @click="showPasswordConfirm = !showPasswordConfirm"
                        >
                            <Eye v-if="!showPasswordConfirm" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/25 transition-all hover:from-blue-700 hover:to-blue-600 hover:shadow-blue-500/40 disabled:opacity-70"
                    tabindex="6"
                    :disabled="isVerifying"
                >
                    <LoaderCircle v-if="isVerifying" class="h-4 w-4 animate-spin" />
                    Continue
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="7">Log in</TextLink>
            </div>
        </form>

        <!-- Confirm Identity Modal -->
        <Dialog :open="showConfirmModal" @update:open="showConfirmModal = $event">
            <DialogContent class="max-w-sm overflow-hidden rounded-2xl border-0 p-0 shadow-2xl">
                <!-- Gradient header band -->
                <div class="relative bg-gradient-to-br from-blue-600 to-blue-500 px-6 pb-8 pt-6 text-white">
                    <div class="pointer-events-none absolute inset-0 opacity-20"
                         style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 18px 18px;"></div>

                    <DialogHeader class="relative z-10 space-y-1 text-center">
                        <div class="mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur">
                            <ShieldCheck class="h-6 w-6" />
                        </div>
                        <DialogTitle class="text-lg font-semibold">Confirm Your Identity</DialogTitle>
                        <DialogDescription class="text-xs text-blue-100">
                            Please verify that this is you before proceeding.
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Avatar that overlaps the band -->
                    <div v-if="employeeInfo"
                         class="absolute -bottom-7 left-1/2 z-20 flex h-14 w-14 -translate-x-1/2 items-center justify-center rounded-full border-4 border-background bg-gradient-to-br from-blue-500 to-blue-600 text-base font-bold text-white shadow-lg">
                        {{ initials(employeeInfo.fullname) || 'U' }}
                    </div>
                </div>

                <div v-if="employeeInfo" class="flex flex-col gap-4 px-6 pb-6 pt-10">
                    <div class="divide-y rounded-xl border bg-card">
                        <div class="flex items-center gap-3 p-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50">
                                <User class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Full Name</p>
                                <p class="truncate text-sm font-semibold">{{ employeeInfo.fullname }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50">
                                <Hash class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Employee Code</p>
                                <p class="truncate font-mono text-sm font-semibold">{{ employeeInfo.empcode }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50">
                                <Building2 class="h-4 w-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-wide text-muted-foreground">Office / Division</p>
                                <p class="truncate text-sm font-semibold">{{ employeeInfo.office_division }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-center text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                        <MailCheck class="h-4 w-4 shrink-0" />
                        <span>Confirm to receive a one-time code on your email.</span>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <Button variant="outline" size="sm" class="flex-1" :disabled="isSendingOtp" @click="showConfirmModal = false">
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            class="flex-1 bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-md shadow-blue-500/25 transition-all hover:from-blue-700 hover:to-blue-600 hover:shadow-blue-500/40 disabled:opacity-70"
                            :disabled="isSendingOtp"
                            @click="confirmAndSendOtp"
                        >
                            <LoaderCircle v-if="isSendingOtp" class="mr-1 h-4 w-4 animate-spin" />
                            Confirm &amp; Send OTP
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

    </AuthBase>
</template>