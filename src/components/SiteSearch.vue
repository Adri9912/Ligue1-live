<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { state, day, time, statusLabel } from '../state'
import { searchSite } from '../search.js'
import Badge from './Badge.vue'
import Icon from './Icon.vue'

const route = useRoute()
const router = useRouter()
const dialog = ref(null)
const field = ref(null)
const opened = ref(false)
const query = ref('')
let previousOverflow = null
const results = computed(() => searchSite(state.teams, state.matches, query.value))
const hasQuery = computed(() => query.value.trim().length >= 2)
const resultCount = computed(() => results.value.clubs.length + results.value.matches.length)

async function open() {
  if (opened.value) return
  query.value = ''
  previousOverflow = document.documentElement.style.overflow
  document.documentElement.style.overflow = 'hidden'
  dialog.value.showModal()
  opened.value = true
  await nextTick()
  field.value.focus()
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
function submit() {
  const club = results.value.clubs[0]
  const match = results.value.matches[0]
  if (club) router.push(`/clubs/${club.id}`)
  else if (match) router.push(`/matchs/${match.id}`)
  else return
  close()
}
function trapTab(event) {
  if (event.key !== 'Tab') return
  const controls = [...dialog.value.querySelectorAll('input, button, a[href]')]
  const first = controls[0], last = controls.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
watch(() => route.fullPath, close)
onBeforeUnmount(closed)
</script>

<template>
  <button class="icon-button site-search-toggle" aria-label="Rechercher un club ou un match" title="Rechercher" aria-haspopup="dialog" aria-controls="site-search" :aria-expanded="opened" @click="open"><Icon name="search" :size="21" /></button>
  <dialog id="site-search" ref="dialog" class="site-search" aria-labelledby="site-search-title" @close="closed" @click="outside" @keydown="trapTab" @keydown.esc.prevent="close">
    <div class="site-search-heading"><h2 id="site-search-title">Rechercher</h2><button class="icon-button" aria-label="Fermer la recherche" @click="close"><Icon name="close" /></button></div>
    <form role="search" @submit.prevent="submit">
      <label class="site-search-field"><Icon name="search" :size="21" /><span class="sr-only">Rechercher un club ou un match</span><input ref="field" v-model="query" type="search" placeholder="Un club ou une affiche…" autocomplete="off" enterkeyhint="search" /></label>
    </form>
    <p class="site-search-status" role="status" aria-live="polite"><template v-if="state.loading">Les clubs et les matchs sont en cours de chargement…</template><template v-else-if="!hasQuery">Saisissez au moins deux caractères, par exemple Lyon ou Lyon Marseille.</template><template v-else-if="!resultCount">Aucun club ni match trouvé pour « {{ query.trim() }} ».</template><template v-else>{{ resultCount }} résultat{{ resultCount > 1 ? 's' : '' }} affiché{{ resultCount > 1 ? 's' : '' }}.</template></p>
    <div v-if="hasQuery" class="site-search-results">
      <section v-if="results.clubs.length" aria-labelledby="site-search-clubs"><h3 id="site-search-clubs">Clubs</h3><ul><li v-for="club in results.clubs" :key="club.id"><RouterLink :to="`/clubs/${club.id}`" @click="close"><Badge :team="club" size="small" /><span><strong>{{ club.name }}</strong><small>Effectif, stade et palmarès</small></span><Icon name="arrow" :size="16" /></RouterLink></li></ul></section>
      <section v-if="results.matches.length" aria-labelledby="site-search-matches"><h3 id="site-search-matches">Matchs</h3><ul><li v-for="match in results.matches" :key="match.id"><RouterLink :to="`/matchs/${match.id}`" @click="close"><Badge :team="match.home" size="small" /><span><strong>{{ match.home.name }} – {{ match.away.name }}</strong><small>{{ day(match.date, { month: 'short', year: 'numeric' }) }} · {{ time(match.date) }}<template v-if="statusLabel(match) !== time(match.date)"> · {{ statusLabel(match) }}</template></small></span><Icon name="arrow" :size="16" /></RouterLink></li></ul></section>
    </div>
    <p v-if="state.error && !resultCount" class="site-search-unavailable">Les données sont actuellement indisponibles. Réessayez après le chargement des rencontres.</p>
  </dialog>
</template>

<style scoped>
.site-search-toggle { width:40px; height:40px; color:var(--ink); }
.site-search { width:min(580px,calc(100vw - 32px)); max-height:calc(100dvh - 48px); margin:24px auto; padding:24px; border:1px solid var(--line); border-radius:12px; background:var(--surface); color:var(--ink); overflow-y:auto; overscroll-behavior:contain; }
.site-search::backdrop { background:#07132199; }
.site-search-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; }
.site-search-heading h2 { font-size:23px; letter-spacing:-.5px; }
.site-search-heading .icon-button { width:44px; height:44px; }
.site-search-field { display:flex; align-items:center; gap:12px; border:1px solid var(--line); border-radius:8px; padding:0 14px; background:var(--soft); color:var(--muted); }
.site-search-field:focus-within { outline:2px solid var(--green-ink); outline-offset:2px; }
.site-search-field input { min-width:0; flex:1; width:100%; min-height:48px; padding:10px 0; border:0; outline:none; background:transparent; color:var(--ink); font:inherit; font-size:16px; caret-color:var(--green-ink); }
.site-search-field input::placeholder { color:var(--muted); opacity:1; }
.site-search-status,.site-search-unavailable { font-size:13px; line-height:1.6; color:var(--muted); margin:16px 0 0; overflow-wrap:anywhere; }
.site-search-results section { margin-top:24px; }
.site-search-results h3 { font-size:13px; font-weight:700; color:var(--muted); margin:0 0 8px; }
.site-search-results ul { list-style:none; padding:0; margin:0; }
.site-search-results li+li { border-top:1px solid var(--line); }
.site-search-results a { display:flex; align-items:center; gap:12px; padding:13px 8px; border-radius:6px; }
.site-search-results a:hover { background:var(--soft); }
.site-search-results a>span:not(.club-badge) { flex:1; min-width:0; }
.site-search-results strong { display:block; font-size:14px; font-weight:600; line-height:1.5; overflow-wrap:anywhere; }
.site-search-results small { display:block; font-size:12px; line-height:1.5; color:var(--muted); margin-top:3px; }
@media(max-width:600px) { .site-search { padding:18px 16px; margin:12px auto; max-height:calc(100dvh - 24px); } .site-search-heading { margin-bottom:16px; } }
</style>
