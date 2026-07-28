<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { updateTheme } from '@/composables/useAppearance';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// ── Force light mode on the login page ──────────────────────────────────────
onMounted(() => {
    document.documentElement.classList.remove('dark');
});

onBeforeUnmount(() => {
    updateTheme((localStorage.getItem('appearance') as 'light' | 'dark' | 'system' | null) || 'system');
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Log in to your account" description="Enter your email and password below to log in">
        <Head title="Log in" />

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 py-2 text-center text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <div class="relative">
                        <Mail class="pointer-events-none absolute inset-y-0 left-0 my-auto ml-3.5 h-4 w-4 text-blue-900/50" />
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="email@example.com"
                            class="h-11 rounded-xl border-blue-100 bg-white pl-10 shadow-sm focus-visible:ring-blue-700"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm text-blue-800" tabindex="5"> Forgot password? </TextLink>
                    </div>
                    <div class="relative">
                        <Lock class="pointer-events-none absolute inset-y-0 left-0 my-auto ml-3.5 h-4 w-4 text-blue-900/50" />
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            placeholder="Password"
                            class="h-11 rounded-xl border-blue-100 bg-white pl-10 pr-10 shadow-sm focus-visible:ring-blue-700"
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
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3 ">
                        <Checkbox id="remember" class="border-blue-800 data-[state=checked]:bg-blue-800 data-[state=checked]:border-blue-800 data-[state=checked]:text-white" v-model:checked="form.remember" tabindex="4" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-2 h-11 w-full rounded-xl bg-gradient-to-b from-blue-700 to-blue-900 font-extrabold shadow-md shadow-blue-900/20 transition hover:brightness-110" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Log in
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Don't have an account?
                <TextLink :href="route('register')" class="text-blue-800" :tabindex="5">Sign up</TextLink>
            </div>
        </form>
    </AuthBase>
</template>