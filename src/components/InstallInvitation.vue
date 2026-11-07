<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { install } from '../install'

const route = useRoute()
const offered = ref(false)
const visible = computed(() => offered.value && !install.state.installed && (install.state.available || install.state.pending) && !route.meta.account && !route.meta.information)
let timer, checking = false
const readyAt = Date.now() + 20000
function canOffer() {
  return Date.now() >= readyAt && !document.hidden && !document.querySelector('dialog[open]') && !route.meta.account && !route.meta.information && install.state.available && !install.state.installed
}
async function propose() {
  if (checking || !install.state.offerEligible || !canOffer()) return
  checking = true
  try {
    const claim = () => canOffer() && install.claimOffer()
    const claimed = navigator.locks ? await navigator.locks.request('l1-install-offer', claim) : claim()
    if (claimed) { offered.value = true; clearInterval(timer) }
  } catch { /* L’invitation ne doit jamais bloquer la navigation. */ }
  finally { checking = false }
}
function dismiss() {
  install.postpone()
  offered.value = false
  document.getElementById('main')?.focus({ preventScroll: true })
}
async function add() {
  await install.prompt()
  offered.value = false
  document.getElementById('main')?.focus({ preventScroll: true })
}
watch(() => [install.state.offerEligible, install.state.available, install.state.installed], () => {
  clearInterval(timer)
  if (install.state.offerEligible && install.state.available && !install.state.installed) timer = setInterval(propose, 2000)
}, { immediate: true })
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <section v-if="visible" class="install-invitation" aria-labelledby="install-invitation-title">
    <div class="install-invitation-copy"><img src="/icons/icon-192.png" width="44" height="44" alt="" /><h2 id="install-invitation-title">Gardez Ligue 1 Live sur votre écran d’accueil</h2></div>
    <div class="install-invitation-actions"><button class="button" :disabled="install.state.pending" @click="dismiss">Plus tard</button><button class="button primary" :disabled="install.state.pending" @click="add">{{ install.state.pending ? 'Installation…' : 'Ajouter' }}</button></div>
  </section>
</template>

<style scoped>
.install-invitation { position:fixed; right:20px; bottom:24px; z-index:30; width:min(380px,calc(100% - 32px)); max-height:calc(100dvh - 120px); overflow-y:auto; padding:20px; border:1px solid var(--line); border-radius:14px; background:var(--surface); color:var(--ink); box-shadow:0 8px 32px #0002; }
.install-invitation-copy { display:flex; align-items:center; gap:12px; }
.install-invitation-copy img { flex-shrink:0; border-radius:10px; }
.install-invitation h2 { font-size:16px; line-height:1.45; letter-spacing:0; }
.install-invitation-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
.install-invitation-actions .button { min-height:44px; font-size:14px; touch-action:manipulation; }
@media(max-width:600px) { .install-invitation { right:16px; bottom:calc(88px + env(safe-area-inset-bottom,0px)); padding:16px; } .install-invitation-actions .button { flex:1; } }
</style>
