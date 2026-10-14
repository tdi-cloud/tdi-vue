<template>
    <div v-if="programs.length || !summaryOnly" :class="{ 'mp-themed': !summaryOnly }">
    <!-- Dedicated My Programs page: title, stats and filters combined in one dark header -->
    <header v-if="!summaryOnly" class="page-hero">
        <div class="page-hero__inner">
            <Link :href="route('home')" class="page-hero__back"><ArrowLeft :size="15" /> Back to Home</Link>
            <div class="page-hero__row">
                <div>
                    <span class="page-hero__eyebrow"><GraduationCap :size="14" /> YOUR LEARNING JOURNEY</span>
                    <h1>My Programs</h1>
                    <p class="snapshot snapshot--dark"><BookOpen :size="16" />{{ programs.length }} programs <i></i><Clock :size="16" />{{ totalHours }} learning hours <i></i><FileWarning :size="16" />{{ totalMissing }} requirements pending</p>
                </div>
                <div class="filters" aria-label="Program filters">
                    <label class="filter"><CalendarDays :size="16" /><select v-model="selectedYear" aria-label="Filter by year"><option value="">All years</option><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select></label>
                    <label class="filter"><Layers3 :size="16" /><select v-model="selectedType" aria-label="Filter by program type"><option value="">All types</option><option v-for="t in TYPE_OPTIONS" :key="t" :value="t">{{ t }}</option></select></label>
                    <button type="button" class="filter filter--toggle" :class="{ 'filter--active': missingOnly }" @click="missingOnly = !missingOnly"><FileWarning :size="16" />Missing requirements only</button>
                </div>
            </div>
        </div>
    </header>
    <section id="my-programs" class="enrolled" :class="{ 'enrolled--page': !summaryOnly }">
        <div class="enrolled__inner">
            <header v-if="summaryOnly" class="enrolled__header">
                <div>
                    <span class="eyebrow">Your learning journey</span>
                    <h2>My Enrolled Programs</h2>
                    <p class="enrolled__sub">Track your attendance, hours, and pending requirements at a glance.</p>
                    <p class="snapshot"><BookOpen :size="16" />{{ programs.length }} programs <i></i><Clock :size="16" />{{ totalHours }} learning hours <i></i><FileWarning :size="16" />{{ totalMissing }} requirements pending</p>
                </div>
                <Link :href="route('programs.my-programs')" class="view-all">View all programs <ArrowRight :size="16" /></Link>
            </header>

            <div class="summary">
                <article class="metric"><div class="metric__icon metric__icon--blue"><GraduationCap :size="25" /></div><div><strong>{{ programs.length }}</strong><span>Enrolled Programs</span></div></article>
                <article class="metric"><div class="metric__icon metric__icon--green"><Clock :size="25" /></div><div><strong>{{ totalHours }}</strong><span>Hours Completed</span></div></article>
                <article class="metric"><div class="metric__icon metric__icon--amber"><FileWarning :size="25" /></div><div><strong>{{ totalMissing }}</strong><span>Requirements To Submit</span></div></article>
                <article class="attendance-card"><div class="donut" :style="{ background: donutGradient }"><div><strong>{{ programs.length }}</strong><span>total</span></div></div><div class="legend"><p>Attendance Overview</p><span><i class="complete"></i>Complete <b>{{ attendanceCounts.Complete }}</b></span><span><i class="pending"></i>Pending <b>{{ attendanceCounts.Pending }}</b></span><span><i class="absent"></i>Absent <b>{{ attendanceCounts.Absent }}</b></span></div></article>
            </div>

            <template v-if="!summaryOnly">
            <div class="section-heading"><h3>Your Programs</h3><span v-if="filteredPrograms.length">{{ filteredPrograms.length }} available</span></div>
            <div v-if="filteredPrograms.length" class="grid">
                <Link v-for="p in paginatedPrograms" :key="p.batch_id" :href="route('programs.my-progress', p.batch_id)" class="program-card">
                    <div class="cover"><img v-if="p.cover_image" :src="p.cover_image" :alt="p.program_title" /><div v-else class="placeholder"><BookOpen :size="35" /></div><span class="shade"></span><span class="badge badge--cover" :class="attendanceBadge(p.attendance).class"><component :is="attendanceBadge(p.attendance).icon" :size="14" />{{ p.attendance }}</span></div>
                    <div class="program-card__body">
                        <span class="meta"><CalendarDays :size="13" />{{ p.year }}<i></i>{{ p.batch_label }}</span>
                        <h4>{{ p.program_title }}</h4>
                        <div class="badges"><span class="badge" :class="attendanceBadge(p.attendance).class"><component :is="attendanceBadge(p.attendance).icon" :size="13" />{{ p.attendance }}</span><span class="badge badge--neutral"><Clock :size="13" />{{ p.hours_completed }} / {{ p.total_hours || '-' }} hrs</span></div>
                        <div class="progress"><div><span>Learning Progress</span><strong>{{ progressPercent(p) }}%</strong></div><div class="progress__track" role="progressbar" :aria-valuenow="progressPercent(p)" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${progressPercent(p)}%` }"></span></div></div>
                        <div class="requirements" :class="{ 'requirements--clear': p.requirements_missing === 0 }"><span v-if="p.requirements_missing > 0"><FileWarning :size="13" />{{ p.requirements_missing }} requirement{{ p.requirements_missing === 1 ? '' : 's' }} pending submission</span><span v-else><CheckCircle2 :size="13" />All requirements submitted</span><ChevronRight class="arrow" :size="17" /></div>
                    </div>
                </Link>
            </div>
            <div v-else-if="programs.length" class="empty"><div><FileWarning :size="25" /></div><h3>No programs found</h3><p>Try adjusting your filters to see more enrolled programs.</p></div>
            <div v-else class="empty"><div><GraduationCap :size="25" /></div><h3>No enrolled programs yet</h3><p>Programs you are enrolled in will appear here.</p></div>
            <nav v-if="filteredPrograms.length" class="pagination" aria-label="Program pages"><p>Showing {{ (currentPage - 1) * PER_PAGE + 1 }}-{{ Math.min(currentPage * PER_PAGE, filteredPrograms.length) }} of {{ filteredPrograms.length }} programs</p><div v-if="totalPages > 1"><button type="button" class="page" aria-label="Previous page" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)"><ChevronLeft :size="17" /></button><button v-for="page in totalPages" :key="page" type="button" class="page" :class="{ active: page === currentPage }" :aria-label="`Page ${page}`" @click="goToPage(page)">{{ page }}</button><button type="button" class="page" aria-label="Next page" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)"><ChevronRight :size="17" /></button></div></nav>
            </template>
        </div>
    </section>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, ArrowRight, BookOpen, CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock, FileWarning, GraduationCap, Layers3 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const TYPE_OPTIONS = ['ADMIN', 'TECHNICAL', 'SUPERVISORY/MANAGERIAL', 'TEAM-BUILDING', 'OTHER'];
const PER_PAGE = 6;
// summaryOnly: homepage shows just the summary metrics + a link to the dedicated My Programs page.
const props = defineProps({ programs: { type: Array, default: () => [] }, summaryOnly: { type: Boolean, default: false } });
const selectedYear = ref('');
const selectedType = ref('');
const missingOnly = ref(false);
const currentPage = ref(1);
const years = computed(() => [...new Set(props.programs.map((p) => p.year))].sort((a, b) => b - a));
const filteredPrograms = computed(() => props.programs.filter((p) => {
    if (selectedYear.value && p.year !== Number(selectedYear.value)) return false;
    if (selectedType.value && p.program_type !== selectedType.value) return false;
    if (missingOnly.value && p.requirements_missing === 0) return false;
    return true;
}));
const totalPages = computed(() => Math.max(1, Math.ceil(filteredPrograms.value.length / PER_PAGE)));
const paginatedPrograms = computed(() => filteredPrograms.value.slice((currentPage.value - 1) * PER_PAGE, currentPage.value * PER_PAGE));
watch([selectedYear, selectedType, missingOnly], () => { currentPage.value = 1; });
function goToPage(page) { currentPage.value = Math.min(Math.max(1, page), totalPages.value); }
const totalHours = computed(() => props.programs.reduce((sum, p) => sum + (p.hours_completed || 0), 0));
const totalMissing = computed(() => props.programs.reduce((sum, p) => sum + (p.requirements_missing || 0), 0));
const attendanceCounts = computed(() => { const counts = { Complete: 0, Pending: 0, Absent: 0 }; props.programs.forEach((p) => { counts[p.attendance] = (counts[p.attendance] || 0) + 1; }); return counts; });
const donutGradient = computed(() => { const total = props.programs.length || 1; const counts = attendanceCounts.value; const complete = (counts.Complete / total) * 100; const pending = (counts.Pending / total) * 100; return `conic-gradient(#0CA678 0% ${complete}%, #F59F00 ${complete}% ${complete + pending}%, #E03131 ${complete + pending}% 100%)`; });
function progressPercent(p) { return p.total_hours > 0 ? Math.min(100, Math.round((p.hours_completed / p.total_hours) * 100)) : p.attendance === 'Complete' ? 100 : 0; }
function attendanceBadge(status) { if (status === 'Complete') return { class: 'badge--success', icon: CheckCircle2 }; if (status === 'Absent') return { class: 'badge--danger', icon: AlertCircle }; return { class: 'badge--pending', icon: Clock }; }
</script>

<style scoped>
.enrolled{position:relative;isolation:isolate;overflow:hidden;padding:5.25rem 2rem;background:radial-gradient(circle at 7% 8%,#d3e4ff92,transparent 25rem),radial-gradient(circle at 90% 2%,#dfebffb3,transparent 23rem),#f7f9fc}.enrolled:before,.enrolled:after{position:absolute;z-index:-1;width:20rem;height:20rem;border:1px solid #77a1de1f;border-radius:50%;content:''}.enrolled:before{top:-14rem;right:15%}.enrolled:after{right:-12rem;bottom:7rem}.enrolled__inner{max-width:1420px;margin:auto}.enrolled__header{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;margin-bottom:2.15rem}.eyebrow{display:block;margin-bottom:.5rem;color:#1d3fc4;font-size:.72rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase}.enrolled h2{margin:0 0 .4rem;color:#17233f;font-size:clamp(1.75rem,3vw,2.15rem);font-weight:800}.enrolled__sub{max-width:520px;margin:0;color:#6b7280;font-size:.92rem;line-height:1.6}.snapshot{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem;margin:.9rem 0 0;color:#254273;font-size:.83rem;font-weight:600}.snapshot svg{color:#1d3fc4}.snapshot--dark{margin-top:.95rem;color:#ffffffd9}.snapshot--dark svg{color:#f5d76e}.snapshot--dark i{background:#ffffff33}.page-hero{padding:5.5rem 2rem 1.75rem;color:#fff;background:radial-gradient(70% 110% at 88% 0%,#4c6ef56b,transparent 62%),linear-gradient(160deg,#0c1739 0%,#0f1c48 55%,#13275f 100%)}.page-hero__inner{max-width:1420px;margin:auto}.page-hero__back{display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.75rem;color:#ffffffbf;font-size:.82rem;font-weight:600;text-decoration:none;transition:color .2s}.page-hero__back:hover{color:#fff}.page-hero__row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1.25rem}.page-hero__eyebrow{display:flex;width:fit-content;align-items:center;gap:.45rem;margin-bottom:.6rem;padding:.35rem .75rem;color:#f5d76e;font-size:.7rem;font-weight:700;letter-spacing:.16em;background:#f5b8001a;border:1px solid #f5b8004d;border-radius:999px}.page-hero h1{margin:0;color:#fff;font-size:clamp(1.6rem,3.5vw,2.1rem);font-weight:800;letter-spacing:-.025em;line-height:1.1}.enrolled--page{padding-top:2rem}.snapshot i{width:1px;height:1.1rem;margin:0 .45rem;background:#cad6e8}.filters{display:flex;flex-wrap:wrap;gap:.65rem}.filter{display:flex;align-items:center;gap:.45rem;min-height:43px;padding:.55rem .85rem;color:#374151;font-size:.82rem;background:#fff;border:1px solid #dce4f0;border-radius:11px;box-shadow:0 2px 8px #1d3fc40a}.filter svg{color:#1d3fc4}.filter select{max-width:135px;color:#374151;font:inherit;background:transparent;border:0;cursor:pointer}.filter select:focus{outline:0}.filter--toggle{font-weight:700;cursor:pointer}.view-all{display:inline-flex;align-items:center;gap:.45rem;min-height:43px;padding:.6rem 1.15rem;color:#fff;font-size:.85rem;font-weight:700;text-decoration:none;background:#1d3fc4;border-radius:11px;box-shadow:0 6px 16px #1d3fc433;transition:background .2s ease,transform .2s ease}.view-all:hover{background:#1535a8;transform:translateY(-1px)}.view-all:focus-visible{outline:3px solid #1d3fc44d;outline-offset:3px}.summary:last-child{margin-bottom:0}.filter--toggle:hover{border-color:#1d3fc4}.filter--active{color:#1d3fc4;background:#eef4ff;border-color:#1d3fc4}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:2.35rem}.metric,.attendance-card{display:flex;align-items:center;min-height:124px;padding:1.2rem;background:#ffffffe6;border:1px solid #e0e8f3;border-radius:15px;box-shadow:0 7px 20px #1c3c7110}.metric{gap:1rem}.metric__icon{display:grid;width:50px;height:50px;place-items:center;flex-shrink:0;border-radius:50%}.metric__icon--blue{color:#1d3fc4;background:#e8f0ff}.metric__icon--green{color:#07836a;background:#e4f8f1}.metric__icon--amber{color:#c97800;background:#fff3d9}.summary strong{display:block;color:#17233f;font-size:1.75rem;font-weight:800;line-height:1.1}.metric span{color:#405072;font-size:.75rem;font-weight:600}.attendance-card{gap:1rem}.donut{display:grid;width:68px;height:68px;place-items:center;flex-shrink:0;border-radius:50%}.donut>div{display:flex;width:46px;height:46px;align-items:center;justify-content:center;flex-direction:column;background:#fff;border-radius:50%}.donut strong{font-size:.95rem}.donut span{color:#71809c;font-size:.55rem}.legend p{margin:0 0 .25rem;color:#17233f;font-size:.77rem;font-weight:800}.legend span{display:flex;align-items:center;gap:.42rem;color:#405072;font-size:.7rem;line-height:1.55}.legend i{width:8px;height:8px;border-radius:50%}.complete{background:#0ca678}.pending{background:#f59f00}.absent{background:#e03131}.legend b{margin-left:auto;color:#17233f}.section-heading{display:flex;align-items:baseline;justify-content:space-between;gap:1rem;margin:0 0 1rem .25rem}.section-heading h3{margin:0;color:#17233f;font-size:1.45rem;font-weight:800}.section-heading span{color:#71809c;font-size:.78rem;font-weight:600}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.35rem}.program-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;color:inherit;text-decoration:none;background:#fff;border:1px solid #dfe7f2;border-radius:15px;box-shadow:0 5px 16px #1633600f;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}.program-card:hover{transform:translateY(-4px);border-color:#b8caf0;box-shadow:0 14px 28px #1633601f}.program-card:focus-visible,.filter:focus-within,.filter--toggle:focus-visible,.page:focus-visible{outline:3px solid #1d3fc44d;outline-offset:3px}.cover{position:relative;width:100%;height:130px;overflow:hidden;background:#e8f0ff}.cover img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}.program-card:hover .cover img{transform:scale(1.035)}.shade{position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,#091f482e,transparent 55%)}.placeholder{display:grid;width:100%;height:100%;place-items:center;color:#1d3fc4;background:linear-gradient(135deg,#edf4ff,#dceaff)}.program-card__body{width:100%;min-width:0;padding:1rem;box-sizing:border-box}.badge--cover{position:absolute;top:.85rem;right:.85rem;box-shadow:0 2px 8px #10224624}.meta{display:inline-flex;align-items:center;gap:.32rem;color:#31558f;font-size:.7rem;font-weight:700}.meta i{width:3px;height:3px;border-radius:50%;background:#7b96c1}.program-card h4{display:-webkit-box;min-height:2.6em;margin:.35rem 0 .7rem;overflow:hidden;color:#17233f;font-size:1.02rem;font-weight:800;line-height:1.3;-webkit-box-orient:vertical;-webkit-line-clamp:2}.badges{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.8rem}.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .55rem;font-size:.69rem;font-weight:800;border-radius:999px}.badge--success{color:#08765e;background:#eaf8f4}.badge--pending{color:#9a5a00;background:#fff3d9}.badge--danger{color:#b42323;background:#fff0f0}.badge--neutral{color:#31558f;background:#f1f5fb}.progress{margin-bottom:.85rem}.progress>div:first-child{display:flex;align-items:center;justify-content:space-between;margin-bottom:.38rem;color:#415476;font-size:.69rem;font-weight:600}.progress strong{color:#1d3fc4;font-size:.72rem}.progress__track{height:6px;overflow:hidden;background:#dfe9f7;border-radius:999px}.progress__track span{display:block;height:100%;border-radius:inherit;background:#1d63dd;transition:width .35s ease}.requirements{display:flex;align-items:center;justify-content:space-between;gap:.6rem;min-height:36px;padding:.55rem .65rem;color:#9a5a00;background:#fff5e5;border-radius:8px}.requirements--clear{color:#08765e;background:#eaf8f4}.requirements span{display:inline-flex;min-width:0;align-items:center;gap:.3rem;font-size:.67rem;font-weight:700}.arrow{flex-shrink:0;color:#71809c;transition:transform .22s ease,color .22s ease}.program-card:hover .arrow{color:#1d3fc4;transform:translateX(3px)}.empty{display:grid;min-height:215px;place-items:center;padding:1.5rem;color:#71809c;text-align:center;background:#fff;border:1px solid #dfe7f2;border-radius:15px}.empty div{display:grid;width:48px;height:48px;place-items:center;color:#c97800;background:#fff3d9;border-radius:50%}.empty h3{margin:.75rem 0 .2rem;color:#17233f;font-size:1rem}.empty p{margin:0}.pagination{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-top:1.45rem}.pagination p{margin:0;color:#405072;font-size:.78rem;font-weight:600}.pagination>div{display:flex;gap:.35rem}.page{display:inline-grid;width:36px;height:36px;place-items:center;color:#374151;font-size:.82rem;font-weight:700;cursor:pointer;background:#fff;border:1px solid #dbe5f1;border-radius:9px}.page:hover:not(:disabled){color:#1d3fc4;border-color:#1d3fc4}.page:disabled{opacity:.4;cursor:not-allowed}.page.active{color:#fff;background:#1d3fc4;border-color:#1d3fc4}@media(max-width:1100px){.summary{grid-template-columns:repeat(2,minmax(0,1fr))}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.enrolled{padding:3.75rem 1rem}.enrolled--page{padding-top:1.5rem}.page-hero{padding-inline:1rem}.enrolled__header{align-items:stretch}.filters{width:100%}.filter{flex:1 1 145px}.filter--toggle{justify-content:center}.snapshot{font-size:.75rem}.snapshot i{display:none}.summary,.grid{grid-template-columns:1fr}.pagination{align-items:flex-start;flex-direction:column}}@media(prefers-reduced-motion:reduce){.program-card,.cover img,.progress__track span,.arrow{transition:none}.program-card:hover{transform:none}}
</style>

<style scoped>
/* Dark mode for the dedicated My Programs page only (.mp-themed). The homepage
   summary keeps its fixed light design, matching the rest of the homepage. */
.dark .mp-themed .enrolled {
    background: radial-gradient(circle at 7% 8%, #1d3fc426, transparent 25rem), radial-gradient(circle at 90% 2%, #3b82f61f, transparent 23rem), #0b1020;
}
.dark .mp-themed .enrolled:before,
.dark .mp-themed .enrolled:after {
    border-color: #3b82f61f;
}
.dark .mp-themed .filter,
.dark .mp-themed .filter select {
    color: #d5deee;
}
.dark .mp-themed .filter {
    background: #121a2b;
    border-color: #26324b;
    box-shadow: none;
}
.dark .mp-themed .filter select option {
    color: #0f172a;
}
.dark .mp-themed .filter--active {
    color: #93c5fd;
    background: #1d3fc433;
    border-color: #3b82f6;
}
.dark .mp-themed .metric,
.dark .mp-themed .attendance-card,
.dark .mp-themed .program-card,
.dark .mp-themed .empty,
.dark .mp-themed .page {
    background: #121a2b;
    border-color: #26324b;
    box-shadow: 0 7px 20px #00000040;
}
.dark .mp-themed .program-card:hover {
    border-color: #3b5a96;
}
.dark .mp-themed .summary strong,
.dark .mp-themed .legend p,
.dark .mp-themed .legend b,
.dark .mp-themed .section-heading h3,
.dark .mp-themed .program-card h4,
.dark .mp-themed .empty h3 {
    color: #e6ecf7;
}
.dark .mp-themed .metric span,
.dark .mp-themed .legend span,
.dark .mp-themed .progress > div:first-child,
.dark .mp-themed .pagination p,
.dark .mp-themed .page {
    color: #b7c3d9;
}
.dark .mp-themed .section-heading span,
.dark .mp-themed .donut span,
.dark .mp-themed .arrow,
.dark .mp-themed .empty {
    color: #8a97b0;
}
.dark .mp-themed .donut > div {
    background: #121a2b;
}
.dark .mp-themed .meta {
    color: #93b4e8;
}
.dark .mp-themed .progress strong {
    color: #93c5fd;
}
.dark .mp-themed .progress__track {
    background: #26324b;
}
.dark .mp-themed .metric__icon--blue {
    color: #93c5fd;
    background: #1d3fc433;
}
.dark .mp-themed .metric__icon--green {
    color: #6ee7b7;
    background: #05966933;
}
.dark .mp-themed .metric__icon--amber {
    color: #fcd34d;
    background: #d9770633;
}
.dark .mp-themed .cover,
.dark .mp-themed .placeholder {
    color: #93c5fd;
    background: linear-gradient(135deg, #16213a, #1b2a4a);
}
.dark .mp-themed .badge--success,
.dark .mp-themed .requirements--clear {
    color: #6ee7b7;
    background: #05966926;
}
.dark .mp-themed .badge--pending,
.dark .mp-themed .requirements {
    color: #fcd34d;
    background: #d9770626;
}
.dark .mp-themed .badge--danger {
    color: #fca5a5;
    background: #dc262626;
}
.dark .mp-themed .badge--neutral {
    color: #93b4e8;
    background: #1d3fc426;
}
.dark .mp-themed .page:hover:not(:disabled) {
    color: #93c5fd;
    border-color: #3b82f6;
}
.dark .mp-themed .page.active {
    color: #fff;
    background: #1d3fc4;
    border-color: #1d3fc4;
}
.dark .mp-themed .empty div {
    background: #d9770626;
}
</style>
