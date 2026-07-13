<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TnaBackdrop from './TnaBackdrop.vue'
import BackToTop from './BackToTop.vue'
import TnaScanUpload from '@/components/TnaScanUpload.vue'

defineProps({
  assessment: { type: Object, required: true },
  units: { type: Array, default: () => [] },
  priority: { type: Array, default: () => [] },
  revisedPriority: { type: Array, default: () => [] },
  bands: { type: Array, default: () => [] },
})

const weighted = (self, sup) => (0.4 * Number(self)) + (0.6 * Number(sup))

const badgeClass = (label) => ({
  'Not Competent': 'bg-red-100 text-red-700',
  'Slightly Competent': 'bg-orange-100 text-orange-700',
  'Moderately Competent': 'bg-amber-100 text-amber-700',
  'Competent': 'bg-green-100 text-green-700',
  'Highly Competent': 'bg-emerald-100 text-emerald-700',
}[label] || 'bg-gray-100 text-gray-600')

const fmt = (n) => Number(n).toFixed(1)
</script>

<template>
  <Head title="TNA Result" />

  <div class="tna-page relative min-h-screen px-4 py-8">
    <TnaBackdrop />

    <div class="relative z-10 mx-auto max-w-6xl space-y-6">

      <Link
        :href="route('home')"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-white/80 transition hover:text-white"
      >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        Back to Home
      </Link>

      <!-- HEADER -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">
              TRAINING NEEDS ANALYSIS OF CURRENT COMPETENCIES
            </h1>
            <p class="mt-1 text-sm font-semibold uppercase text-gray-500">{{ assessment.position }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-bold tracking-wide text-white">
              TNA {{ assessment.period }}
            </span>
            <a
              :href="route('tna.result.pdf', assessment.id)"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
              View / Print PDF
            </a>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-3 border-t border-gray-100 pt-5 md:grid-cols-2">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Name of Employee</p>
            <p class="text-sm font-semibold text-gray-900">{{ assessment.employee_name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Name of Supervisor</p>
            <p class="text-sm font-semibold text-gray-900">{{ assessment.supervisor_name }}</p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Office / Division / Unit</p>
            <p class="text-sm font-semibold text-gray-900">
              {{ assessment.office }}<span v-if="assessment.division"> / {{ assessment.division }}</span>
            </p>
          </div>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Rated on</p>
            <p class="text-sm font-semibold text-gray-900">{{ assessment.reviewed_at }}</p>
          </div>
        </div>
      </div>

      <!-- SIGNED COPIES -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <h2 class="text-sm font-bold uppercase tracking-wide text-blue-700">Signed Copies</h2>
        <p class="mt-1 text-sm text-gray-500">
          Optional attachments — the auto-generated PDF above remains the official record.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-6 border-t border-gray-100 pt-5 md:grid-cols-2">
          <div>
            <TnaScanUpload
              v-if="assessment.is_owner"
              :assessment-id="assessment.id"
              type="result-subordinate"
              label="Subordinate's Copy"
              :has-file="assessment.result_scan_subordinate_uploaded"
            />
            <template v-else>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Subordinate's Copy</p>
              <a
                v-if="assessment.result_scan_subordinate_uploaded"
                :href="route('tna.scans.download', [assessment.id, 'result-subordinate'])"
                target="_blank"
                rel="noopener"
                class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 shadow-sm transition hover:bg-blue-50"
              >
                View Signed Copy
              </a>
              <p v-else class="mt-1.5 text-xs text-gray-400">Not yet uploaded.</p>
            </template>
          </div>
          <div>
            <TnaScanUpload
              v-if="assessment.is_supervisor"
              :assessment-id="assessment.id"
              type="result-supervisor"
              label="Supervisor's Copy"
              :has-file="assessment.result_scan_supervisor_uploaded"
            />
            <template v-else>
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Supervisor's Copy</p>
              <a
                v-if="assessment.result_scan_supervisor_uploaded"
                :href="route('tna.scans.download', [assessment.id, 'result-supervisor'])"
                target="_blank"
                rel="noopener"
                class="mt-1.5 inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-600 shadow-sm transition hover:bg-blue-50"
              >
                View Signed Copy
              </a>
              <p v-else class="mt-1.5 text-xs text-gray-400">Not yet uploaded.</p>
            </template>
          </div>
        </div>
      </div>

      <!-- TRAINING PRIORITY -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="rounded-2xl bg-white p-6 shadow-xl lg:col-span-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-blue-700">Training Priority List</h2>
          <p class="mt-2 text-sm leading-relaxed text-gray-600">
            Based on the ratings provided by the respondents, the employee/official must
            undergo training programs that will address deficiencies on the following competencies:
          </p>
          <ol v-if="priority.length" class="mt-4 space-y-2">
            <li
              v-for="(p, i) in priority"
              :key="p.unit"
              class="flex items-start gap-3 rounded-xl bg-sky-50/70 p-3"
            >
              <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
                {{ i + 1 }}
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-800">{{ p.unit }}</p>
                <p class="mt-0.5 text-xs text-gray-500">
                  Lowest score <span class="font-semibold">{{ fmt(p.score) }}</span> ·
                  <span :class="badgeClass(p.label)" class="rounded-full px-2 py-0.5 font-semibold">{{ p.label }}</span>
                </p>
              </div>
            </li>
          </ol>
          <p v-else class="mt-4 rounded-xl bg-green-50 p-4 text-sm font-medium text-green-700">
            No training deficiencies identified. All competencies are rated Competent or higher.
          </p>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-xl lg:col-span-2">
          <h2 class="text-sm font-bold uppercase tracking-wide text-blue-700">Result Criteria</h2>
          <ul class="mt-3 space-y-1.5 text-sm text-gray-600">
            <li v-for="b in bands" :key="b.range" class="flex items-center justify-between">
              <span class="font-semibold text-gray-700">{{ b.range }}</span>
              <span>{{ b.label }}</span>
            </li>
          </ul>
          <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">
            Not Competent, Slightly Competent, and Moderately Competent results
            <b>automatically require trainings</b>.
          </p>
          <p class="mt-3 text-xs text-gray-400">
            Score = (0.4 × Self + 0.6 × Supervisor) for each scale, multiplied together. Max 36.
          </p>
        </div>
      </div>

      <!-- RESULT TABLE -->
      <div class="overflow-x-auto rounded-2xl bg-white p-6 shadow-xl">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="border-b-2 border-gray-200 text-xs uppercase text-gray-500">
              <th rowspan="2" class="px-3 py-2 text-left">Unit of Competency</th>
              <th rowspan="2" class="px-3 py-2 text-left">Elements of Unit</th>
              <th colspan="2" class="border-l border-gray-200 px-2 py-2 text-center">Criticality</th>
              <th colspan="2" class="border-l border-gray-200 px-2 py-2 text-center">Competency</th>
              <th colspan="2" class="border-l border-gray-200 px-2 py-2 text-center">Frequency</th>
              <th rowspan="2" class="border-l border-gray-200 px-3 py-2 text-center">Result</th>
              <th rowspan="2" class="px-3 py-2 text-center">Profile</th>
            </tr>
            <tr class="border-b border-gray-200 text-[10px] italic text-gray-400">
              <th class="border-l border-gray-200 px-2 py-1">Sf</th><th class="px-2 py-1">Sp</th>
              <th class="border-l border-gray-200 px-2 py-1">Sf</th><th class="px-2 py-1">Sp</th>
              <th class="border-l border-gray-200 px-2 py-1">Sf</th><th class="px-2 py-1">Sp</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(u, ui) in units" :key="ui">
              <tr v-for="(r, ri) in u.rows" :key="r.competency_id" class="border-b border-gray-100">
                <td v-if="ri === 0" :rowspan="u.rows.length" class="px-3 py-2 align-middle font-semibold text-gray-700">
                  {{ u.unit }}
                </td>
                <td class="px-3 py-2 text-gray-700">{{ r.element }}</td>
                <td class="border-l border-gray-100 px-2 py-2 text-center text-gray-500">{{ r.crit_self }}</td>
                <td class="px-2 py-2 text-center text-gray-900">{{ r.crit_sup }}</td>
                <td class="border-l border-gray-100 px-2 py-2 text-center text-gray-500">{{ r.comp_self }}</td>
                <td class="px-2 py-2 text-center text-gray-900">{{ r.comp_sup }}</td>
                <td class="border-l border-gray-100 px-2 py-2 text-center text-gray-500">{{ r.freq_self }}</td>
                <td class="px-2 py-2 text-center text-gray-900">{{ r.freq_sup }}</td>
                <td class="border-l border-gray-100 px-3 py-2 text-center font-bold text-gray-900">{{ fmt(r.score) }}</td>
                <td class="px-3 py-2 text-center">
                  <span :class="badgeClass(r.label)" class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold">
                    {{ r.label }}
                  </span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-400">
          <b>Sf</b> = Self rating · <b>Sp</b> = Supervisor rating
        </p>
      </div>

      <!-- REVISED RESULT (NEW FORMULA) -->
      <div class="rounded-2xl bg-white p-8 shadow-xl">
        <h2 class="text-sm font-bold uppercase tracking-wide text-purple-700">Revised Result (New Formula)</h2>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">
          Score = Criticality × (4 − Competence). Unlike the result above, a
          <b>higher</b> score here means a <b>higher</b> training need.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-5">
          <div class="lg:col-span-3">
            <h3 class="text-xs font-bold uppercase tracking-wide text-purple-700">Revised Training Priority</h3>
            <ol v-if="revisedPriority.length" class="mt-3 space-y-2">
              <li
                v-for="(p, i) in revisedPriority"
                :key="p.unit"
                class="flex items-start gap-3 rounded-xl bg-purple-50/70 p-3"
              >
                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-purple-600 text-xs font-bold text-white">
                  {{ i + 1 }}
                </span>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-800">{{ p.unit }}</p>
                  <p class="mt-0.5 text-xs text-gray-500">
                    Highest score <span class="font-semibold">{{ fmt(p.revised_score) }}</span>
                  </p>
                </div>
              </li>
            </ol>
            <p v-else class="mt-3 text-sm text-gray-500">No data available.</p>
          </div>

          <div class="rounded-xl border border-purple-100 bg-purple-50/40 p-4 text-xs leading-relaxed text-gray-600 lg:col-span-2">
            <p><b>Criticality</b>: 1 (slightly) – 3 (highly important)</p>
            <p class="mt-1"><b>Competence</b>: 0 (not competent) – 4 (highly competent)</p>
            <p class="mt-1">Max score = 3 × 4 = 12.</p>
          </div>
        </div>

        <div class="mt-6 overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="border-b-2 border-gray-200 text-xs uppercase text-gray-500">
                <th class="px-3 py-2 text-left">Unit of Competency</th>
                <th class="px-3 py-2 text-left">Elements of Unit</th>
                <th class="border-l border-gray-200 px-3 py-2 text-center">Criticality</th>
                <th class="border-l border-gray-200 px-3 py-2 text-center">Competence</th>
                <th class="border-l border-gray-200 px-3 py-2 text-center">Revised Score</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(u, ui) in units" :key="`revised-${ui}`">
                <tr v-for="(r, ri) in u.rows" :key="`revised-${r.competency_id}`" class="border-b border-gray-100">
                  <td v-if="ri === 0" :rowspan="u.rows.length" class="px-3 py-2 align-middle font-semibold text-gray-700">
                    {{ u.unit }}
                  </td>
                  <td class="px-3 py-2 text-gray-700">{{ r.element }}</td>
                  <td class="border-l border-gray-100 px-3 py-2 text-center text-gray-900">
                    {{ fmt(weighted(r.crit_self, r.crit_sup)) }}
                  </td>
                  <td class="border-l border-gray-100 px-3 py-2 text-center text-gray-900">
                    {{ fmt(weighted(r.comp_self, r.comp_sup)) }}
                  </td>
                  <td class="border-l border-gray-100 px-3 py-2 text-center font-bold text-purple-700">
                    {{ fmt(r.revised_score) }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
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