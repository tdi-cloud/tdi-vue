<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import TnaBackdrop from './TnaBackdrop.vue'
import BackToTop from './BackToTop.vue'
import TnaScanUpload from '@/components/TnaScanUpload.vue'
import { computed } from 'vue'

defineProps({
  assessments: { type: Array, default: () => [] },
  pendingCount: { type: Number, default: 0 },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)

function redoRating(id) {
  if (!confirm('Clear your rating and start over? Your ratings for this subordinate will be deleted.')) return
  router.delete(route('tna.supervisory.redo', id), { preserveScroll: true })
}
</script>

<template>
  <Head title="Supervisory Rating" />

  <div class="tna-page relative min-h-screen px-4 py-8">
    <TnaBackdrop />
    <div class="relative z-10 mx-auto max-w-4xl space-y-6">

      <Link
        :href="route('home')"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to Home
      </Link>

      <div
        v-if="flashSuccess"
        class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-800 shadow"
      >
        {{ flashSuccess }}
      </div>

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
          class="rounded-2xl bg-white p-5 shadow-xl"
        >
        <div class="flex flex-wrap items-center justify-between gap-4">
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
              <Link
                :href="route('tna.result.show', a.id)"
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
              >
                View Result
              </Link>
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
              <Link
                :href="route('tna.supervisory.fasd.edit', a.id)"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50"
              >
                Change FASD
              </Link>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50"
                @click="redoRating(a.id)"
              >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Redo
              </button>
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

        <div v-if="a.reviewed" class="mt-4 border-t border-gray-100 pt-4">
          <TnaScanUpload
            :assessment-id="a.id"
            type="supervisory"
            label="Supervisory Rating"
            :has-file="a.supervisor_scan_uploaded"
          />
        </div>
        </div>
      </div>

    </div>
    <BackToTop />
</div>
</template>