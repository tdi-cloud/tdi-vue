<template>
  <section class="fstp" id="foreign-scholarships">
    <div class="fstp__inner">

      <!-- Header -->
      <div class="fstp__top">
        <div class="fstp__header">
          <span class="eyebrow">FOREIGN SCHOLARSHIP &amp; TRAINING PROGRAM</span>
          <h2 class="fstp__title">Connecting government personnel to learning opportunities abroad.</h2>
          <p class="fstp__sub">
            The Foreign Scholarship and Training Program (FSTP) is the unit of the TESDA Development
            Institute responsible for processing international scholarships and training programs made
            available through partner sponsoring organizations, for nominees from government agencies
            across the Philippines.
          </p>

          <a
            href="https://lawphil.net/executive/execord/eo2005/pdf/eo_402_2005.pdf"
            target="_blank"
            rel="noopener"
            class="eo-chip"
          >
            <span class="eo-chip__icon">§</span>
            <span>
              Governed by <strong>Executive Order No. 402, s. 2005</strong> — Promoting the Foreign Scholarship
              and Training Program for Government Personnel
            </span>
            <span class="eo-chip__arrow">→</span>
          </a>
        </div>

        <!--
          NOTE FOR ADMIN: Replace these with actual photos of TDI/FSTP
          delegates during their training abroad once available, saved to
          public/storage/fstp/<filename>.jpg
        -->
        <div class="fstp__media" ref="mediaRef">
          <div class="snap snap--1" :class="{ 'snap--visible': snapVisible }">
            <img src="https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=500&q=80" alt="Delegates during a foreign training session" />
          </div>
          <div class="snap snap--2" :class="{ 'snap--visible': snapVisible }">
            <img src="https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=500&q=80" alt="Group photo of Filipino delegates abroad" />
          </div>
          <div class="snap snap--3" :class="{ 'snap--visible': snapVisible }">
            <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?w=500&q=80" alt="Nominees in a classroom setting abroad" />
          </div>
          <div class="snap snap--4" :class="{ 'snap--visible': snapVisible }">
            <img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=500&q=80" alt="Delegates posing at a foreign training venue" />
          </div>
          <span class="fstp__media-tag" :class="{ 'fstp__media-tag--visible': snapVisible }">FSTP nominees abroad</span>
        </div>
      </div>

      <!-- How to avail -->
      <div class="fstp__process">
        <div class="fstp__process-label">How to avail of a program</div>

        <div class="fstp__avail">
          <p class="fstp__avail-text">
            Available programs are announced by each sponsoring organization and disseminated
            through government agencies, including TESDA. Employees who wish to take part in a
            program should watch for these announcements through their respective agency and
            coordinate with their HR or training office for endorsement as a nominee.
          </p>

          <a href="mailto:fstp.unit@tesda.gov.ph" class="fstp__contact">
            <span class="fstp__contact-icon">✉</span>
            <span>
              For concerns and questions, reach the FSTP unit at
              <strong>fstp.unit@tesda.gov.ph</strong>
            </span>
          </a>
        </div>
      </div>

      <!-- Sponsoring Organizations -->
      <div class="fstp__sponsors">
        <div class="fstp__sponsors-header">
          <div>
            <div class="fstp__process-label">Currently processed by FSTP</div>
            <h3 class="fstp__sponsors-title">Our Sponsoring Organizations</h3>
          </div>
          <p class="fstp__sponsors-note">
            Each sponsor maintains its own set of programs, requirements, and reference materials.
            Nominated employees are guided through a dedicated nomination form per sponsor.
          </p>
        </div>

        <div class="fstp__sponsors-grid">
          <component
            :is="org.url ? 'a' : 'div'"
            v-for="org in sponsors"
            :key="org.name"
            v-bind="org.url ? { href: org.url, target: '_blank', rel: 'noopener' } : {}"
            class="sponsor-card"
          >
            <div class="sponsor-card__mark">
              <img
                :src="org.logo"
                :alt="`${org.name} logo`"
                class="sponsor-card__logo"
                loading="lazy"
                @error="handleLogoError"
              />
              <span class="sponsor-card__fallback sponsor-card__fallback--hidden">{{ org.initials }}</span>
            </div>
            <div class="sponsor-card__name">{{ org.name }}</div>
          </component>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const mediaRef    = ref(null)
const snapVisible = ref(false)
let observer       = null

onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        snapVisible.value = true
        observer.unobserve(entry.target)
      }
    })
  }, { threshold: 0.25 })

  if (mediaRef.value) observer.observe(mediaRef.value)
})

onBeforeUnmount(() => observer?.disconnect())

/**
 * NOTE FOR ADMIN: Save each organization's official logo to:
 *   public/storage/sponsors/<filename>.png
 * using the exact filenames listed below. Transparent PNG, roughly
 * square, works best in this grid. If a logo file is missing or
 * fails to load, the card automatically falls back to showing the
 * organization's initials so the layout never breaks.
 *
 * Pwede ring i-fetch ito dynamically mula sa /organizing-sponsors
 * endpoint kung gusto nang i-sync sa database.
 */
