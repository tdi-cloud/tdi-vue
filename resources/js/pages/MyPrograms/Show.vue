<template>
  <Head :title="program.program_title" />
  <div class="progress-page">
    <TheNavbar />

    <!-- Hero header -->
    <section class="ph">
      <div class="ph__bg" :style="{ backgroundImage: program.cover_image ? `url(${program.cover_image})` : 'none' }"></div>
      <div class="ph__overlay"></div>
      <div class="ph__inner">
        <Link href="/#my-programs" class="ph__back"><ArrowLeft :size="15" /> Back to My Programs</Link>
        <span class="ph__year"><CalendarDays :size="13" /> {{ program.year }} &middot; {{ program.batch_label }}</span>
        <h1>{{ program.program_title }}</h1>
        <div class="ph__meta">
          <span><MapPin :size="14" /> {{ program.venue || program.modality }}</span>
          <span><Clock :size="14" /> {{ formatDate(program.date_start) }} – {{ formatDate(program.date_end) }}</span>
        </div>
      </div>
    </section>

    <!-- Flash message -->
    <div v-if="$page.props.flash?.success" class="flash flash--success">
      <CheckCircle2 :size="16" /> {{ $page.props.flash.success }}
    </div>

    <section class="stats">
      <div class="stats__inner">

        <!-- Hours / progress ring -->
        <div class="stat-card stat-card--ring">
          <div class="ring-lg" :style="{ background: ringGradient }">
            <div class="ring-lg__hole">
              <strong>{{ hoursPercent }}%</strong>
              <span>complete</span>
            </div>
          </div>
          <div>
            <div class="stat-card__label">Hours Completed</div>
            <div class="stat-card__value">{{ program.hours_completed }} / {{ program.total_hours || '—' }} hrs</div>
          </div>
        </div>

        <!-- Attendance -->
        <div class="stat-card">
          <component :is="attendanceIcon" class="stat-card__icon" :class="attendanceColorClass" :size="26" />
          <div>
            <div class="stat-card__label">Attendance Status</div>
            <div class="stat-card__value">{{ program.attendance }}</div>
          </div>
        </div>

        <!-- Requirements -->
        <div class="stat-card">
          <FileWarning class="stat-card__icon" :class="program.requirements_missing > 0 ? 'icon--gold' : 'icon--green'" :size="26" />
          <div>
            <div class="stat-card__label">Requirements</div>
            <div class="stat-card__value">
              {{ program.requirements_total - program.requirements_missing }} / {{ program.requirements_total }} submitted
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- Requirements breakdown -->
    <section class="reqs">
      <div class="reqs__inner">
        <h2>Requirements Checklist</h2>
        <p class="reqs__sub">Submit each requirement below as a PDF file. You can re-submit anytime before it's approved.</p>

        <div class="reqs__chips">
          <span class="chip chip--approved"><CheckCircle2 :size="13" /> {{ program.requirements_approved }} Approved</span>
          <span class="chip chip--pending"><Clock :size="13" /> {{ program.requirements_pending }} Pending Review</span>
          <span class="chip chip--rejected"><XCircle :size="13" /> {{ program.requirements_rejected }} Rejected</span>
          <span class="chip chip--missing"><FileWarning :size="13" /> {{ program.requirements_missing }} Not Submitted</span>
        </div>

        <div class="reqs__list">
          <div v-for="r in program.requirements" :key="r.id" class="req-row" :class="{ 'req-row--missing': !r.status && r.is_required }">
            <div class="req-row__icon">
              <component :is="reqIcon(r.status)" :size="18" :class="reqIconClass(r.status)" />
            </div>

            <div class="req-row__main">
              <div class="req-row__title">{{ r.name }}</div>
              <div class="req-row__meta">
                Due {{ formatDate(r.due_date) }}
                <span v-if="!r.is_required" class="req-row__optional">&middot; Optional</span>
              </div>
              <div v-if="r.remarks && r.status === 'Rejected'" class="req-row__remarks">
                <strong>Reviewer note:</strong> {{ r.remarks }}
              </div>

              <!-- Naisubmit na file -->
              <div v-if="r.file_url" class="req-row__file">
                <a :href="r.file_url" target="_blank" rel="noopener" class="file-link">
                  <FileText :size="14" /> {{ r.file_name }}
                </a>
                <button
                  v-if="r.status !== 'Approved'"
                  type="button"
                  class="delete-btn"
                  :disabled="deletingId === r.id"
                  @click="confirmDelete(r)"
                >
                  <Trash2 :size="13" /> {{ deletingId === r.id ? 'Deleting…' : 'Delete' }}
                </button>
              </div>

              <!-- Submit / Re-submit form — hindi ipapakita kapag Approved na -->
              <form
                v-if="r.status !== 'Approved'"
                class="req-row__upload"
                @submit.prevent="submitFile(r)"
              >
                <label class="upload-input">
                  <UploadCloud :size="14" />
                  <span>{{ selectedFile[r.id]?.name || (r.file_url ? 'Replace file (PDF)' : 'Choose PDF file') }}</span>
                  <input type="file" accept="application/pdf" @change="onFileChange($event, r.id)" />
                </label>
                <button
                  type="submit"
                  class="upload-btn"
                  :disabled="(!selectedFile[r.id] && noteDraft[r.id] === (r.notes || '')) || uploadingId === r.id"
                >
                  {{ uploadingId === r.id ? 'Saving…' : (r.file_url ? 'Save Changes' : 'Submit') }}
                </button>
              </form>

              <!-- Notes -->
              <div v-if="r.status !== 'Approved'" class="req-row__notes">
                <textarea
                  v-model="noteDraft[r.id]"
                  placeholder="Add a note for the reviewer (optional)…"
                  rows="2"
                ></textarea>
              </div>
              <div v-else-if="r.notes" class="req-row__notes-readonly">
                <strong>Your note:</strong> {{ r.notes }}
              </div>

              <div v-if="fileError[r.id]" class="req-row__error">{{ fileError[r.id] }}</div>
            </div>

            <span class="req-row__status" :class="reqBadgeClass(r.status)">
              {{ r.status || 'Not yet submitted' }}
            </span>
          </div>

          <div v-if="!program.requirements.length" class="reqs__empty">
            <CheckCircle2 :size="18" /> No requirements have been set for this batch yet.
          </div>
        </div>
      </div>
    </section>

    <!-- Resource Speakers — naka-hide kapag walang laman -->
    <section v-if="program.resource_speakers && program.resource_speakers.length" class="speakers">
      <div class="speakers__inner">
        <h2>Resource Speakers</h2>
        <p class="reqs__sub">Facilitators and resource persons engaged for this program.</p>

        <div class="speakers__grid">
          <div v-for="s in program.resource_speakers" :key="s.id" class="speaker-card">
            <div class="speaker-card__avatar"><Mic2 :size="18" /></div>
            <div class="speaker-card__name">{{ s.name }}</div>
            <div v-if="s.designation || s.affiliation" class="speaker-card__role">
              {{ [s.designation, s.affiliation].filter(Boolean).join(' — ') }}
            </div>
            <div v-if="s.topic" class="speaker-card__topic"><BookOpen :size="13" /> {{ s.topic }}</div>
            <div v-if="s.date_engaged" class="speaker-card__date"><CalendarDays :size="13" /> {{ formatDate(s.date_engaged) }}</div>
          </div>
        </div>
      </div>
    </section>

    <TheFooter />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TheNavbar from '../Welcome/sections/TheNavbar.vue'
