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

    <!-- Stats -->
    <section class="stats">
      <div class="stats__inner">
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
        <div class="stat-card">
          <component :is="attendanceIcon" class="stat-card__icon" :class="attendanceColorClass" :size="26" />
          <div>
            <div class="stat-card__label">Attendance Status</div>
            <div class="stat-card__value">{{ program.attendance }}</div>
          </div>
        </div>
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
              <div v-if="r.file_url" class="req-row__file">
                <a :href="r.file_url" target="_blank" rel="noopener" class="file-link">
                  <FileText :size="14" /> {{ r.file_name }}
                </a>
                <button v-if="r.status !== 'Approved'" type="button" class="delete-btn"
                  :disabled="deletingId === r.id" @click="confirmDelete(r)">
                  <Trash2 :size="13" /> {{ deletingId === r.id ? 'Deleting…' : 'Delete' }}
                </button>
              </div>
              <form v-if="r.status !== 'Approved'" class="req-row__upload" @submit.prevent="submitFile(r)">
                <label class="upload-input">
                  <UploadCloud :size="14" />
                  <span>{{ selectedFile[r.id]?.name || (r.file_url ? 'Replace file (PDF)' : 'Choose PDF file') }}</span>
                  <input type="file" accept="application/pdf" @change="onFileChange($event, r.id)" />
                </label>
                <button type="submit" class="upload-btn"
                  :disabled="(!selectedFile[r.id] && noteDraft[r.id] === (r.notes || '')) || uploadingId === r.id">
                  {{ uploadingId === r.id ? 'Saving…' : (r.file_url ? 'Save Changes' : 'Submit') }}
                </button>
              </form>
              <div v-if="r.status !== 'Approved'" class="req-row__notes">
                <textarea v-model="noteDraft[r.id]" placeholder="Add a note for the reviewer (optional)…" rows="2"></textarea>
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

    <!-- Resource Speakers -->
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

    <!-- ══════════════════════════════════════════
         CERTIFICATES SECTION
    ══════════════════════════════════════════ -->
    <section class="certs">
      <div class="certs__inner">

        <!-- Section header -->
        <div class="certs__header">
          <div class="certs__title-group">
            <div class="certs__icon-wrap">
              <Award :size="24" color="#fff" />
            </div>
            <div>
              <h2>My Certificates</h2>
              <p class="certs__sub">
                Upload your training certificates for this program.
                Only PDF files are accepted (max 5MB each).
              </p>
            </div>
          </div>
        </div>

        <!-- Existing certificate cards -->
        <div v-if="program.certificates && program.certificates.length" class="cert-grid">
          <div v-for="cert in program.certificates" :key="cert.id" class="cert-card">
            <div class="cert-card__ribbon" :class="`cert-card__ribbon--${cert.status.toLowerCase()}`">
              {{ cert.status }}
            </div>
            <div class="cert-card__top">
              <span class="cert-card__emoji" :style="{ background: `${certTypeColor(cert.type)}18` }">
                <component :is="certTypeIconComponent(cert.type)" :size="22" :color="certTypeColor(cert.type)" />
              </span>
              <div>
                <p class="cert-card__type">{{ certTypeLabel(cert.type) }}</p>
                <p class="cert-card__number">{{ cert.certificate_number || '—' }}</p>
              </div>
            </div>
            <div class="cert-card__meta">
              <span v-if="cert.issued_date">📅 Issued: {{ formatDate(cert.issued_date) }}</span>
              <span v-if="cert.hours > 0">⏱ {{ cert.hours }} hr(s)</span>
              <span v-if="cert.issued_by" class="cert-card__uploader">✍️ Signed by: {{ cert.issued_by }}</span>
            </div>
            <p v-if="cert.remarks" class="cert-card__remarks">{{ cert.remarks }}</p>
            <div class="cert-card__actions">
              <a v-if="cert.file_url" :href="cert.file_url" target="_blank" rel="noopener" class="cert-btn cert-btn--view">
                <FileText :size="13" /> View PDF
              </a>
              <span v-else class="cert-card__no-file">No file yet</span>
              <button v-if="cert.status !== 'Issued'" type="button" class="cert-btn cert-btn--delete"
                :disabled="deletingCertId === cert.id" @click="confirmDeleteCert(cert)">
                <Trash2 :size="13" /> {{ deletingCertId === cert.id ? 'Removing…' : 'Remove' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="certs__empty">
          <div class="certs__empty-icon"><Award :size="48" color="#c7d2fe" /></div>
          <p class="certs__empty-title">No certificates uploaded yet</p>
          <p class="certs__empty-sub">Use the form below to upload your certificate for this program.</p>
        </div>

        <!-- Upload form -->
        <div class="cert-upload">
          <div class="cert-upload__header">
            <span class="cert-upload__title">Upload a Certificate</span>
            <span class="cert-upload__hint">PDF only · Max 10MB</span>
          </div>
          <div class="cert-upload__row">
            <div class="cert-upload__field">
              <label class="cert-upload__label">Certificate Type</label>
              <select v-model="certType" class="cert-upload__select">
                <option
                  v-for="t in CERT_TYPES"
                  :key="t.value"
                  :value="t.value"
                  :disabled="existingCertTypes.has(t.value)"
                >
                  {{ t.label }}{{ existingCertTypes.has(t.value) ? ' (already uploaded)' : '' }}
                </option>
              </select>
              <p v-if="selectedTypeAlreadyExists" class="cert-type-warning">
                ⚠ You already have a {{ certTypeLabel(certType) }}. Remove it first before uploading a new one.
              </p>
            </div>
            <div class="cert-upload__field cert-upload__field--file">
              <label class="cert-upload__label">PDF File</label>
              <label class="cert-file-btn">
                <UploadCloud :size="14" />
                <span>{{ certFile?.name || 'Choose PDF file…' }}</span>
                <input type="file" accept="application/pdf" @change="onCertFileChange" />
              </label>
            </div>
            <button type="button" class="cert-upload__btn"
              :disabled="!certFile || uploadingCert || selectedTypeAlreadyExists" @click="uploadCertificate">
              <UploadCloud v-if="!uploadingCert" :size="14" />
              {{ uploadingCert ? 'Uploading…' : 'Upload Certificate' }}
            </button>
          </div>
          <p v-if="certError" class="cert-upload__error">⚠ {{ certError }}</p>
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
  Award, Star, Trophy, BadgeCheck, Sparkles, Medal,
} from 'lucide-vue-next'


const props = defineProps({
  program: { type: Object, required: true },
})

/* ---- Note drafts ---- */
const noteDraft = ref(
  Object.fromEntries(props.program.requirements.map(r => [r.id, r.notes || '']))
)

/* ---- Stats computed ---- */
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

/* ---- Requirement helpers ---- */
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

/* ---- Requirement file submission ---- */
const selectedFile = ref({})
const fileError    = ref({})
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
      onSuccess: () => { selectedFile.value[requirement.id] = null },
      onError: (errors) => { fileError.value[requirement.id] = errors.file || 'Save failed. Please try again.' },
      onFinish: () => { uploadingId.value = null },
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
      onFinish: () => { deletingId.value = null },
    }
  )
}

