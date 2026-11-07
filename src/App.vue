<script setup>
import { onMounted, onUnmounted, computed, ref, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { state, load, syncStatus, favoriteTeam, toggleTheme, syncTheme } from './state'
import { account, syncAccount } from './account'
import Icon from './components/Icon.vue'
import TeamPicker from './components/TeamPicker.vue'
import AlertToasts from './components/AlertToasts.vue'
import { alerts } from './notifications'
import DataControls from './components/DataControls.vue'
import NavigationMenu from './components/NavigationMenu.vue'
import IntroAnimation from './components/IntroAnimation.vue'
import WelcomePrompt from './components/WelcomePrompt.vue'
import InstallInvitation from './components/InstallInvitation.vue'
const props = defineProps({ showIntro: Boolean })
const introVisible = ref(props.showIntro)
async function finishIntro() {
  introVisible.value = false
  await nextTick()
  document.getElementById('main')?.focus({ preventScroll: true })
}
const route = useRoute()
const isInformation = computed(() => route.meta.information === true || route.meta.account === true)
const isRetro = computed(() => route.path === '/retro')
const isClubs = computed(() => route.path.startsWith('/clubs'))
const links = [{ to: '/', label: 'Matchs', icon: 'matches' }, { to: '/actualites', label: 'Actualités', icon: 'news' }, { to: '/classement', label: 'Classement', icon: 'table' }, { to: '/clubs', label: 'Clubs', icon: 'ball' }, { to: '/mon-equipe', label: 'Mon équipe', icon: 'heart' }, { to: '/statistiques', label: 'Statistiques', icon: 'table' }, { to: '/retro', label: 'Rétro', icon: 'clock' }]
const primaryLinks = links.filter(link => !['/retro', '/statistiques'].includes(link.to))
const mobileLinks = primaryLinks.filter(link => link.to !== '/actualites')
const seasonLabel = computed(() => state.season ? `${state.season} / ${String(state.season + 1).slice(-2)}` : 'Ligue 1')
let interval
let lastAutomaticAttempt = 0, checking = false
async function checkUpdates() {
  syncTheme()
  if (checking) return
  checking = true
  try {
    syncAccount().catch(() => {})
    await syncStatus()
    if (state.mode === 'api' && state.refreshMode === 'auto' && state.nextRefreshAt !== null && Date.now() >= state.nextRefreshAt && Date.now() - lastAutomaticAttempt >= 600000 && (!document.hidden || (alerts.enabled && state.favorite))) {
      lastAutomaticAttempt = Date.now(); await load()
    }
  } catch { /* Une erreur locale de statut ne remplace pas les données déjà affichées. */ }
  finally { checking = false }
}
onMounted(async () => {
  syncAccount().catch(() => {})
  syncTheme()
  document.addEventListener('visibilitychange', syncTheme)
  try { await syncStatus() } catch (error) { state.error = error.message }
  lastAutomaticAttempt = Date.now(); await load()
  interval = setInterval(checkUpdates, 60000)
})
onUnmounted(() => { clearInterval(interval); document.removeEventListener('visibilitychange', syncTheme) })
</script>
<template>
  <IntroAnimation v-if="introVisible" @finish="finishIntro" />
  <div :inert="introVisible" :aria-hidden="introVisible || undefined">
  <a class="skip-link" href="#main">Aller au contenu</a>
  <header class="site-header"><div class="header-inner"><RouterLink to="/" class="brand" aria-label="Ligue 1 Live, accueil"><span class="brand-symbol"><Icon name="ball" :size="27" /></span><span>LIGUE 1 <b>LIVE<span class="brand-dot">.</span></b></span></RouterLink><nav class="desktop-nav" aria-label="Navigation principale"><RouterLink v-for="link in primaryLinks" :key="link.to" :to="link.to" :class="{ active: link.to === '/' ? route.path === '/' || route.path.startsWith('/matchs/') : (link.to === '/classement' ? route.path.startsWith('/classement') : route.path === link.to || link.to === '/clubs' && isClubs) }"><Icon :name="link.icon" :size="18" />{{ link.label }}</RouterLink></nav><div class="header-actions"><RouterLink to="/actualites" class="icon-button mobile-news-link" aria-label="Actualités" title="Actualités"><Icon name="news" :size="21" /></RouterLink><span class="season-pill">{{ isRetro ? 'LES ARCHIVES' : `SAISON ${seasonLabel}` }}</span><button v-if="favoriteTeam" class="icon-button favorite-crest-button" :aria-label="`Changer mon équipe favorite : ${favoriteTeam.name}`" :title="`Mon club : ${favoriteTeam.name} · Changer de club`" @click="state.pickerOpen = true"><img v-if="favoriteTeam.logo" :src="favoriteTeam.logo" :crossorigin="favoriteTeam.logo.startsWith('https://highlightly.net/') ? 'anonymous' : undefined" width="28" height="28" decoding="async" referrerpolicy="no-referrer" alt="" /><Icon v-else name="heart" :size="24" /></button><button class="icon-button theme-button" :aria-label="state.theme === 'dark' ? 'Activer le mode clair' : 'Activer le mode sombre'" :title="state.theme === 'dark' ? 'Passer au mode clair' : 'Passer au mode sombre'" @click="toggleTheme"><Icon :name="state.theme === 'dark' ? 'sun' : 'moon'" /></button><NavigationMenu :links="links" /></div></div></header>
  <main id="main" class="shell" tabindex="-1">
    <div v-if="state.mode === 'demo' && !isRetro && !isInformation" class="demo-notice"><span class="notice-icon"><Icon name="info" :size="16" /></span><p><strong>Mode démonstration</strong><span> · Scores, joueurs et classement fictifs. Le scénario en direct est figé.</span></p><span class="demo-tag">DÉMO</span></div>
    <DataControls v-if="!isRetro && !isClubs && !isInformation" />
    <p v-if="state.favoriteError && !state.pickerOpen" class="alert error" role="alert">{{ state.favoriteError }}</p>
    <div v-if="state.storageError" class="alert warning" role="status">{{ state.storageError }}</div>
    <div v-if="state.error && !isRetro && !isInformation" class="alert error" role="alert"><div><strong>Le chargement n’a pas abouti.</strong><p>{{ state.error }}</p></div><button class="button" :disabled="state.refreshing || state.refreshMode === 'pause'" @click="load({ manual: true })">{{ state.refreshing ? 'Chargement…' : 'Réessayer' }}</button></div>
    <div v-if="state.loading && !isRetro && !isInformation" class="loading-state initial-loading-state" role="status"><span class="loader"></span><h1>On prépare le terrain…</h1><p>Chargement des rencontres et du classement.</p></div>
    <RouterView v-else-if="state.mode || isRetro || isInformation" />
    <div v-else-if="state.error" class="empty panel"><Icon name="ball" :size="44" /><h1>Les données ne sont pas encore accessibles</h1><p>Vérifiez la clé et la saison dans le fichier .env du serveur. Aucune donnée fictive ne remplace silencieusement l’API.</p></div>
    <footer class="site-footer"><span class="league-footer-mark" role="img" aria-label="Logo de la Ligue 1"></span><RouterLink to="/" class="footer-brand">LIGUE 1 LIVE<span>·</span></RouterLink><p>Le championnat, au plus près.</p><span>{{ route.path === '/actualites' ? 'Actualités : Maxifoot · Projet indépendant' : isClubs ? 'Palmarès : LFP · Projet indépendant' : isRetro ? 'Archives : API-Football · Projet indépendant' : state.mode === 'demo' ? 'Démonstration · Projet indépendant' : `Données : ${state.source === 'highlightly' ? 'Highlightly' : 'API-Football'} · Projet indépendant` }}</span><nav class="footer-links" aria-label="Informations sur le site"><RouterLink to="/a-propos">À propos</RouterLink><span aria-hidden="true">·</span><RouterLink to="/contact">Contact</RouterLink></nav></footer>
  </main>
  <nav class="mobile-nav" aria-label="Navigation mobile"><RouterLink v-for="link in mobileLinks" :key="link.to" :to="link.to" :class="{ active: link.to === '/' ? route.path === '/' || route.path.startsWith('/matchs/') : (link.to === '/classement' ? route.path.startsWith('/classement') : route.path === link.to || link.to === '/clubs' && isClubs) }"><Icon :name="link.icon" :size="21" /><span>{{ link.label }}</span></RouterLink></nav>
  <TeamPicker /><AlertToasts />
  <WelcomePrompt v-if="!introVisible" />
  <InstallInvitation v-if="!introVisible" />
  </div>
</template>
