<template>
  <section class="hero">
    <div class="hero__slides">
      <div
        v-for="(slide, i) in slides"
        :key="i"
        :ref="el => { if (el) slideRefs[i] = el }"
        class="hero__slide"
        :class="{ 'hero__slide--active': currentSlide === i }"
        :style="{ backgroundImage: `url(${slide.img})` }"
      ></div>
      <div class="hero__overlay"></div>
    </div>

    <div class="hero__content">
      <div class="hero__eyebrow">TESDA DEVELOPMENT INSTITUTE</div>
      <div class="hero__headline">
        <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[0] }">Learn<span class="text-blue-500">.</span></span>
        <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[1] }">Develop<span class="text-blue-500">.</span></span>
        <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[2] }">Lead<span class="text-blue-500">.</span></span>
      </div>
      <p class="hero__sub" :class="{ 'hero__sub--visible': subVisible }">
        Supporting TESDA's workforce through scholarships, training programs,<br class="hero__br">
        competency development, and lifelong learning opportunities.
      </p>
      <div class="hero__cta" :class="{ 'hero__cta--visible': ctaVisible }">
        <Link :href="route('programs.index')" class="btn btn--hero-primary">Explore Programs</Link>
        <a href="#scholarships" @click.prevent="scrollTo('scholarships')" class="btn btn--hero-outline">
          Check Scholarships
        </a>
      </div>
      <div class="hero__stats" :class="{ 'hero__stats--visible': ctaVisible }">
        <div class="hero__stat"><strong>40+</strong><span>Learning Programs</span></div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat"><strong>7-Step</strong><span>Growth Pathway</span></div>
        <div class="hero__stat-divider"></div>
        <div class="hero__stat"><strong>360°</strong><span>Competency Focus</span></div>
      </div>
    </div>

    <div class="hero__indicators">
      <button
        v-for="(_, i) in slides"
        :key="i"
        class="hero__dot"
        :class="{ 'hero__dot--active': currentSlide === i }"
        @click="goToSlide(i)"
        :aria-label="`Slide ${i + 1}`"
      ></button>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useScrollTo } from '@/composables/useScrollTo'

const { scrollTo } = useScrollTo()

const props = defineProps({
  heroSlides: {
    type: Array,
    default: () => []
  }
})

// const slides = props.heroSlides.map(img => ({ img }))