const sponsors = [
  {
    name: 'Japan International Cooperation Agency',
    initials: 'JICA',
    logo: '/storage/sponsors/jica.png',
    url: 'https://www.jica.go.jp/english/',
  },
  {
    name: 'Korea International Cooperation Agency',
    initials: 'KOICA',
    logo: '/storage/sponsors/koica.png',
    url: 'http://www.koica.go.kr/sites/koica_en/index.do',
  },
  {
    name: 'Malaysian Technical Cooperation Programme',
    initials: 'MTCP',
    logo: '/storage/sponsors/mtcp.png',
    url: 'https://mtcp.kln.gov.my/',
  },
  {
    name: 'Singapore Cooperation Programme',
    initials: 'SCP',
    logo: '/storage/sponsors/scp.png',
    url: 'https://scp.gov.sg/',
  },
  {
    name: 'Thailand International Cooperation Agency',
    initials: 'TICA',
    logo: '/storage/sponsors/tica.png',
    url: null,
  },
  {
    name: 'Indian Technical and Economic Cooperation',
    initials: 'ITEC',
    logo: '/storage/sponsors/itec.png',
    url: 'https://itecgoi.in/index',
  },
]

function handleLogoError(e) {
  e.target.style.display = 'none'
  e.target.nextElementSibling?.classList.remove('sponsor-card__fallback--hidden')
}
</script>

<style scoped>
.fstp {
  padding: 5.5rem 2rem;
  background: #0f1c48;
  position: relative;
  overflow: hidden;
}
.fstp::before {
  content: '';
  position: absolute;
  top: -120px; right: -120px;
  width: 420px; height: 420px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(29,63,196,0.35) 0%, rgba(29,63,196,0) 70%);
  pointer-events: none;
}

.fstp__inner { max-width: 1100px; margin: 0 auto; position: relative; }

/* Header + Media */
.fstp__top {
  display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 3.5rem;
  align-items: center; margin-bottom: 4.5rem;
}
.fstp__header { max-width: 560px; }

.fstp__media {
  position: relative;
  height: 380px;
  width: 100%;
}
.snap {
  position: absolute;
  background: #fff;
  border-radius: 6px;
  padding: 0.4rem 0.4rem 1.4rem;
  box-shadow: 0 14px 34px rgba(0,0,0,0.35);
  opacity: 0;
  transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
}
.snap img {
  display: block; width: 100%; height: 100%;
  object-fit: cover; border-radius: 2px;
}
.snap:hover { z-index: 10; box-shadow: 0 20px 44px rgba(0,0,0,0.45); }

.snap--1 {
  width: 47%; aspect-ratio: 4/5;
  top: 0; left: 4%;
  transform: rotate(-6deg) translateY(40px) scale(0.9);
  transition-delay: 0s;
}
.snap--1.snap--visible { transform: rotate(-6deg); opacity: 1; }
.snap--1.snap--visible:hover { transform: rotate(-6deg) translateY(-6px) scale(1.03); }

.snap--2 {
  width: 40%; aspect-ratio: 1/1;
  top: 6%; right: 0;
  transform: rotate(5deg) translateY(40px) scale(0.9);
  transition-delay: 0.12s;
}
.snap--2.snap--visible { transform: rotate(5deg); opacity: 1; }
.snap--2.snap--visible:hover { transform: rotate(5deg) translateY(-6px) scale(1.03); }

.snap--3 {
  width: 38%; aspect-ratio: 1/1;
  bottom: 2%; left: 0;
  transform: rotate(4deg) translateY(40px) scale(0.9);
  transition-delay: 0.24s;
}
.snap--3.snap--visible { transform: rotate(4deg); opacity: 1; }
.snap--3.snap--visible:hover { transform: rotate(4deg) translateY(-6px) scale(1.03); }

.snap--4 {
  width: 44%; aspect-ratio: 4/5;
  bottom: -6%; right: 8%;
  transform: rotate(-4deg) translateY(40px) scale(0.9);
  transition-delay: 0.36s;
}
.snap--4.snap--visible { transform: rotate(-4deg); opacity: 1; }
.snap--4.snap--visible:hover { transform: rotate(-4deg) translateY(-6px) scale(1.03); }

.fstp__media-tag {
  position: absolute;
  bottom: -2.25rem; left: 4%;
  font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
  color: rgba(255,255,255,0.4); text-transform: uppercase;
  opacity: 0; transition: opacity 0.6s ease 0.55s;
}
.fstp__media-tag--visible { opacity: 1; }
.eyebrow {
  font-size: 0.72rem; font-weight: 700; letter-spacing: 0.15em;
  color: #f5d76e; text-transform: uppercase; display: block; margin-bottom: 0.9rem;
}
.fstp__title {
  font-size: clamp(1.7rem, 3.4vw, 2.6rem);
  font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 1.1rem;
}
.fstp__sub { color: rgba(255,255,255,0.68); line-height: 1.7; margin-bottom: 1.5rem; }

