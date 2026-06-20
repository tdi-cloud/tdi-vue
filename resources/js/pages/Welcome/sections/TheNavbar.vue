<template>
  <nav class="navbar" :class="{ 'navbar--scrolled': isScrolled }">
    <div class="navbar__inner">

      <!-- Brand -->
      <a href="/" class="navbar__brand">
         <div class="w-10">
          <img class="w-full" src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/ef/TESDA_Seal.svg/1280px-TESDA_Seal.svg.png" alt="">
         </div>
        <div class="navbar__brand-text">
          <span class="brand-name">TESDA Development Institute</span>
          <span class="brand-sub">Learning &amp; Development Portal</span>
        </div>
      </a>

      <!-- Desktop Nav Links -->
      <div class="navbar__links">
        <a href="#about"         class="nav-link" @click.prevent="scrollTo('about')">About TDI</a>
        <a href="#programs"      class="nav-link" @click.prevent="scrollTo('programs')">Programs</a>
        <a href="#scholarships"  class="nav-link" @click.prevent="scrollTo('scholarships')">Scholarships</a>
        <a href="#resources"     class="nav-link" @click.prevent="scrollTo('resources')">Resources</a>
        <Link v-if="auth?.user" href="/#my-programs" class="nav-link nav-link--mine">
          <GraduationCap class="w-3.5 h-3.5" /> My Programs
        </Link>
      </div>

      <!-- Auth Buttons -->
      <div class="navbar__actions">
        <template v-if="auth?.user">
          <!-- Admin: Dashboard button -->
          <Link v-if="isAdmin" :href="route('dashboard')" class="btn btn--primary">
            <ChartPie class="w-4 mr-2"/> Dashboard
          </Link>

          <!-- Regular user: name + dropdown (Settings, Logout) -->
          <div v-else class="user-menu" ref="userMenuRef">
            <button class="user-menu__trigger" @click="userMenuOpen = !userMenuOpen">
              <span class="user-menu__avatar">{{ initials }}</span>
              <span class="user-menu__name">{{ auth.user.name }}</span>
              <ChevronsUpDown class="user-menu__chevron" :size="15" />
            </button>

            <div v-if="userMenuOpen" class="user-menu__dropdown">
              <div class="user-menu__header">
                <span class="user-menu__avatar user-menu__avatar--lg">{{ initials }}</span>
                <div class="user-menu__header-text">
                  <span class="user-menu__header-name">{{ auth.user.name }}</span>
                  <span class="user-menu__header-email">{{ auth.user.email }}</span>
                </div>
              </div>
              <div class="user-menu__divider"></div>
              <Link :href="route('profile.edit')" class="user-menu__item" @click="userMenuOpen = false">
                <Settings :size="15" /> Settings
              </Link>
              <div class="user-menu__divider"></div>
              <button type="button" class="user-menu__item user-menu__item--danger" @click="logout">
                <LogOut :size="15" /> Log out
              </button>
            </div>
          </div>
        </template>
        <template v-else>
          <Link :href="route('login')"    class="btn btn--outline">Login</Link>
          <Link :href="route('register')" class="btn btn--primary">Sign Up</Link>
        </template>
      </div>

      <!-- Hamburger -->
      <button class="navbar__hamburger" @click="mobileMenuOpen = !mobileMenuOpen" aria-label="Toggle menu">
        <span></span><span></span><span></span>
      </button>
    </div>

    <!-- Mobile Menu -->
    <div class="mobile-menu" :class="{ 'mobile-menu--open': mobileMenuOpen }">
      <Link v-if="auth?.user" href="/#my-programs" class="nav-link nav-link--mine" @click="mobileMenuOpen = false">
        <GraduationCap class="w-3.5 h-3.5" /> My Programs
      </Link>
      <a href="#about"         class="nav-link" @click="close('about')">About TDI</a>
      <a href="#programs"      class="nav-link" @click="close('programs')">Programs</a>
      <a href="#scholarships"  class="nav-link" @click="close('scholarships')">Scholarships</a>
      <a href="#resources"     class="nav-link" @click="close('resources')">Resources</a>

      <div class="mobile-menu__actions">
        <template v-if="auth?.user">
          <Link v-if="isAdmin" :href="route('dashboard')" class="btn btn--primary" @click="mobileMenuOpen = false">
            <ChartPie /> Dashboard
          </Link>
          <template v-else>
            <Link :href="route('profile.edit')" class="btn btn--outline" @click="mobileMenuOpen = false">
              <Settings class="w-4 h-4 mr-1" /> Settings
            </Link>
            <button type="button" class="btn btn--outline" @click="logout">
              <LogOut class="w-4 h-4 mr-1" /> Log out
            </button>
          </template>
        </template>
        <template v-else>
          <Link :href="route('login')"    class="btn btn--outline" @click="mobileMenuOpen = false">Login</Link>
          <Link :href="route('register')" class="btn btn--primary" @click="mobileMenuOpen = false">Sign Up</Link>
        </template>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { useScrollTo } from '@/composables/useScrollTo'
import { ChartPie, GraduationCap, ChevronsUpDown, Settings, LogOut } from 'lucide-vue-next'

const { scrollTo } = useScrollTo()
const page = usePage()
const auth = computed(() => page.props.auth)

const isScrolled     = ref(false)
const mobileMenuOpen = ref(false)
const userMenuOpen   = ref(false)
const userMenuRef    = ref(null)

