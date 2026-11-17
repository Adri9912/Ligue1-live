<script setup>
import { gsap } from 'gsap'
import { account } from '../account'
import { ref, computed, watch, toRefs, onMounted, onUnmounted, inject } from 'vue'
import { filterRoundMatches } from '../teamMatches.js'
import { featuredMatch } from '../featuredMatch.js'
import { state, currentRound, isLive, isFinished, isUpcoming, favoriteTeam, day, dateKey, time, statusLabel } from '../state'
import MatchCard from '../components/MatchCard.vue'
import StandingsTable from '../components/StandingsTable.vue'
import Badge from '../components/Badge.vue'
import Icon from '../components/Icon.vue'
import NewsFeed from '../components/NewsFeed.vue'
const stage = ref(null)
const introVisible = inject('introVisible', ref(false))
let entrance, entrancePlayed = false
watch([stage, introVisible], ([element, intro]) => {
  if (!element || intro || entrancePlayed) return
  entrancePlayed = true
  entrance = gsap.matchMedia()
  entrance.add('(prefers-reduced-motion: no-preference)', () => {
    gsap.from(element.querySelector('.stadium-art'), { scale: 1.035, duration: 1.2, ease: 'power2.out' })
    gsap.from(element.querySelector('.hero-copy'), { y: 14, opacity: 0, duration: .55, ease: 'power3.out' })
    gsap.from(element.querySelector('.stadium-sheen'), { xPercent: -35, opacity: 0, duration: 1.4, ease: 'power2.out' })
    gsap.from(element.querySelector('.hero-feature'), { y: 10, opacity: 0, duration: .5, delay: .1, ease: 'power3.out' })
  })
}, { flush: 'post' })
onUnmounted(() => entrance?.revert())
const { filter, round, selectedTeam, followCurrentRound } = toRefs(state.matchFilters)
watch(currentRound, value => {
  if (followCurrentRound.value) {
    if (round.value !== value) filter.value = 'all'
    round.value = value
  }
}, { immediate: true })
function selectRound() { followCurrentRound.value = Number(round.value) === currentRound.value; filter.value = 'all' }
const rounds = computed(() => [...new Set(state.matches.map(m => m.round).filter(Boolean))].sort((a, b) => a - b))
const roundMatches = computed(() => state.matches.filter(m => m.round === Number(round.value)))
const clubMatches = computed(() => filterRoundMatches(state.matches, { round: round.value, teamId: selectedTeam.value }))
const liveCount = computed(() => clubMatches.value.filter(isLive).length)
const clubOptions = computed(() => [...state.teams].sort((a, b) => a.name.localeCompare(b.name, 'fr')))
const matches = computed(() => filterRoundMatches(state.matches, { round: round.value, teamId: selectedTeam.value, status: filter.value }))
const groups = computed(() => { const grouped = new Map(); for (const m of matches.value) { const key = dateKey(m.date); if (!grouped.has(key)) grouped.set(key, []); grouped.get(key).push(m) } return [...grouped].map(([date, fixtures]) => ({ date, fixtures })) })
const nextFavorite = computed(() => [...state.matches].filter(m => [m.home.id, m.away.id].includes(state.favorite) && (isLive(m) || isUpcoming(m))).sort((a, b) => Number(isLive(b)) - Number(isLive(a)) || new Date(a.date) - new Date(b.date))[0])
const favoriteRank = computed(() => state.standings.find(s => s.team.id === state.favorite))
const now = ref(Date.now())
let clock
onMounted(() => { clock = setInterval(() => { now.value = Date.now() }, 60000) })
onUnmounted(() => clearInterval(clock))
const featured = computed(() => featuredMatch(state.matches, state.standings, state.favorite, now.value))
const heroMatch = computed(() => featured.value?.match)
const seasonStats = computed(() => {
  const rows = state.standings
  if (!rows.length || !rows.every(row => Number.isInteger(row.goalsFor) && row.goalsFor >= 0 && Number.isInteger(row.played) && row.played >= 0)) return null
  const goals = rows.reduce((sum,row) => sum + row.goalsFor, 0)
  const games = rows.reduce((sum,row) => sum + row.played, 0) / 2
  return { goals, games, average: games ? (goals / games).toLocaleString('fr-FR', { maximumFractionDigits: 2 }) : '—' }
})
const roundRange = computed(() => { if (!roundMatches.value.length) return ''; const dates = roundMatches.value.map(m => m.date).sort(); return `${day(dates[0], { month: 'short' })} — ${day(dates.at(-1), { month: 'short', year: 'numeric' })}` })
function showAllMatches() { filter.value = 'all'; selectedTeam.value = ''; round.value = currentRound.value; followCurrentRound.value = true; document.getElementById('rencontres')?.focus({ preventScroll: true }) }
function changeRound(delta) { const index = rounds.value.indexOf(Number(round.value)); if (rounds.value[index + delta]) { round.value = rounds.value[index + delta]; selectRound() } }
</script>
<template>
  <div ref="stage" class="dashboard-grid home-dashboard premium-home">
  <section class="hero home-hero premium-hero" aria-label="Ligue 1 Live, au cœur du championnat">
    <img class="stadium-art" src="/images/stadium-tunnel-v2.webp" alt="" width="1983" height="793" fetchpriority="high" decoding="async" />
    <div class="hero-copy">
      <h1>Le championnat,<span>au plus près.</span></h1>
      <p>Les affiches qui comptent, les scores qui évoluent.<br /> Votre rendez-vous avec la Ligue 1.</p>
      <div class="hero-actions"><a href="#rencontres" class="hero-action" @click="showAllMatches">Voir les matchs <Icon name="arrow" :size="16" /></a><RouterLink to="/actualites" class="hero-action secondary">Actualités</RouterLink></div>
    </div>
    <section class="hero-feature" aria-label="Match à l’affiche">
      <template v-if="heroMatch">
        <div class="featured-heading"><h2>À l’affiche</h2><span v-if="isLive(heroMatch)" class="featured-live"><i aria-hidden="true"></i>{{ state.mode === 'demo' ? 'DIRECT SIMULÉ' : 'EN DIRECT' }}</span><span v-else class="featured-live">J{{ heroMatch.round }}</span></div>
        <p class="featured-date">{{ day(heroMatch.date, { weekday: 'long', month: 'short' }) }} · {{ time(heroMatch.date) }}</p>
        <RouterLink :to="`/matchs/${heroMatch.id}`" class="featured-scoreboard" :aria-label="`Voir le match ${heroMatch.home.name} contre ${heroMatch.away.name}`">
          <span class="featured-side"><Badge :team="heroMatch.home" size="large" /><span>{{ heroMatch.home.name }}</span></span>
          <span class="featured-tally"><strong v-if="heroMatch.homeGoals !== null && heroMatch.awayGoals !== null">{{ heroMatch.homeGoals }}<span>:</span>{{ heroMatch.awayGoals }}</strong><strong v-else>VS</strong><small>{{ statusLabel(heroMatch) }}</small></span>
          <span class="featured-side"><Badge :team="heroMatch.away" size="large" /><span>{{ heroMatch.away.name }}</span></span>
        </RouterLink>
        <p v-if="featured.reason" class="featured-context">{{ featured.reason }}</p>
        <RouterLink :to="`/matchs/${heroMatch.id}`" class="featured-footer">{{ isLive(heroMatch) ? 'Suivre le match' : 'Voir la rencontre' }}<Icon name="arrow" :size="16" /></RouterLink>
      </template>
      <template v-else><div class="featured-heading"><h2>À l’affiche</h2></div><p class="featured-empty">Aucun prochain match disponible dans le calendrier reçu.</p></template>
    </section>
    <div class="stadium-sheen" aria-hidden="true"></div>
  </section>
  <NewsFeed compact class="home-news" />
  <section id="rencontres" class="match-section" tabindex="-1"><div class="section-heading"><div class="section-title"><Icon name="matches" :size="23" /><h2>Calendrier</h2></div><span class="round-current">Journée {{ String(round).padStart(2, '0') }}</span></div><div class="round-toolbar"><div class="round-selector"><button class="icon-button" aria-label="Journée précédente" :disabled="Number(round) === rounds[0]" @click="changeRound(-1)"><Icon name="back" :size="16" /></button><label><span class="sr-only">Choisir la journée</span><select v-model="round" @change="selectRound"><option v-for="r in rounds" :key="r" :value="r">Journée {{ String(r).padStart(2, '0') }}</option></select></label><button class="icon-button" aria-label="Journée suivante" :disabled="Number(round) === rounds.at(-1)" @click="changeRound(1)"><Icon name="arrow" :size="16" /></button></div><label class="club-filter"><span class="sr-only">Filtrer par club</span><select v-model="selectedTeam"><option value="">Tous les clubs</option><option v-for="club in clubOptions" :key="club.id" :value="club.id">{{ club.name }}</option></select></label></div><div class="round-period"><span>{{ roundRange }}</span><button v-if="!followCurrentRound" class="text-button" @click="showAllMatches">Revenir à la journée en cours</button></div><div class="filters"><div class="filter-tabs" role="group" aria-label="Filtrer les matchs"><button :class="{ active: filter === 'all' }" :aria-pressed="filter === 'all'" @click="filter = 'all'">Tous <span>{{ clubMatches.length }}</span></button><button :class="{ active: filter === 'live' }" :aria-pressed="filter === 'live'" @click="filter = 'live'"><span class="live-dot"></span>En cours <span>{{ liveCount }}</span></button><button :class="{ active: filter === 'upcoming' }" :aria-pressed="filter === 'upcoming'" @click="filter = 'upcoming'">À venir</button><button :class="{ active: filter === 'finished' }" :aria-pressed="filter === 'finished'" @click="filter = 'finished'">Terminés</button></div><button v-if="favoriteTeam" class="favorite-filter icon-button" :class="{ selected: Number(selectedTeam) === state.favorite }" :aria-pressed="Number(selectedTeam) === state.favorite" aria-label="Afficher uniquement mon équipe" @click="selectedTeam = Number(selectedTeam) === state.favorite ? '' : state.favorite"><Icon name="heart" :size="18" /></button></div><div v-for="group in groups" :key="group.date" class="match-day"><div class="day-heading"><h3>{{ day(group.fixtures[0].date, { weekday: 'long' }) }}</h3><span>{{ group.fixtures.length }} MATCH{{ group.fixtures.length > 1 ? 'S' : '' }}</span></div><div class="match-grid"><MatchCard v-for="match in group.fixtures" :key="match.id" :match="match" horizontal /></div></div><div v-if="!matches.length" class="empty panel"><Icon name="matches" :size="36" /><h3>Aucun match ici pour le moment</h3><p>{{ selectedTeam ? 'Cette équipe ne correspond pas à ce filtre.' : 'Essayez un autre filtre ou une autre journée.' }}</p><button class="text-button" @click="filter = 'all'; selectedTeam = ''">Afficher tous les matchs</button></div><div class="update-note"><Icon name="clock" :size="13" /><span>{{ state.mode === 'demo' ? 'Scénario fictif · aucune actualisation des scores' : `Données mises à jour à ${time(state.matchesFetchedAt ?? state.fetchedAt)} · ${day(state.matchesFetchedAt ?? state.fetchedAt, { month: 'short' })} · ${state.refreshMode === 'pause' ? 'actualisation en pause' : state.refreshMode === 'manual' ? 'actualisation manuelle' : 'vérification pendant les matchs'}` }}</span></div></section>
  <aside class="dashboard-aside"><section class="panel mini-table"><div class="aside-heading"><h3>Classement</h3><Icon name="trophy" :size="17" /></div><StandingsTable :rows="state.standings.slice(0, 5)" compact show-form /><RouterLink to="/classement" class="aside-footer-link">Tout le classement <Icon name="arrow" :size="15" /></RouterLink></section><section class="panel favorite-panel"><div class="aside-heading"><span class="eyebrow">MON ÉQUIPE</span><Icon name="heart" :size="17" /></div><template v-if="favoriteTeam"><div class="favorite-club"><Badge :team="favoriteTeam" size="large" /><div><h3>{{ favoriteTeam.name }}</h3><span>{{ favoriteRank ? `${favoriteRank.rank}${favoriteRank.rank === 1 ? 'er' : 'e'} du classement · ${favoriteRank.points} points` : 'Votre club favori' }}</span></div></div><p class="aside-subtitle">{{ nextFavorite && isLive(nextFavorite) ? 'MATCH EN COURS' : 'PROCHAIN RENDEZ-VOUS' }}</p><RouterLink v-if="nextFavorite" :to="`/matchs/${nextFavorite.id}`" class="next-match"><span>{{ nextFavorite.home.code }} <b>vs</b> {{ nextFavorite.away.code }}</span><span>{{ day(nextFavorite.date, { month: 'short' }) }} · {{ time(nextFavorite.date) }}</span></RouterLink><p v-else class="muted">Aucun prochain match disponible.</p><RouterLink to="/mon-equipe" class="button full">Suivre mon équipe <Icon name="arrow" :size="15" /></RouterLink><button class="text-button small-text" @click="state.pickerOpen = true">Changer d’équipe</button></template><template v-else><div class="favorite-art"><Icon name="heart" :size="34" /></div><h3>Un club. Votre passion.</h3><p>Gardez votre équipe favorite<br />au plus près du terrain.</p><button class="button primary full" :disabled="!state.teams.length" @click="state.pickerOpen = true">Choisir mon équipe <Icon name="arrow" :size="15" /></button><span class="small-text muted">{{ account.enabled ? 'Compte gratuit · favoris synchronisés.' : 'Sans compte, en un clic.' }}</span></template></section></aside>
  <section class="home-statistics" aria-label="Statistiques de la saison"><div class="summary-heading"><div class="section-title"><Icon name="table" :size="23" /><h2>Statistiques</h2></div><RouterLink to="/statistiques" aria-label="Voir toutes les statistiques"><Icon name="arrow" :size="16" /></RouterLink></div><div class="season-metrics"><div><Icon name="ball" :size="28" /><div><strong>{{ seasonStats?.goals ?? '—' }}</strong><span>Buts inscrits</span></div></div><div><Icon name="trophy" :size="28" /><div><strong>{{ seasonStats?.average ?? '—' }}</strong><span>Buts / match</span></div></div><div><Icon name="matches" :size="28" /><div><strong>{{ seasonStats?.games ?? '—' }}</strong><span>Matchs joués</span></div></div><div><Icon name="ball" :size="28" /><div><strong>{{ state.teams.length || '—' }}</strong><span>Clubs</span></div></div></div><p class="summary-note">{{ state.mode === 'demo' ? 'Données fictives · scénario de démonstration.' : 'Calculé à partir du classement reçu.' }}</p></section>
  </div>
</template>
