<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { ImagePlus, UserRound, X } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};

const { getInitials } = useInitials();

const avatarInput = ref<HTMLInputElement | null>(null);
const avatarProcessing = ref(false);
const avatarError = ref('');
const showAvatarPreview = ref(false);

const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

function triggerAvatarPick() {
    avatarError.value = '';
    avatarInput.value?.click();
}

function handleAvatarChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file) return;

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        avatarError.value = 'Please choose a JPG, PNG, or WEBP image.';
        return;
    }
    if (file.size > MAX_AVATAR_BYTES) {
        avatarError.value = 'Image is too large — please choose one under 2MB.';
        return;
    }

    avatarError.value = '';
    avatarProcessing.value = true;
    const data = new FormData();
    data.append('avatar', file);
    router.post(route('profile.avatar.update'), data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            avatarProcessing.value = false;
        },
    });
}

function removeAvatar() {
    if (!confirm('Remove your profile picture?')) return;
    avatarProcessing.value = true;
    router.delete(route('profile.avatar.destroy'), {
        preserveScroll: true,
        onFinish: () => {
            avatarProcessing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <div class="rounded-2xl border p-5 md:p-6">
                    <HeadingSmall
                        title="Profile picture"
                        description="Upload a photo to personalize your account"
                        :icon="ImagePlus"
                        icon-class="bg-violet-100 text-violet-600 dark:bg-violet-900/40 dark:text-violet-400"
                    />

                    <div class="mt-5 flex items-center gap-4">
                        <Avatar
                            class="h-16 w-16 overflow-hidden rounded-full bg-blue-600"
                            :class="user.avatar ? 'cursor-pointer' : ''"
                            @click="user.avatar && (showAvatarPreview = true)"
                        >
                            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                            <AvatarFallback class="rounded-full text-lg font-extrabold text-white">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <Button type="button" variant="outline" size="sm" :disabled="avatarProcessing" @click="triggerAvatarPick">
                                    {{ avatarProcessing ? 'Uploading…' : user.avatar ? 'Change photo' : 'Upload photo' }}
                                </Button>
                                <Button
                                    v-if="user.avatar"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="avatarProcessing"
                                    class="text-red-600 hover:text-red-700"
                                    @click="removeAvatar"
                                >
                                    Remove
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">JPG, PNG, or WEBP. Max 2MB.</p>
                            <p v-if="avatarError" class="text-xs text-red-600">{{ avatarError }}</p>
                        </div>

                        <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="handleAvatarChange" />
                    </div>
                </div>

                <div class="rounded-2xl border p-5 md:p-6">
                    <HeadingSmall title="Profile information" description="Update your name and email address" :icon="UserRound" />

                    <form @submit.prevent="submit" class="mt-5 space-y-6">
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Full name" />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                class="mt-1 block w-full"
                                v-model="form.email"
                                required
                                autocomplete="username"
                                placeholder="Email address"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div v-if="mustVerifyEmail && !user.email_verified_at">
                            <p class="mt-2 text-sm text-neutral-800">
                                Your email address is unverified.
                                <Link
                                    :href="route('verification.send')"
                                    method="post"
                                    as="button"
                                    class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                                >
                                    Click here to re-send the verification email.
                                </Link>
                            </p>

                            <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                                A new verification link has been sent to your email address.
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button :disabled="form.processing">Save</Button>

                            <TransitionRoot
                                :show="form.recentlySuccessful"
                                enter="transition ease-in-out"
                                enter-from="opacity-0"
                                leave="transition ease-in-out"
                                leave-to="opacity-0"
                            >
                                <p class="text-sm text-neutral-600">Saved.</p>
                            </TransitionRoot>
                        </div>
                    </form>
                </div>
            </div>

            <DeleteUser />
        </SettingsLayout>

        <!-- ===== Avatar Preview Lightbox ===== -->
        <div
            v-if="showAvatarPreview && user.avatar"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-4"
            @click.self="showAvatarPreview = false"
        >
            <button
                type="button"
                class="absolute right-4 top-4 text-white/80 transition-colors hover:text-white"
                aria-label="Close preview"
                @click="showAvatarPreview = false"
            >
                <X class="h-7 w-7" />
            </button>
            <img :src="user.avatar" :alt="user.name" class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl" />
        </div>
    </AppLayout>
</template>
