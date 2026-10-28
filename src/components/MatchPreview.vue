<script setup>
import { computed } from 'vue'
import { state, day } from '../state'
import { previousMeetings } from '../teamMatches'
import TeamForm from './TeamForm.vue'
import Badge from './Badge.vue'
const props = defineProps({ fixture: {type:Object,required:true} })
const before = computed(() => Math.min(Date.now(),Date.parse(props.fixture.date)))
const meetings = computed(() => previousMeetings(state.matches,props.fixture))
function rank(id) { return state.standings.find(row=>row.team.id===id) }
</script>
<template>
 <section class="panel match-preview" aria-labelledby="match-preview-title">
  <div class="panel-title"><div><span class="eyebrow">AVANT LE COUP D’ENVOI</span><h2 id="match-preview-title">Les repères du match</h2></div><span class="data-pill">LIGUE 1</span></div>
  <div class="preview-teams"><div v-for="team in [fixture.home,fixture.away]" :key="team.id" class="preview-team"><RouterLink :to="`/equipes/${team.id}`" class="preview-team-name"><Badge :team="team" size="small" /><h3>{{ team.name }}</h3></RouterLink><p v-if="rank(team.id)" class="preview-rank">{{ rank(team.id).rank }}{{ rank(team.id).rank===1?'er':'e' }} au classement actuel · {{ rank(team.id).points }} points</p><p v-else class="preview-rank">Classement indisponible</p><h4>La forme récente</h4><TeamForm :team-id="team.id" :matches="state.matches" :before="before" /></div></div>
  <div class="preview-meetings"><h3>Les confrontations disponibles</h3><p class="muted small-text">Cette saison de Ligue 1 uniquement.</p><RouterLink v-for="r in meetings" :key="r.match.id" :to="`/matchs/${r.match.id}`" class="preview-meeting"><span>{{ day(r.match.date,{month:'short',year:'numeric'}) }}</span><span>{{ r.match.home.name }} <strong>{{ r.match.homeGoals }}–{{ r.match.awayGoals }}</strong> {{ r.match.away.name }}</span></RouterLink><p v-if="!meetings.length" class="preview-no-meetings">Aucune confrontation terminée disponible cette saison.</p></div>
  <p class="preview-note">Repères calculés à partir des matchs disponibles de cette saison. Les résultats absents ne sont pas reconstitués.</p>
 </section>
</template>