import TheFooter from '../Welcome/sections/TheFooter.vue'
import {
  ArrowLeft, CalendarDays, MapPin, Clock, FileWarning,
  CheckCircle2, AlertCircle, XCircle, CircleDashed,
  UploadCloud, Mic2, BookOpen, FileText, Trash2,
} from 'lucide-vue-next'

const props = defineProps({
  program: { type: Object, required: true },
})

// Initialize note drafts from existing notes per requirement
const noteDraft = ref(
  Object.fromEntries(props.program.requirements.map(r => [r.id, r.notes || '']))
)

const hoursPercent = computed(() => {
  if (props.program.total_hours > 0) {
    return Math.min(100, Math.round((props.program.hours_completed / props.program.total_hours) * 100))
  }
  return props.program.attendance === 'Complete' ? 100 : 0
})

const ringGradient = computed(() => `conic-gradient(#1d3fc4 ${hoursPercent.value}%, #e5e7eb ${hoursPercent.value}%)`)

const attendanceIcon = computed(() => {
  if (props.program.attendance === 'Complete') return CheckCircle2
  if (props.program.attendance === 'Absent')   return AlertCircle
  return Clock
})
const attendanceColorClass = computed(() => {
  if (props.program.attendance === 'Complete') return 'icon--green'
  if (props.program.attendance === 'Absent')   return 'icon--red'
  return 'icon--gold'
})

