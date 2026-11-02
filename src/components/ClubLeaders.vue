<script setup>
import { computed } from 'vue'
import { state } from '../state'
import { clubRanking } from '../football.js'
import Badge from './Badge.vue'
const props = defineProps({ kind: { type: String, required: true }, limit: { type: Number, default: 3 } })
const attack = computed(() => props.kind === 'attack')
const rows = computed(() => clubRanking(state.standings, props.kind, props.limit))
</script>
<template>
 <section class="panel club-leaders" :aria-label="attack ? 'Meilleures attaques' : 'Meilleures défenses'">
  <h2>{{ attack ? 'Meilleures attaques' : 'Meilleures défenses' }}</h2>
  <p class="muted">{{ attack ? 'Buts marqués' : 'Buts encaissés' }} · saison {{ state.season }}–{{ state.season + 1 }}</p>
  <ol v-if="rows.length"><li v-for="row in rows" :key="row.team.id"><RouterLink :to="`/equipes/${row.team.id}`"><Badge :team="row.team" size="small" /><span><strong>{{ row.team.name }}</strong><small>{{ row.played }} matchs joués</small></span><b>{{ attack ? row.goalsFor : row.goalsAgainst }} <small>buts</small></b></RouterLink></li></ol>
  <p v-else class="club-leaders-empty">Pas encore de statistiques disponibles.</p>
  <small>Totaux des matchs joués, sans ajustement au nombre de rencontres. Égalités départagées par le classement publié.</small>
 </section>
</template>