.eo-chip {
  display: inline-flex; align-items: center; gap: 0.65rem;
  background: rgba(245, 184, 0, 0.1);
  border: 1px solid rgba(245, 184, 0, 0.35);
  border-radius: 12px;
  padding: 0.75rem 1.1rem;
  text-decoration: none;
  font-size: 0.82rem;
  color: rgba(255,255,255,0.85);
  line-height: 1.5;
  transition: background 0.2s, border-color 0.2s;
  max-width: 100%;
}
.eo-chip:hover { background: rgba(245, 184, 0, 0.18); border-color: #f5b800; }
.eo-chip strong { color: #f5d76e; }
.eo-chip__icon {
  flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
  background: rgba(245, 184, 0, 0.18); color: #f5d76e;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.85rem;
}
.eo-chip__arrow { flex-shrink: 0; color: #f5d76e; font-weight: 700; }

/* Process */
.fstp__process { margin-bottom: 4rem; }
.fstp__process-label {
  font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em;
  color: rgba(255,255,255,0.45); text-transform: uppercase; margin-bottom: 1.5rem;
}

.fstp__avail-text {
  font-size: 1rem; color: rgba(255,255,255,0.78); line-height: 1.8;
  max-width: 720px; border-left: 3px solid #f5b800; padding-left: 1.5rem;
}

.fstp__contact {
  display: inline-flex; align-items: center; gap: 0.6rem;
  margin-top: 1.5rem;
  text-decoration: none;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.7);
  transition: color 0.2s;
}
.fstp__contact:hover { color: #fff; }
.fstp__contact strong { color: #f5d76e; font-weight: 700; }
.fstp__contact-icon {
  flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%;
  background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.16);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; color: rgba(255,255,255,0.6);
  transition: background 0.2s, border-color 0.2s, color 0.2s;
}
.fstp__contact:hover .fstp__contact-icon {
  background: rgba(245, 184, 0, 0.15); border-color: rgba(245, 184, 0, 0.4); color: #f5d76e;
}

/* Sponsors */
.fstp__sponsors {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 22px;
  padding: 2.5rem;
}
.fstp__sponsors-header {
  display: flex; justify-content: space-between; align-items: flex-end;
  gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem;
}
.fstp__sponsors-title { font-size: 1.2rem; font-weight: 700; color: #fff; margin-top: 0.3rem; }
.fstp__sponsors-note { font-size: 0.82rem; color: rgba(255,255,255,0.55); max-width: 360px; line-height: 1.6; }

.fstp__sponsors-grid {
  display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem;
}

.sponsor-card {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  padding: 1.25rem 0.75rem;
  text-align: center;
  text-decoration: none;
  cursor: default;
  transition: transform 0.2s, background 0.2s, border-color 0.2s;
}
a.sponsor-card { cursor: pointer; }
.sponsor-card:hover {
  transform: translateY(-3px);
  background: rgba(255,255,255,0.08);
  border-color: rgba(245, 184, 0, 0.4);
}
.sponsor-card__mark {
  width: 64px; height: 64px; margin: 0 auto 0.75rem;
  border-radius: 14px;
  background: rgba(255,255,255,0.9);
  display: flex; align-items: center; justify-content: center;
  padding: 0;
  overflow: hidden;
  position: relative;
}
.sponsor-card__logo {
  width: 100%; height: 100%; object-fit: contain;
}
.sponsor-card__fallback {
  position: absolute; inset: 0;
  border-radius: 14px;
  background: linear-gradient(135deg, #1d3fc4, #0CA678);
  color: #fff; font-weight: 800; font-size: 0.62rem;
  display: flex; align-items: center; justify-content: center;
  text-align: center; padding: 0.2rem;
}
.sponsor-card__fallback--hidden { display: none; }
.sponsor-card__name { font-size: 0.7rem; color: rgba(255,255,255,0.7); line-height: 1.4; }

@media (max-width: 1024px) {
  .fstp__top { grid-template-columns: 1fr; gap: 3rem; }
  .fstp__header { max-width: none; }
  .fstp__media { max-width: 420px; margin: 0 auto; height: 320px; }
  .fstp__sponsors-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
  .fstp__media { height: 280px; }
}
@media (max-width: 640px) {
  .fstp__sponsors-grid { grid-template-columns: repeat(2, 1fr); }
  .fstp__sponsors { padding: 1.75rem; }
}

@media (prefers-reduced-motion: reduce) {
  .snap { transition: none; opacity: 1; }
  .snap--1 { transform: rotate(-6deg); }
  .snap--2 { transform: rotate(5deg); }
  .snap--3 { transform: rotate(4deg); }
  .snap--4 { transform: rotate(-4deg); }
  .fstp__media-tag { transition: none; opacity: 1; }
}
</style>