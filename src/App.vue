<script setup>
import { onMounted, onUnmounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { state, load, toggleTheme, favoriteTeam, time, syncStatus } from './state'
import Icon from './components/Icon.vue'
import Badge from './components/Badge.vue'
import TeamPicker from './components/TeamPicker.vue'
import AlertToasts from './components/AlertToasts.vue'
import { alerts } from './notifications'
import DataControls from './components/DataControls.vue'
const route = useRoute()
const isRetro = computed(() => route.path === '/retro')
const isClubs = computed(() => route.path.startsWith('/clubs'))
const links = [{ to: '/', label: 'Matchs', icon: 'matches' }, { to: '/classement', label: 'Classement', icon: 'table' }, { to: '/clubs', label: 'Clubs', icon: 'ball' }, { to: '/mon-equipe', label: 'Mon équipe', icon: 'heart' }, { to: '/retro', label: 'Rétro', icon: 'clock' }]
const seasonLabel = computed(() => state.season ? `${state.season} / ${String(state.season + 1).slice(-2)}` : 'Ligue 1')
let interval
let lastAutomaticAttempt = 0, checking = false
async function checkUpdates() {
  if (checking) return
  checking = true
  try {
    await syncStatus()
    if (state.mode === 'api' && state.refreshMode === 'auto' && state.nextRefreshAt !== null && Date.now() >= state.nextRefreshAt && Date.now() - lastAutomaticAttempt >= 600000 && (!document.hidden || (alerts.enabled && state.favorite))) {
      lastAutomaticAttempt = Date.now(); await load()
    }
  } catch { /* Une erreur locale de statut ne remplace pas les données déjà affichées. */ }
  finally { checking = false }
}
onMounted(async () => {
  try { await syncStatus() } catch (error) { state.error = error.message }
  lastAutomaticAttempt = Date.now(); await load()
  interval = setInterval(checkUpdates, 60000)
})
onUnmounted(() => clearInterval(interval))
</script>
<template>
  <a class="skip-link" href="#main">Aller au contenu</a>
  <header class="site-header"><div class="header-inner"><RouterLink to="/" class="brand" aria-label="Ligue 1 Live, accueil"><span class="brand-symbol"><Icon name="ball" :size="27" /></span><span>LIGUE 1 <b>LIVE<span class="brand-dot">.</span></b></span></RouterLink><nav class="desktop-nav" aria-label="Navigation principale"><RouterLink v-for="link in links" :key="link.to" :to="link.to" :class="{ active: link.to === '/' ? route.path === '/' || route.path.startsWith('/matchs/') : (link.to === '/classement' ? route.path.startsWith('/classement') : route.path === link.to || link.to === '/clubs' && isClubs) }"><Icon :name="link.icon" :size="18" />{{ link.label }}</RouterLink></nav><div class="header-actions"><span class="season-pill">{{ isRetro ? 'LES ARCHIVES' : `SAISON ${seasonLabel}` }}</span><button class="icon-button theme-button" :aria-label="state.theme === 'dark' ? 'Activer le mode clair' : 'Activer le mode sombre'" @click="toggleTheme"><Icon :name="state.theme === 'dark' ? 'sun' : 'moon'" /></button><button class="favorite-button" :aria-label="favoriteTeam ? `Changer mon équipe favorite : ${favoriteTeam.name}` : 'Choisir mon équipe favorite'" :disabled="!state.teams.length" @click="state.pickerOpen = true"><Badge v-if="favoriteTeam" :team="favoriteTeam" size="tiny" /><Icon v-else name="heart" :size="17" /><span>{{ favoriteTeam ? favoriteTeam.code : 'Choisir mon club' }}</span></button><span class="league-header-mark" role="img" aria-label="Logo de la Ligue 1"></span></div></div></header>
  <main id="main" class="shell" tabindex="-1">
    <div v-if="state.mode === 'demo' && !isRetro" class="demo-notice"><span class="notice-icon"><Icon name="info" :size="16" /></span><p><strong>Mode démonstration</strong><span> · Scores, joueurs et classement fictifs. Le scénario en direct est figé.</span></p><span class="demo-tag">DÉMO</span></div>
    <DataControls v-if="!isRetro && !isClubs" />
    <div v-if="state.storageError" class="alert warning" role="status">{{ state.storageError }}</div>
    <div v-if="state.error && !isRetro" class="alert error" role="alert"><div><strong>Le chargement n’a pas abouti.</strong><p>{{ state.error }}</p></div><button class="button" :disabled="state.refreshing || state.refreshMode === 'pause'" @click="load({ manual: true })">{{ state.refreshing ? 'Chargement…' : 'Réessayer' }}</button></div>
    <div v-if="state.loading && !isRetro" class="loading-state" role="status"><span class="loader"></span><h1>On prépare le terrain…</h1><p>Chargement des rencontres et du classement.</p></div>
    <RouterView v-else-if="state.mode || isRetro" />
    <div v-else-if="state.error" class="empty panel"><Icon name="ball" :size="44" /><h1>Les données ne sont pas encore accessibles</h1><p>Vérifiez la clé et la saison dans le fichier .env du serveur. Aucune donnée fictive ne remplace silencieusement l’API.</p></div>
    <footer class="site-footer"><span class="league-footer-mark" role="img" aria-label="Logo de la Ligue 1"></span><RouterLink to="/" class="footer-brand">LIGUE 1 LIVE<span>·</span></RouterLink><p>Le championnat, au plus près.</p><span>{{ isClubs ? 'Fiches : API-Football · Palmarès : LFP' : isRetro ? 'Archives : API-Football · Projet indépendant' : state.mode === 'demo' ? 'Démonstration · Projet indépendant' : `Données : ${state.source === 'highlightly' ? 'Highlightly' : 'API-Football'} · Projet indépendant` }}</span></footer>
  </main>
  <nav class="mobile-nav" aria-label="Navigation mobile"><RouterLink v-for="link in links" :key="link.to" :to="link.to" :class="{ active: link.to === '/' ? route.path === '/' || route.path.startsWith('/matchs/') : (link.to === '/classement' ? route.path.startsWith('/classement') : route.path === link.to || link.to === '/clubs' && isClubs) }"><Icon :name="link.icon" :size="21" /><span>{{ link.label }}</span></RouterLink></nav>
  <TeamPicker /><AlertToasts />
</template>
