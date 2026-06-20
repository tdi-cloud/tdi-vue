<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
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
            verifyErrors.value = err.response.data.errors;
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
                    <Input id="password" type="password" required tabindex="4" autocomplete="new-password" v-model="form.password" placeholder="Password" />
                    <InputError :message="verifyErrors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input id="password_confirmation" type="password" required tabindex="5" autocomplete="new-password" v-model="form.password_confirmation" placeholder="Confirm password" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="6" :disabled="isVerifying">
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
            <DialogContent class="max-w-sm rounded-2xl">
                <DialogHeader>
                    <DialogTitle>Confirm Your Identity</DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Please verify that this is you before proceeding.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="employeeInfo" class="flex flex-col gap-3 py-2">
                    <div class="rounded-xl border p-4 flex flex-col gap-2 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Full Name</p>
                            <p class="font-bold">{{ employeeInfo.fullname }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Employee Code</p>
                            <p class="font-bold font-mono">{{ employeeInfo.empcode }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Office / Division</p>
                            <p class="font-bold">{{ employeeInfo.office_division }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground text-center">Is this you? Click confirm to receive an OTP on your email.</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" size="sm" @click="showConfirmModal = false">Cancel</Button>
                    <Button size="sm" :disabled="isSendingOtp" @click="confirmAndSendOtp">
                        <LoaderCircle v-if="isSendingOtp" class="h-4 w-4 animate-spin mr-1" />
                        Confirm & Send OTP
                    </Button>
                </div>
            </DialogContent>
        </Dialog>

    </AuthBase>
</template>