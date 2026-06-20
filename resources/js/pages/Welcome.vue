<template>
  <div class="tdi-home" ref="homeRef">

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar" :class="{ 'navbar--scrolled': isScrolled }">
      <div class="navbar__inner">
        <a href="/" class="navbar__brand">
          <div class="navbar__logo">TDI</div>
          <div class="navbar__brand-text">
            <span class="brand-name">TESDA Development Institute</span>
            <span class="brand-sub">Learning &amp; Development Portal</span>
          </div>
        </a>
        <div class="navbar__links">
          <a href="#about" class="nav-link" @click.prevent="scrollTo('about')">About TDI</a>
          <a href="#programs" class="nav-link" @click.prevent="scrollTo('programs')">Programs</a>
          <a href="#scholarships" class="nav-link" @click.prevent="scrollTo('scholarships')">Scholarships</a>
          <a href="#resources" class="nav-link" @click.prevent="scrollTo('resources')">Resources</a>
        </div>
        <div class="navbar__actions">
          <Link :href="route('login')" class="btn btn--outline">Login</Link>
          <Link :href="route('register')" class="btn btn--primary">Sign Up</Link>
        </div>
        <button class="navbar__hamburger" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Menu">
          <span></span><span></span><span></span>
        </button>
      </div>
      <!-- Mobile menu -->
      <div class="mobile-menu" :class="{ 'mobile-menu--open': mobileMenuOpen }">
        <a href="#about" class="nav-link" @click="mobileMenuOpen = false; scrollTo('about')">About TDI</a>
        <a href="#programs" class="nav-link" @click="mobileMenuOpen = false; scrollTo('programs')">Programs</a>
        <a href="#scholarships" class="nav-link" @click="mobileMenuOpen = false; scrollTo('scholarships')">Scholarships</a>
        <a href="#resources" class="nav-link" @click="mobileMenuOpen = false; scrollTo('resources')">Resources</a>
        <div class="mobile-menu__actions">
          <Link :href="route('login')" class="btn btn--outline">Login</Link>
          <Link :href="route('register')" class="btn btn--primary">Sign Up</Link>
        </div>
      </div>
    </nav>

    <!-- ===== HERO ===== -->
    <section class="hero">
      <!-- Slideshow backgrounds -->
      <div class="hero__slides">
        <div
          v-for="(slide, i) in heroSlides"
          :key="i"
          class="hero__slide"
          :class="{ 'hero__slide--active': currentSlide === i, 'hero__slide--zooming': currentSlide === i }"
          :style="{ backgroundImage: `url(${slide.img})` }"
        ></div>
        <div class="hero__overlay"></div>
      </div>

      <!-- Hero content -->
      <div class="hero__content">
        <div class="hero__eyebrow">TESDA DEVELOPMENT INSTITUTE</div>
        <div class="hero__headline">
          <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[0] }">Learn.</span>
          <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[1] }">Develop.</span>
          <span class="hero__word" :class="{ 'hero__word--visible': wordVisible[2] }">Lead.</span>
        </div>
        <p class="hero__sub" :class="{ 'hero__sub--visible': subVisible }">
          Supporting TESDA's workforce through scholarships, training programs,<br class="hero__br">
          competency development, and lifelong learning opportunities.
        </p>
        <div class="hero__cta" :class="{ 'hero__cta--visible': ctaVisible }">
          <Link :href="route('login')" class="btn btn--hero-primary">Explore Programs</Link>
          <a href="#scholarships" @click.prevent="scrollTo('scholarships')" class="btn btn--hero-outline">Check Scholarships</a>
        </div>
        <div class="hero__stats" :class="{ 'hero__stats--visible': ctaVisible }">
          <div class="hero__stat"><strong>40+</strong><span>Learning Programs</span></div>
          <div class="hero__stat-divider"></div>
          <div class="hero__stat"><strong>7-Step</strong><span>Growth Pathway</span></div>
          <div class="hero__stat-divider"></div>
          <div class="hero__stat"><strong>360°</strong><span>Competency Focus</span></div>
        </div>
      </div>

      <!-- Slide indicators -->
      <div class="hero__indicators">
        <button
          v-for="(_, i) in heroSlides"
          :key="i"
          class="hero__dot"
          :class="{ 'hero__dot--active': currentSlide === i }"
          @click="goToSlide(i)"
        ></button>
      </div>
    </section>

    <!-- ===== IMAGE PANELS ===== -->
    <section class="panels">
      <div class="panels__grid">
        <div
          v-for="(panel, i) in panels"
          :key="i"
          class="panel"
          :class="{ 'panel--visible': panelVisible[i] }"
          :style="{ transitionDelay: `${i * 0.15}s` }"
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

    <!-- ===== ABOUT ===== -->
    <section class="about" id="about" ref="aboutRef">
      <div class="about__inner">
        <div class="about__media">
          <div class="about__img-wrap">
            <img
              src="https://images.unsplash.com/photo-1531482615713-2afd69097998?w=700&q=80"
              alt="TDI Training Session"
              class="about__img"
            />
            <div class="about__badge">
              <strong>One learning ecosystem</strong>
              <span>built for TESDA personnel growth</span>
            </div>
          </div>
        </div>
        <div class="about__text">
          <div class="about__eyebrow">ABOUT THE INSTITUTE</div>
          <h2 class="about__heading">A future-ready workforce starts with intentional growth.</h2>
          <p class="about__body">
            TESDA Development Institute serves as the learning arm of TESDA, creating meaningful
            development experiences that build capability, confidence, and public service excellence.
          </p>
          <div class="about__pillars">
            <div class="about__pillar">
              <div class="pillar__icon pillar__icon--blue">⊕</div>
              <div>
                <div class="pillar__title">Build Capability</div>
                <div class="pillar__desc">Strengthen core and technical competencies.</div>
              </div>
            </div>
            <div class="about__pillar">
              <div class="pillar__icon pillar__icon--teal">↗</div>
              <div>
                <div class="pillar__title">Grow People</div>
                <div class="pillar__desc">Create opportunities for continuous advancement.</div>
              </div>
            </div>
            <div class="about__pillar">
              <div class="pillar__icon pillar__icon--gold">◎</div>
              <div>
                <div class="pillar__title">Lead Change</div>
                <div class="pillar__desc">Prepare leaders for innovation and impact.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== PROGRAMS ===== -->
    <section class="programs" id="programs">
      <div class="section-header">
        <div class="eyebrow">LEARNING PROGRAMS</div>
        <h2 class="section-title">Development opportunities for<br>every stage of your journey.</h2>
        <p class="section-sub">Explore learning experiences designed to support technical excellence, leadership readiness, and lifelong growth.</p>
      </div>
      <div class="programs__grid">
        <div class="program-card">
          <div class="program-card__icon program-card__icon--blue">🎓</div>
          <h3>Degree Programs</h3>
          <p>Advance academically through scholarship-supported higher education pathways.</p>
          <ul>
            <li>Masteral Programs</li>
            <li>Doctoral Programs</li>
            <li>Scholarship Support</li>
          </ul>
        </div>
        <div class="program-card">
          <div class="program-card__icon program-card__icon--teal">🖥</div>
          <h3>Professional Development</h3>
          <p>Stay current through practical and relevant development experiences.</p>
          <ul>
            <li>Seminars &amp; Workshops</li>
            <li>Conferences</li>
            <li>Learning Forums</li>
          </ul>
        </div>
        <div class="program-card">
          <div class="program-card__icon program-card__icon--gold">💼</div>
          <h3>Workplace Learning</h3>
          <p>Grow through meaningful experiences embedded in everyday work.</p>
          <ul>
            <li>Coaching &amp; Mentoring</li>
            <li>Job Rotation</li>
            <li>Stretch Assignments</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- ===== SCHOLARSHIPS ===== -->
    <section class="scholarships" id="scholarships">
      <div class="scholarships__inner">
        <div class="scholarships__text">
          <div class="eyebrow eyebrow--gold">SCHOLARSHIP HUB</div>
          <h2 class="scholarships__heading">Invest in your next level of growth.</h2>
          <p class="scholarships__body">Discover opportunities that support advanced study, deeper expertise, and broader professional capability.</p>
          <div class="scholarships__img-wrap">
            <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=600&q=80" alt="Graduates" class="scholarships__img" />
          </div>
        </div>
        <div class="eligibility-card">
          <div class="eligibility-card__header">
            <div>
              <div class="eyebrow">QUICK ASSESSMENT</div>
              <h3>Scholarship Eligibility Checker</h3>
            </div>
            <button class="eligibility-card__help" title="Help">?</button>
          </div>
          <div class="field">
            <label>Position Level</label>
            <select v-model="eligibility.position">
              <option value="">Select position level</option>
              <option>Salary Grade 1–10</option>
              <option>Salary Grade 11–15</option>
              <option>Salary Grade 16–20</option>
              <option>Salary Grade 21+</option>
            </select>
          </div>
          <div class="field">
            <label>Years of Service</label>
            <select v-model="eligibility.years">
              <option value="">Select years of service</option>
              <option>Less than 2 years</option>
              <option>2–5 years</option>
              <option>6–10 years</option>
              <option>More than 10 years</option>
            </select>
          </div>
          <div class="field">
            <label>Latest Performance Rating</label>
            <select v-model="eligibility.rating">
              <option value="">Select latest rating</option>
              <option>Outstanding</option>
              <option>Very Satisfactory</option>
              <option>Satisfactory</option>
              <option>Unsatisfactory</option>
            </select>
          </div>
          <div v-if="eligibilityResult" class="eligibility-result" :class="eligibilityResult.type">
            <span>{{ eligibilityResult.icon }}</span> {{ eligibilityResult.message }}
          </div>
          <div class="eligibility-card__actions">
            <button class="btn btn--primary" @click="checkEligibility">Check My Eligibility</button>
            <button class="btn btn--outline-dark" @click="resetEligibility">Reset</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== LEARNING PATHWAY ===== -->
    <section class="pathway">
      <div class="section-header">
        <div class="eyebrow">LEARNING PATHWAY</div>
        <h2 class="section-title">From learning need to<br>measurable impact.</h2>
        <p class="section-sub">A structured journey that transforms learning into stronger performance and recognition.</p>
      </div>
      <div class="pathway__steps">
        <div class="pathway__step" v-for="(step, i) in pathwaySteps" :key="i">
          <div class="pathway__step-num">{{ String(i+1).padStart(2,'0') }}</div>
          <div class="pathway__step-icon">{{ step.icon }}</div>
          <div class="pathway__step-title">{{ step.title }}</div>
          <div class="pathway__step-desc">{{ step.desc }}</div>
        </div>
      </div>
    </section>

    <!-- ===== RESOURCES ===== -->
    <section class="resources" id="resources">
      <div class="section-header">
        <div class="eyebrow">DIGITAL RESOURCES</div>
        <h2 class="section-title">Everything you need,<br>in one resource center.</h2>
        <p class="section-sub">Access key references, templates, and forms that support your development journey.</p>
      </div>
      <div class="resources__grid">
        <div class="resource-card" v-for="(r, i) in resources" :key="i">
          <div class="resource-card__icon" :style="{ color: r.color }">{{ r.icon }}</div>
          <div class="resource-card__title">{{ r.title }}</div>
        </div>
      </div>
    </section>

    <!-- ===== CTA BANNER ===== -->
    <section class="cta-banner">
      <div class="cta-banner__inner">
        <h2>Ready to take your next step?</h2>
        <p>Join thousands of TESDA personnel investing in their growth through TDI.</p>
        <div class="cta-banner__actions">
          <Link :href="route('register')" class="btn btn--primary btn--lg">Create an Account</Link>
          <Link :href="route('login')" class="btn btn--outline-white btn--lg">Sign In</Link>
        </div>
      </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="footer">
      <div class="footer__inner">
        <div class="footer__brand">
          <div class="footer__logo">TDI</div>
          <div>
            <div class="footer__name">TESDA Development Institute</div>
            <div class="footer__tagline">Learning &amp; Development Portal</div>
          </div>
        </div>
        <p class="footer__desc">Empowering TESDA personnel through continuous learning, leadership development, and workforce excellence.</p>
        <div class="footer__links-col">
          <div class="footer__links-group">
            <div class="footer__col-title">Quick Links</div>
            <a href="#about" @click.prevent="scrollTo('about')">About TDI</a>
            <a href="#scholarships" @click.prevent="scrollTo('scholarships')">Scholarships</a>
            <a href="#programs" @click.prevent="scrollTo('programs')">Programs</a>
            <a href="#resources" @click.prevent="scrollTo('resources')">Resources</a>
          </div>
          <div class="footer__contact">
            <div class="footer__col-title">Stay Connected</div>
            <p>For learning inquiries, scholarship updates, and resource access, connect with the TESDA Development Institute.</p>
          </div>
        </div>
        <div class="footer__copy">© 2026 TESDA Development Institute</div>
      </div>
    </footer>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'

