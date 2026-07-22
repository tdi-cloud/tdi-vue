<script setup>
import { Link } from '@inertiajs/vue3'
import { ClipboardCheck, ArrowRight, UserCheck } from 'lucide-vue-next'

const props = defineProps({
  data: { type: Object, required: true }, // { pending, rated, total }
})
</script>

<template>
  <section id="nhrdc-rating" class="nhrdc-banner">
    <div class="nhrdc-banner__card">
      <div class="nhrdc-banner__glow" aria-hidden="true"></div>

      <div class="nhrdc-banner__inner">
        <div class="nhrdc-banner__icon">
          <UserCheck :size="26" />
        </div>

        <div class="nhrdc-banner__text">
          <span class="nhrdc-banner__eyebrow">
            <ClipboardCheck :size="14" /> NHRDC Interview Rating
          </span>

          <h2 v-if="data.pending" class="nhrdc-banner__title">
            You have {{ data.pending }} nominee interview<span v-if="data.pending > 1">s</span>
            awaiting your rating
          </h2>
          <h2 v-else class="nhrdc-banner__title">
            You have rated all {{ data.rated }} nominee<span v-if="data.rated > 1">s</span>
          </h2>

          <p v-if="data.pending" class="nhrdc-banner__lead">
            As an NHRDC committee member, please rate the interview of the nominees assigned to you.
            {{ data.rated }} of {{ data.total }} already rated.
          </p>
          <p v-else class="nhrdc-banner__lead">
            All caught up. You can still review or update your ratings anytime.
          </p>
        </div>

        <Link :href="route('nhrdc.programs.index')" class="nhrdc-banner__cta">
          {{ data.pending ? 'Rate Now' : 'View Ratings' }}
          <ArrowRight :size="17" />
        </Link>
      </div>
    </div>
  </section>
</template>

<style scoped>
.nhrdc-banner { padding: 0 1.5rem 1.5rem; background: #fff; }
.nhrdc-banner__card {
  position: relative;
  max-width: 1120px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 20px;
  padding: 1.75rem 2rem;
  background: linear-gradient(135deg, #4338ca 0%, #4f46e5 55%, #0ea5e9 100%);
  box-shadow: 0 18px 44px rgba(67, 56, 202, 0.28);
}
.nhrdc-banner__glow {
  position: absolute; top: -100px; left: -60px;
  width: 260px; height: 260px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 70%);
  pointer-events: none;
}
.nhrdc-banner__inner {
  position: relative;
  display: flex; align-items: center; gap: 1.25rem;
}
.nhrdc-banner__icon {
  flex-shrink: 0;
  width: 54px; height: 54px; border-radius: 14px;
  background: rgba(255,255,255,0.15); color: #fff;
  display: flex; align-items: center; justify-content: center;
}
.nhrdc-banner__text { flex: 1; min-width: 0; }
.nhrdc-banner__eyebrow {
  display: inline-flex; align-items: center; gap: 0.35rem;
  background: rgba(255,255,255,0.16);
  color: #fff; font-size: 0.7rem; font-weight: 800;
  letter-spacing: 0.04em; text-transform: uppercase;
  padding: 0.3rem 0.7rem; border-radius: 30px;
}
.nhrdc-banner__title { margin-top: 0.6rem; font-size: 1.3rem; font-weight: 800; color: #fff; line-height: 1.2; }
.nhrdc-banner__lead { margin-top: 0.4rem; font-size: 0.88rem; color: rgba(255,255,255,0.82); max-width: 40rem; }
.nhrdc-banner__cta {
  flex-shrink: 0;
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: #fff; color: #4338ca;
  font-weight: 800; font-size: 0.9rem;
  padding: 0.7rem 1.4rem; border-radius: 10px;
  text-decoration: none;
  transition: transform 0.18s, box-shadow 0.18s;
  box-shadow: 0 8px 20px rgba(0,0,0,0.18);
}
.nhrdc-banner__cta:hover { transform: translateY(-2px); }

@media (max-width: 820px) {
  .nhrdc-banner__inner { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .nhrdc-banner__cta { align-self: stretch; justify-content: center; }
}
</style>
