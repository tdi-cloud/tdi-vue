<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import TnaBackdrop from './TnaBackdrop.vue'
import BackToTop from './BackToTop.vue'
import SignaturePad from '@/components/ui/signature-pad/SignaturePad.vue'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  assessment: { type: Object, required: true },   // { id, period, subordinate_name, subordinate_position }
  supervisor: { type: Object, required: true },   // { name, office, division }
  coreUnits: { type: Array, default: () => [] },
  electiveUnits: { type: Array, default: () => [] },
})

const SCALES = [
  { key: 'criticality', label: 'Criticality to Job', options: [1, 2, 3] },
  { key: 'competence', label: 'Level of Competence', options: [0, 1, 2, 3, 4] },
  { key: 'frequency', label: 'Frequency of Utilization', options: [1, 2, 3] },
]
const CRITICALITY_GUIDE = [[1, 'Slightly important'], [2, 'Moderately important'], [3, 'Highly important']]
const COMPETENCE_GUIDE = [[0, 'Not competent'], [1, 'Slightly competent'], [2, 'Moderately competent'], [3, 'Competent'], [4, 'Highly competent']]
const FREQUENCY_GUIDE = [[1, 'Rarely'], [2, 'Occasionally'], [3, 'Frequently']]

/* Mobile: alin ang nakikita (para hindi magsiksikan sa screen) */
const mobileView = ref('mine') // 'mine' | 'self'

/* Answers keyed by competency_id */
const answers = reactive({})
const allElements = [
  ...props.coreUnits.flatMap((u) => u.elements),
  ...props.electiveUnits.flatMap((u) => u.elements),
]
allElements.forEach((el) => {
  answers[el.competency_id] = { criticality: null, competence: null, frequency: null }
})
const requiredIds = allElements.map((e) => e.competency_id)

const doneCount = computed(
  () => requiredIds.filter((id) => {
    const a = answers[id]
    return a.criticality && a.competence !== null && a.frequency
  }).length,
)
const donePct = computed(() =>
  requiredIds.length ? Math.round((doneCount.value / requiredIds.length) * 100) : 100,
)

/* FASD search (Noted by signatory sa TNA Result) */
const fasdQuery = ref('')
const fasdResults = ref([])
const fasdLoading = ref(false)
const fasdOpen = ref(false)
const selectedFasd = ref(null)
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
  fasdQuery.value = ''
  fasdResults.value = []
  form.fasd_empcode = ''
  form.fasd_name = ''
  form.fasd_position = ''
  form.fasd_office = ''
}

const form = useForm({
  name: props.supervisor.name ?? '',
  office: props.supervisor.office ?? '',
  division: props.supervisor.division ?? '',
  subordinate_name: props.assessment.subordinate_name ?? '',
  subordinate_position: props.assessment.subordinate_position ?? '',
  signature: null,
  fasd_empcode: '',
  fasd_name: '',
  fasd_position: '',
  fasd_office: '',
  ratings: [],
})

const canSubmit = computed(() => donePct.value === 100 && !!form.fasd_empcode)

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
    .post(route('tna.supervisory.store', props.assessment.id), {
      preserveScroll: true,
      // Pagka-submit, buksan ang generated PDF sa bagong tab
      onSuccess: () => {
        window.open(route('tna.supervisory.pdf', props.assessment.id), '_blank', 'noopener')
      },
    })
}
</script>