// ---- Hero slideshow ----
const heroSlides = [
  { img: 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=1600&q=80' },
  { img: 'https://images.unsplash.com/photo-1557425529-b1ae9c141e7d?w=1600&q=80' },
  { img: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1600&q=80' },
]
const currentSlide = ref(0)
let slideInterval = null

function goToSlide(i) {
  currentSlide.value = i
}

// ---- Hero text animation ----
const wordVisible = ref([false, false, false])
const subVisible = ref(false)
const ctaVisible = ref(false)

// ---- Panels ----
const panels = [
  {
    img: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80',
    title: 'Classroom Training',
    desc: 'Face-to-face learning sessions led by expert facilitators.',
    icon: '🎒',
  },
  {
    img: 'https://images.unsplash.com/photo-1491975474562-1f4e30bc9468?w=600&q=80',
    title: 'Mentoring & Coaching',
    desc: 'One-on-one guidance from experienced public servants.',
    icon: '🤝',
  },
  {
    img: 'https://images.unsplash.com/photo-1506784983877-45594efa4cbe?w=600&q=80',
    title: 'Online & Blended',
    desc: 'Flexible digital learning accessible anytime, anywhere.',
    icon: '💻',
  },
  {
    img: 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?w=600&q=80',
    title: 'Leadership Programs',
    desc: 'Build the next generation of public sector leaders.',
    icon: '🏆',
  },
]
const panelVisible = ref([false, false, false, false])

// ---- Scholarships eligibility ----
const eligibility = ref({ position: '', years: '', rating: '' })
const eligibilityResult = ref(null)

function checkEligibility() {
  const { position, years, rating } = eligibility.value
  if (!position || !years || !rating) {
    eligibilityResult.value = { type: 'result--warn', icon: '⚠️', message: 'Please complete all fields to check your eligibility.' }
    return
  }
  const goodRating = ['Outstanding', 'Very Satisfactory'].includes(rating)
  const enoughService = years !== 'Less than 2 years'
  if (goodRating && enoughService) {
    eligibilityResult.value = { type: 'result--success', icon: '✅', message: 'You may be eligible for TDI scholarships. Sign in to apply.' }
  } else {
    eligibilityResult.value = { type: 'result--info', icon: 'ℹ️', message: 'You may not yet meet all criteria. Keep growing — check again after your next review.' }
  }
}

function resetEligibility() {
  eligibility.value = { position: '', years: '', rating: '' }
  eligibilityResult.value = null
}

// ---- Pathway ----
const pathwaySteps = [
  { icon: '🔍', title: 'Assess', desc: 'Identify needs and growth priorities.' },
  { icon: '📋', title: 'Plan', desc: 'Map targeted development actions.' },
  { icon: '📖', title: 'Learn', desc: 'Engage in relevant experiences.' },
  { icon: '🏅', title: 'Achieve', desc: 'Translate learning into impact.' },
]

// ---- Resources ----
const resources = [
  { icon: '📄', title: 'Circulars', color: '#3B5BDB' },
  { icon: '📚', title: 'Training Materials', color: '#0CA678' },
  { icon: '📋', title: 'Templates', color: '#4C6EF5' },
  { icon: '📝', title: 'Application Forms', color: '#F59F00' },
]

// ---- Scroll / intersection ----
const isScrolled = ref(false)
const mobileMenuOpen = ref(false)
const aboutRef = ref(null)

function scrollTo(id) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}

function handleScroll() {
  isScrolled.value = window.scrollY > 60
}

// Panels intersection observer
let panelObserver = null

onMounted(() => {
  // Navbar scroll
  window.addEventListener('scroll', handleScroll)

  // Hero animation
  setTimeout(() => { wordVisible.value[0] = true }, 300)
  setTimeout(() => { wordVisible.value[1] = true }, 600)
  setTimeout(() => { wordVisible.value[2] = true }, 900)
  setTimeout(() => { subVisible.value = true }, 1200)
  setTimeout(() => { ctaVisible.value = true }, 1500)

  // Slideshow
  slideInterval = setInterval(() => {
    currentSlide.value = (currentSlide.value + 1) % heroSlides.length
  }, 5000)

  // Panel observer
  const panelEls = document.querySelectorAll('.panel')
  panelObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const i = parseInt(entry.target.dataset.index)
        panelVisible.value[i] = true
        panelObserver.unobserve(entry.target)
      }
    })
  }, { threshold: 0.15 })

  panelEls.forEach((el, i) => {
    el.dataset.index = i
    panelObserver.observe(el)
  })
})

