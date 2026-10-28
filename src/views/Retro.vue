<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, state, day, time } from '../state'
import StandingsTable from '../components/StandingsTable.vue'
import MatchCard from '../components/MatchCard.vue'
import Badge from '../components/Badge.vue'
import Icon from '../components/Icon.vue'
import DataFreshness from '../components/DataFreshness.vue'
const route = useRoute(), router = useRouter()
const seasons = ref([]), year = ref(Number(route.query.saison) || 2024), tab = ref('standings'), round = ref(1)
const result = ref(null), budget = ref(null), loading = ref(true), error = ref(''), available = ref(true)
const cache = new Map()
const tabs = [{id:'standings',label:'Classement',icon:'table'},{id:'matches',label:'Résultats',icon:'matches'},{id:'scorers',label:'Buteurs',icon:'ball'},{id:'assists',label:'Passeurs',icon:'assist'}]
const rounds = computed(() => [...new Set((result.value?.matches || []).map(m=>m.round).filter(Boolean))].sort((a,b)=>a-b))
const matches = computed(() => (result.value?.matches || []).filter(m=>m.round === Number(round.value)).sort((a,b)=>Date.parse(a.date)-Date.parse(b.date)))
const champion = computed(() => result.value?.standings?.find(r=>r.rank===1))
let version = 0, ready = false
async function load(manual = false) {
  const requestVersion = ++version, season = year.value, section = tab.value, key = `${season}/${section}`
  error.value = ''; result.value = null
  if (cache.has(key)) { result.value = cache.get(key); if (result.value.matches && !rounds.value.includes(round.value)) round.value = rounds.value[0] || 1; loading.value = false; return }
  loading.value = true
  try {
    const data = await api(`/api/retro/${season}/${section}`, {manual})
    if (requestVersion !== version) return
    if (data.archiveSeason !== season) throw new Error('La saison reçue ne correspond pas à votre sélection.')
    result.value = data; budget.value = data.retroBudget; cache.set(key,data)
    if (data.matches) round.value = rounds.value[0] || 1
  } catch(e) { if (requestVersion === version) error.value=e.message }
  finally { if(requestVersion===version) loading.value=false }
}
onMounted(async()=>{
  try {
    const meta = await api('/api/retro'); seasons.value=meta.seasons; available.value=meta.available; budget.value=meta.retroBudget
    if (!meta.seasons.includes(year.value)) year.value=meta.seasons[0]
    ready=true
    if(meta.available) await load(); else loading.value=false
  } catch(e) { error.value=e.message; loading.value=false }
})
watch([year,tab],()=>{ if(!ready)return; router.replace({path:'/retro',query:{saison:year.value}}); load() })
watch(() => route.query.saison, value => { const parsed=Number(value) || 2024; if(seasons.value.includes(parsed) && parsed!==year.value) year.value=parsed })
onUnmounted(()=>version++)
</script>
<template>
  <div class="page-heading"><span class="eyebrow">LES SAISONS PASSENT. LES SOUVENIRS RESTENT.</span><h1>Rétro<span class="heading-dot">.</span></h1><p>Retrouvez les classements, les résultats et les joueurs qui ont marqué la Ligue 1.</p></div>
  <section class="panel retro-toolbar"><label class="retro-select"><Icon name="clock" :size="20" /><span>Saison<select v-model.number="year" :disabled="!seasons.length"><option v-for="s in seasons" :key="s" :value="s">{{ s }}–{{ s+1 }}</option></select></span></label><span class="data-pill">ARCHIVES · API-FOOTBALL</span><div class="retro-controls"><span>{{ ({auto:'Actualisation automatique',manual:'Mode manuel',pause:'Appels en pause'})[state.refreshMode] }}</span><button class="button" :disabled="loading || !available || state.refreshMode==='pause'" @click="load(true)">Charger les archives</button></div></section>
  <p class="update-note"><Icon name="info" :size="14" />Saisons proposées avec l’offre gratuite. Les archives sont conservées 30 jours pour économiser les appels. Les modes Manuel et Pause choisis dans les réglages s’appliquent aussi ici.</p>
  <nav class="standings-subnav retro-tabs" aria-label="Rubriques rétro"><button v-for="item in tabs" :key="item.id" :class="{active:tab===item.id}" :aria-pressed="tab===item.id" @click="tab=item.id"><Icon :name="item.icon" :size="16" />{{ item.label }}</button></nav>
  <div v-if="!available" class="empty panel"><Icon name="clock" :size="36" /><h2>Les archives attendent leur clé</h2><p>La clé API-Football est nécessaire pour consulter les anciennes saisons.</p></div>
  <div v-else-if="loading" class="loading-state" role="status"><span class="loader"></span><h2>On remonte le temps…</h2><p>Chargement de la saison {{ year }}–{{ year+1 }}.</p></div>
  <div v-else-if="error" class="alert error" role="alert"><p>{{ error }}</p><button class="button" :disabled="state.refreshMode==='pause'" @click="load(true)">Réessayer</button></div>
  <template v-else-if="result">
    <DataFreshness :fetched-at="result.fetchedAt" :stale="result.stale" label="Archives de cette saison" />
    <template v-if="tab==='standings'"><section v-if="champion" class="retro-champion"><Icon name="trophy" :size="30" /><Badge :team="champion.team" size="large" /><div><span class="eyebrow">CHAMPION DE FRANCE {{ year }}–{{ year+1 }}</span><h2>{{ champion.team.name }}</h2><p>{{ champion.points }} points · {{ champion.wins }} victoires</p></div></section><section class="panel"><div class="panel-title"><h2>Le classement final</h2><span class="data-pill">{{ result.standings.length }} CLUBS</span></div><StandingsTable :rows="result.standings" archive /></section></template>
    <section v-else-if="tab==='matches'"><div class="round-toolbar"><div class="round-selector"><button class="icon-button" aria-label="Journée précédente" :disabled="round===rounds[0]" @click="round=rounds[rounds.indexOf(round)-1]"><Icon name="back" :size="16" /></button><label><span class="sr-only">Journée des archives</span><select v-model.number="round"><option v-for="r in rounds" :key="r" :value="r">Journée {{ r }}</option></select></label><button class="icon-button" aria-label="Journée suivante" :disabled="round===rounds.at(-1)" @click="round=rounds[rounds.indexOf(round)+1]"><Icon name="arrow" :size="16" /></button></div><span class="round-date">{{ matches.length }} rencontres</span></div><div class="match-grid"><MatchCard v-for="match in matches" :key="match.id" :match="match" show-date archive /></div><p v-if="!matches.length" class="empty panel">Aucun résultat disponible pour cette journée.</p></section>
    <section v-else class="panel players-panel"><div class="panel-title"><h2>{{ tab==='scorers' ? 'Meilleurs buteurs' : 'Meilleurs passeurs' }}</h2><span class="data-pill">SAISON {{ year }}–{{ year+1 }}</span></div><div v-if="result.players.length" class="table-scroll"><table class="players-table"><caption class="sr-only">{{ tab==='scorers' ? 'Buteurs' : 'Passeurs' }} de Ligue 1 {{ year }}–{{ year+1 }}</caption><thead><tr><th>#</th><th>Joueur</th><th>Club</th><th>{{ tab==='scorers' ? 'Buts' : 'Passes' }}</th></tr></thead><tbody><tr v-for="(player,i) in result.players" :key="player.id"><td class="player-rank">{{ i+1 }}</td><td><strong>{{ player.name }}</strong></td><td><span class="table-team"><Badge :team="player.team" size="small" /><span>{{ player.team.name }}</span></span></td><td class="player-value">{{ tab==='scorers' ? player.goals : player.assists }}</td></tr></tbody></table></div><p v-else class="empty">Ce classement n’est pas disponible pour cette saison.</p></section>
    <p class="update-note">Archives réelles · {{ year }}–{{ year+1 }} · reçues le {{ day(result.fetchedAt,{year:'numeric'}) }} à {{ time(result.fetchedAt) }}. Les fiches des clubs et matchs actuels restent séparées.</p>
  </template>
  <details v-if="budget" class="technical-details"><summary>Détails des appels aux archives</summary><p class="update-note">Budget API-Football partagé avec les fiches Clubs : {{ budget.used }} / {{ budget.limit }} appels aujourd’hui<span v-if="budget.providerRemaining!==null"> · quota fournisseur restant : {{ budget.providerRemaining }}</span>. Le budget de la saison actuelle chez Highlightly est distinct.</p></details>
</template>
