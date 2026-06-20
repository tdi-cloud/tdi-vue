<template>
  <section v-if="programs.length" class="enrolled" id="my-programs">
    <div class="enrolled__inner">

      <div class="enrolled__header">
        <div>
          <span class="eyebrow">YOUR LEARNING JOURNEY</span>
          <h2 class="enrolled__title">My Enrolled Programs</h2>
          <p class="enrolled__sub">Track your attendance, hours, and pending requirements at a glance.</p>
        </div>

        <div class="enrolled__filters">
          <div class="filter">
            <CalendarDays class="filter__icon" :size="15" />
            <select v-model="selectedYear">
              <option value="">All years</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <button
            class="filter filter--toggle"
            :class="{ 'filter--active': missingOnly }"
            @click="missingOnly = !missingOnly"
          >
            <FileWarning :size="15" />
            Missing requirements only
          </button>
        </div>
      </div>

      <!-- Summary strip -->
      <div class="summary">
        <div class="summary__stat">
          <GraduationCap class="summary__icon" :size="20" />
          <div>
            <strong>{{ programs.length }}</strong>
            <span>Enrolled Program{{ programs.length === 1 ? '' : 's' }}</span>
          </div>
        </div>
        <div class="summary__stat">
          <Clock class="summary__icon summary__icon--blue" :size="20" />
          <div>
            <strong>{{ totalHours }}</strong>
            <span>Hours Completed</span>
          </div>
        </div>
        <div class="summary__stat">
          <FileWarning class="summary__icon summary__icon--gold" :size="20" />
          <div>
            <strong>{{ totalMissing }}</strong>
            <span>Requirements To Submit</span>
          </div>
        </div>

        <!-- Attendance donut -->
        <div class="summary__donut">
          <div class="donut" :style="{ background: donutGradient }">
            <div class="donut__hole">
              <strong>{{ programs.length }}</strong>
              <span>total</span>
            </div>
          </div>
          <div class="donut__legend">
            <div class="legend__item"><i style="background:#0CA678"></i>Complete ({{ attendanceCounts.Complete }})</div>
            <div class="legend__item"><i style="background:#F59F00"></i>Pending ({{ attendanceCounts.Pending }})</div>
            <div class="legend__item"><i style="background:#e03131"></i>Absent ({{ attendanceCounts.Absent }})</div>
          </div>
        </div>
      </div>

      <!-- Program cards -->
      <div v-if="filteredPrograms.length" class="enrolled__grid">
        <Link
          v-for="p in filteredPrograms"
          :key="p.batch_id"
          :href="route('programs.my-progress', p.batch_id)"
          class="tile"
        >
          <div class="tile__cover">
            <div class="ring" :style="{ background: ringGradient(p) }">
              <div class="ring__inner">
                <img v-if="p.cover_image" :src="p.cover_image" :alt="p.program_title" />
                <BookOpen v-else class="ring__placeholder" :size="26" />
              </div>
            </div>
            <span class="ring__percent">{{ progressPercent(p) }}%</span>
          </div>

          <div class="tile__body">
            <span class="tile__year">{{ p.year }} &middot; {{ p.batch_label }}</span>
            <h3 class="tile__title">{{ p.program_title }}</h3>

            <div class="tile__badges">
              <span class="badge" :class="attendanceBadge(p.attendance).class">
                <component :is="attendanceBadge(p.attendance).icon" :size="13" />
                {{ p.attendance }}
              </span>
              <span class="badge badge--neutral">
                <Clock :size="13" /> {{ p.hours_completed }}/{{ p.total_hours || '—' }} hrs
              </span>
            </div>

            <div class="tile__footer">
              <span v-if="p.requirements_missing > 0" class="missing-pill">
                <FileWarning :size="13" /> {{ p.requirements_missing }} requirement{{ p.requirements_missing === 1 ? '' : 's' }} pending submission
              </span>
              <span v-else class="missing-pill missing-pill--clear">
                <CheckCircle2 :size="13" /> All requirements submitted
              </span>
              <ChevronRight class="tile__arrow" :size="16" />
            </div>
          </div>
        </Link>
      </div>

      <div v-else class="enrolled__empty">
        <FileWarning :size="22" />
        No programs match this filter.
      </div>

    </div>
  </section>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  GraduationCap, Clock, FileWarning, CalendarDays,
  CheckCircle2, AlertCircle, ChevronRight, BookOpen,
} from 'lucide-vue-next'

