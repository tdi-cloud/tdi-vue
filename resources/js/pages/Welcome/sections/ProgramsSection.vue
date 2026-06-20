<template>
  <section class="programs" id="programs">
    <div class="section-header">
      <span class="eyebrow">LEARNING PROGRAMS</span>
      <h2 class="section-title">Development opportunities for<br>every stage of your journey.</h2>
      <p class="section-sub">
        Explore learning experiences designed to support technical excellence,
        leadership readiness, and lifelong growth.
      </p>
    </div>

    <div class="programs__grid">
      <div
        v-for="card in programs"
        :key="card.title"
        class="program-card"
      >
        <div class="program-card__icon" :class="card.iconClass">{{ card.icon }}</div>
        <h3>{{ card.title }}</h3>
        <p>{{ card.desc }}</p>
        <ul>
          <li v-for="item in card.items" :key="item">{{ item }}</li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup>
const programs = [
  {
    icon: '🎓', iconClass: 'icon--blue',
    title: 'Degree Programs',
    desc:  'Advance academically through scholarship-supported higher education pathways.',
    items: ['Masteral Programs', 'Doctoral Programs', 'Scholarship Support'],
  },
  {
    icon: '🖥', iconClass: 'icon--teal',
    title: 'Professional Development',
    desc:  'Stay current through practical and relevant development experiences.',
    items: ['Seminars & Workshops', 'Conferences', 'Learning Forums'],
  },
  {
    icon: '💼', iconClass: 'icon--gold',
    title: 'Workplace Learning',
    desc:  'Grow through meaningful experiences embedded in everyday work.',
    items: ['Coaching & Mentoring', 'Job Rotation', 'Stretch Assignments'],
  },
]
</script>

<style scoped>
.programs { padding: 5rem 2rem; background: #f4f6fb; }

.section-header { text-align: center; margin-bottom: 3rem; }
.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; color: #0CA678; margin-bottom: 0.75rem; text-transform: uppercase; display: block; }
.section-title { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem; }
.section-sub { color: #6b7280; max-width: 600px; margin: 0 auto; line-height: 1.65; }

.programs__grid {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;
}

.program-card {
  background: #fff; border-radius: 16px; padding: 2rem;
  box-shadow: 0 2px 16px rgba(0,0,0,0.05);
  transition: box-shadow 0.2s, transform 0.2s;
}
.program-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.1); transform: translateY(-3px); }

.program-card__icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem; margin-bottom: 1.25rem;
}
.icon--blue { background: #eef2ff; }
.icon--teal { background: #e6fcf5; }
.icon--gold { background: #fff9db; }

.program-card h3 { font-size: 1.05rem; font-weight: 700; color: #1a2744; margin-bottom: 0.5rem; }
.program-card p  { font-size: 0.85rem; color: #6b7280; line-height: 1.6; margin-bottom: 1rem; }
.program-card ul { list-style: none; padding: 0; }
.program-card ul li { font-size: 0.82rem; color: #4b5563; padding: 0.2rem 0; }
.program-card ul li::before { content: '• '; color: #1d3fc4; }

@media (max-width: 1024px) { .programs__grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .programs__grid { grid-template-columns: 1fr; } }
</style>