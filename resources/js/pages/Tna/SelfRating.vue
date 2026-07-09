<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  period: { type: String, default: '' },
  employee: { type: Object, required: true },
  coreUnits: { type: Array, default: () => [] },
  electiveUnits: { type: Array, default: () => [] },
  alreadySubmitted: { type: Object, default: null },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)

/* Scale guide */
const SCALES = [
  { key: 'criticality', label: 'Criticality to Job', options: [1, 2, 3] },
  { key: 'competence', label: 'Level of Competence', options: [0, 1, 2, 3, 4] },
  { key: 'frequency', label: 'Frequency of Utilization', options: [1, 2, 3] },
]
const CRITICALITY_GUIDE = [[1, 'Slightly important'], [2, 'Moderately important'], [3, 'Highly important']]
const COMPETENCE_GUIDE = [[0, 'Not competent'], [1, 'Slightly competent'], [2, 'Moderately competent'], [3, 'Competent'], [4, 'Highly competent']]
const FREQUENCY_GUIDE = [[1, 'Rarely'], [2, 'Occasionally'], [3, 'Frequently']]

/* Answers */
const answers = reactive({})
const allElements = [
  ...props.coreUnits.flatMap((u) => u.elements),
  ...props.electiveUnits.flatMap((u) => u.elements),
]
allElements.forEach((el) => {
  answers[el.id] = { criticality: null, competence: null, frequency: null }
})
const requiredIds = [
  ...props.coreUnits.flatMap((u) => u.elements.map((e) => e.id)),
  ...props.electiveUnits.flatMap((u) => u.elements.map((e) => e.id)),
]

const coreDone = computed(
  () => requiredIds.filter((id) => {
    const a = answers[id]
    return a.criticality && a.competence !== null && a.frequency
  }).length,
)
const corePct = computed(() =>
  requiredIds.length ? Math.round((coreDone.value / requiredIds.length) * 100) : 100,
)

/* Supervisor search */
const supQuery = ref('')
const supResults = ref([])
const supLoading = ref(false)
const supOpen = ref(false)
const selectedSupervisor = ref(null)
let supTimer = null

watch(supQuery, (val) => {
  clearTimeout(supTimer)
  if (val.trim().length < 2) {
    supResults.value = []
    supOpen.value = false
    return
  }
  supTimer = setTimeout(runSupSearch, 300)
})

async function runSupSearch() {
  supLoading.value = true
  supOpen.value = true
  try {
    const res = await fetch(
      route('tna.supervisor.search') + '?q=' + encodeURIComponent(supQuery.value),
      { headers: { Accept: 'application/json' } },
    )
    supResults.value = res.ok ? await res.json() : []
  } catch (e) {
    supResults.value = []
  } finally {
    supLoading.value = false
  }
}

function pickSupervisor(s) {
  selectedSupervisor.value = s
  supQuery.value = ''
  supResults.value = []
  supOpen.value = false
  form.supervisor_empcode = s.empcode
  form.supervisor_name = s.name
  form.supervisor_position = s.position ?? ''
}

function clearSupervisor() {
  selectedSupervisor.value = null
  supQuery.value = ''
  supResults.value = []
  form.supervisor_empcode = ''
  form.supervisor_name = ''
  form.supervisor_position = ''
}

/* Form */
const form = useForm({
  name: props.employee.name ?? '',
  office: props.employee.office ?? '',
  division: props.employee.division ?? '',
  designation: '',
  supervisor_empcode: '',
  supervisor_name: '',
  supervisor_position: '',
  signature: '',
  ratings: [],
})

const canSubmit = computed(
  () => !!form.supervisor_empcode && corePct.value === 100 && !!form.signature.trim(),
)

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      ratings: Object.entries(answers).map(([id, a]) => ({
        competency_id: Number(id),
        criticality: a.criticality,
        competence: a.competence,
        frequency: a.frequency,
      })),
    }))
    .post(route('tna.self-rating.store'), { preserveScroll: true })
}

function deleteAssessment() {
  if (!props.alreadySubmitted?.id) return
  if (!confirm('Delete your self-rating and start over? This action cannot be undone.')) return
  router.delete(route('tna.self-rating.destroy', props.alreadySubmitted.id), {
    preserveScroll: true,
  })
}

/* ── Change supervisor (submitted state) ────────────────────────── */
const chgOpen = ref(false)
const chgQuery = ref('')
const chgResults = ref([])
const chgLoading = ref(false)
const chgSelected = ref(null)
const chgSaving = ref(false)
let chgTimer = null

watch(chgQuery, (val) => {
  clearTimeout(chgTimer)
  if (val.trim().length < 2) {
    chgResults.value = []
    return
  }
  chgTimer = setTimeout(runChgSearch, 300)
})