const props = defineProps({
  programs: { type: Array, default: () => [] },
})

const selectedYear = ref('')
const missingOnly  = ref(false)

const years = computed(() =>
  [...new Set(props.programs.map(p => p.year))].sort((a, b) => b - a)
)

const filteredPrograms = computed(() =>
  props.programs.filter(p => {
    if (selectedYear.value && p.year !== Number(selectedYear.value)) return false
    if (missingOnly.value && p.requirements_missing === 0) return false
    return true
  })
)

const totalHours = computed(() =>
  props.programs.reduce((sum, p) => sum + (p.hours_completed || 0), 0)
)

const totalMissing = computed(() =>
  props.programs.reduce((sum, p) => sum + (p.requirements_missing || 0), 0)
)

const attendanceCounts = computed(() => {
  const counts = { Complete: 0, Pending: 0, Absent: 0 }
  props.programs.forEach(p => { counts[p.attendance] = (counts[p.attendance] || 0) + 1 })
  return counts
})

const donutGradient = computed(() => {
  const total = props.programs.length || 1
  const c = attendanceCounts.value
  const pComplete = (c.Complete / total) * 100
  const pPending  = (c.Pending  / total) * 100
  return `conic-gradient(#0CA678 0% ${pComplete}%, #F59F00 ${pComplete}% ${pComplete + pPending}%, #e03131 ${pComplete + pPending}% 100%)`
})

function progressPercent(p) {
  if (p.total_hours > 0) return Math.min(100, Math.round((p.hours_completed / p.total_hours) * 100))
  return p.attendance === 'Complete' ? 100 : 0
}

function ringGradient(p) {
  const pct = progressPercent(p)
  return `conic-gradient(#1d3fc4 ${pct}%, #e5e7eb ${pct}%)`
}

function attendanceBadge(status) {
  if (status === 'Complete') return { class: 'badge--success', icon: CheckCircle2 }
  if (status === 'Absent')   return { class: 'badge--danger',  icon: AlertCircle }
  return { class: 'badge--pending', icon: Clock }
}
</script>

