<template>
  <section class="cta-banner">
    <div class="cta-banner__inner">
      <h2>Ready to take your next step?</h2>
      <p>Join thousands of TESDA personnel investing in their growth through TDI.</p>
      <div class="cta-banner__actions">
        <!-- If already logged in, go to dashboard instead -->
        <template v-if="auth?.user">
          <Link :href="route('dashboard')" class="btn btn--primary btn--lg">Go to My Dashboard</Link>
        </template>
        <template v-else>
          <Link :href="route('register')" class="btn btn--primary btn--lg">Create an Account</Link>
          <Link :href="route('login')"    class="btn btn--outline-white btn--lg">Sign In</Link>
        </template>
      </div>
    </div>
  </section>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
const { auth } = usePage().props
</script>

<style scoped>
.cta-banner {
  padding: 5rem 2rem;
  background: linear-gradient(135deg, #1d3fc4 0%, #0e2587 100%);
  text-align: center;
}
.cta-banner__inner { max-width: 600px; margin: 0 auto; }
.cta-banner h2 { font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800; color: #fff; margin-bottom: 0.75rem; }
.cta-banner p  { color: rgba(255,255,255,0.75); margin-bottom: 2rem; line-height: 1.6; }
.cta-banner__actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

.btn {
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer;
  transition: all 0.2s; white-space: nowrap;
}
.btn--lg { padding: 0.85rem 2rem; font-size: 1rem; }
.btn--primary { background: #f5b800; color: #1a2744; }
.btn--primary:hover { background: #e0a800; }
.btn--outline-white { border: 2px solid rgba(255,255,255,0.6); color: #fff; background: transparent; }
.btn--outline-white:hover { background: rgba(255,255,255,0.1); }
</style>