onBeforeUnmount(() => {
  clearInterval(slideInterval)
  window.removeEventListener('scroll', handleScroll)
  panelObserver?.disconnect()
})
</script>

<style scoped>
/* ============================================================
   BASE
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

.tdi-home {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  color: #1a2744;
  background: #fff;
  overflow-x: hidden;
}

/* ============================================================
   NAVBAR
============================================================ */
.navbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  padding: 0 2rem;
  transition: background 0.3s, box-shadow 0.3s;
  background: transparent;
}
.navbar--scrolled {
  background: rgba(15, 28, 72, 0.97);
  box-shadow: 0 2px 20px rgba(0,0,0,0.25);
}
.navbar__inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 2rem;
  height: 68px;
}
.navbar__brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  flex-shrink: 0;
}
.navbar__logo {
  width: 40px; height: 40px;
  background: #1d3fc4;
  border-radius: 8px;
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center;
  letter-spacing: 0.02em;
}
.brand-name { display: block; font-weight: 700; font-size: 0.85rem; color: #fff; line-height: 1.2; }
.brand-sub  { display: block; font-size: 0.7rem; color: rgba(255,255,255,0.6); }

.navbar__links {
  display: flex;
  gap: 1.75rem;
  margin-left: auto;
}
.nav-link {
  color: rgba(255,255,255,0.85);
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 500;
  transition: color 0.2s;
}
.nav-link:hover { color: #fff; }

.navbar__actions {
  display: flex;
  gap: 0.75rem;
}

/* ---- Buttons ---- */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.55rem 1.25rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}
.btn--outline {
  border: 1.5px solid rgba(255,255,255,0.5);
  color: #fff;
  background: transparent;
}
.btn--outline:hover { background: rgba(255,255,255,0.1); border-color: #fff; }

.btn--primary {
  background: #1d3fc4;
  color: #fff;
}
.btn--primary:hover { background: #1535a8; }

.btn--hero-primary {
  background: #f5b800;
  color: #1a2744;
  padding: 0.75rem 1.75rem;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 700;
}
.btn--hero-primary:hover { background: #e0a800; }

.btn--hero-outline {
  border: 2px solid rgba(255,255,255,0.7);
  color: #fff;
  background: transparent;
  padding: 0.75rem 1.75rem;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.btn--hero-outline:hover { background: rgba(255,255,255,0.1); border-color: #fff; }

.btn--outline-dark {
  border: 1.5px solid #1d3fc4;
  color: #1d3fc4;
  background: transparent;
}
.btn--outline-dark:hover { background: #eef1fc; }

.btn--outline-white {
  border: 2px solid rgba(255,255,255,0.6);
  color: #fff;
  background: transparent;
}
.btn--outline-white:hover { background: rgba(255,255,255,0.1); }
.btn--lg { padding: 0.85rem 2rem; font-size: 1rem; }

/* Hamburger */
.navbar__hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  margin-left: auto;
}
.navbar__hamburger span {
  display: block;
  width: 24px; height: 2px;
  background: #fff;
  border-radius: 2px;
}

/* Mobile menu */
.mobile-menu {
  display: none;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem 0 1.5rem;
  background: rgba(15,28,72,0.98);
}
.mobile-menu--open { display: flex; }
.mobile-menu .nav-link { padding: 0.5rem 0; }
.mobile-menu__actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; }

/* ============================================================
   HERO
============================================================ */
.hero {
  position: relative;
  height: 100vh;
  min-height: 600px;
  display: flex;
  align-items: center;
  overflow: hidden;
}

.hero__slides {
  position: absolute;
  inset: 0;
}
.hero__slide {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transition: opacity 1.2s ease;
  transform: scale(1);
}
.hero__slide--active {
  opacity: 1;
}
.hero__slide--zooming {
  animation: kenBurns 5.5s ease-in-out forwards;
}
@keyframes kenBurns {
  from { transform: scale(1); }
  to   { transform: scale(1.07); }
}

.hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(10,21,60,0.82) 0%, rgba(10,21,60,0.55) 60%, rgba(10,21,60,0.35) 100%);
}

.hero__content {
  position: relative;
  z-index: 2;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
  padding: 0 2rem;
  padding-top: 68px;
}

.hero__eyebrow {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.15em;
  color: rgba(255,255,255,0.65);
  background: rgba(255,255,255,0.1);
  display: inline-block;
  padding: 0.35rem 0.9rem;
  border-radius: 30px;
  margin-bottom: 1.5rem;
  backdrop-filter: blur(4px);
}

.hero__headline {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin-bottom: 1.5rem;
}
.hero__word {
  font-size: clamp(3.2rem, 7vw, 6rem);
  font-weight: 800;
  color: #fff;
  line-height: 1.05;
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.7s ease, transform 0.7s ease;
  letter-spacing: -0.02em;
}
.hero__word--visible {
  opacity: 1;
  transform: translateY(0);
}

.hero__sub {
  font-size: 1.05rem;
  color: rgba(255,255,255,0.82);
  max-width: 560px;
  line-height: 1.65;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.7s ease 0.1s, transform 0.7s ease 0.1s;
  margin-bottom: 2rem;
}
.hero__sub--visible { opacity: 1; transform: translateY(0); }

.hero__cta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.7s ease, transform 0.7s ease;
  margin-bottom: 2.5rem;
}
.hero__cta--visible { opacity: 1; transform: translateY(0); }

.hero__stats {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  opacity: 0;
  transition: opacity 0.8s ease 0.2s;
}
.hero__stats--visible { opacity: 1; }
.hero__stat { display: flex; flex-direction: column; }
.hero__stat strong { font-size: 1.4rem; font-weight: 800; color: #fff; }
.hero__stat span { font-size: 0.72rem; color: rgba(255,255,255,0.6); letter-spacing: 0.04em; }
.hero__stat-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.25); }

.hero__indicators {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 3;
  display: flex;
  gap: 0.5rem;
}
.hero__dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: rgba(255,255,255,0.35);
  border: none;
  cursor: pointer;
  transition: background 0.3s, transform 0.3s;
}
.hero__dot--active {
  background: #f5b800;
  transform: scale(1.3);
}