const slides = [
  { img: '/storage/hero/hrmo.jpg' },
  { img: '/storage/hero/hrmo1.jpeg' },
  { img: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1600&q=80' },
]

const currentSlide = ref(0)
const wordVisible  = ref([false, false, false])
const subVisible   = ref(false)
const ctaVisible   = ref(false)
const slideRefs    = ref([])

let slideInterval  = null
let rafId          = null
let zoomStart      = null
const ZOOM_DURATION = 5000   // 5 seconds — pareho sa slideshow interval
const ZOOM_FROM     = 1.0
const ZOOM_TO       = 1.12

// Pure JS zoom — walang CSS animation issue
function startZoom() {
  cancelAnimationFrame(rafId)
  zoomStart = null

  function tick(timestamp) {
    if (!zoomStart) zoomStart = timestamp
    const elapsed  = timestamp - zoomStart
    const progress = Math.min(elapsed / ZOOM_DURATION, 1)

    // easeInOut para smooth
    const eased = progress < 0.5
      ? 2 * progress * progress
      : 1 - Math.pow(-2 * progress + 2, 2) / 2

    const scale = ZOOM_FROM + (ZOOM_TO - ZOOM_FROM) * eased
    const el = slideRefs.value[currentSlide.value]
    if (el) el.style.transform = `scale(${scale})`

    if (progress < 1) {
      rafId = requestAnimationFrame(tick)
    }
  }

  rafId = requestAnimationFrame(tick)
}

function resetSlideZoom(i) {
  const el = slideRefs.value[i]
  if (el) el.style.transform = 'scale(1)'
}

function goToSlide(i) {
  resetSlideZoom(currentSlide.value)  // i-reset ang luma
  currentSlide.value = i
  startZoom()
}

onMounted(() => {
  setTimeout(() => { wordVisible.value[0] = true }, 300)
  setTimeout(() => { wordVisible.value[1] = true }, 600)
  setTimeout(() => { wordVisible.value[2] = true }, 900)
  setTimeout(() => { subVisible.value = true },     1200)
  setTimeout(() => { ctaVisible.value = true },     1500)

  // Start zoom sa unang slide
  startZoom()

  slideInterval = setInterval(() => {
    const prev = currentSlide.value
    const next = (prev + 1) % slides.length
    resetSlideZoom(prev)
    currentSlide.value = next
    startZoom()
  }, 5000)
})

onBeforeUnmount(() => {
  clearInterval(slideInterval)
  cancelAnimationFrame(rafId)
})
</script>

<style>
.hero {
  position: relative;
  height: 100vh;
  min-height: 600px;
  display: flex;
  align-items: center;
  overflow: hidden;
}

.hero__slides { position: absolute; inset: 0; }

.hero__slide {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transform: scale(1);
  transition: opacity 1.2s ease;
  /* transform ay hawak na ng JS — walang CSS transition para hindi mag-conflict */
  will-change: transform;
}

.hero__slide--active { opacity: 1; }

.hero__overlay {
  position: absolute; inset: 0; z-index: 1;
  background: linear-gradient(135deg,
    rgba(10,21,60,0.82) 0%,
    rgba(10,21,60,0.55) 60%,
    rgba(10,21,60,0.35) 100%);
}

.hero__content {
  position: relative; z-index: 2;
  max-width: 1200px; width: 100%;
  margin: 0 auto;
  padding: 68px 2rem 0;
}

.hero__eyebrow {
  font-size: 0.75rem; font-weight: 700; letter-spacing: 0.15em;
  color: rgba(255,255,255,0.65);
  background: rgba(255,255,255,0.1);
  display: inline-block;
  padding: 0.35rem 0.9rem; border-radius: 30px;
  margin-bottom: 1.5rem;
  backdrop-filter: blur(4px);
}

.hero__headline { display: flex; flex-direction: column; margin-bottom: 1.5rem; }

.hero__word {
  font-size: clamp(3.2rem, 7vw, 6rem);
  font-weight: 800; color: #fff; line-height: 1.05;
  opacity: 0; transform: translateY(30px);
  transition: opacity 0.7s ease, transform 0.7s ease;
  letter-spacing: -0.02em;
}
.hero__word--visible { opacity: 1; transform: translateY(0); }

.hero__sub {
  font-size: 1.05rem; color: rgba(255,255,255,0.82);
  max-width: 560px; line-height: 1.65;
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.7s ease 0.1s, transform 0.7s ease 0.1s;
  margin-bottom: 2rem;
}
.hero__sub--visible { opacity: 1; transform: translateY(0); }

.hero__cta {
  display: flex; gap: 1rem; flex-wrap: wrap;
  opacity: 0; transform: translateY(20px);
  transition: opacity 0.7s ease, transform 0.7s ease;
  margin-bottom: 2.5rem;
}
.hero__cta--visible { opacity: 1; transform: translateY(0); }

.hero .btn { display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; text-decoration: none; font-weight: 600; transition: all 0.2s; }
.hero .btn--hero-primary { background: #002fff; color: #ffffff; padding: 0.75rem 1.75rem; border-radius: 8px; font-size: 1rem; font-weight: 700; }
.hero .btn--hero-primary:hover { background: #017bec; }
.hero .btn--hero-outline { border: 2px solid rgba(255,255,255,0.7); color: #fff; background: transparent; padding: 0.75rem 1.75rem; border-radius: 8px; font-size: 1rem; }
.hero .btn--hero-outline:hover { background: rgba(255,255,255,0.1); border-color: #fff; }

.hero__stats {
  display: flex; align-items: center; gap: 1.5rem;
  opacity: 0; transition: opacity 0.8s ease 0.2s;
}
.hero__stats--visible { opacity: 1; }
.hero__stat { display: flex; flex-direction: column; }
.hero__stat strong { font-size: 1.4rem; font-weight: 800; color: #fff; }
.hero__stat span   { font-size: 0.72rem; color: rgba(255,255,255,0.6); letter-spacing: 0.04em; }
.hero__stat-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.25); }

.hero__indicators {
  position: absolute; bottom: 2rem; left: 50%;
  transform: translateX(-50%); z-index: 3;
  display: flex; gap: 0.5rem;
}
.hero__dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: rgba(255,255,255,0.35);
  border: none; cursor: pointer;
  transition: background 0.3s, transform 0.3s;
}
.hero__dot--active { background: #f5b800; transform: scale(1.3); }

@media (max-width: 768px) { .hero__br { display: none; } }
</style>