/* ════════════════════════════════════════
   CERTIFICATE SECTION
════════════════════════════════════════ */

const CERT_TYPES = [
  { value: 'Participation', label: 'Certificate of Participation', icon: Medal,     color: '#1d3fc4' },
  { value: 'Completion',    label: 'Certificate of Completion',    icon: Award,     color: '#059669' },
  { value: 'Appearance',    label: 'Certificate of Appearance',    icon: Mic2,      color: '#7c3aed' },
  { value: 'Appreciation',  label: 'Certificate of Appreciation',  icon: Star,      color: '#d97706' },
  { value: 'Recognition',   label: 'Certificate of Recognition',   icon: Trophy,    color: '#dc2626' },
  { value: 'Achievement',   label: 'Certificate of Achievement',   icon: Sparkles,  color: '#0891b2' },
]

// Returns the lucide component for a cert type
function certTypeIconComponent(type) {
  return CERT_TYPES.find(t => t.value === type)?.icon ?? BadgeCheck
}
function certTypeColor(type) {
  return CERT_TYPES.find(t => t.value === type)?.color ?? '#1d3fc4'
}

const certFile       = ref(null)
const certType       = ref('Participation')
const certError      = ref('')
const uploadingCert  = ref(false)
const deletingCertId = ref(null)

