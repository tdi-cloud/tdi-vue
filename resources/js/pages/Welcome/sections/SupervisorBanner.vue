<script setup>
import { Link } from '@inertiajs/vue3'
import { ClipboardList, ArrowRight, Users } from 'lucide-vue-next'

const props = defineProps({
  data: { type: Object, required: true }, // { pending }
})
</script>

<template>
  <section id="supervisory" class="sup-banner">
    <div class="sup-banner__card">
      <div class="sup-banner__glow" aria-hidden="true"></div>

      <div class="sup-banner__inner">
        <div class="sup-banner__icon">
          <Users :size="26" />
        </div>

        <div class="sup-banner__text">
          <span class="sup-banner__eyebrow">
            <ClipboardList :size="14" /> Supervisory Rating
          </span>

          <h2 v-if="data.pending" class="sup-banner__title">
            You have {{ data.pending }} self-rating<span v-if="data.pending > 1">s</span>
            awaiting your review
          </h2>
          <h2 v-else class="sup-banner__title">
            You have rated {{ data.rated }} team member<span v-if="data.rated > 1">s</span>
          </h2>

          <p v-if="data.pending" class="sup-banner__lead">
            Some of your team members selected you as their supervisor. Please
            review and rate their competency assessment.
          </p>
          <p v-else class="sup-banner__lead">
            All caught up. You can review the results of the team members you have rated.
          </p>
        </div>

        <Link :href="route('tna.supervisory.index')" class="sup-banner__cta">
          {{ data.pending ? 'Review Now' : 'View Ratings' }}
          <ArrowRight :size="17" />
        </Link>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sup-banner { padding: 0 1.5rem 1.5rem; background: #fff; }
.sup-banner__card {
  position: relative;
  max-width: 1120px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 20px;
  padding: 1.75rem 2rem;
  background: linear-gradient(135deg, #1d3fc4 0%, #2751d6 60%, #1a3aa8 100%);
  box-shadow: 0 18px 44px rgba(29, 63, 196, 0.28);
}
.sup-banner__glow {
  position: absolute; top: -100px; left: -60px;
  width: 260px; height: 260px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 70%);
  pointer-events: none;
}
.sup-banner__inner {
  position: relative;
  display: flex; align-items: center; gap: 1.25rem;
}
.sup-banner__icon {
  flex-shrink: 0;
  width: 54px; height: 54px; border-radius: 14px;
  background: rgba(255,255,255,0.15); color: #fff;
  display: flex; align-items: center; justify-content: center;
}
.sup-banner__text { flex: 1; min-width: 0; }
.sup-banner__eyebrow {
  display: inline-flex; align-items: center; gap: 0.35rem;
  background: rgba(255,255,255,0.16);
  color: #fff; font-size: 0.7rem; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 0.3rem 0.7rem; border-radius: 30px;
}
.sup-banner__title { margin-top: 0.6rem; font-size: 1.3rem; font-weight: 800; color: #fff; line-height: 1.2; }
.sup-banner__lead { margin-top: 0.4rem; font-size: 0.88rem; color: rgba(255,255,255,0.82); max-width: 40rem; }
.sup-banner__cta {
  flex-shrink: 0;
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: #fff; color: #1d3fc4;
  font-weight: 800; font-size: 0.9rem;
  padding: 0.7rem 1.4rem; border-radius: 10px;
  text-decoration: none;
  transition: transform 0.18s, box-shadow 0.18s;
  box-shadow: 0 8px 20px rgba(0,0,0,0.18);
}
.sup-banner__cta:hover { transform: translateY(-2px); }

@media (max-width: 820px) {
  .sup-banner__inner { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .sup-banner__cta { align-self: stretch; justify-content: center; }
}
</style>