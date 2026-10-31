<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { isLive, statusLabel, day, time } from '../state'
import { nextTeamMatch, kickoffCountdown } from '../teamMatches'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
const props = defineProps({ matches: {type:Array,required:true} })
const now = ref(Date.now())
const match = computed(() => nextTeamMatch(props.matches,now.value))
const label = computed(() => isLive(match.value) ? 'MATCH EN COURS' : 'LE PROCHAIN RENDEZ-VOUS')
let timer
onMounted(() => { timer = setInterval(() => { now.value=Date.now() },30000) })
onUnmounted(() => clearInterval(timer))
</script>
<template>
 <section v-if="match" class="next-team-match" aria-labelledby="next-team-match-title">
  <div class="next-team-intro"><span class="eyebrow" id="next-team-match-title"><span v-if="isLive(match)" class="live-dot"></span>{{ label }}</span><span class="next-team-countdown">{{ isLive(match) ? statusLabel(match) : match.status==='TBD' ? 'Horaire à confirmer' : kickoffCountdown(match.date,now) }}</span></div>
  <div class="next-team-versus"><div class="next-team-club"><Badge :team="match.home" size="large" /><strong>{{ match.home.name }}</strong></div><div class="next-team-time"><strong v-if="isLive(match)">{{ match.homeGoals ?? '–' }} <span>:</span> {{ match.awayGoals ?? '–' }}</strong><strong v-else>{{ match.status==='TBD' ? 'À confirmer' : time(match.date) }}</strong><span>{{ day(match.date,{weekday:'short',month:'short'}) }}</span></div><div class="next-team-club"><Badge :team="match.away" size="large" /><strong>{{ match.away.name }}</strong></div></div>
  <div class="next-team-footer"><span><Icon name="pin" :size="14" />{{ match.venue || 'Stade non renseigné' }}<span v-if="match.venueSource === 'club'"> (stade habituel)</span> · J{{ match.round || '—' }}</span><RouterLink :to="`/matchs/${match.id}`" class="button primary">{{ isLive(match) ? 'Suivre le match' : 'Voir le match' }}<Icon name="arrow" :size="15" /></RouterLink></div>
 </section>
 <section v-else class="panel next-team-empty"><Icon name="matches" :size="24" /><div><h2>Le prochain rendez-vous</h2><p>Aucun prochain match confirmé dans les données disponibles.</p></div></section>
</template>