function reqIcon(status) {
  if (status === 'Approved') return CheckCircle2
  if (status === 'Rejected') return XCircle
  if (status === 'Pending')  return Clock
  return CircleDashed
}
function reqIconClass(status) {
  if (status === 'Approved') return 'icon--green'
  if (status === 'Rejected') return 'icon--red'
  if (status === 'Pending')  return 'icon--gold'
  return 'icon--muted'
}
function reqBadgeClass(status) {
  if (status === 'Approved') return 'req-row__status--approved'
  if (status === 'Rejected') return 'req-row__status--rejected'
  if (status === 'Pending')  return 'req-row__status--pending'
  return 'req-row__status--missing'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}

/* ---- File submission ---- */
const selectedFile = ref({})   // { [requirementId]: File }
const fileError    = ref({})   // { [requirementId]: string }
const uploadingId  = ref(null)
const deletingId   = ref(null)

function onFileChange(event, requirementId) {
  const file = event.target.files[0]
  fileError.value[requirementId] = null

  if (file && file.type !== 'application/pdf') {
    fileError.value[requirementId] = 'Only PDF files are accepted.'
    selectedFile.value[requirementId] = null
    return
  }
  if (file && file.size > 20 * 1024 * 1024) {
    fileError.value[requirementId] = 'File must not exceed 20MB.'
    selectedFile.value[requirementId] = null
    return
  }
  selectedFile.value[requirementId] = file
}

function submitFile(requirement) {
  const file = selectedFile.value[requirement.id]

  uploadingId.value = requirement.id

  const formData = new FormData()
  if (file) formData.append('file', file)
  formData.append('notes', noteDraft.value[requirement.id] || '')

  router.post(
    route('programs.my-progress.submit', { batch: props.program.batch_id, requirement: requirement.id }),
    formData,
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        selectedFile.value[requirement.id] = null
      },
      onError: (errors) => {
        fileError.value[requirement.id] = errors.file || 'Save failed. Please try again.'
      },
      onFinish: () => {
        uploadingId.value = null
      },
    }
  )
}

function confirmDelete(requirement) {
  if (!window.confirm(`Delete your submission for "${requirement.name}"? You'll need to submit again.`)) return

  deletingId.value = requirement.id

  router.delete(
    route('programs.my-progress.destroy', { batch: props.program.batch_id, requirement: requirement.id }),
    {
      preserveScroll: true,
      onFinish: () => {
        deletingId.value = null
      },
    }
  )
}
</script>