<style scoped>
.enrolled { padding: 5rem 2rem; background: #f7f9fd; }
.enrolled__inner { max-width: 1100px; margin: 0 auto; }

.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; color: #0CA678; text-transform: uppercase; display: block; margin-bottom: 0.6rem; }
.enrolled__title { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; color: #1a2744; margin-bottom: 0.4rem; }
.enrolled__sub   { color: #6b7280; max-width: 480px; line-height: 1.6; }

.enrolled__header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 2rem; }
.enrolled__filters { display: flex; gap: 0.75rem; flex-wrap: wrap; }

.filter {
  display: flex; align-items: center; gap: 0.4rem;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 0.5rem 0.85rem; font-size: 0.82rem; color: #374151;
}
.filter select { border: none; background: none; font-size: 0.82rem; color: #374151; cursor: pointer; color-scheme: light; }
.filter select:focus { outline: none; }
.filter__icon { color: #9ca3af; }
.filter--toggle { cursor: pointer; font-weight: 600; transition: all 0.15s; }
.filter--toggle:hover { border-color: #1d3fc4; }
.filter--active { background: #eef1fc; border-color: #1d3fc4; color: #1d3fc4; }

/* Summary strip */
.summary {
  display: grid; grid-template-columns: repeat(3, 1fr) auto;
  gap: 1.25rem; align-items: center;
  background: #fff; border-radius: 18px; padding: 1.75rem 2rem;
  box-shadow: 0 4px 24px rgba(15,28,72,0.06);
  margin-bottom: 2.5rem;
}
.summary__stat { display: flex; align-items: center; gap: 0.85rem; }
.summary__icon { color: #6b7280; flex-shrink: 0; }
.summary__icon--blue { color: #1d3fc4; }
.summary__icon--gold { color: #e67700; }
.summary__stat strong { display: block; font-size: 1.3rem; font-weight: 800; color: #1a2744; line-height: 1.1; }
.summary__stat span   { font-size: 0.75rem; color: #6b7280; }

.summary__donut { display: flex; align-items: center; gap: 0.85rem; padding-left: 1.25rem; border-left: 1px solid #eee; }
.donut {
  width: 56px; height: 56px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.donut__hole {
  width: 36px; height: 36px; border-radius: 50%; background: #fff;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.donut__hole strong { font-size: 0.78rem; font-weight: 800; color: #1a2744; line-height: 1; }
.donut__hole span   { font-size: 0.5rem; color: #9ca3af; }
.donut__legend { display: flex; flex-direction: column; gap: 0.25rem; }
.legend__item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.72rem; color: #4b5563; white-space: nowrap; }
.legend__item i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

/* Cards */
.enrolled__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }

.tile {
  display: flex; gap: 1rem; align-items: flex-start;
  background: #fff; border-radius: 16px; padding: 1.25rem;
  text-decoration: none; color: inherit;
  box-shadow: 0 2px 14px rgba(15,28,72,0.06);
  transition: transform 0.2s, box-shadow 0.2s;
}
.tile:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(15,28,72,0.12); }

.tile__cover { position: relative; flex-shrink: 0; text-align: center; }
.ring { width: 64px; height: 64px; border-radius: 50%; padding: 3px; }
.ring__inner {
  width: 100%; height: 100%; border-radius: 50%; overflow: hidden;
  background: #eef1fc; display: flex; align-items: center; justify-content: center;
}
.ring__inner img { width: 100%; height: 100%; object-fit: cover; }
.ring__placeholder { color: #1d3fc4; }
.ring__percent { display: block; font-size: 0.65rem; font-weight: 700; color: #1d3fc4; margin-top: 0.3rem; }

.tile__body { flex: 1; min-width: 0; }
.tile__year { font-size: 0.7rem; color: #9ca3af; font-weight: 600; letter-spacing: 0.03em; }
.tile__title { font-size: 0.98rem; font-weight: 700; color: #1a2744; line-height: 1.3; margin: 0.25rem 0 0.6rem; }

.tile__badges { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.badge {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.55rem; border-radius: 20px;
}
.badge--success { background: #ecfdf5; color: #065f46; }
.badge--pending { background: #fffbeb; color: #92400e; }
.badge--danger  { background: #fef2f2; color: #991b1b; }
.badge--neutral { background: #f3f4f6; color: #374151; }

.tile__footer { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.missing-pill {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 0.72rem; font-weight: 600; color: #92400e;
}
.missing-pill--clear { color: #065f46; }
.tile__arrow { color: #9ca3af; flex-shrink: 0; }

.enrolled__empty {
  display: flex; align-items: center; gap: 0.5rem; justify-content: center;
  color: #9ca3af; font-size: 0.9rem; padding: 2rem; background: #fff; border-radius: 16px;
}

@media (max-width: 1024px) {
  .summary { grid-template-columns: repeat(2, 1fr); }
  .summary__donut { grid-column: 1 / -1; border-left: none; padding-left: 0; border-top: 1px solid #eee; padding-top: 1rem; }
  .enrolled__grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .summary { grid-template-columns: 1fr; }
  .enrolled__grid { grid-template-columns: 1fr; }
}
</style>