// ⚠️ I-verify: 'admin' ba talaga ang eksaktong value ng `access` column
// para sa mga admin? (base sa users migration: access default 'guest')
const isAdmin = computed(() => auth.value?.user?.access === 'admin')

const initials = computed(() => {
  const name = auth.value?.user?.name || ''
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
})

function logout() {
  userMenuOpen.value = false
  mobileMenuOpen.value = false
  router.post(route('logout'))
}

function close(id) {
  mobileMenuOpen.value = false
  scrollTo(id)
}

function onScroll() {
  isScrolled.value = window.scrollY > 60
}

function onClickOutside(e) {
  if (userMenuRef.value && !userMenuRef.value.contains(e.target)) {
    userMenuOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('scroll', onScroll)
  document.addEventListener('click', onClickOutside)
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  document.removeEventListener('click', onClickOutside)
})
</script>

<style scoped>
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

/* Brand */
.navbar__brand {
  display: flex; align-items: center; gap: 0.75rem;
  text-decoration: none; flex-shrink: 0;
}
.brand-name { display: block; font-weight: 700; font-size: 0.85rem; color: #fff; line-height: 1.2; }
.brand-sub  { display: block; font-size: 0.7rem; color: rgba(255,255,255,0.6); }

/* Links */
.navbar__links { display: flex; align-items: center; gap: 1.75rem; margin-left: auto; }
.nav-link {
  color: rgba(255,255,255,0.85);
  text-decoration: none; font-size: 0.88rem; font-weight: 500;
  transition: color 0.2s;
}
.nav-link:hover { color: #fff; }

.nav-link--mine {
  display: inline-flex; align-items: center; gap: 0.35rem;
  background: rgba(245, 184, 0, 0.14);
  border: 1px solid rgba(245, 184, 0, 0.4);
  color: #f5d76e;
  padding: 0.4rem 0.85rem;
  border-radius: 30px;
  font-weight: 700;
  font-size: 0.8rem;
}
.nav-link--mine:hover {
  background: rgba(245, 184, 0, 0.24);
  color: #fff;
  border-color: #f5b800;
}

.navbar__actions { display: flex; gap: 0.75rem; align-items: center; }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 0.55rem 1.25rem; border-radius: 8px; font-size: 0.875rem;
  font-weight: 600; text-decoration: none; border: none; cursor: pointer;
  transition: all 0.2s; white-space: nowrap;
}
.btn--outline {
  border: 1.5px solid rgba(255,255,255,0.5); color: #fff; background: transparent;
}
.btn--outline:hover { background: rgba(255,255,255,0.1); border-color: #fff; }
.btn--primary { background: #1d3fc4; color: #fff; }
.btn--primary:hover { background: #1535a8; }

/* User menu (non-admin) */
.user-menu { position: relative; }
.user-menu__trigger {
  display: flex; align-items: center; gap: 0.6rem;
  background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18);
  border-radius: 10px; padding: 0.35rem 0.75rem 0.35rem 0.35rem;
  cursor: pointer; transition: background 0.2s, border-color 0.2s;
}
.user-menu__trigger:hover { background: rgba(255,255,255,0.14); border-color: rgba(255,255,255,0.35); }
.user-menu__avatar {
  width: 32px; height: 32px; border-radius: 8px;
  background: #1d3fc4; color: #fff; font-size: 0.72rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.user-menu__avatar--lg { width: 38px; height: 38px; font-size: 0.8rem; }
.user-menu__name { color: #fff; font-size: 0.85rem; font-weight: 600; max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-menu__chevron { color: rgba(255,255,255,0.55); flex-shrink: 0; }

.user-menu__dropdown {
  position: absolute; top: calc(100% + 0.6rem); right: 0;
  background: #fff; border-radius: 12px; min-width: 240px;
  box-shadow: 0 10px 30px rgba(15,28,72,0.18); overflow: hidden;
  padding: 0.4rem;
}
.user-menu__header { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0.6rem; }
.user-menu__header-text { display: flex; flex-direction: column; min-width: 0; }
.user-menu__header-name { font-size: 0.85rem; font-weight: 700; color: #1a2744; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-menu__header-email { font-size: 0.74rem; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-menu__divider { height: 1px; background: #eee; margin: 0.3rem 0; }
.user-menu__item {
  display: flex; align-items: center; gap: 0.6rem; width: 100%;
  padding: 0.55rem 0.7rem; border-radius: 8px; border: none; background: none;
  font-size: 0.85rem; font-weight: 600; color: #374151; text-decoration: none;
  cursor: pointer; text-align: left;
}
.user-menu__item:hover { background: #f3f4f6; }
.user-menu__item--danger { color: #991b1b; }
.user-menu__item--danger:hover { background: #fef2f2; }

/* Hamburger */
.navbar__hamburger {
  display: none; flex-direction: column; gap: 5px;
  background: none; border: none; cursor: pointer; padding: 4px; margin-left: auto;
}
.navbar__hamburger span { display: block; width: 24px; height: 2px; background: #fff; border-radius: 2px; }

/* Mobile menu */
.mobile-menu { display: none; flex-direction: column; gap: 1rem; padding: 1rem 2rem 1.5rem; }
.mobile-menu--open { display: flex; }
.mobile-menu .nav-link { padding: 0.5rem 0; }
.mobile-menu .nav-link--mine { align-self: flex-start; padding: 0.4rem 0.85rem; }
.mobile-menu__actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap; }

@media (max-width: 768px) {
  .navbar__links, .navbar__actions { display: none; }
  .navbar__hamburger { display: flex; }
}
</style>