<style scoped>
.progress-page { font-family: 'Inter', system-ui, sans-serif; color: #1a2744; color-scheme: light; }

/* Hero */
.ph { position: relative; padding: 9rem 2rem 4rem; overflow: hidden; background: #0f1c48; }
.ph__bg { position: absolute; inset: 0; background-size: cover; background-position: center; }
.ph__overlay { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(10,21,60,0.92) 0%, rgba(10,21,60,0.7) 100%); }
.ph__inner { position: relative; max-width: 900px; margin: 0 auto; }
.ph__back { display: inline-flex; align-items: center; gap: 0.4rem; color: rgba(255,255,255,0.7); text-decoration: none; font-size: 0.82rem; margin-bottom: 1.5rem; }
.ph__back:hover { color: #fff; }
.ph__year { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; color: #f5b800; text-transform: uppercase; margin-bottom: 0.75rem; }
.ph h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 1rem; }
.ph__meta { display: flex; gap: 1.5rem; flex-wrap: wrap; }
.ph__meta span { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: rgba(255,255,255,0.75); }

/* Flash */
.flash {
  max-width: 900px; margin: 1.5rem auto 0; padding: 0.85rem 1.25rem; border-radius: 10px;
  display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600;
}
.flash--success { background: #ecfdf5; color: #065f46; }

/* Stats */
.stats { background: #f7f9fd; padding: 0 2rem; }
.stats__inner {
  max-width: 900px; margin: 0 auto; transform: translateY(-2.5rem);
  display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 1.25rem;
}
.stat-card {
  background: #fff; border-radius: 16px; padding: 1.5rem;
  box-shadow: 0 8px 30px rgba(15,28,72,0.1);
  display: flex; align-items: center; gap: 1rem;
}
.stat-card--ring { gap: 1.25rem; }
.stat-card__icon { flex-shrink: 0; }
.stat-card__label { font-size: 0.75rem; color: #6b7280; font-weight: 600; margin-bottom: 0.2rem; }
.stat-card__value { font-size: 1.05rem; font-weight: 800; color: #1a2744; }

.ring-lg { width: 72px; height: 72px; border-radius: 50%; padding: 4px; flex-shrink: 0; }
.ring-lg__hole { width: 100%; height: 100%; border-radius: 50%; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.ring-lg__hole strong { font-size: 1rem; font-weight: 800; color: #1d3fc4; line-height: 1; }
.ring-lg__hole span { font-size: 0.55rem; color: #9ca3af; }

.icon--green { color: #0CA678; } .icon--gold { color: #e67700; } .icon--red { color: #e03131; } .icon--muted { color: #9ca3af; }

/* Requirements */
.reqs { padding: 4.5rem 2rem 3rem; background: #f7f9fd; }
.reqs__inner { max-width: 900px; margin: 0 auto; }
.reqs h2 { font-size: 1.5rem; font-weight: 800; color: #1a2744; margin-bottom: 0.4rem; }
.reqs__sub { color: #6b7280; margin-bottom: 1.5rem; }

.reqs__chips { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 2rem; }
.chip { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 20px; }
.chip--approved { background: #ecfdf5; color: #065f46; }
.chip--pending  { background: #fffbeb; color: #92400e; }
.chip--rejected { background: #fef2f2; color: #991b1b; }
.chip--missing  { background: #eff6ff; color: #1e40af; }

.reqs__list { display: flex; flex-direction: column; gap: 0.75rem; }
.req-row {
  display: flex; align-items: flex-start; gap: 0.9rem;
  background: #fff; border-radius: 14px; padding: 1.1rem 1.25rem;
  box-shadow: 0 2px 12px rgba(15,28,72,0.05);
  border-left: 3px solid transparent;
}
.req-row--missing { border-left-color: #f59f00; }
.req-row__icon { margin-top: 0.1rem; flex-shrink: 0; }
.req-row__main { flex: 1; min-width: 0; }
.req-row__title { font-weight: 700; font-size: 0.92rem; color: #1a2744; }
.req-row__meta { font-size: 0.76rem; color: #9ca3af; margin-top: 0.15rem; }
.req-row__optional { font-style: italic; }
.req-row__remarks { font-size: 0.78rem; color: #991b1b; margin-top: 0.4rem; background: #fef2f2; border-radius: 8px; padding: 0.4rem 0.6rem; }

.req-row__status { font-size: 0.72rem; font-weight: 700; padding: 0.3rem 0.7rem; border-radius: 20px; white-space: nowrap; flex-shrink: 0; }
.req-row__status--approved { background: #ecfdf5; color: #065f46; }
.req-row__status--pending  { background: #fffbeb; color: #92400e; }
.req-row__status--rejected { background: #fef2f2; color: #991b1b; }
.req-row__status--missing  { background: #f3f4f6; color: #6b7280; }

/* Submitted file + delete */
.req-row__file { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.6rem; flex-wrap: wrap; }
.file-link {
  display: inline-flex; align-items: center; gap: 0.35rem;
  font-size: 0.78rem; font-weight: 600; color: #1d3fc4; text-decoration: none;
  background: #eef1fc; padding: 0.35rem 0.7rem; border-radius: 8px;
}
.file-link:hover { background: #e0e7fb; }
.delete-btn {
  display: inline-flex; align-items: center; gap: 0.3rem;
  border: none; background: none; color: #991b1b; font-size: 0.74rem; font-weight: 600;
  cursor: pointer; padding: 0.3rem 0.4rem;
}
.delete-btn:hover:not(:disabled) { text-decoration: underline; }
.delete-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Notes */
.req-row__notes { margin-top: 0.6rem; }
.req-row__notes textarea {
  width: 100%; resize: vertical; border: 1.5px solid #e5e7eb; border-radius: 8px;
  padding: 0.5rem 0.7rem; font-size: 0.8rem; font-family: inherit;
  background: #fff; color: #374151; color-scheme: light;
}
.req-row__notes textarea::placeholder { color: #9ca3af; }
.req-row__notes textarea:focus { outline: none; border-color: #1d3fc4; }
.req-row__notes-readonly {
  font-size: 0.78rem; color: #4b5563; margin-top: 0.5rem;
  background: #f9fafb; border-radius: 8px; padding: 0.4rem 0.6rem;
}

/* Upload form */
.req-row__upload { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.75rem; flex-wrap: wrap; }
.upload-input {
  display: inline-flex; align-items: center; gap: 0.4rem;
  border: 1.5px dashed #d1d5db; border-radius: 8px; padding: 0.45rem 0.75rem;
  font-size: 0.78rem; color: #4b5563; cursor: pointer; max-width: 260px;
  transition: border-color 0.15s, background 0.15s;
}
.upload-input:hover { border-color: #1d3fc4; background: #f7f9fd; }
.upload-input span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.upload-input input { display: none; }
.upload-btn {
  border: none; background: #1d3fc4; color: #fff; font-size: 0.78rem; font-weight: 700;
  padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; transition: background 0.15s;
}
.upload-btn:hover:not(:disabled) { background: #1535a8; }
.upload-btn:disabled { background: #c7d2fe; cursor: not-allowed; }
.req-row__error { font-size: 0.74rem; color: #991b1b; margin-top: 0.4rem; }

.reqs__empty { display: flex; align-items: center; gap: 0.5rem; color: #9ca3af; font-size: 0.9rem; padding: 1.5rem; background: #fff; border-radius: 14px; }

/* Resource speakers */
.speakers { padding: 0 2rem 5rem; background: #f7f9fd; }
.speakers__inner { max-width: 900px; margin: 0 auto; }
.speakers h2 { font-size: 1.5rem; font-weight: 800; color: #1a2744; margin-bottom: 0.4rem; }
.speakers__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
.speaker-card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 2px 12px rgba(15,28,72,0.05); }
.speaker-card__avatar {
  width: 36px; height: 36px; border-radius: 50%; background: #eef1fc; color: #1d3fc4;
  display: flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;
}
.speaker-card__name { font-weight: 700; font-size: 0.92rem; color: #1a2744; }
.speaker-card__role { font-size: 0.76rem; color: #6b7280; margin-top: 0.15rem; }
.speaker-card__topic, .speaker-card__date {
  display: flex; align-items: center; gap: 0.35rem;
  font-size: 0.76rem; color: #4b5563; margin-top: 0.5rem;
}

@media (max-width: 768px) {
  .stats__inner { grid-template-columns: 1fr; transform: none; margin-top: 1.5rem; }
  .ph { padding-top: 7rem; }
  .speakers__grid { grid-template-columns: 1fr; }
}
</style>