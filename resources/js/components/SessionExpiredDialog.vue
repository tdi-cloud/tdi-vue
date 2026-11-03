<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { flushFormDrafts } from '@/composables/useFormDraft';
import { sessionExpired } from '@/lib/http';
import { usePage } from '@inertiajs/vue3';
import { LogIn, RotateCw, TimerOff } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const signingIn = ref(false);

// Signed-out visitors (sign-up, public evaluation) only need a fresh page, not a sign-in.
const page = usePage<{ auth?: { user?: unknown } }>();
const isSignedIn = computed(() => !!page.props?.auth?.user);

/**
 * Reloading the current page lets the server decide: if the session is gone,
 * the auth middleware remembers this URL as the "intended" page and sends the
 * user to login, which brings them back here afterwards. If only the page's
 * security token expired, the page simply reloads with a fresh one. Either
 * way, autosaved form drafts are restored on return.
 */
const signIn = () => {
    signingIn.value = true;
    flushFormDrafts();
    window.location.reload();
};
</script>

<template>
    <Dialog :open="sessionExpired" @update:open="sessionExpired = $event">
        <DialogContent class="!max-w-md !rounded-2xl" @interact-outside.prevent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <TimerOff class="h-5 w-5 text-amber-500" /> {{ isSignedIn ? 'Your session has expired' : 'This page expired' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isSignedIn
                            ? 'Sign in again to continue. Your work on this page has been kept.'
                            : 'Reload the page to continue. What you typed in long forms has been kept.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="signingIn" @click="sessionExpired = false">Not now</Button>
                <Button :disabled="signingIn" @click="signIn">
                    <LogIn v-if="isSignedIn" class="h-4 w-4" />
                    <RotateCw v-else class="h-4 w-4" />
                    {{ signingIn ? 'Reloading…' : isSignedIn ? 'Sign in' : 'Reload page' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