// Set ng mga types na may existing na certificate — hindi na pwedeng mag-upload ulit
const existingCertTypes = computed(() =>
  new Set((props.program.certificates ?? []).map(c => c.type))
)
const selectedTypeAlreadyExists = computed(() => existingCertTypes.value.has(certType.value))

function certTypeLabel(type) {
  return CERT_TYPES.find(t => t.value === type)?.label ?? type
}

function onCertFileChange(e) {
  certError.value = ''
  const file = e.target.files[0]
  if (!file) return
  if (file.type !== 'application/pdf') {
    certError.value = 'Only PDF files are accepted.'
    certFile.value  = null
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    certError.value = 'File must not exceed 10MB.'
    certFile.value  = null
    return
  }
  certFile.value = file
}

function uploadCertificate() {
  if (!certFile.value) { certError.value = 'Please choose a PDF file.'; return }
  if (existingCertTypes.value.has(certType.value)) {
    certError.value = 'You already have a certificate of this type. Remove it first.'
    return
  }
  uploadingCert.value = true
  certError.value     = ''
  const formData = new FormData()
  formData.append('file', certFile.value)
  formData.append('type', certType.value)
  router.post(
    route('certificates.upload-by-user', { batch: props.program.batch_id }),
    formData,
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { certFile.value = null },
      onError: (e) => { certError.value = e.file || 'Upload failed. Please try again.' },
      onFinish: () => { uploadingCert.value = false },
    }
  )
}