/* ============================================================
   PANELS
============================================================ */
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
  aspect-ratio: 3/4;
  opacity: 0;
  transform: translateY(40px) scale(0.96);
  transition: opacity 0.6s ease, transform 0.6s ease;
  cursor: pointer;
}
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
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(10,21,60,0.85) 0%, rgba(10,21,60,0.1) 60%);
}
.panel__caption {
  position: absolute;
  bottom: 0;
  left: 0; right: 0;
  padding: 1.25rem;
  display: flex;
  align-items: flex-end;
  gap: 0.6rem;
}
.panel__icon { font-size: 1.4rem; }
.panel__title { font-size: 0.95rem; font-weight: 700; color: #fff; }
.panel__desc  { font-size: 0.75rem; color: rgba(255,255,255,0.7); margin-top: 0.2rem; }

/* ============================================================
   ABOUT
============================================================ */
.about {
  padding: 5rem 2rem;
  background: #fff;
}
.about__inner {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 4rem;
  align-items: center;
}
.about__img-wrap { position: relative; border-radius: 20px; overflow: hidden; }
.about__img { width: 100%; display: block; border-radius: 20px; }
.about__badge {
  position: absolute;
  bottom: 1.5rem; left: 1.5rem;
  background: #fff;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.about__badge strong { display: block; font-size: 0.9rem; font-weight: 700; color: #1a2744; }
.about__badge span { font-size: 0.75rem; color: #6b7280; }

.about__eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; color: #0CA678; margin-bottom: 1rem; }
.about__heading { font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem; }
.about__body { color: #4b5563; line-height: 1.7; margin-bottom: 2rem; }

.about__pillars { display: flex; flex-direction: column; gap: 1.25rem; }
.about__pillar { display: flex; align-items: flex-start; gap: 1rem; }
.pillar__icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.pillar__icon--blue  { background: #eef2ff; color: #3B5BDB; }
.pillar__icon--teal  { background: #e6fcf5; color: #0CA678; }
.pillar__icon--gold  { background: #fff9db; color: #e67700; }
.pillar__title { font-weight: 700; font-size: 0.95rem; color: #1a2744; }
.pillar__desc  { font-size: 0.82rem; color: #6b7280; margin-top: 0.15rem; }

/* ============================================================
   SECTION HEADERS (shared)
============================================================ */
.section-header { text-align: center; margin-bottom: 3rem; }
.eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em; color: #0CA678; margin-bottom: 0.75rem; text-transform: uppercase; }
.eyebrow--gold { color: #f5b800; }
.section-title { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; color: #1a2744; line-height: 1.2; margin-bottom: 1rem; }
.section-sub { color: #6b7280; max-width: 600px; margin: 0 auto; line-height: 1.65; }

/* ============================================================
   PROGRAMS
============================================================ */
.programs {
  padding: 5rem 2rem;
  background: #f4f6fb;
}
.programs__grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
}
.program-card {
  background: #fff;
  border-radius: 16px;
  padding: 2rem;
  box-shadow: 0 2px 16px rgba(0,0,0,0.05);
  transition: box-shadow 0.2s, transform 0.2s;
}
.program-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.1); transform: translateY(-3px); }
.program-card__icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 1.25rem;
}
.program-card__icon--blue  { background: #eef2ff; }
.program-card__icon--teal  { background: #e6fcf5; }
.program-card__icon--gold  { background: #fff9db; }
.program-card h3 { font-size: 1.05rem; font-weight: 700; color: #1a2744; margin-bottom: 0.5rem; }
.program-card p  { font-size: 0.85rem; color: #6b7280; line-height: 1.6; margin-bottom: 1rem; }
.program-card ul { list-style: none; padding: 0; }
.program-card ul li { font-size: 0.82rem; color: #4b5563; padding: 0.2rem 0; }
.program-card ul li::before { content: '• '; color: #1d3fc4; }

/* ============================================================
   SCHOLARSHIPS
============================================================ */
.scholarships {
  padding: 5rem 2rem;
  background: #1a2744;
}
.scholarships__inner {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  gap: 4rem;
  align-items: start;
}
.scholarships__heading { font-size: clamp(1.8rem, 3.5vw, 3rem); font-weight: 800; color: #fff; line-height: 1.15; margin: 0.75rem 0 1rem; }
.scholarships__body { color: rgba(255,255,255,0.7); line-height: 1.65; margin-bottom: 1.5rem; }
.scholarships__img-wrap { border-radius: 16px; overflow: hidden; }
.scholarships__img { width: 100%; display: block; border-radius: 16px; }

/* Eligibility card */
.eligibility-card {
  background: #fff;
  border-radius: 20px;
  padding: 2rem;
  box-shadow: 0 8px 40px rgba(0,0,0,0.2);
}
.eligibility-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}
.eligibility-card__header h3 { font-size: 1.1rem; font-weight: 700; color: #1a2744; margin-top: 0.25rem; }
.eligibility-card__help {
  width: 32px; height: 32px;
  border-radius: 50%;
  border: 1.5px solid #d1d5db;
  background: none;
  cursor: pointer;
  color: #6b7280;
  font-weight: 700;
  font-size: 0.9rem;
}

.field { margin-bottom: 1rem; }
.field label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
.field select {
  width: 100%;
  padding: 0.6rem 0.85rem;
  border: 1.5px solid #d1d5db;
  border-radius: 8px;
  font-size: 0.9rem;
  color: #374151;
  background: #fff;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24'%3E%3Cpath stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.75rem center;
  cursor: pointer;
}
.field select:focus { outline: none; border-color: #1d3fc4; }

.eligibility-result {
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  margin-bottom: 1rem;
  font-weight: 500;
}
.result--success { background: #ecfdf5; color: #065f46; }
.result--info    { background: #eff6ff; color: #1e40af; }
.result--warn    { background: #fffbeb; color: #92400e; }

.eligibility-card__actions { display: flex; gap: 0.75rem; margin-top: 1.25rem; }

/* ============================================================
   PATHWAY
============================================================ */
.pathway {
  padding: 5rem 2rem;
  background: #fff;
}
.pathway__steps {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}
.pathway__step {
  background: #f4f6fb;
  border-radius: 16px;
  padding: 1.75rem 1.5rem;
  position: relative;
  transition: transform 0.2s, box-shadow 0.2s;
}
.pathway__step:hover { transform: translateY(-4px); box-shadow: 0 6px 24px rgba(0,0,0,0.08); }
.pathway__step-num {
  font-size: 0.7rem;
  font-weight: 800;
  color: #1d3fc4;
  letter-spacing: 0.05em;
  margin-bottom: 0.75rem;
  opacity: 0.6;
}
.pathway__step-icon { font-size: 1.5rem; margin-bottom: 0.75rem; }
.pathway__step-title { font-size: 1rem; font-weight: 700; color: #1a2744; margin-bottom: 0.4rem; }
.pathway__step-desc  { font-size: 0.82rem; color: #6b7280; line-height: 1.5; }

/* ============================================================
   RESOURCES
============================================================ */
.resources {
  padding: 5rem 2rem;
  background: #f4f6fb;
}
.resources__grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}
.resource-card {
  background: #fff;
  border-radius: 16px;
  padding: 1.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}
.resource-card:hover { transform: translateY(-3px); box-shadow: 0 6px 24px rgba(0,0,0,0.1); }
.resource-card__icon { font-size: 1.5rem; }
.resource-card__title { font-size: 0.95rem; font-weight: 700; color: #1a2744; }

/* ============================================================
   CTA BANNER
============================================================ */
.cta-banner {
  padding: 5rem 2rem;
  background: linear-gradient(135deg, #1d3fc4 0%, #0e2587 100%);
  text-align: center;
}
.cta-banner__inner { max-width: 600px; margin: 0 auto; }
.cta-banner h2 { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; color: #fff; margin-bottom: 0.75rem; }
.cta-banner p  { color: rgba(255,255,255,0.75); margin-bottom: 2rem; line-height: 1.6; }
.cta-banner__actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

/* ============================================================
   FOOTER
============================================================ */
.footer { background: #0f1c48; padding: 3.5rem 2rem 1.5rem; }
.footer__inner { max-width: 1100px; margin: 0 auto; }
.footer__brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.footer__logo {
  width: 40px; height: 40px;
  background: #1d3fc4;
  border-radius: 8px;
  color: #fff;
  font-weight: 800;
  font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center;
}
.footer__name    { font-weight: 700; color: #fff; font-size: 0.95rem; }
.footer__tagline { font-size: 0.72rem; color: rgba(255,255,255,0.5); }
.footer__desc { color: rgba(255,255,255,0.55); font-size: 0.82rem; line-height: 1.65; max-width: 320px; margin-bottom: 2rem; }

.footer__links-col {
  display: flex;
  gap: 4rem;
  margin-bottom: 2.5rem;
}
.footer__links-group { display: flex; flex-direction: column; gap: 0.5rem; }
.footer__col-title { font-size: 0.85rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem; }
.footer__links-group a { font-size: 0.82rem; color: rgba(255,255,255,0.55); text-decoration: none; transition: color 0.2s; }
.footer__links-group a:hover { color: #fff; }
.footer__contact p { font-size: 0.82rem; color: rgba(255,255,255,0.55); max-width: 260px; line-height: 1.6; }
.footer__copy { font-size: 0.75rem; color: rgba(255,255,255,0.3); padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1024px) {
  .panels__grid { grid-template-columns: repeat(2, 1fr); }
  .programs__grid { grid-template-columns: repeat(2, 1fr); }
  .about__inner { grid-template-columns: 1fr; gap: 2.5rem; }
  .scholarships__inner { grid-template-columns: 1fr; gap: 2.5rem; }
  .pathway__steps { grid-template-columns: repeat(2, 1fr); }
  .resources__grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .navbar__links, .navbar__actions { display: none; }
  .navbar__hamburger { display: flex; }
  .panels__grid { grid-template-columns: repeat(2, 1fr); }
  .programs__grid { grid-template-columns: 1fr; }
  .hero__br { display: none; }
  .hero__stats { flex-wrap: wrap; gap: 1rem; }
  .footer__links-col { flex-direction: column; gap: 2rem; }
}

@media (max-width: 480px) {
  .panels__grid { grid-template-columns: 1fr; }
  .pathway__steps { grid-template-columns: 1fr; }
  .resources__grid { grid-template-columns: repeat(2, 1fr); }
}

@media (prefers-reduced-motion: reduce) {
  .hero__word, .hero__sub, .hero__cta, .hero__stats, .panel { transition: none; opacity: 1; transform: none; }
  .hero__slide--zooming { animation: none; }
}
</style>