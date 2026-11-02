<script setup>
import { computed } from 'vue'
import { matchSquadPlayer } from '../players'
import PlayerAvatar from './PlayerAvatar.vue'
const props=defineProps({lineup:{type:Object,required:true},squad:{type:Array,default:()=>[]}})
const emit=defineEmits(['player'])
const rows=computed(()=>props.lineup.rows.map(row=>row.map(player=>({...player,member:matchSquadPlayer(player,props.squad)}))))
</script>
<template>
  <div class="lineup-pitch" role="group" :aria-label="`Composition en ${lineup.formation}, gardien en bas du terrain`"><div class="pitch-markings" aria-hidden="true"><i></i></div><div class="lineup-row" v-for="(row,i) in rows" :key="i"><component :is="player.member ? 'button' : 'div'" v-for="(player,j) in row" :key="`${player.id ?? player.name}-${j}`" class="pitch-player" :class="{'is-clickable':player.member}" :aria-label="player.member ? `Voir la fiche de ${player.member.name}, numéro ${player.number ?? 'non renseigné'}` : undefined" @click="player.member && emit('player',player.member)"><span class="pitch-portrait"><PlayerAvatar :photo="player.member?.photo" :name="player.name" /><b>{{ player.number ?? '—' }}</b></span><span class="pitch-player-name">{{ player.name }}</span></component></div></div>
</template>