async function runChgSearch() {
  chgLoading.value = true
  try {
    const res = await fetch(
      route('tna.supervisor.search') + '?q=' + encodeURIComponent(chgQuery.value),
      { headers: { Accept: 'application/json' } },
    )
    chgResults.value = res.ok ? await res.json() : []
  } catch (e) {
    chgResults.value = []
  } finally {
    chgLoading.value = false
  }
}

function pickChg(s) {
  chgSelected.value = s
  chgQuery.value = ''
  chgResults.value = []
}

function saveSupervisor() {
  if (!chgSelected.value || !props.alreadySubmitted?.id) return
  chgSaving.value = true
  router.patch(
    route('tna.self-rating.supervisor', props.alreadySubmitted.id),
    {
      supervisor_empcode: chgSelected.value.empcode,
      supervisor_name: chgSelected.value.name,
      supervisor_position: chgSelected.value.position ?? '',
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        chgOpen.value = false
        chgSelected.value = null
        chgQuery.value = ''
        chgResults.value = []
      },
      onFinish: () => { chgSaving.value = false },
    },
  )
}
</script>

<template>
  <Head title="Self Rating" />

  <div class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-800 px-4 py-8">
    <div class="mx-auto max-w-5xl space-y-6">

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

      <!-- Already submitted state -->
      <div
        v-if="alreadySubmitted"
        class="rounded-2xl bg-white p-8 text-center shadow-xl"
      >
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-50">
          <svg class="h-7 w-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
          </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900">Your self-rating has been submitted</h1>
        <p class="mt-2 text-sm text-gray-600">
          For TNA period <b>{{ period }}</b>. Submitted on
          {{ alreadySubmitted.submitted_at }}. Please wait for the supervisory
          rating from your selected supervisor.
        </p>
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
          <a
            v-if="alreadySubmitted.id"
            :href="route('tna.self-rating.pdf', alreadySubmitted.id)"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
            </svg>
            View / Print PDF
          </a>

          <button
            v-if="alreadySubmitted.id && !alreadySubmitted.reviewed"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-red-300 bg-white px-5 py-2.5 text-sm font-semibold text-red-600 shadow-sm transition hover:bg-red-50"
            @click="deleteAssessment"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
            </svg>
            Delete &amp; Redo
          </button>
        </div>
        <p v-if="alreadySubmitted.reviewed" class="mt-3 text-xs text-gray-400">
          This self-rating has been reviewed by your supervisor and can no longer be edited.
        </p>

        <!-- Change supervisor -->
        <div v-if="!alreadySubmitted.reviewed" class="mx-auto mt-6 max-w-md border-t border-gray-100 pt-5 text-left">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Supervisor</p>
              <p class="text-sm font-semibold text-gray-800">{{ alreadySubmitted.supervisor_name }}</p>
            </div>
            <button
              type="button"
              class="text-xs font-semibold text-blue-600 hover:underline"
              @click="chgOpen = !chgOpen"
            >
              {{ chgOpen ? 'Cancel' : 'Change Supervisor' }}
            </button>
          </div>

          <div v-if="chgOpen" class="mt-3">
            <div class="relative">
              <input
                v-model="chgQuery"
                type="text"
                placeholder="Search by name or empcode…"
                autocomplete="off"
                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
              />
              <div
                v-if="chgLoading || chgResults.length"
                class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
              >
                <div v-if="chgLoading" class="px-4 py-3 text-sm text-gray-400">Searching…</div>
                <button
                  v-for="s in chgResults"
                  :key="s.empcode"
                  type="button"
                  class="flex w-full flex-col items-start border-b border-gray-50 px-4 py-2.5 text-left last:border-0 hover:bg-blue-50"
                  @click="pickChg(s)"
                >
                  <span class="text-sm font-semibold text-gray-900">{{ s.name }}</span>
                  <span class="text-xs text-gray-500">{{ s.position }} · {{ s.office }}</span>
                </button>
              </div>
            </div>

            <div
              v-if="chgSelected"
              class="mt-3 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3"
            >
              <div>
                <p class="text-xs font-medium uppercase tracking-wide text-green-700">New Supervisor</p>
                <p class="text-sm font-semibold text-gray-900">{{ chgSelected.name }}</p>
                <p class="text-xs text-gray-500">{{ chgSelected.position }} · {{ chgSelected.office }}</p>
              </div>
              <button
                type="button"
                :disabled="chgSaving"
                class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                @click="saveSupervisor"
              >
                {{ chgSaving ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Form (when not yet submitted) -->
      <template v-else>
        <!-- HEADER -->
        <div class="rounded-2xl bg-white p-8 shadow-xl">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h1 class="text-2xl font-bold tracking-tight text-gray-900">
                ASSESSMENT OF CURRENT COMPETENCIES
              </h1>
              <p class="mt-1 text-sm text-gray-500">Self Rating</p>
            </div>
            <span class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-bold tracking-wide text-white">
              TNA {{ period }}
            </span>
          </div>

          <div class="mt-6 rounded-xl border border-sky-300 bg-sky-50/60 px-5 py-4">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Position:</p>
            <p class="mt-0.5 text-lg font-semibold text-gray-900">{{ employee.position }}</p>
          </div>

          <p class="mt-6 text-xs text-gray-400">These details are auto-filled but you may edit them if needed.</p>
          <div class="mt-2 grid grid-cols-1 gap-x-8 gap-y-5 md:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Name</label>
              <input v-model="form.name" type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Office</label>
              <input v-model="form.office" type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Division</label>
              <input v-model="form.division" type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Designation</label>
              <input v-model="form.designation" type="text" placeholder="Enter your designation"
                class="mt-1.5 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
          </div>
        </div>

        <!-- STEP 1: SUPERVISOR -->
        <div class="rounded-2xl bg-white p-8 shadow-xl">
          <div class="mb-1 flex items-center gap-2">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
            <h2 class="text-lg font-bold text-gray-900">Select your Supervisor</h2>
          </div>
          <p class="mb-4 text-sm text-gray-500">
            Your selected supervisor will provide the supervisory rating after your
            self-rating. This is required before you can submit.
          </p>

          <div class="relative max-w-xl">
            <input
              v-model="supQuery"
              type="text"
              placeholder="Search by name or empcode…"
              autocomplete="off"
              class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
              @focus="supResults.length && (supOpen = true)"
            />

            <!-- Dropdown -->
            <div
              v-if="supOpen && (supLoading || supResults.length)"
              class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
            >
              <div v-if="supLoading" class="px-4 py-3 text-sm text-gray-400">Searching…</div>
              <button
                v-for="s in supResults"
                :key="s.empcode"
                type="button"
                class="flex w-full flex-col items-start border-b border-gray-50 px-4 py-2.5 text-left last:border-0 hover:bg-blue-50"
                @click="pickSupervisor(s)"
              >
                <span class="text-sm font-semibold text-gray-900">{{ s.name }}</span>
                <span class="text-xs text-gray-500">{{ s.position }} · {{ s.office }}</span>
              </button>
            </div>
            <div
              v-else-if="supOpen && !supLoading && supQuery.length >= 2"
              class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-400 shadow-lg"
            >
              No matching results found.
            </div>
          </div>

          <!-- Selected chip -->
          <div
            v-if="selectedSupervisor"
            class="mt-4 flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-3"
          >
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-green-700">Selected Supervisor</p>
              <p class="text-sm font-semibold text-gray-900">{{ selectedSupervisor.name }}</p>
              <p class="text-xs text-gray-500">{{ selectedSupervisor.position }} · {{ selectedSupervisor.office }}</p>
            </div>
            <button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="clearSupervisor">
              Change
            </button>
          </div>
          <p v-if="form.errors.supervisor_empcode" class="mt-2 text-xs text-red-600">
            {{ form.errors.supervisor_empcode }}
          </p>
        </div>

        <!-- INSTRUCTIONS -->
        <div class="rounded-2xl bg-white p-6 shadow-xl">
          <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
            <p class="text-sm font-bold text-blue-700">INSTRUCTIONS:</p>
            <p class="mt-1 text-sm leading-relaxed text-gray-700">
              Below are the units of competencies required in the performance of your
              job. Using the scale below, rate the competency units according to its
              <b>CRITICALITY</b> to your job, your level of <b>COMPETENCY</b>, and
              <b>FREQUENCY</b> of utilization. Aside from the list, choose any of the
              elective competencies that you need in your work, if applicable. Please
              answer carefully as this assessment will determine your Professional
              Development Plan.
            </p>
          </div>

          <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-5">
            <p class="text-sm font-bold text-gray-800">Scale Guide:</p>
            <div class="mt-3 grid grid-cols-1 gap-6 text-sm sm:grid-cols-3">
              <div>
                <p class="font-semibold text-blue-700">Criticality to Job:</p>
                <ul class="mt-1 space-y-0.5 text-gray-600">
                  <li v-for="[n, t] in CRITICALITY_GUIDE" :key="n">{{ n }} - {{ t }}</li>
                </ul>
              </div>
              <div>
                <p class="font-semibold text-blue-700">Level of Competence:</p>
                <ul class="mt-1 space-y-0.5 text-gray-600">
                  <li v-for="[n, t] in COMPETENCE_GUIDE" :key="n">{{ n }} - {{ t }}</li>
                </ul>
              </div>
              <div>
                <p class="font-semibold text-blue-700">Frequency of Utilization:</p>
                <ul class="mt-1 space-y-0.5 text-gray-600">
                  <li v-for="[n, t] in FREQUENCY_GUIDE" :key="n">{{ n }} - {{ t }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- PROGRESS -->
        <div class="sticky top-4 z-10 rounded-xl bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
          <div class="flex items-center justify-between text-xs font-medium text-gray-600">
            <span>Step 2 · Competencies</span>
            <span>{{ coreDone }} / {{ requiredIds.length }}</span>
          </div>
          <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
            <div class="h-full rounded-full bg-blue-600 transition-all duration-300" :style="{ width: corePct + '%' }" />
          </div>
        </div>

        <!-- CORE -->
        <div class="space-y-8">
          <section v-for="(unit, ui) in coreUnits" :key="'core-' + ui" class="rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-lg font-bold text-blue-700">{{ ui + 1 }}. {{ unit.unit }}</h2>
            <div class="space-y-4">
              <div v-for="el in unit.elements" :key="el.id" class="rounded-xl bg-sky-50/70 p-5">
                <p class="text-[15px] font-semibold text-gray-800">{{ el.element }}</p>
                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-3">
                  <div v-for="scale in SCALES" :key="scale.key">
                    <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                      <label v-for="opt in scale.options" :key="opt"
                        class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-700">
                        <input v-model="answers[el.id][scale.key]" type="radio" :value="opt" class="h-4 w-4 accent-blue-600" />
                        {{ opt }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- ELECTIVE -->
        <div v-if="electiveUnits.length" class="space-y-8">
          <div class="rounded-2xl bg-white px-6 py-4 shadow-xl">
            <p class="text-sm font-bold tracking-wide text-blue-700">ELECTIVE COMPETENCIES</p>
            <p class="mt-1 text-xs text-gray-500">
              Please rate all of the following competencies.
            </p>
          </div>
          <section v-for="(unit, ui) in electiveUnits" :key="'elec-' + ui" class="rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="mb-4 text-lg font-bold text-blue-700">{{ ui + 1 }}. {{ unit.unit }}</h2>
            <div class="space-y-4">
              <div v-for="el in unit.elements" :key="el.id" class="rounded-xl bg-sky-50/70 p-5">
                <p class="text-[15px] font-semibold text-gray-800">{{ el.element }}</p>
                <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-3">
                  <div v-for="scale in SCALES" :key="scale.key">
                    <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                      <label v-for="opt in scale.options" :key="opt"
                        class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-700">
                        <input v-model="answers[el.id][scale.key]" type="radio" :value="opt" class="h-4 w-4 accent-blue-600" />
                        {{ opt }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- SIGNATURE + SUBMIT -->
        <div class="rounded-2xl bg-white p-8 shadow-xl">
          <label class="block text-sm font-medium leading-relaxed text-gray-700">
            By typing my name below, I understand and agree that this form of
            electronic signature has the same legal force and effect as a manual
            signature.
          </label>
          <input v-model="form.signature" type="text" placeholder="e.g. Juan D. Dela Cruz"
            aria-label="Electronic signature"
            class="mt-1.5 w-full max-w-md rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
          <p v-if="form.errors.signature" class="mt-1 text-xs text-red-600">{{ form.errors.signature }}</p>
          <p v-if="form.errors.ratings" class="mt-1 text-xs text-red-600">{{ form.errors.ratings }}</p>

          <div class="mt-6 flex flex-wrap items-center gap-4">
            <button type="button" :disabled="form.processing || !canSubmit"
              class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-60"
              @click="submit">
              {{ form.processing ? 'Submitting…' : 'Submit Self-Rating' }}
            </button>
            <span v-if="!form.supervisor_empcode" class="text-xs text-gray-500">Please select a supervisor first.</span>
            <span v-else-if="corePct < 100" class="text-xs text-gray-500">
              {{ requiredIds.length - coreDone }} more element(s) to answer.
            </span>
          </div>
        </div>
      </template>

      <!-- FOOTER -->
      <footer class="flex items-center justify-center gap-2 pt-4 pb-2">
        <img
          src="https://upload.wikimedia.org/wikipedia/commons/e/ef/TESDA_Seal.svg"
          alt="TESDA Seal"
          class="h-6 w-6"
        />
        <span class="text-xs font-medium text-white/80">TESDA Development Institute</span>
      </footer>

    </div>
  </div>
</template>