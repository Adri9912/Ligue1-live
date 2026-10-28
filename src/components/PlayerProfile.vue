<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { api, state, day, time } from '../state'
import PlayerAvatar from './PlayerAvatar.vue'
import Icon from './Icon.vue'
const props=defineProps({clubId:{type:Number,required:true},player:{type:Object,required:true}})
const emit=defineEmits(['close','budget'])
const dialog=ref(null), data=ref(null), loading=ref(true), error=ref('')
let mounted=true
async function load(manual=false) {
 loading.value=true;error.value=''
 try { const result=await api(`/api/clubs/${props.clubId}/players/${props.player.id}`,{manual});if(mounted){data.value=result;emit('budget',result.clubBudget)} }
 catch(e){if(mounted)error.value=e.message}
 finally{if(mounted)loading.value=false}
}
function measure(value, unit) { if(!value)return 'Non renseigné';return /^\d+(?:[.,]\d+)?$/.test(value) ? `${value} ${unit}` : value }
onMounted(()=>{dialog.value.showModal();load()})
onUnmounted(()=>{mounted=false})
</script>
<template><dialog ref="dialog" class="team-dialog player-dialog" aria-labelledby="player-profile-title" @close="emit('close')"><div class="dialog-head"><div><span class="eyebrow">FICHE JOUEUR</span><h2 id="player-profile-title">{{ data?.player.name || player.name }}</h2></div><button class="icon-button" aria-label="Fermer la fiche joueur" @click="dialog.close()"><Icon name="close" /></button></div><div class="player-profile-hero"><PlayerAvatar :photo="data?.player.photo || player.photo" :name="player.name" large /><div><strong>{{ data?.club.name || 'Effectif actuel' }}</strong><p>{{ player.position }} · N° {{ player.number ?? '—' }}</p></div></div><div v-if="loading" class="profile-loading" role="status"><span class="loader"></span><p>Chargement de la fiche…</p></div><div v-else-if="error" class="alert error" role="alert"><p>{{ error }}</p><button class="button" :disabled="state.refreshMode==='pause'" @click="load(true)">Charger la fiche</button></div><template v-else-if="data"><p v-if="data.stale" class="alert warning">Fiche en cache, actualisation temporairement indisponible.</p><dl class="player-profile-facts"><div><dt>Âge</dt><dd>{{ data.player.age != null ? `${data.player.age} ans` : 'Non renseigné' }}</dd></div><div><dt>Nationalité</dt><dd>{{ data.player.nationality || 'Non renseignée' }}</dd></div><div><dt>Date de naissance</dt><dd>{{ data.player.birthDate ? day(data.player.birthDate,{year:'numeric'}) : 'Non renseignée' }}</dd></div><div><dt>Lieu de naissance</dt><dd>{{ [data.player.birthPlace,data.player.birthCountry].filter(Boolean).join(', ') || 'Non renseigné' }}</dd></div><div><dt>Taille</dt><dd>{{ measure(data.player.height,'cm') }}</dd></div><div><dt>Poids</dt><dd>{{ measure(data.player.weight,'kg') }}</dd></div></dl><p class="player-profile-note">Profil : API-Football · reçu le {{ day(data.fetchedAt,{year:'numeric'}) }} à {{ time(data.fetchedAt) }}. Fiche conservée 7 jours pour économiser les appels. Les statistiques de la saison ne sont pas incluses dans ce profil.</p></template></dialog></template>
