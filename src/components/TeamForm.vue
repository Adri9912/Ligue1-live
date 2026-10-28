<script setup>
import { computed } from 'vue'
import { recentTeamResults } from '../teamMatches'
import { day } from '../state'
const props = defineProps({ teamId: {type:Number,required:true}, matches: {type:Array,required:true}, before: Number })
const results = computed(() => recentTeamResults(props.matches,props.teamId,{before:props.before}).reverse())
const labels = { W:'Victoire', D:'Match nul', L:'Défaite' }, letters = { W:'V', D:'N', L:'D' }
const points = computed(() => results.value.reduce((sum,r)=>sum+(r.result==='W'?3:r.result==='D'?1:0),0))
function description(r) { return `${labels[r.result]} : ${r.match.home.name} ${r.match.homeGoals}–${r.match.awayGoals} ${r.match.away.name}, le ${day(r.match.date,{year:'numeric'})}` }
</script>
<template>
 <div class="team-recent-form">
  <template v-if="results.length"><div class="form recent-form-chips" aria-label="Résultats du plus ancien au plus récent"><RouterLink v-for="r in results" :key="r.match.id" :to="`/matchs/${r.match.id}`" :class="r.result" :title="description(r)" :aria-label="description(r)">{{ letters[r.result] }}</RouterLink><span class="recent-form-direction" aria-hidden="true">→</span></div><p class="recent-form-caption">{{ points }} point{{ points>1?'s':'' }} sur {{ results.length*3 }} · {{ results.length }} dernier{{ results.length>1?'s':'' }} résultat{{ results.length>1?'s':'' }} disponible{{ results.length>1?'s':'' }}</p><p class="recent-form-legend">V : victoire · N : nul · D : défaite · Plus récent à droite</p></template>
  <p v-else class="muted small-text">Aucun résultat exploitable dans les matchs disponibles.</p>
 </div>
</template>
