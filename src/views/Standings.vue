<script setup>
import { computed, ref, reactive, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { state, day, time, isLive, api } from '../state'
import { provisionalStandings } from '../football'
import StandingsTable from '../components/StandingsTable.vue'
import Badge from '../components/Badge.vue'
import Icon from '../components/Icon.vue'
const route = useRoute(), projection = ref(true)
const kind = computed(() => route.params.kind || 'equipes')
const isPlayers = computed(() => kind.value !== 'equipes')
const scorerTab = computed(() => kind.value === 'buteurs')
const liveMatches = computed(() => state.matches.filter(isLive))
const projected = computed(() => projection.value && liveMatches.value.length > 0)
const rows = computed(() => projected.value ? provisionalStandings(state.standings, state.matches) : state.standings)
const playerCache = reactive({ buteurs: null, passeurs: null })
const players = computed(() => playerCache[kind.value]?.players || [])
const playerMeta = computed(() => playerCache[kind.value])
const loading = ref(false), error = ref('')
let version = 0
async function loadPlayers(force = false, manual = false) {
  const tab = kind.value, requestVersion = ++version
  error.value = ''
  if (tab === 'equipes') { loading.value = false; return }
  if (playerCache[tab] && !force) { loading.value = false; return }
  loading.value = true
  try {
    const data = await api(`/api/players/${tab === 'buteurs' ? 'scorers' : 'assists'}`, { manual })
    if (data.mode !== state.mode || data.season !== state.season) throw new Error('La source a changé. Actualisez la page pour afficher des données cohérentes.')
    playerCache[tab] = data
  } catch (e) { if (requestVersion === version) error.value = e.message }
  finally { if (requestVersion === version) loading.value = false }
}
watch(kind, () => loadPlayers(), { immediate: true })
watch(() => state.refreshSerial, () => loadPlayers(true, state.lastRefreshWasManual))
onUnmounted(() => version++)
const zones = [
  ['champions', '1–3', 'Ligue des champions'], ['champions-qualifier', '4', 'Qualifications LDC'],
  ['europa', '5', 'Ligue Europa'], ['conference', '6', 'Barrages de Ligue Conference'],
  ['playoff', '16', 'Barrage de maintien'], ['relegation', '17–18', 'Relégation en Ligue 2'],
]
</script>
<template>
  <div class="page-heading"><span class="eyebrow">CHAQUE POINT COMPTE</span><h1>Le classement<span class="heading-dot">.</span></h1><p>Les équipes, les buteurs et les passeurs qui font le championnat.</p></div>
  <nav class="standings-subnav" aria-label="Classements de Ligue 1"><RouterLink to="/classement" :class="{ active: !isPlayers }"><Icon name="table" :size="16" />Équipes</RouterLink><RouterLink to="/classement/buteurs" :class="{ active: kind === 'buteurs' }"><Icon name="ball" :size="16" />Buteurs</RouterLink><RouterLink to="/classement/passeurs" :class="{ active: kind === 'passeurs' }"><Icon name="assist" :size="16" />Passeurs</RouterLink></nav>
  <template v-if="!isPlayers">
    <section class="panel"><div class="panel-title"><div class="flex items-center gap-3"><span class="league-mark dark-mark">L1</span><div><h2>Ligue 1</h2><p class="muted small-text">Saison {{ state.season }}–{{ state.season + 1 }} · {{ rows.length }} équipes</p></div></div><span v-if="projected" class="ranking-live"><span class="live-dot"></span>{{ state.mode === 'demo' ? 'DIRECT SIMULÉ' : 'PROVISOIRE' }}</span><span v-else class="data-pill">{{ state.mode === 'demo' ? 'CLASSEMENT FICTIF' : 'CLASSEMENT PUBLIÉ' }}</span></div>
      <div v-if="liveMatches.length" class="projection-toolbar"><div><strong>{{ liveMatches.length }} match{{ liveMatches.length > 1 ? 's' : '' }} en cours</strong><p>Projection selon les derniers scores reçus.</p></div><div class="segmented" role="group" aria-label="Type de classement"><button :class="{ active: projection }" :aria-pressed="projection" @click="projection = true">En direct</button><button :class="{ active: !projection }" :aria-pressed="!projection" @click="projection = false">{{ state.mode === 'demo' ? 'Hors direct' : 'Publié' }}</button></div></div>
      <p class="table-hint">Faites glisser le tableau pour voir toutes les statistiques.</p><StandingsTable :rows="rows" :provisional="projected" /><div v-if="!rows.length" class="empty"><p>Le classement de cette saison n’est pas encore disponible.</p></div>
    </section>
    <div class="ranking-legend" aria-label="Légende des zones du classement"><span v-for="[zone, position, label] in zones" :key="zone"><i :class="zone" aria-hidden="true"></i><b>{{ position }}</b>{{ label }}</span></div>
    <p class="ranking-disclaimer">Repères indicatifs pour une Ligue 1 à 18 clubs. Les places européennes dépendent notamment de la Coupe de France et des règles UEFA. <a href="https://ligue1.com/fr/articles/l1_article_2443-course-a-l-europe-le-suspense-est-total" target="_blank" rel="noopener noreferrer">Comprendre les places européennes ↗</a></p>
    <p v-if="projected" class="projection-note"><Icon name="info" :size="15" />Simulation si les scores actuels devenaient définitifs, calculée à partir des matchs reçus. Elle ne tient pas compte des éventuelles sanctions administratives ou des départages avancés. Le classement publié reste la référence.</p>
    <div class="table-explainer"><span><b>MJ</b> Matchs joués</span><span><b>V / N / D</b> Victoires / Nuls / Défaites</span><span><b>BP / BC</b> Buts pour / contre</span><span><b>Diff.</b> Différence de buts</span><span v-if="projected"><b>↗ / ↘</b> Évolution par rapport au classement publié</span></div>
    <p class="update-note"><Icon name="info" :size="14" />{{ state.mode === 'demo' ? 'Ce classement illustre l’interface et ne représente pas la saison réelle.' : `Dernières données reçues : ${day(state.fetchedAt)} à ${time(state.fetchedAt)}. Actualisation selon le mode choisi et le quota disponible.` }}</p>
  </template>
  <section v-else class="panel players-panel"><div class="panel-title"><div class="flex items-center gap-3"><span class="player-heading-icon"><Icon :name="scorerTab ? 'ball' : 'assist'" :size="22" /></span><div><h2>{{ scorerTab ? 'Meilleurs buteurs' : 'Meilleurs passeurs' }}</h2><p class="muted small-text">Ligue 1 · Saison {{ state.season }}–{{ state.season + 1 }}</p></div></div><span class="data-pill">{{ playerMeta?.unavailable ? 'À VENIR' : state.mode === 'demo' ? 'JOUEURS FICTIFS' : 'SAISON EN COURS' }}</span></div>
    <div v-if="loading" class="loading-state" role="status"><span class="loader"></span><p>Chargement des joueurs…</p></div>
    <div v-else-if="error" class="alert error" role="alert"><p>{{ error }}</p><button class="button" @click="loadPlayers(true, true)" :disabled="state.refreshMode === 'pause'">Réessayer</button></div>
    <template v-else><p v-if="playerMeta?.stale" class="alert warning">Données en cache, mise à jour le {{ day(playerMeta.fetchedAt) }} à {{ time(playerMeta.fetchedAt) }}.</p><div class="table-scroll"><table v-if="players.length" class="players-table"><caption class="sr-only">{{ scorerTab ? 'Meilleurs buteurs' : 'Meilleurs passeurs' }}{{ state.mode === 'demo' ? ' — données fictives' : '' }}</caption><thead><tr><th scope="col">#</th><th scope="col">Joueur</th><th scope="col">Club</th><th scope="col">{{ scorerTab ? 'Buts' : 'Passes' }}</th></tr></thead><tbody><tr v-for="(player, i) in players" :key="player.id"><td class="player-rank"><span :class="{ podium: i < 3 }">{{ i + 1 }}</span></td><td><div class="player-name"><span class="player-avatar">{{ player.name.split(' ').map(n => n[0]).slice(0, 2).join('') }}</span><span><strong>{{ player.name }}</strong><RouterLink :to="`/equipes/${player.team.id}`" class="mobile-player-team">{{ player.team.name }}</RouterLink></span></div></td><td><RouterLink :to="`/equipes/${player.team.id}`" class="table-team"><Badge :team="player.team" size="small" /><span>{{ player.team.name }}</span></RouterLink></td><td class="player-value">{{ scorerTab ? player.goals : player.assists }}</td></tr></tbody></table><div v-else class="empty"><Icon name="info" :size="32" /><h3>{{ playerMeta?.unavailable ? 'À venir' : 'Classement des joueurs indisponible' }}</h3><p>{{ playerMeta?.unavailable ? `Le classement des meilleurs ${scorerTab ? 'buteurs' : 'passeurs'} de Ligue 1 est en préparation.` : 'Le fournisseur ne transmet pas encore ces données pour cette saison ou cette offre.' }}</p></div></div><p v-if="!playerMeta?.unavailable" class="players-footnote">{{ state.mode === 'demo' ? 'Tous les joueurs et leurs statistiques sont fictifs.' : (state.source === 'highlightly' ? 'Données de saison : Highlightly. Classements joueurs indisponibles.' : 'Données fournies par API-Football, conservées en cache jusqu’au lendemain pour économiser les appels.') }}</p></template>
  </section>
</template>
