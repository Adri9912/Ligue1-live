<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { api, state, chooseFavorite, day, time } from '../state'
import Badge from '../components/Badge.vue'
import Icon from '../components/Icon.vue'
import DataFreshness from '../components/DataFreshness.vue'
import PlayerAvatar from '../components/PlayerAvatar.vue'
import PlayerProfile from '../components/PlayerProfile.vue'
import ClubLineup from '../components/ClubLineup.vue'
const route=useRoute(), search=ref(''), result=ref(null), loading=ref(false), error=ref('')
const activePlayer=ref(null)
const selected=computed(()=>Number(route.params.id)||null)
const clubs=computed(()=>[...state.teams].filter(t=>t.name.toLocaleLowerCase('fr').includes(search.value.toLocaleLowerCase('fr'))).sort((a,b)=>a.name.localeCompare(b.name,'fr')))
const groups=computed(()=>{
 const map=new Map()
 for(const p of result.value?.squad || []) { const label=({Gardien:'Gardiens',Défenseur:'Défenseurs',Milieu:'Milieux',Attaquant:'Attaquants'})[p.position] || 'Autres postes'; if(!map.has(label))map.set(label,[]);map.get(label).push(p) }
 return [...map].map(([name,players])=>({name,players}))
})
const current=computed(()=>state.teams.find(t=>t.id===selected.value))
let version=0
async function load(manual=false) {
 const requestVersion=++version
 result.value=null;error.value=''
 if(!selected.value){loading.value=false;return}
 loading.value=true
 try { const data=await api(`/api/clubs/${selected.value}`,{manual});if(requestVersion===version)result.value=data }
 catch(e){if(requestVersion===version)error.value=e.message}
 finally{if(requestVersion===version)loading.value=false}
}
watch(selected,()=>{activePlayer.value=null;load()},{immediate:true})
onUnmounted(()=>version++)
</script>
<template>
  <div class="page-heading"><span class="eyebrow">DERRIÈRE LE BLASON, UNE HISTOIRE.</span><h1>Les clubs<span class="heading-dot">.</span></h1><p>Effectifs, stades et palmarès des clubs de Ligue 1 · Saison {{ state.season }}–{{ state.season+1 }}.</p></div>
  <template v-if="!selected"><label class="club-search"><Icon name="search" :size="18" /><span class="sr-only">Rechercher un club</span><input v-model="search" type="search" placeholder="Rechercher un club…" /></label><div class="clubs-grid"><RouterLink v-for="club in clubs" :key="club.id" :to="`/clubs/${club.id}`" class="panel club-tile"><Badge :team="club" size="large" /><div><h2>{{ club.name }}</h2><span>Effectif · Stade · Palmarès</span></div><Icon name="arrow" :size="17" /></RouterLink></div><p v-if="!clubs.length" class="empty panel">Aucun club ne correspond à votre recherche.</p></template>
  <template v-else><RouterLink to="/clubs" class="back-link"><Icon name="back" :size="16" />Tous les clubs</RouterLink>
    <div v-if="loading" class="loading-state" role="status"><span class="loader"></span><h2>Dans les coulisses du club…</h2><p>Chargement de l’effectif et des informations.</p></div>
    <div v-else-if="error" class="alert error" role="alert"><p>{{ error }}</p><button class="button" :disabled="state.refreshMode==='pause'" @click="load(true)">Réessayer</button></div>
    <template v-else-if="result">
      <DataFreshness :fetched-at="result.fetchedAt" :stale="result.stale" label="Fiche du club" />
      <section class="club-profile"><div class="team-title"><Badge :team="result.club" size="large" /><div><span class="eyebrow">LIGUE 1 · SAISON {{ state.season }}–{{ state.season+1 }}</span><h2>{{ result.club.name }}</h2><p>{{ result.club.city || 'France' }}</p></div></div><button class="button" :disabled="!current" @click="chooseFavorite(selected)"><Icon name="heart" :size="16" />{{ state.favorite===selected ? 'Mon club favori' : 'Suivre ce club' }}</button></section>
      <div class="club-facts"><section class="panel"><Icon name="clock" :size="22" /><span>ANNÉE DE CRÉATION</span><strong>{{ result.club.founded || 'Non renseignée' }}</strong></section><section class="panel"><Icon name="pin" :size="22" /><span>LE STADE</span><strong>{{ result.club.stadium || 'Non renseigné' }}</strong></section><section class="panel"><Icon name="trophy" :size="22" /><span>CHAMPIONNATS DE FRANCE</span><strong>{{ result.honours.known ? result.honours.seasons.length : 'À vérifier' }}</strong><small>Première division, depuis 1932</small></section></div>
      <section class="panel club-honours"><div class="panel-title"><h2>Les saisons du sacre</h2><Icon name="trophy" :size="20" /></div><ul v-if="result.honours.seasons.length" class="honours-seasons"><li v-for="season in result.honours.seasons" :key="season"><Icon name="trophy" :size="15" />{{ season }}</li></ul><p v-else class="club-empty-note">{{ result.honours.known ? 'Aucun titre de champion de France de première division à ce jour.' : 'Le palmarès de ce club reste à vérifier.' }}</p><p class="players-footnote">Division 1 et Ligue 1, hors championnats de guerre. <a :href="result.honours.source" target="_blank" rel="noopener noreferrer">Palmarès officiel de la Ligue ↗</a> · vérifié le 27 octobre 2026.</p></section>
      <ClubLineup v-if="state.source==='highlightly'" :club-id="selected" :squad="result.squad" @player="activePlayer=$event" />
      <section class="panel club-squad"><div class="panel-title"><div><h2>L’effectif actuel</h2><p class="muted small-text">Saison {{ state.season }}–{{ state.season+1 }} · {{ result.squad.length }} joueurs transmis</p></div><span class="data-pill">DES GARDIENS AUX ATTAQUANTS</span></div><div v-if="result.squadError" class="club-empty-note" role="status"><p>{{ result.squadError }}</p><button class="button" :disabled="state.refreshMode==='pause'" @click="load(true)">Charger l’effectif</button></div><section v-for="group in groups" :key="group.name" class="squad-group"><h3>{{ group.name }} <span>{{ group.players.length }}</span></h3><table class="squad-table"><caption class="sr-only">{{ group.name }} de {{ result.club.name }}</caption><thead><tr><th scope="col">N°</th><th scope="col">Joueur</th><th scope="col">Poste</th></tr></thead><tbody><tr v-for="player in group.players" :key="player.id"><td><span class="shirt-number">{{ player.number ?? '—' }}</span></td><td><button class="squad-player-link" @click="activePlayer=player" :aria-label="`Voir la fiche de ${player.name}`"><PlayerAvatar :photo="player.photo" :name="player.name" /><span>{{ player.name }}</span><Icon name="arrow" :size="13" /></button></td><td>{{ player.position }}</td></tr></tbody></table></section><p class="players-footnote">Effectif actuel fourni par API-Football, reçu le {{ day(result.fetchedAt,{year:'numeric'}) }} à {{ time(result.fetchedAt) }}. Les noms peuvent être abrégés et les numéros absents sont indiqués par un tiret. Cette source fournit l’effectif actuel, sans garantie d’exhaustivité des inscriptions en championnat.</p></section>
      <p class="update-note">Informations du club : API-Football. Année de création affichée selon les données disponibles.<template v-if="result.club.correctionSource"> <a :href="result.club.correctionSource" target="_blank" rel="noopener noreferrer">Précision historique ou stade : source officielle ↗</a></template></p>
      <div class="retro-toolbar panel"><details class="technical-details"><summary>Détails des appels à la fiche</summary><p class="small-text muted">Budget API-Football partagé avec Rétro : {{ result.clubBudget.used }} / {{ result.clubBudget.limit }} appels aujourd’hui. Effectifs en cache 24 h ; informations du club 30 jours.</p></details></div>
    </template>
  </template>
  <PlayerProfile v-if="activePlayer && selected" :key="`${selected}-${activePlayer.id}`" :club-id="selected" :player="activePlayer" @close="activePlayer=null" @budget="result && (result.clubBudget=$event)" />
</template>
