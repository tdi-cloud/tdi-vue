<template>
  <section class="resources" id="resources">
    <div class="section-header">
      <span class="eyebrow">DIGITAL RESOURCES</span>
      <h2 class="section-title">Everything you need,<br>in one resource center.</h2>
      <p class="section-sub">Access key references, templates, and forms that support your development journey.</p>
    </div>

    <div class="resources__grid">
      <div
        v-for="item in resources"
        :key="item.title"
        class="resource-card"
      >
        <div class="resource-card__icon" :style="{ color: item.color }">{{ item.icon }}</div>
        <div class="resource-card__title">{{ item.title }}</div>
        <div class="resource-card__desc">{{ item.desc }}</div>
      </div>
    </div>
  </section>
</template>

<script setup>
const resources = [
  { icon: '📄', title: 'Circulars',          desc: 'Official TDI issuances and memoranda.',   color: '#3B5BDB' },
  { icon: '📚', title: 'Training Materials',  desc: 'Modules, slides, and reference readings.', color: '#0CA678' },
  { icon: '📋', title: 'Templates',           desc: 'Pre-formatted documents for reports.',    color: '#4C6EF5' },
  { icon: '📝', title: 'Application Forms',   desc: 'Forms for programs and scholarships.',    color: '#F59F00' },
]
</script>

<style scoped>
.resources { padding: 5rem 2rem; background: #f4f6fb; }

.section-header { text-align: center; margin-bottom: 3rem; }
.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; color: #0CA678; margin-bottom: 0.75rem; text-transform: uppercase; display: block; }
.section-title { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem; }
.section-sub { color: #6b7280; max-width: 600px; margin: 0 auto; line-height: 1.65; }

.resources__grid {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;
}

.resource-card {
  background: #fff; border-radius: 16px; padding: 1.75rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.resource-card:hover { transform: translateY(-3px); box-shadow: 0 6px 24px rgba(0,0,0,0.1); }

.resource-card__icon  { font-size: 1.5rem; margin-bottom: 0.75rem; }
.resource-card__title { font-size: 0.95rem; font-weight: 700; color: #1a2744; margin-bottom: 0.35rem; }
.resource-card__desc  { font-size: 0.78rem; color: #6b7280; line-height: 1.5; }

@media (max-width: 1024px) { .resources__grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .resources__grid { grid-template-columns: repeat(2, 1fr); } }
</style>