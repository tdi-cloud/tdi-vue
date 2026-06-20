<template>
  <section class="about" id="about">
    <div class="about__inner">

      <!-- Left: image -->
      <div class="about__media">
        <div class="about__img-wrap">
          <img
            src="/storage/hero/cpsc2.jpg"
            alt="TDI Training Session"
            class="about__img"
          />
          <div class="about__badge">
            <strong>One learning ecosystem</strong>
            <span>built for TESDA personnel growth</span>
          </div>
        </div>
      </div>

      <!-- Right: text -->
      <div class="about__text">
        <span class="eyebrow">ABOUT THE INSTITUTE</span>
        <h2 class="about__heading">A future-ready workforce starts with intentional growth.</h2>
        <p class="about__body">
          TESDA Development Institute serves as the learning arm of TESDA, creating meaningful
          development experiences that build capability, confidence, and public service excellence.
        </p>

        <div class="about__pillars">
          <div class="about__pillar" v-for="pillar in pillars" :key="pillar.title">
            <div class="pillar__icon" :class="pillar.colorClass">{{ pillar.icon }}</div>
            <div>
              <div class="pillar__title">{{ pillar.title }}</div>
              <div class="pillar__desc">{{ pillar.desc }}</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
const pillars = [
  { icon: '⊕', title: 'Build Capability', desc: 'Strengthen core and technical competencies.',       colorClass: 'pillar__icon--blue' },
  { icon: '↗', title: 'Grow People',      desc: 'Create opportunities for continuous advancement.',  colorClass: 'pillar__icon--teal' },
  { icon: '◎', title: 'Lead Change',      desc: 'Prepare leaders for innovation and impact.',        colorClass: 'pillar__icon--gold' },
]
</script>

<style scoped>
.about { padding: 5rem 2rem; background: #fff; }

.about__inner {
  max-width: 1100px; margin: 0 auto;
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 4rem; align-items: center;
}

.about__img-wrap { position: relative; border-radius: 20px; overflow: hidden; }
.about__img { width: 100%; display: block; border-radius: 20px; }

.about__badge {
  position: absolute; bottom: 1.5rem; left: 1.5rem;
  background: #fff; border-radius: 12px;
  padding: 0.75rem 1rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.about__badge strong { display: block; font-size: 0.9rem; font-weight: 700; color: #1a2744; }
.about__badge span   { font-size: 0.75rem; color: #6b7280; }

.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; color: #0CA678; margin-bottom: 1rem; display: block; text-transform: uppercase; }
.about__heading {
  font-size: clamp(1.6rem, 3vw, 2.4rem);
  font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem;
}
.about__body { color: #4b5563; line-height: 1.7; margin-bottom: 2rem; }

.about__pillars { display: flex; flex-direction: column; gap: 1.25rem; }
.about__pillar  { display: flex; align-items: flex-start; gap: 1rem; }

.pillar__icon {
  width: 40px; height: 40px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; flex-shrink: 0;
}
.pillar__icon--blue { background: #eef2ff; color: #3B5BDB; }
.pillar__icon--teal { background: #e6fcf5; color: #0CA678; }
.pillar__icon--gold { background: #fff9db; color: #e67700; }

.pillar__title { font-weight: 700; font-size: 0.95rem; color: #1a2744; }
.pillar__desc  { font-size: 0.82rem; color: #6b7280; margin-top: 0.15rem; }

@media (max-width: 1024px) { .about__inner { grid-template-columns: 1fr; gap: 2.5rem; } }
</style>