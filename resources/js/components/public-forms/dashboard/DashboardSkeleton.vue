<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

/** The dashboard's layout while its numbers load: header, filters, tiles, timeline, profile and question cards. */
</script>

<template>
    <div role="status" aria-live="polite" class="flex flex-col gap-5">
        <span class="sr-only">Loading the summary of responses…</span>

        <div aria-hidden="true" class="flex flex-col gap-5">
            <div class="flex flex-col gap-3 rounded-2xl border bg-card p-6">
                <Skeleton class="h-3 w-40" />
                <Skeleton class="h-6 w-2/3" />
                <Skeleton class="h-3 w-1/3" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Skeleton v-for="n in 4" :key="n" class="h-9 w-36 rounded-lg" />
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="n in 6" :key="n" class="flex flex-col gap-2.5 rounded-2xl border bg-card p-4">
                    <Skeleton class="h-8 w-8 rounded-lg" />
                    <Skeleton class="h-6 w-1/2" />
                    <Skeleton class="h-3 w-3/4" />
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-2xl border bg-card p-5">
                <Skeleton class="h-4 w-48" />
                <Skeleton class="h-52 w-full rounded-xl" />
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="n in 3" :key="n" class="flex flex-col gap-3 rounded-2xl border bg-card p-5">
                    <Skeleton class="h-4 w-1/2" />
                    <Skeleton v-for="row in 5" :key="row" class="h-4" :style="{ width: `${92 - row * 12}%` }" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="n in 4" :key="n" class="flex flex-col gap-3 rounded-2xl border bg-card p-5">
                    <Skeleton class="h-3 w-24" />
                    <Skeleton class="h-4 w-5/6" />
                    <Skeleton class="h-3 w-2/3" />
                    <Skeleton class="h-32 w-full rounded-xl" />
                </div>
            </div>
        </div>
    </div>
</template>