<template>
  <Head title="Supervisory Rating" />

  <div class="tna-page relative min-h-screen px-4 py-8">
    <TnaBackdrop />
    <div class="relative z-10 mx-auto max-w-5xl space-y-6">

      <Link
        :href="route('tna.supervisory.index')"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to list
      </Link>

      <!-- HEADER -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">
              ASSESSMENT OF CURRENT COMPETENCIES
            </h1>
            <p class="mt-1 text-sm text-gray-500">Supervisory Rating</p>
          </div>
          <span class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-bold tracking-wide text-white">
            TNA {{ assessment.period }}
          </span>
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
        </div>

        <div class="mt-6 rounded-xl border border-sky-300 bg-sky-50/60 p-5">
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Rating for</p>
          <div class="mt-2 grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-2">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Name of Subordinate</label>
              <input v-model="form.subordinate_name" type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Position of Subordinate</label>
              <input v-model="form.subordinate_position" type="text"
                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200" />
            </div>
          </div>
        </div>
      </div>

      <!-- SCALE GUIDE -->
      <div class="rounded-2xl bg-white p-6 shadow-xl">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
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

      <!-- PROGRESS + mobile toggle -->
      <div class="sticky top-4 z-10 rounded-xl bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
        <div class="flex items-center justify-between text-xs font-medium text-gray-600">
          <span>Competencies</span>
          <span>{{ doneCount }} / {{ requiredIds.length }}</span>
        </div>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
          <div class="h-full rounded-full bg-blue-600 transition-all duration-300" :style="{ width: donePct + '%' }" />
        </div>
        <!-- Lalabas lang sa phone: toggle sa pagitan ng dalawang panel -->
        <div class="mt-3 flex rounded-lg bg-gray-100 p-1 md:hidden">
          <button type="button"
            class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
            :class="mobileView === 'mine' ? 'bg-white text-blue-600 shadow' : 'text-gray-500'"
            @click="mobileView = 'mine'">
            My Rating
          </button>
          <button type="button"
            class="flex-1 rounded-md px-3 py-1.5 text-xs font-semibold transition"
            :class="mobileView === 'self' ? 'bg-white text-blue-600 shadow' : 'text-gray-500'"
            @click="mobileView = 'self'">
            Subordinate's Self-Rating
          </button>
        </div>
      </div>

      <!-- CORE -->
      <div class="space-y-8">
        <section v-for="(unit, ui) in coreUnits" :key="'core-' + ui" class="rounded-2xl bg-white p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-bold text-blue-700">{{ ui + 1 }}. {{ unit.unit }}</h2>
          <div class="space-y-4">
            <div v-for="el in unit.elements" :key="el.competency_id" class="rounded-xl bg-sky-50/70 p-5">
              <p class="text-[15px] font-semibold text-gray-800">{{ el.element }}</p>

              <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- LEFT: subordinate self-rating (reference, read-only) -->
                <div
                  :class="[mobileView === 'self' ? 'block' : 'hidden', 'md:block']"
                  class="rounded-lg border border-gray-200 bg-white/70 p-4"
                >
                  <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Subordinate's Self-Rating</p>
                  <div class="space-y-3">
                    <div v-for="scale in SCALES" :key="scale.key">
                      <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                      <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <span v-for="opt in scale.options" :key="opt"
                          class="flex h-6 min-w-[1.6rem] items-center justify-center rounded-full px-2 text-xs font-bold"
                          :class="el.self[scale.key] === opt ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-300'">
                          {{ opt }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- RIGHT: supervisor rating (inputs) -->
                <div
                  :class="[mobileView === 'mine' ? 'block' : 'hidden', 'md:block']"
                  class="rounded-lg border border-blue-200 bg-white p-4"
                >
                  <p class="mb-3 text-xs font-bold uppercase tracking-wide text-blue-600">My Rating</p>
                  <div class="space-y-3">
                    <div v-for="scale in SCALES" :key="scale.key">
                      <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                      <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <label v-for="opt in scale.options" :key="opt"
                          class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-700">
                          <input v-model="answers[el.competency_id][scale.key]" type="radio" :value="opt" class="h-4 w-4 accent-blue-600" />
                          {{ opt }}
                        </label>
                      </div>
                    </div>
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
        </div>
        <section v-for="(unit, ui) in electiveUnits" :key="'elec-' + ui" class="rounded-2xl bg-white p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-bold text-blue-700">{{ ui + 1 }}. {{ unit.unit }}</h2>
          <div class="space-y-4">
            <div v-for="el in unit.elements" :key="el.competency_id" class="rounded-xl bg-sky-50/70 p-5">
              <p class="text-[15px] font-semibold text-gray-800">{{ el.element }}</p>

              <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                  :class="[mobileView === 'self' ? 'block' : 'hidden', 'md:block']"
                  class="rounded-lg border border-gray-200 bg-white/70 p-4"
                >
                  <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-500">Subordinate's Self-Rating</p>
                  <div class="space-y-3">
                    <div v-for="scale in SCALES" :key="scale.key">
                      <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                      <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <span v-for="opt in scale.options" :key="opt"
                          class="flex h-6 min-w-[1.6rem] items-center justify-center rounded-full px-2 text-xs font-bold"
                          :class="el.self[scale.key] === opt ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-300'">
                          {{ opt }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <div
                  :class="[mobileView === 'mine' ? 'block' : 'hidden', 'md:block']"
                  class="rounded-lg border border-blue-200 bg-white p-4"
                >
                  <p class="mb-3 text-xs font-bold uppercase tracking-wide text-blue-600">My Rating</p>
                  <div class="space-y-3">
                    <div v-for="scale in SCALES" :key="scale.key">
                      <p class="text-xs font-semibold text-gray-500">{{ scale.label }}</p>
                      <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <label v-for="opt in scale.options" :key="opt"
                          class="inline-flex cursor-pointer items-center gap-1.5 text-sm text-gray-700">
                          <input v-model="answers[el.competency_id][scale.key]" type="radio" :value="opt" class="h-4 w-4 accent-blue-600" />
                          {{ opt }}
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- FASD SIGNATORY (Noted by, TNA Result) -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <h2 class="text-lg font-bold text-gray-900">FASD Signatory for TNA Result</h2>
        <p class="mb-4 text-sm text-gray-500">
          Select the FASD of your region who will be the "Noted by" signatory on the
          TNA Result. This is required before you can submit.
        </p>

        <div class="relative max-w-xl">
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
        <p v-if="form.errors.fasd_empcode" class="mt-2 text-xs text-red-600">
          {{ form.errors.fasd_empcode }}
        </p>
      </div>

      <!-- SIGNATURE + SUBMIT -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <label class="block text-sm font-medium leading-relaxed text-gray-700">
          You may sign below using your mouse, finger, or an uploaded photo. By
          signing, you understand and agree that this form of electronic
          signature has the same legal force and effect as a manual signature.
          This step is optional — you may leave it blank and add your signature later.
        </label>
        <SignaturePad v-model="form.signature" class="mt-1.5" />
        <p v-if="form.errors.signature" class="mt-1 text-xs text-red-600">{{ form.errors.signature }}</p>
        <p v-if="form.errors.ratings" class="mt-1 text-xs text-red-600">{{ form.errors.ratings }}</p>

        <div class="mt-6 flex flex-wrap items-center gap-4">
          <button type="button" :disabled="form.processing || !canSubmit"
            class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-60"
            @click="submit">
            {{ form.processing ? 'Submitting…' : 'Submit Supervisor Rating' }}
          </button>
          <span v-if="donePct < 100" class="text-xs text-gray-500">
            {{ requiredIds.length - doneCount }} more element(s) to answer.
          </span>
          <span v-else-if="!form.fasd_empcode" class="text-xs text-gray-500">
            Please select the FASD signatory above.
          </span>
        </div>
      </div>

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
    <BackToTop />
</div>
</template>