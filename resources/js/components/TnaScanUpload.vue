<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  assessmentId: { type: [Number, String], required: true },
  type: { type: String, required: true }, // 'self' | 'supervisory' | 'result-subordinate' | 'result-supervisor'
  label: { type: String, required: true },
  hasFile: { type: Boolean, default: false },
})

const MAX_BYTES = 10 * 1024 * 1024

const fileInput = ref(null)
const processing = ref(false)
const error = ref('')

function triggerPick() {
  error.value = ''
  fileInput.value?.click()
}

function handleFileChange(e) {
  const file = e.target.files?.[0]
  e.target.value = ''
  if (!file) return

  if (file.type !== 'application/pdf') {
    error.value = 'Please choose a PDF file.'
    return
  }
  if (file.size > MAX_BYTES) {
    error.value = 'File is too large — please choose one under 10MB.'
    return
  }

  error.value = ''
  processing.value = true
  const data = new FormData()
  data.append('file', file)
  router.post(route('tna.scans.upload', [props.assessmentId, props.type]), data, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => { processing.value = false },
  })
}

function destroy() {
  if (!confirm('Delete this signed copy?')) return
  error.value = ''
  processing.value = true
  router.delete(route('tna.scans.destroy', [props.assessmentId, props.type]), {
    preserveScroll: true,
    onFinish: () => { processing.value = false },
  })
}
</script>

<template>
  <div>
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ label }}</p>
    <div class="mt-1.5 flex flex-wrap items-center gap-3">
      <a
        v-if="hasFile"
        :href="route('tna.scans.download', [assessmentId, type])"
        target="_blank"
        rel="noopener"
        class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 shadow-sm transition hover:bg-blue-50"
      >
        View Signed Copy
      </a>
      <button
        type="button"
        :disabled="processing"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
        @click="triggerPick"
      >
        {{ processing ? 'Uploading…' : hasFile ? 'Replace Signed Copy' : 'Upload Signed Copy' }}
      </button>
      <button
        v-if="hasFile"
        type="button"
        :disabled="processing"
        class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
        @click="destroy"
      >
        Delete
      </button>
      <span v-if="!hasFile && !processing" class="text-xs text-gray-400">No signed copy uploaded yet.</span>
    </div>
    <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="handleFileChange" />
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
