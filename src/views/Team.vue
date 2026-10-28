<script setup>
import { computed, ref, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { state, favoriteTeam, chooseFavorite, isLive, isFinished, isUpcoming, api } from '../state'
import Badge from '../components/Badge.vue'
import MatchCard from '../components/MatchCard.vue'
import Icon from '../components/Icon.vue'
import ClubNotifications from '../components/ClubNotifications.vue'
import ClubLineup from '../components/ClubLineup.vue'
import PlayerProfile from '../components/PlayerProfile.vue'
import NextTeamMatch from '../components/NextTeamMatch.vue'
import TeamForm from '../components/TeamForm.vue'
const route = useRoute()
const team = computed(() => route.params.id ? state.teams.find(t => t.id === Number(route.params.id)) : favoriteTeam.value)
const clubDetails = ref(null), squadError = ref(''), activePlayer = ref(null), squadLoading = ref(false)
const lineupTeamId = computed(() => !route.params.id && state.source === 'highlightly' ? team.value?.id : null)
let squadVersion = 0
async function loadSquad(manual = false) {
  const requestVersion = ++squadVersion, id = lineupTeamId.value
  clubDetails.value = null; squadError.value = ''
  if (!id) { squadLoading.value = false; return }
  squadLoading.value = true
  try {
    const details = await api(`/api/clubs/${id}`, { manual })
    if (requestVersion === squadVersion) { clubDetails.value = details; squadError.value = details.squadError || '' }
  } catch (error) { if (requestVersion === squadVersion) squadError.value = error.message }
  finally { if (requestVersion === squadVersion) squadLoading.value = false }
}
watch(lineupTeamId, () => { activePlayer.value = null; loadSquad() }, { immediate: true })
onUnmounted(() => { squadVersion++ })
const showAllUpcoming = ref(false), showAllResults = ref(false)
watch(() => team.value?.id, () => { showAllUpcoming.value=false; showAllResults.value=false })
const rank = computed(() => state.standings.find(r => r.team.id === team.value?.id))
const fixtures = computed(() => state.matches.filter(m => [m.home.id, m.away.id].includes(team.value?.id)))
const upcoming = computed(() => fixtures.value.filter(m => isLive(m) || isUpcoming(m)).sort((a, b) => new Date(a.date) - new Date(b.date)))
const results = computed(() => fixtures.value.filter(isFinished).sort((a, b) => new Date(b.date) - new Date(a.date)))
const visibleUpcoming = computed(() => showAllUpcoming.value ? upcoming.value : upcoming.value.slice(0,5))
const visibleResults = computed(() => showAllResults.value ? results.value : results.value.slice(0,5))
</script>
<template><template v-if="team"><div class="page-heading team-page-heading"><span class="eyebrow">{{ state.favorite === team.id ? 'VOTRE CLUB, VOTRE PASSION' : 'LES CLUBS DU CHAMPIONNAT' }}</span><div class="team-title"><Badge :team="team" size="large" /><div><h1>{{ team.name }}<span class="heading-dot">.</span></h1><p>{{ team.stadium || 'Ligue 1' }}<span v-if="team.founded"> · Depuis {{ team.founded }}</span></p></div></div><button v-if="state.favorite !== team.id" class="button primary" @click="chooseFavorite(team.id)"><Icon name="heart" :size="17" />Suivre cette équipe</button><button v-else class="text-button" @click="state.pickerOpen = true">Changer mon équipe favorite <Icon name="arrow" :size="14" /></button></div><ClubNotifications v-if="state.favorite === team.id" :team="team" /><NextTeamMatch :matches="fixtures" /><div v-if="rank" class="team-metrics"><div class="panel"><span>CLASSEMENT</span><b>{{ rank.rank }}<small>ᵉ</small></b></div><div class="panel"><span>POINTS</span><b>{{ rank.points }}</b></div><div class="panel"><span>VICTOIRES</span><b>{{ rank.wins }}</b></div><div class="panel"><span>DIFF. DE BUTS</span><b>{{ rank.difference > 0 ? '+' : '' }}{{ rank.difference }}</b></div></div><section class="panel team-form-panel" aria-labelledby="team-form-title"><h2 id="team-form-title">La forme du moment</h2><TeamForm :team-id="team.id" :matches="fixtures" /><p class="small-text muted">Ligue 1 · résultats disponibles de la saison en cours</p></section><template v-if="lineupTeamId"><ClubLineup :key="lineupTeamId" :club-id="lineupTeamId" :squad="clubDetails?.squad || []" @player="activePlayer=$event" /><p v-if="squadLoading" class="update-note" role="status">Chargement des portraits et des liens vers les fiches joueurs…</p><div v-else-if="squadError" class="alert warning" role="status"><p>Les portraits et fiches joueurs n’ont pas pu être chargés. {{ squadError }}</p><button class="button" :disabled="state.refreshMode === 'pause'" @click="loadSquad(true)">Charger les joueurs</button></div><PlayerProfile v-if="activePlayer" :key="`${lineupTeamId}-${activePlayer.id}`" :club-id="lineupTeamId" :player="activePlayer" @close="activePlayer=null" /></template><section class="team-fixtures" aria-labelledby="team-upcoming-title"><div class="section-heading"><h2 id="team-upcoming-title">Les prochains rendez-vous</h2><span class="muted">{{ upcoming.length }} match{{ upcoming.length > 1 ? 's' : '' }}</span></div><div class="match-grid"><MatchCard v-for="match in visibleUpcoming" :key="match.id" :match="match" show-date /></div><button v-if="upcoming.length>5" class="button team-show-more" :aria-expanded="showAllUpcoming" @click="showAllUpcoming=!showAllUpcoming">{{ showAllUpcoming ? 'Réduire les prochains matchs' : `Voir les ${upcoming.length} prochains matchs` }}</button><div v-if="!upcoming.length" class="empty panel"><p>Aucun prochain match disponible pour cette équipe.</p></div></section><section class="team-fixtures" aria-labelledby="team-results-title"><div class="section-heading"><h2 id="team-results-title">Les derniers résultats</h2><span class="muted">{{ results.length }} match{{ results.length>1 ? 's' : '' }}</span></div><div class="match-grid"><MatchCard v-for="match in visibleResults" :key="match.id" :match="match" show-date /></div><button v-if="results.length>5" class="button team-show-more" :aria-expanded="showAllResults" @click="showAllResults=!showAllResults">{{ showAllResults ? 'Réduire les résultats' : `Voir les ${results.length} résultats` }}</button><div v-if="!results.length" class="empty panel"><p>Aucun résultat disponible pour cette équipe.</p></div></section></template><section v-else class="favorite-empty panel"><span class="eyebrow">LE FOOTBALL SE VIT ENSEMBLE</span><div class="favorite-art"><Icon name="heart" :size="42" /></div><h1>{{ route.params.id ? 'Équipe introuvable' : 'Et vous, c’est quel club ?' }}</h1><p>{{ route.params.id ? 'Cette équipe ne figure pas dans les données de cette saison.' : 'Choisissez votre équipe pour retrouver ses matchs, ses résultats et sa place au classement.' }}</p><button class="button primary" :disabled="!state.teams.length" @click="state.pickerOpen = true">Choisir mon équipe <Icon name="arrow" :size="16" /></button><small class="muted">Votre choix sera mémorisé sur ce navigateur.</small></section></template>
