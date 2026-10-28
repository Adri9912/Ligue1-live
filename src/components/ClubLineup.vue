<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { api, state, day, time } from '../state'
import { matchSquadPlayer } from '../players'
import PlayerAvatar from './PlayerAvatar.vue'
import Icon from './Icon.vue'
const props=defineProps({clubId:{type:Number,required:true},squad:{type:Array,default:()=>[]}})
const emit=defineEmits(['player'])
const result=ref(null), loading=ref(false), error=ref(''), examined=ref([])
const rows=computed(()=>(result.value?.lineup?.rows || []).map(row=>row.map(player=>({...player,member:matchSquadPlayer(player,props.squad)}))))
let version=0
async function load(manual=false, more=false) {
 const requestVersion=++version
 const offset=more ? result.value?.nextOffset : 0
 if(offset==null)return
 if(!more)examined.value=[]
 loading.value=true;error.value=''
 try{
  const data=await api(`/api/clubs/${props.clubId}/lineup?offset=${offset}`,{manual})
  if(requestVersion!==version)return
  result.value=data;examined.value=[...new Set([...examined.value,...data.checkedMatchIds])]
 }catch(e){if(requestVersion===version)error.value=e.message}
 finally{if(requestVersion===version)loading.value=false}
}
watch(()=>props.clubId,()=>{result.value=null;examined.value=[];load()},{immediate:true})
onUnmounted(()=>version++)
</script>
<template>
 <section class="panel club-lineup"><div class="panel-title"><div><h2>La dernière composition disponible</h2><p class="muted small-text">Ligue 1 · Saison {{ state.season }}–{{ state.season+1 }}</p></div><span class="data-pill">{{ result?.lineup?.formation || 'COMPOSITION RÉELLE' }}</span></div>
 <div v-if="loading" class="lineup-loading" role="status"><span class="loader"></span><p>Recherche parmi les matchs les plus récents…</p></div>
 <div v-else-if="error" class="club-empty-note" role="alert"><p>{{ error }}</p><button class="button" :disabled="state.refreshMode==='pause'" @click="load(true)">Charger la composition</button></div>
 <template v-else-if="result">
  <template v-if="result.lineup"><div class="lineup-match-info"><span>J{{ result.lineup.match.round }} · {{ day(result.lineup.match.date,{year:'numeric'}) }}</span><strong>{{ result.lineup.match.home.name }} {{ result.lineup.match.homeGoals }}–{{ result.lineup.match.awayGoals }} {{ result.lineup.match.away.name }}</strong><RouterLink :to="`/matchs/${result.lineup.match.id}`">Voir le match <Icon name="arrow" :size="13" /></RouterLink></div>
  <div class="lineup-pitch" role="group" :aria-label="`Composition en ${result.lineup.formation}, gardien en bas du terrain`"><div class="pitch-markings" aria-hidden="true"><i></i></div><div class="lineup-row" v-for="(row,i) in rows" :key="i"><component :is="player.member ? 'button' : 'div'" v-for="(player,j) in row" :key="`${player.id ?? player.name}-${j}`" class="pitch-player" :class="{'is-clickable':player.member}" :aria-label="player.member ? `Voir la fiche de ${player.member.name}, numéro ${player.number ?? 'non renseigné'}` : undefined" @click="player.member && emit('player',player.member)"><span class="pitch-portrait"><PlayerAvatar :photo="player.member?.photo" :name="player.name" /><b>{{ player.number ?? '—' }}</b></span><span class="pitch-player-name">{{ player.name }}</span></component></div></div>
  <p class="players-footnote">Gardien en bas. Les portraits et fiches sont reliés à l’effectif actuel uniquement lorsqu’une correspondance sûre est trouvée. Certains joueurs restent affichés sans portrait.</p></template>
  <div v-else class="club-empty-note"><p>{{ result.totalMatches ? `Aucune composition complète trouvée parmi les ${examined.length} matchs consultés.` : 'Ce club n’a pas encore de match terminé dans cette saison.' }}</p><button v-if="result.nextOffset!==null" class="button" :disabled="state.refreshMode==='pause'" @click="load(true,true)">Chercher dans les matchs précédents</button></div>
  <p v-if="result.stale" class="players-footnote">Données en cache ; dernière vérification le {{ day(result.fetchedAt,{year:'numeric'}) }} à {{ time(result.fetchedAt) }}.</p>
  <div class="lineup-analysis"><Icon name="info" :size="18" /><div><strong>Composition habituelle : analyse à venir</strong><p>Base examinée : {{ result.lineup ? '1 composition exploitable' : '0 composition exploitable' }} · {{ examined.length }} match{{ examined.length>1 ? 's' : '' }} consulté{{ examined.length>1 ? 's' : '' }} sur {{ result.totalMatches }} terminé{{ result.totalMatches>1 ? 's' : '' }}. Aucun onze habituel n’est déduit de cette recherche.</p></div></div>
  <p class="players-footnote">Source : Highlightly. Recherche de trois matchs au maximum par chargement ; arrêt dès qu’une composition complète est trouvée. Les réponses restent en cache pour limiter les appels.</p>
 </template>
 </section>
</template>
