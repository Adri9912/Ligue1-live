<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { state, favoriteTeam, toggleTheme } from '../state'
import Icon from './Icon.vue'
import Badge from './Badge.vue'

defineProps({ links: { type: Array, required: true } })
const route = useRoute(), dialog = ref(null), opened = ref(false)
let previousOverflow = null
function open() {
  if (dialog.value.open) return
  previousOverflow = document.documentElement.style.overflow
  document.documentElement.style.overflow = 'hidden'
  dialog.value.showModal()
  opened.value = true
}
function close() { dialog.value?.close() }
function closed() {
  opened.value = false
  if (previousOverflow !== null) document.documentElement.style.overflow = previousOverflow
  previousOverflow = null
}
function outside(event) {
  if (event.target !== dialog.value) return
  const bounds = dialog.value.getBoundingClientRect()
  if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close()
}
function trapTab(event) {
  if (event.key !== 'Tab') return
  const controls = [...dialog.value.querySelectorAll('a[href], button:not(:disabled)')]
  const first = controls[0], last = controls.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
function chooseClub() { close(); state.pickerOpen = true }
function active(to) {
  return to === '/' ? route.path === '/' || route.path.startsWith('/matchs/') : route.path === to || route.path.startsWith(`${to}/`)
}
watch(() => route.fullPath, close)
onUnmounted(closed)
</script>
<template>
  <button class="icon-button menu-toggle" aria-label="Ouvrir le menu" aria-haspopup="dialog" aria-controls="navigation-menu" :aria-expanded="opened" @click="open"><Icon name="menu" :size="22" /></button>
  <dialog id="navigation-menu" ref="dialog" class="navigation-menu" aria-labelledby="navigation-menu-title" @close="closed" @click="outside" @keydown="trapTab">
    <div class="navigation-menu-content">
      <div class="navigation-menu-heading"><div><span class="eyebrow">LIGUE 1 LIVE</span><h2 id="navigation-menu-title">Votre terrain de jeu<span class="heading-dot">.</span></h2></div><button class="icon-button" aria-label="Fermer le menu" autofocus @click="close"><Icon name="close" /></button></div>
      <nav class="drawer-links" aria-label="Rubriques du site"><RouterLink v-for="link in links" :key="link.to" :to="link.to" :class="{ 'drawer-link-active': active(link.to) }" :aria-current="active(link.to) ? 'page' : undefined" @click="close"><Icon :name="link.icon" :size="21" /><span>{{ link.label }}</span><Icon name="arrow" :size="16" /></RouterLink></nav>
      <section class="drawer-preferences" aria-labelledby="drawer-preferences-title"><h3 id="drawer-preferences-title">Réglages</h3><button class="drawer-preference" :disabled="!state.teams.length" @click="chooseClub"><Badge v-if="favoriteTeam" :team="favoriteTeam" size="tiny" /><Icon v-else name="heart" /><span><strong>{{ favoriteTeam ? favoriteTeam.name : 'Choisir mon club' }}</strong><small>{{ favoriteTeam ? 'Changer mon équipe favorite' : 'Retrouver mon équipe facilement' }}</small></span><Icon name="arrow" :size="16" /></button><button class="drawer-preference" @click="toggleTheme"><Icon :name="state.theme === 'dark' ? 'sun' : 'moon'" /><span><strong>{{ state.theme === 'dark' ? 'Passer au thème clair' : 'Passer au thème sombre' }}</strong><small>Choix mémorisé sur ce navigateur</small></span></button></section>
      <p class="drawer-footer">Une seule compétition. Toute votre passion.</p>
    </div>
  </dialog>
</template>
