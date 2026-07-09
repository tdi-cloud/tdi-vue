<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ClipboardCheck, UserCheck, ListChecks, ShieldCheck, ArrowRight, CalendarRange, Trash2, Settings2 } from 'lucide-vue-next'

/*
 | The Welcome/index page passes the `tna` prop coming from
 | TnaController::bannerData(). If it is null, the parent does not
 | render this component (no TNA Tool / not logged in).
 */
const props = defineProps({
  data: { type: Object, required: true }, // { period, position, submitted }
})

const steps = [
  { icon: UserCheck, title: 'Select Supervisor', desc: 'Search for and select your immediate supervisor.' },
  { icon: ClipboardCheck, title: 'Self-Rating', desc: 'Rate each competency using the scale guide.' },
  { icon: ShieldCheck, title: 'Supervisory Rating', desc: 'Your supervisor reviews your rating.' },
]

function deleteAssessment() {
  if (!props.data.assessment_id) return
  if (!confirm('Delete your self-rating? This action cannot be undone.')) return
  router.delete(route('tna.self-rating.destroy', props.data.assessment_id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <section id="tna" class="tna-banner">
    <div class="tna-banner__card">
      <!-- Decorative glow -->
      <div class="tna-banner__glow" aria-hidden="true"></div>

      <div class="tna-banner__grid">
        <!-- LEFT: message -->
        <div class="tna-banner__intro">
          <span class="tna-banner__period">
            <CalendarRange :size="14" /> TNA Cycle {{ data.period }}
          </span>

          <h2 class="tna-banner__title">
            Training Needs Analysis
          </h2>

          <p class="tna-banner__lead">
            Conducted every <b>3 years</b>, the TNA determines your Professional
            Development Plan. The assessment is now open for the position of
            <span class="tna-banner__pos">{{ data.position }}</span>.
          </p>

          <!-- Not yet submitted -->
          <template v-if="!data.submitted">
            <Link :href="route('tna.self-rating')" class="tna-banner__cta">
              Start Self-Rating
              <ArrowRight :size="17" />
            </Link>
            <p class="tna-banner__note">
              <ListChecks :size="14" /> Takes about 10–15 minutes to complete.
            </p>
          </template>

          <!-- Submitted -->
          <template v-else>
            <div class="tna-banner__done">
              <ShieldCheck :size="18" />
              <span>You have submitted your self-rating for {{ data.period }}.</span>
            </div>
            <a
              v-if="data.assessment_id"
              :href="route('tna.self-rating.pdf', data.assessment_id)"
              target="_blank"
              rel="noopener"
              class="tna-banner__cta"
            >
              View / Print PDF
              <ArrowRight :size="17" />
            </a>
            <div class="tna-banner__subactions">
              <Link :href="route('tna.self-rating')" class="tna-banner__link">
                <Settings2 :size="14" /> Manage
              </Link>
              <button
                v-if="data.assessment_id && !data.reviewed"
                type="button"
                class="tna-banner__danger"
                @click="deleteAssessment"
              >
                <Trash2 :size="14" /> Delete
              </button>
            </div>
            <p class="tna-banner__note">
              Please wait for the supervisory rating from your selected supervisor.
            </p>
          </template>
        </div>

        <!-- RIGHT: steps -->
        <ol class="tna-banner__steps">
          <li v-for="(s, i) in steps" :key="i" class="tna-step" :class="{ 'tna-step--done': data.submitted && i < 2 }">
            <span class="tna-step__num">{{ i + 1 }}</span>
            <span class="tna-step__icon"><component :is="s.icon" :size="18" /></span>
            <span class="tna-step__body">
              <span class="tna-step__title">{{ s.title }}</span>
              <span class="tna-step__desc">{{ s.desc }}</span>
            </span>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>

<style scoped>
.tna-banner {
  padding: 3rem 1.5rem;
  background: #fff;
}
.tna-banner__card {
  position: relative;
  max-width: 1120px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 24px;
  padding: 2.5rem;
  background: linear-gradient(135deg, #0f1c48 0%, #1a2f6e 55%, #21316b 100%);
  box-shadow: 0 24px 60px rgba(15, 28, 72, 0.28);
}
.tna-banner__glow {
  position: absolute;
  top: -120px; right: -80px;
  width: 340px; height: 340px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(245, 184, 0, 0.35), transparent 70%);
  filter: blur(10px);
  pointer-events: none;
}
.tna-banner__grid {
  position: relative;
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 2.5rem;
  align-items: center;
}

/* LEFT */
.tna-banner__period {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: rgba(245, 184, 0, 0.14);
  border: 1px solid rgba(245, 184, 0, 0.45);
  color: #f5d76e;
  font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em;
  padding: 0.35rem 0.8rem; border-radius: 30px;
  text-transform: uppercase;
}
.tna-banner__title {
  margin-top: 0.9rem;
  font-size: 2rem; line-height: 1.1; font-weight: 800; color: #fff;
}
.tna-banner__lead {
  margin-top: 0.85rem;
  font-size: 0.95rem; line-height: 1.6; color: rgba(255,255,255,0.8);
  max-width: 34rem;
}
.tna-banner__pos { color: #f5d76e; font-weight: 700; }

.tna-banner__cta {
  display: inline-flex; align-items: center; gap: 0.5rem;
  margin-top: 1.4rem;
  background: #f5b800; color: #0f1c48;
  font-weight: 800; font-size: 0.9rem;
  padding: 0.7rem 1.4rem; border-radius: 10px;
  text-decoration: none;
  transition: transform 0.18s, box-shadow 0.18s, background 0.18s;
  box-shadow: 0 8px 22px rgba(245, 184, 0, 0.32);
}
.tna-banner__cta:hover { transform: translateY(-2px); background: #ffca28; box-shadow: 0 12px 26px rgba(245, 184, 0, 0.42); }

.tna-banner__note {
  display: flex; align-items: center; gap: 0.4rem;
  margin-top: 0.9rem; font-size: 0.78rem; color: rgba(255,255,255,0.6);
}

/* Manage / Delete secondary actions */
.tna-banner__subactions {
  display: flex; align-items: center; gap: 0.75rem;
  margin-top: 0.9rem;
}
.tna-banner__link,
.tna-banner__danger {
  display: inline-flex; align-items: center; gap: 0.35rem;
  font-size: 0.8rem; font-weight: 700;
  padding: 0.4rem 0.85rem; border-radius: 8px;
  text-decoration: none; cursor: pointer;
  border: 1px solid rgba(255,255,255,0.28);
  background: rgba(255,255,255,0.06);
  color: rgba(255,255,255,0.9);
  transition: background 0.18s, border-color 0.18s, color 0.18s;
}
.tna-banner__link:hover { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.5); }
.tna-banner__danger {
  border-color: rgba(248,113,113,0.5);
  color: #fca5a5;
  background: rgba(248,113,113,0.1);
}
.tna-banner__danger:hover { background: rgba(248,113,113,0.2); color: #fff; border-color: #f87171; }
.tna-banner__done {
  display: inline-flex; align-items: center; gap: 0.55rem;
  margin-top: 1.4rem;
  background: rgba(34, 197, 94, 0.16);
  border: 1px solid rgba(34, 197, 94, 0.5);
  color: #a7f3d0; font-weight: 700; font-size: 0.88rem;
  padding: 0.6rem 1rem; border-radius: 10px;
}

/* RIGHT: steps */
.tna-banner__steps {
  display: flex; flex-direction: column; gap: 0.75rem;
  list-style: none; margin: 0; padding: 0;
}
.tna-step {
  position: relative;
  display: grid;
  grid-template-columns: auto auto 1fr;
  align-items: center; gap: 0.85rem;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 0.85rem 1rem;
}
.tna-step--done { border-color: rgba(34,197,94,0.4); background: rgba(34,197,94,0.1); }
.tna-step__num {
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(245,184,0,0.2); color: #f5d76e;
  font-size: 0.72rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
}
.tna-step__icon {
  width: 38px; height: 38px; border-radius: 10px;
  background: rgba(255,255,255,0.1); color: #fff;
  display: flex; align-items: center; justify-content: center;
}
.tna-step__body { display: flex; flex-direction: column; min-width: 0; }
.tna-step__title { font-size: 0.9rem; font-weight: 700; color: #fff; }
.tna-step__desc { font-size: 0.76rem; color: rgba(255,255,255,0.62); line-height: 1.4; }

@media (max-width: 860px) {
  .tna-banner__card { padding: 1.75rem; }
  .tna-banner__grid { grid-template-columns: 1fr; gap: 1.75rem; }
  .tna-banner__title { font-size: 1.6rem; }
}
</style>