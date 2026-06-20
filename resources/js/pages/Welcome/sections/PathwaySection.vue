<template>
  <section class="pathway">
    <div class="section-header">
      <span class="eyebrow">LEARNING PATHWAY</span>
      <h2 class="section-title">From learning need to<br>measurable impact.</h2>
      <p class="section-sub">A structured journey that transforms learning into stronger performance and recognition.</p>
    </div>

    <div class="pathway__steps">
      <div
        v-for="(step, i) in steps"
        :key="step.title"
        class="pathway__step"
      >
        <div class="pathway__step-num">{{ String(i + 1).padStart(2, '0') }}</div>
        <div class="pathway__step-icon">{{ step.icon }}</div>
        <div class="pathway__step-title">{{ step.title }}</div>
        <div class="pathway__step-desc">{{ step.desc }}</div>
      </div>
    </div>
  </section>
</template>

<script setup>
const steps = [
  { icon: '🔍', title: 'Assess', desc: 'Identify needs and growth priorities.' },
  { icon: '📋', title: 'Plan',   desc: 'Map targeted development actions.' },
  { icon: '📖', title: 'Learn',  desc: 'Engage in relevant experiences.' },
  { icon: '🏅', title: 'Achieve',desc: 'Translate learning into impact.' },
]
</script>

<style scoped>
.pathway { padding: 5rem 2rem; background: #fff; }

.section-header { text-align: center; margin-bottom: 3rem; }
.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; color: #0CA678; margin-bottom: 0.75rem; text-transform: uppercase; display: block; }
.section-title { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem; }
.section-sub { color: #6b7280; max-width: 600px; margin: 0 auto; line-height: 1.65; }

.pathway__steps {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;
}

.pathway__step {
  background: #f4f6fb; border-radius: 16px; padding: 1.75rem 1.5rem;
  transition: transform 0.2s, box-shadow 0.2s;
}
.pathway__step:hover { transform: translateY(-4px); box-shadow: 0 6px 24px rgba(0,0,0,0.08); }

.pathway__step-num   { font-size: 0.7rem; font-weight: 800; color: #1d3fc4; letter-spacing: 0.05em; margin-bottom: 0.75rem; opacity: 0.6; }
.pathway__step-icon  { font-size: 1.5rem; margin-bottom: 0.75rem; }
.pathway__step-title { font-size: 1rem; font-weight: 700; color: #1a2744; margin-bottom: 0.4rem; }
.pathway__step-desc  { font-size: 0.82rem; color: #6b7280; line-height: 1.5; }

@media (max-width: 1024px) { .pathway__steps { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .pathway__steps { grid-template-columns: 1fr; } }
</style>