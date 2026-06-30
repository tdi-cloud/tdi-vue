<template>
  <footer class="footer">
    <div class="footer__inner">

      <div class="footer__top">
        <!-- Brand -->
        <div class="footer__brand-col">
          <div class="footer__brand">
            <div class="footer__logo">TDI</div>
            <div>
              <div class="footer__name">TESDA Development Institute</div>
              <div class="footer__tagline">Learning &amp; Development Portal</div>
            </div>
          </div>
          <p class="footer__desc">
            Empowering TESDA personnel through continuous learning,
            leadership development, and workforce excellence.
          </p>
        </div>

        <!-- Quick Links -->
        <div class="footer__links-group">
          <div class="footer__col-title">Quick Links</div>
          <a href="#about"        @click.prevent="scrollTo('about')">About TDI</a>
          <a href="#programs"     @click.prevent="scrollTo('programs')">Programs</a>
          <a href="#resources"    @click.prevent="scrollTo('resources')">Resources</a>
        </div>

        <!-- Contact -->
        <div class="footer__contact-col">
          <div class="footer__col-title">Stay Connected</div>
          <p>For learning inquiries, scholarship updates, and resource access, connect with the TESDA Development Institute.</p>
          <div class="footer__col-title">(02) 8817-9095</div>
        </div>
      </div>

      <div class="footer__copy">© 2026 TESDA Development Institute</div>
    </div>
  </footer>
</template>

<script setup>
import { useScrollTo } from '@/composables/useScrollTo'
const { scrollTo } = useScrollTo()
</script>

<style scoped>
.footer { background: #0f1c48; padding: 3.5rem 2rem 1.5rem; }
.footer__inner { max-width: 1100px; margin: 0 auto; }

.footer__top {
  display: grid;
  grid-template-columns: 2fr 1fr 1.5fr;
  gap: 3rem;
  margin-bottom: 2.5rem;
}

/* Brand */
.footer__brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.footer__logo {
  width: 40px; height: 40px; background: #1d3fc4; border-radius: 8px;
  color: #fff; font-weight: 800; font-size: 0.85rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.footer__name    { font-weight: 700; color: #fff; font-size: 0.95rem; }
.footer__tagline { font-size: 0.72rem; color: rgba(255,255,255,0.5); }
.footer__desc    { color: rgba(255,255,255,0.55); font-size: 0.82rem; line-height: 1.65; }

/* Links */
.footer__col-title { font-size: 0.85rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem; }
.footer__links-group { display: flex; flex-direction: column; gap: 0.5rem; }
.footer__links-group a { font-size: 0.82rem; color: rgba(255,255,255,0.55); text-decoration: none; transition: color 0.2s; }
.footer__links-group a:hover { color: #fff; }

/* Contact */
.footer__contact-col p { font-size: 0.82rem; color: rgba(255,255,255,0.55); line-height: 1.6; }

/* Copyright */
.footer__copy {
  font-size: 0.75rem; color: rgba(255,255,255,0.3);
  padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08);
}

@media (max-width: 768px) {
  .footer__top { grid-template-columns: 1fr; gap: 2rem; }
}
</style>