<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
  assessments: { type: Array, default: () => [] },
  pendingCount: { type: Number, default: 0 },
})
</script>

<template>
  <Head title="Supervisory Rating" />

  <div class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 px-4 py-8">
    <div class="mx-auto max-w-4xl space-y-6">

      <Link
        :href="route('home')"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to Home
      </Link>

      <!-- Header -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Supervisory Rating</h1>
        <p class="mt-1 text-sm text-gray-500">
          Team members who selected you as their supervisor.
          <span v-if="pendingCount" class="font-semibold text-blue-600">
            {{ pendingCount }} pending review.
          </span>
        </p>
      </div>

      <!-- Empty state -->
      <div v-if="!assessments.length" class="rounded-2xl bg-white p-10 text-center shadow-xl">
        <p class="text-sm text-gray-500">No one has selected you as their supervisor yet.</p>
      </div>

      <!-- List -->
      <div v-else class="space-y-3">
        <div
          v-for="a in assessments"
          :key="a.id"
          class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-5 shadow-xl"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <p class="text-base font-semibold text-gray-900">{{ a.name }}</p>
              <span
                v-if="a.reviewed"
                class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700"
              >
                Rated
              </span>
              <span
                v-else
                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700"
              >
                Pending
              </span>
            </div>
            <p class="mt-0.5 text-sm text-gray-600">{{ a.position }}</p>
            <p class="mt-0.5 text-xs text-gray-400">
              {{ a.office }}<span v-if="a.division"> · {{ a.division }}</span>
              · TNA {{ a.period }} · Submitted {{ a.submitted_at }}
            </p>
          </div>

          <div class="flex items-center gap-3">
            <template v-if="a.reviewed">
              <span class="text-xs text-gray-400">Rated {{ a.reviewed_at }}</span>
              <a
                :href="route('tna.supervisory.pdf', a.id)"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-600 shadow-sm transition hover:bg-blue-50"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                </svg>
                View PDF
              </a>
            </template>

            <!-- Rate button -->
            <Link
              v-else
              :href="route('tna.supervisory.show', a.id)"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
              Rate
            </Link>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>