function confirmDeleteCert(cert) {
  if (!window.confirm(`Remove your ${cert.type} certificate? This cannot be undone.`)) return
  deletingCertId.value = cert.id
  router.delete(
    route('certificates.destroy-by-user', { batch: props.program.batch_id, certificate: cert.id }),
    {
      preserveScroll: true,
      onFinish: () => { deletingCertId.value = null },
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
.flash { max-width: 900px; margin: 1.5rem auto 0; padding: 0.85rem 1.25rem; border-radius: 10px; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; }
.flash--success { background: #ecfdf5; color: #065f46; }

/* Stats */
.stats { background: #f7f9fd; padding: 0 2rem; }
.stats__inner { max-width: 900px; margin: 0 auto; transform: translateY(-2.5rem); display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 1.25rem; }
.stat-card { background: #fff; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 30px rgba(15,28,72,0.1); display: flex; align-items: center; gap: 1rem; }
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
.req-row { display: flex; align-items: flex-start; gap: 0.9rem; background: #fff; border-radius: 14px; padding: 1.1rem 1.25rem; box-shadow: 0 2px 12px rgba(15,28,72,0.05); border-left: 3px solid transparent; }
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
.req-row__file { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.6rem; flex-wrap: wrap; }
.file-link { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.78rem; font-weight: 600; color: #1d3fc4; text-decoration: none; background: #eef1fc; padding: 0.35rem 0.7rem; border-radius: 8px; }
.file-link:hover { background: #e0e7fb; }
.delete-btn { display: inline-flex; align-items: center; gap: 0.3rem; border: none; background: none; color: #991b1b; font-size: 0.74rem; font-weight: 600; cursor: pointer; padding: 0.3rem 0.4rem; }
.delete-btn:hover:not(:disabled) { text-decoration: underline; }
.delete-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.req-row__notes { margin-top: 0.6rem; }
.req-row__notes textarea { width: 100%; resize: vertical; border: 1.5px solid #e5e7eb; border-radius: 8px; padding: 0.5rem 0.7rem; font-size: 0.8rem; font-family: inherit; background: #fff; color: #374151; color-scheme: light; }
.req-row__notes textarea::placeholder { color: #9ca3af; }
.req-row__notes textarea:focus { outline: none; border-color: #1d3fc4; }
.req-row__notes-readonly { font-size: 0.78rem; color: #4b5563; margin-top: 0.5rem; background: #f9fafb; border-radius: 8px; padding: 0.4rem 0.6rem; }
.req-row__upload { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.75rem; flex-wrap: wrap; }
.upload-input { display: inline-flex; align-items: center; gap: 0.4rem; border: 1.5px dashed #d1d5db; border-radius: 8px; padding: 0.45rem 0.75rem; font-size: 0.78rem; color: #4b5563; cursor: pointer; max-width: 260px; transition: border-color 0.15s, background 0.15s; }
.upload-input:hover { border-color: #1d3fc4; background: #f7f9fd; }
.upload-input span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.upload-input input { display: none; }
.upload-btn { border: none; background: #1d3fc4; color: #fff; font-size: 0.78rem; font-weight: 700; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; transition: background 0.15s; }
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
.speaker-card__avatar { width: 36px; height: 36px; border-radius: 50%; background: #eef1fc; color: #1d3fc4; display: flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; }
.speaker-card__name { font-weight: 700; font-size: 0.92rem; color: #1a2744; }
.speaker-card__role { font-size: 0.76rem; color: #6b7280; margin-top: 0.15rem; }
.speaker-card__topic, .speaker-card__date { display: flex; align-items: center; gap: 0.35rem; font-size: 0.76rem; color: #4b5563; margin-top: 0.5rem; }

/* ══════════════════════════════════════════
   CERTIFICATES
══════════════════════════════════════════ */
.certs { padding: 0 2rem 5rem; background: #f7f9fd; }
.certs__inner { max-width: 900px; margin: 0 auto; }
.certs__header { display: flex; align-items: flex-start; margin-bottom: 1.75rem; }
.certs__title-group { display: flex; align-items: flex-start; gap: 1rem; }
.certs__icon-wrap { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #1d3fc4 0%, #4f46e5 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.certs__inner h2 { font-size: 1.5rem; font-weight: 800; color: #1a2744; margin-bottom: 0.25rem; }
.certs__sub { color: #6b7280; font-size: 0.85rem; margin: 0; }

.cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.cert-card { position: relative; background: #fff; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 20px rgba(15,28,72,0.08); border: 1.5px solid #e5e7eb; overflow: hidden; transition: box-shadow 0.2s, transform 0.2s; }
.cert-card:hover { box-shadow: 0 8px 30px rgba(15,28,72,0.14); transform: translateY(-2px); }
.cert-card__ribbon { position: absolute; top: 12px; right: -28px; transform: rotate(45deg); width: 100px; text-align: center; font-size: 0.65rem; font-weight: 800; letter-spacing: 0.05em; padding: 3px 0; text-transform: uppercase; }
.cert-card__ribbon--issued   { background: #d1fae5; color: #065f46; }
.cert-card__ribbon--pending  { background: #fef3c7; color: #92400e; }
.cert-card__ribbon--revoked  { background: #fee2e2; color: #991b1b; }
.cert-card__top { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; }
.cert-card__emoji { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cert-card__type { font-size: 0.85rem; font-weight: 800; color: #1a2744; line-height: 1.3; }
.cert-card__number { font-size: 0.7rem; color: #9ca3af; margin-top: 0.15rem; font-family: monospace; }
.cert-card__meta { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.76rem; color: #4b5563; margin-bottom: 0.75rem; }
.cert-card__uploader { color: #9ca3af; font-style: italic; }
.cert-card__remarks { font-size: 0.75rem; color: #6b7280; background: #f9fafb; border-radius: 8px; padding: 0.4rem 0.6rem; margin-bottom: 0.75rem; }
.cert-card__no-file { font-size: 0.75rem; color: #d1d5db; font-style: italic; }
.cert-card__actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.cert-btn { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; transition: background 0.15s; }
.cert-btn--view { background: #eef1fc; color: #1d3fc4; }
.cert-btn--view:hover { background: #dde4fb; }
.cert-btn--delete { background: #fef2f2; color: #991b1b; }
.cert-btn--delete:hover:not(:disabled) { background: #fee2e2; }
.cert-btn--delete:disabled { opacity: 0.5; cursor: not-allowed; }

.certs__empty { text-align: center; padding: 2.5rem; background: #fff; border-radius: 16px; border: 2px dashed #e5e7eb; margin-bottom: 2rem; }
.certs__empty-icon { display: flex; justify-content: center; margin-bottom: 0.75rem; }
.certs__empty-title { font-size: 1rem; font-weight: 800; color: #1a2744; margin-bottom: 0.25rem; }
.certs__empty-sub { font-size: 0.82rem; color: #9ca3af; }

.cert-upload { background: linear-gradient(135deg, #1d3fc4 0%, #3730a3 100%); border-radius: 16px; padding: 1.5rem; }
.cert-upload__header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.cert-upload__title { font-size: 0.9rem; font-weight: 800; color: #fff; }
.cert-upload__hint  { font-size: 0.75rem; color: rgba(255,255,255,0.6); }
.cert-upload__row { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
.cert-upload__field { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 180px; }
.cert-upload__field--file { flex: 2; }
.cert-upload__label { font-size: 0.72rem; font-weight: 700; color: rgba(255,255,255,0.75); letter-spacing: 0.05em; text-transform: uppercase; }
.cert-upload__select { background: rgba(255,255,255,0.12); border: 1.5px solid rgba(255,255,255,0.25); color: #fff; border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.82rem; font-family: inherit; cursor: pointer; appearance: none; color-scheme: dark; }
.cert-upload__select:focus { outline: none; border-color: rgba(255,255,255,0.6); }
.cert-upload__select option { background: #1d3fc4; }
.cert-file-btn { display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.12); border: 1.5px dashed rgba(255,255,255,0.4); color: rgba(255,255,255,0.85); border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.8rem; cursor: pointer; transition: background 0.15s, border-color 0.15s; overflow: hidden; }
.cert-file-btn:hover { background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.7); }
.cert-file-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cert-file-btn input { display: none; }
.cert-upload__btn { display: inline-flex; align-items: center; gap: 0.4rem; background: #fff; color: #1d3fc4; font-weight: 800; font-size: 0.82rem; border: none; border-radius: 10px; padding: 0.55rem 1.25rem; cursor: pointer; white-space: nowrap; transition: background 0.15s, opacity 0.15s; flex-shrink: 0; }
.cert-upload__btn:hover:not(:disabled) { background: #eef1fc; }
.cert-upload__btn:disabled { opacity: 0.5; cursor: not-allowed; }
.cert-upload__error { font-size: 0.78rem; color: #fca5a5; margin-top: 0.6rem; font-weight: 600; }
.cert-type-warning { font-size: 0.75rem; color: #fde68a; margin-top: 0.35rem; font-weight: 600; }

@media (max-width: 768px) {
  .stats__inner { grid-template-columns: 1fr; transform: none; margin-top: 1.5rem; }
  .ph { padding-top: 7rem; }
  .speakers__grid { grid-template-columns: 1fr; }
  .cert-grid { grid-template-columns: 1fr; }
  .cert-upload__row { flex-direction: column; }
  .cert-upload__btn { width: 100%; justify-content: center; }
}
</style>