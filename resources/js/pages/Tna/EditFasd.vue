<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import TnaBackdrop from './TnaBackdrop.vue'
import BackToTop from './BackToTop.vue'
import { ref, watch } from 'vue'

const props = defineProps({
  assessment: { type: Object, required: true }, // { id, subordinate_name, subordinate_position }
  fasd: { type: Object, default: () => ({}) },   // { empcode, name, position, office }
})

const fasdQuery = ref('')
const fasdResults = ref([])
const fasdLoading = ref(false)
const fasdOpen = ref(false)
const selectedFasd = ref(props.fasd?.empcode ? props.fasd : null)
let fasdTimer = null

watch(fasdQuery, (val) => {
  clearTimeout(fasdTimer)
  if (val.trim().length < 2) {
    fasdResults.value = []
    fasdOpen.value = false
    return
  }
  fasdTimer = setTimeout(runFasdSearch, 300)
})

async function runFasdSearch() {
  fasdLoading.value = true
  fasdOpen.value = true
  try {
    const res = await fetch(
      route('tna.fasd.search') + '?q=' + encodeURIComponent(fasdQuery.value),
      { headers: { Accept: 'application/json' } },
    )
    fasdResults.value = res.ok ? await res.json() : []
  } catch {
    fasdResults.value = []
  } finally {
    fasdLoading.value = false
  }
}

function pickFasd(f) {
  selectedFasd.value = f
  fasdQuery.value = ''
  fasdResults.value = []
  fasdOpen.value = false
  form.fasd_empcode = f.empcode
  form.fasd_name = f.name
  form.fasd_position = f.position ?? ''
  form.fasd_office = f.office ?? ''
}

function clearFasd() {
  selectedFasd.value = null
  form.fasd_empcode = ''
  form.fasd_name = ''
  form.fasd_position = ''
  form.fasd_office = ''
}

const form = useForm({
  fasd_empcode: props.fasd?.empcode ?? '',
  fasd_name: props.fasd?.name ?? '',
  fasd_position: props.fasd?.position ?? '',
  fasd_office: props.fasd?.office ?? '',
})

const submit = () => {
  form.patch(route('tna.supervisory.fasd.update', props.assessment.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="Change FASD Signatory" />

  <div class="tna-page relative min-h-screen px-4 py-8">
    <TnaBackdrop />
    <div class="relative z-10 mx-auto max-w-2xl space-y-6">

      <Link
        :href="route('tna.supervisory.index')"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to list
      </Link>

      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <h1 class="text-xl font-bold text-gray-900">Change FASD Signatory</h1>
        <p class="mt-1 text-sm text-gray-500">
          "Noted by" signatory on the TNA Result for {{ assessment.subordinate_name }} ·
          {{ assessment.subordinate_position }}
        </p>

        <div class="relative mt-6 max-w-xl">
          <input
            v-model="fasdQuery"
            type="text"
            placeholder="Search by name or empcode…"
            autocomplete="off"
            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            @focus="fasdResults.length && (fasdOpen = true)"
          />

          <!-- Dropdown -->
          <div
            v-if="fasdOpen && (fasdLoading || fasdResults.length)"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
          >
            <div v-if="fasdLoading" class="px-4 py-3 text-sm text-gray-400">Searching…</div>
            <button
              v-for="f in fasdResults"
              :key="f.empcode"
              type="button"
              class="flex w-full flex-col items-start border-b border-gray-50 px-4 py-2.5 text-left last:border-0 hover:bg-blue-50"
              @click="pickFasd(f)"
            >
              <span class="text-sm font-semibold text-gray-900">{{ f.name }}</span>
              <span class="text-xs text-gray-500">{{ f.position }} · {{ f.office }}</span>
            </button>
          </div>
          <div
            v-else-if="fasdOpen && !fasdLoading && fasdQuery.length >= 2"
            class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-400 shadow-lg"
          >
            No matching results found.
          </div>
        </div>

        <!-- Selected chip -->
        <div
          v-if="selectedFasd"
          class="mt-4 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3"
        >
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-green-700">Selected FASD</p>
            <p class="text-sm font-semibold text-gray-900">{{ selectedFasd.name }}</p>
            <p class="text-xs text-gray-500">{{ selectedFasd.position }} · {{ selectedFasd.office }}</p>
          </div>
          <button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="clearFasd">
            Change
          </button>
        </div>
        <p v-else class="mt-4 text-xs text-gray-500">No FASD signatory selected yet.</p>
        <p v-if="form.errors.fasd_empcode" class="mt-2 text-xs text-red-600">
          {{ form.errors.fasd_empcode }}
        </p>

        <div class="mt-6 flex items-center gap-4">
          <button
            type="button"
            :disabled="form.processing || !form.fasd_empcode"
            class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-60"
            @click="submit"
          >
            {{ form.processing ? 'Saving…' : 'Save FASD Signatory' }}
          </button>
        </div>
      </div>

    </div>
    <BackToTop />
  </div>
</template>
