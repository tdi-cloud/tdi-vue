<script setup>
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  reason: { type: String, default: 'no_competencies' },
  position: { type: String, default: null },
})
</script>

<template>
  <Head title="Self Rating" />

  <div class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center p-6">
    <div class="w-full max-w-lg rounded-2xl bg-white p-10 text-center shadow-xl">
      <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50">
        <svg class="h-7 w-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
        </svg>
      </div>

      <h1 class="text-xl font-bold text-gray-900">No TNA Tool Available</h1>

      <p class="mt-3 text-sm leading-relaxed text-gray-600">
        <template v-if="reason === 'no_employee'">
          We couldn't find your employee record. Please contact your
          administrator to have your account linked.
        </template>
        <template v-else>
          Your position
          <span class="font-semibold text-gray-900">{{ position }}</span>
          does not have any competencies (units and elements) assigned yet, so
          the self-rating is not available. Please contact your administrator.
        </template>
      </p>
    </div>
  </div>
</template>