<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { account } from '../account'
import { claimWelcome, localDay } from '../welcome-session.mjs'
import Icon from './Icon.vue'

const route = useRoute(), dialog = ref(null)
let pending = false, attemptedDay = ''
const eligible = () => account.ready && account.enabled === true && !account.user && route.path === '/'
function claim() {
  if (!eligible()) return false
  try { return claimWelcome(window.localStorage) } catch { return true }
}
async function propose() {
  if (!eligible()) { dialog.value?.close(); return }
  if (!dialog.value || pending || attemptedDay === localDay()) return
  pending = true
  attemptedDay = localDay()
  try {
    const show = navigator.locks ? await navigator.locks.request('l1-welcome', claim) : claim()
    if (show && eligible()) dialog.value?.showModal()
  } catch { /* Si la proposition échoue, l’accueil reste accessible. */ }
  finally { pending = false }
}
function close(focusMain = false) {
  dialog.value?.close()
  if (focusMain) document.getElementById('main')?.focus({ preventScroll: true })
}
onMounted(propose)
watch(() => [account.ready, account.enabled, account.user, route.path], propose)
onUnmounted(() => close())
</script>

<template>
  <dialog ref="dialog" class="welcome-dialog" aria-labelledby="welcome-title" aria-describedby="welcome-description" @cancel.prevent="close(true)">
    <div class="welcome-brand"><span class="brand-symbol"><Icon name="ball" :size="28" /></span><span>LIGUE 1 <b>LIVE.</b></span></div>
    <span class="eyebrow">VOTRE PLACE DANS LES TRIBUNES</span>
    <h2 id="welcome-title">Votre club,<br />au plus près du jeu.</h2>
    <p id="welcome-description">Retrouvez vos clubs favoris et recevez vos rappels avant les matchs.</p>
    <RouterLink to="/connexion" class="button primary full" autofocus @click="close()">Se connecter <Icon name="arrow" :size="18" /></RouterLink>
    <RouterLink to="/inscription" class="welcome-register" @click="close()">Créer un compte</RouterLink>
    <div class="welcome-free"><p>Les matchs, les résultats et le classement restent accessibles sans compte.</p><button class="welcome-continue" @click="close(true)">Continuer sans connexion <Icon name="arrow" :size="18" /></button></div>
  </dialog>
</template>

<style scoped>
.welcome-dialog { width:min(460px,calc(100% - 32px)); max-height:calc(100dvh - 32px); margin:auto; padding:36px; overflow-y:auto; border:1px solid var(--line); border-radius:20px; background:var(--surface); color:var(--ink); box-shadow:0 24px 100px #0005; }
.welcome-dialog::backdrop { background:#07111ed9; backdrop-filter:blur(5px); }
.welcome-brand { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:32px; font-size:20px; font-weight:700; letter-spacing:-.5px; }
.welcome-brand b { color:var(--green-ink); }
.eyebrow { text-align:center; font-size:11px; letter-spacing:1.3px; margin-bottom:12px; }
h2 { text-align:center; font-size:34px; line-height:1.15; letter-spacing:-1px; }
#welcome-description { margin:18px 0 26px; text-align:center; color:var(--muted); font-size:15px; }
.button { min-height:48px; font-size:15px; }
.welcome-register { display:flex; align-items:center; justify-content:center; min-height:48px; margin-top:4px; color:var(--green-ink); font-size:15px; font-weight:650; text-decoration:underline; text-underline-offset:4px; }
.welcome-free { margin-top:24px; padding-top:20px; border-top:1px solid var(--line); }
.welcome-free p { color:var(--muted); font-size:14px; }
.welcome-continue { display:flex; align-items:center; gap:10px; min-height:48px; margin-top:8px; padding:0; border:0; background:none; color:var(--ink); font-size:15px; font-weight:650; text-align:left; text-decoration:underline; text-underline-offset:4px; }
@media(max-width:480px),(max-height:680px) { .welcome-dialog { padding:24px; } .welcome-brand { margin-bottom:22px; font-size:18px; } h2 { font-size:30px; } #welcome-description { margin:14px 0 20px; } .welcome-free { margin-top:16px; padding-top:16px; } }
@media(max-height:620px) { .welcome-dialog { padding:20px; } .welcome-brand { margin-bottom:16px; } .brand-symbol { width:34px; height:34px; } .eyebrow { margin-bottom:8px; font-size:10px; } h2 { font-size:27px; } #welcome-description { margin:12px 0 16px; font-size:14px; } .welcome-free { margin-top:12px; padding-top:12px; } .welcome-continue { margin-top:4px; } }
</style>
