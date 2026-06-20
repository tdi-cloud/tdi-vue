<template>
  <section class="panels">
    <div class="panels__grid">
      <div
        v-for="(panel, i) in panels"
        :key="i"
        class="panel"
        :class="{ 'panel--visible': panelVisible[i] }"
        :style="{ transitionDelay: `${i * 0.15}s` }"
        :data-index="i"
        ref="panelRefs"
      >
        <div class="panel__img-wrap">
          <img :src="panel.img" :alt="panel.title" class="panel__img" loading="lazy" />
          <div class="panel__gradient"></div>
        </div>
        <div class="panel__caption">
          <span class="panel__icon">{{ panel.icon }}</span>
          <div>
            <div class="panel__title">{{ panel.title }}</div>
            <div class="panel__desc">{{ panel.desc }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const panels = [
  {
    img:   'storage/hero/cpsc.JPG',
    title: 'Classroom Training',
    desc:  'Face-to-face learning sessions led by expert facilitators.',
    icon:  '🎒',
  },
  {
    img:   'https://images.unsplash.com/photo-1491975474562-1f4e30bc9468?w=600&q=80',
    title: 'Coaching & Mentoring',
    desc:  'One-on-one guidance from experienced public servants.',
    icon:  '🤝',
  },
  {
    img:   'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?w=600&q=80',
    title: 'Online & Blended',
    desc:  'Flexible digital learning accessible anytime, anywhere.',
    icon:  '💻',
  },
  {
    img:   '/storage/hero/ethno.jpeg',
    title: 'Leadership Programs',
    desc:  'Build the next generation of public sector leaders.',
    icon:  '🏆',
  },
]

const panelVisible = ref(panels.map(() => false))
const panelRefs    = ref([])
let observer       = null

onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        panels.forEach((_, i) => {
          setTimeout(() => {
            panelVisible.value[i] = true
          }, i * 180)   // 180ms bawat panel
        })
        observer.unobserve(entry.target)
      }
    })
  }, { threshold: 0.15 })

  const section = panelRefs.value[0]?.closest('section')
  if (section) observer.observe(section)
})

onBeforeUnmount(() => observer?.disconnect())
</script>

<style scoped>
.panels {
  padding: 4rem 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.panels__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}

.panel {
  border-radius: 16px;
  overflow: hidden;
  position: relative;
  aspect-ratio: 3 / 4;
  max-height: 350px;
  cursor: pointer;

  /* Hidden state */
  opacity: 0;
  transform: translateY(60px) scale(0.85);

  /* Spring/pump easing — cubic-bezier na may overshoot */
  transition:
    opacity 0.6s ease,
    transform 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.4);
}

/* Visible state — mag-o-overshoot ng konti para may "pump" */
.panel--visible {
  opacity: 1;
  transform: translateY(0) scale(1);
}

.panel:hover .panel__img { transform: scale(1.07); }

.panel__img-wrap { position: absolute; inset: 0; overflow: hidden; }
.panel__img {
  width: 100%; height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.panel__gradient {
  position: absolute; inset: 0;
  background: linear-gradient(to top, rgba(10,21,60,0.85) 0%, rgba(10,21,60,0.1) 60%);
}

.panel__caption {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 1.25rem;
  display: flex; align-items: flex-end; gap: 0.6rem;
}
.panel__icon  { font-size: 1.4rem; }
.panel__title { font-size: 0.95rem; font-weight: 700; color: #fff; }
.panel__desc  { font-size: 0.75rem; color: rgba(255,255,255,0.7); margin-top: 0.2rem; }

@media (max-width: 1024px) { .panels__grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px)  { .panels__grid { grid-template-columns: 1fr; } }
</style>