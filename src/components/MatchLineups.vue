<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { api, state } from '../state'
import Badge from './Badge.vue'
import DataFreshness from './DataFreshness.vue'
import LineupPitch from './LineupPitch.vue'
import PlayerAvatar from './PlayerAvatar.vue'
import PlayerProfile from './PlayerProfile.vue'
import { matchSquadPlayer } from '../players'
const props=defineProps({fixture:{type:Object,required:true}})
const data=ref(null), loading=ref(false), error=ref(''), selectedSide=ref('home'), activePlayer=ref(null)
const mobileQuery=window.matchMedia('(max-width:800px)')
const compactLayout=ref(mobileQuery.matches)
function updateLayout(event) { compactLayout.value=event.matches }
let version=0, timer, nextAttempt=Infinity
async function load(manual=false) {
 if(loading.value)return
 const current=++version
 loading.value=true;error.value='';nextAttempt=Date.now()+900000
 try {
  const result=await api(`/api/matches/${props.fixture.id}/lineups`,{manual})
  if(current!==version)return
  data.value=result;nextAttempt=result.nextLookupAt ?? Infinity
 } catch(e){if(current===version)error.value=e.message}
 finally{if(current===version)loading.value=false}
}
function check() { if(!document.hidden&&state.refreshMode==='auto'&&state.mode==='api'&&Date.now()>=nextAttempt)load() }
watch(()=>props.fixture.id,()=>{version++;loading.value=false;data.value=null;selectedSide.value='home';activePlayer.value=null;load()},{immediate:true})
watch(()=>state.refreshSerial,()=>{
 if(state.lastRefreshWasManual && state.refreshMode!=='pause' && Date.now()>=nextAttempt)load(true)
 else check()
})
onMounted(()=>{timer=setInterval(check,60000);mobileQuery.addEventListener('change',updateLayout)})
onUnmounted(()=>{version++;clearInterval(timer);mobileQuery.removeEventListener('change',updateLayout)})
</script>
<template>
 <section class="match-lineups" aria-label="Compositions du match">
  <details class="lineups-info"><summary>À propos de cette composition</summary><p class="lineups-note">Onze de départ annoncé et banc transmis par le fournisseur. Les joueurs ne sont pas déplacés après les remplacements.</p></details>
  <div v-if="loading && !data" class="lineup-loading" role="status"><span class="loader"></span><p>Chargement des compositions…</p></div>
  <div v-if="error" class="alert warning" role="alert"><p>{{ error }}</p><button class="button" :disabled="loading || state.refreshMode==='pause'" @click="load(true)">Réessayer</button></div>
  <template v-if="data">
   <DataFreshness :fetched-at="data.fetchedAt" :stale="data.stale" label="Compositions" />
   <p v-if="data.unavailable" class="lineups-message" role="status">{{ data.unavailable }}</p>
   <template v-else>
   <div class="lineup-team-switch" role="group" aria-label="Choisir l’équipe à afficher"><button v-for="side in ['home','away']" :key="side" :aria-pressed="selectedSide===side" :aria-controls="`match-lineup-${fixture.id}-${side}`" :class="{selected:selectedSide===side}" @click="selectedSide=side"><Badge :team="data.fixture[side]" size="small" /><span>{{ data.fixture[side].name }}</span></button></div>
   <div class="match-lineups-grid">
    <section v-for="side in ['home','away']" :key="side" class="match-lineup-team" :class="{ 'inactive-lineup': selectedSide!==side }" :id="`match-lineup-${fixture.id}-${side}`" :aria-label="`Composition de ${data.fixture[side].name}`">
     <header><Badge :team="data.fixture[side]" size="small" /><h3>{{ data.fixture[side].name }}</h3><span class="data-pill">{{ data.lineups[side]?.formation || 'EN ATTENTE' }}</span></header>
     <template v-if="data.lineups[side]">
      <LineupPitch :lineup="data.lineups[side]" :squad="data.squads?.[side] || []" @player="activePlayer={clubId:data.fixture[side].id,player:$event}" />
      <p class="players-footnote">Gardien en bas du terrain. Portraits issus des effectifs déjà en cache, uniquement si le joueur est reconnu avec certitude.</p>
      <details class="match-bench" :open="!compactLayout"><summary>Remplaçants <span v-if="data.lineups[side].substitutes?.length">({{ data.lineups[side].substitutes.length }})</span></summary><ul v-if="data.lineups[side].substitutes?.length"><li v-for="(player,i) in data.lineups[side].substitutes" :key="`${player.id ?? player.name}-${i}`"><PlayerAvatar :photo="matchSquadPlayer(player,data.squads?.[side] || [])?.photo" :lookup="matchSquadPlayer(player,data.squads?.[side] || [])?.photoLookup" :name="player.name" /><b>{{ player.number ?? '—' }}</b><span>{{ player.name }}<small>{{ player.position }}</small></span></li></ul><p v-else class="muted">Aucun remplaçant transmis par le fournisseur.</p></details>
     </template>
     <div v-else class="empty"><h4>Composition pas encore disponible</h4><p>Elle apparaîtra ici si le fournisseur la transmet pour ce match.</p></div>
    </section>
   </div>
   </template>
  </template>
  <PlayerProfile v-if="activePlayer" :key="`${activePlayer.clubId}-${activePlayer.player.id}`" :club-id="activePlayer.clubId" :player="activePlayer.player" @close="activePlayer=null" />
  <p v-if="state.source === 'highlightly'" class="players-footnote">Source : Highlightly. Réponses partagées entre visiteurs ; compositions complètes conservées en cache, recherche des compositions manquantes espacée d’au moins 15 minutes. Les modes Manuel/Pause et le quota global restent appliqués.</p>
 </section>
</template>
