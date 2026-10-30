<script setup>
import { state, load } from '../state'
import Icon from './Icon.vue'
import DataFreshness from './DataFreshness.vue'
const labels = { auto: 'Automatique', manual: 'Actualisation manuelle', pause: 'Appels en pause' }
</script>
<template>
  <section class="data-control-bar" aria-label="Actualisation des données">
    <div class="data-control-state"><Icon name="refresh" :size="15" /><span>{{ labels[state.refreshMode] }}</span><small v-if="state.mode === 'demo'">Démo · aucun appel réel</small><small v-else-if="state.budget && (state.budget.remaining === 0 || state.budget.providerRemaining === 0)">Quota atteint · données en cache</small></div>
    <DataFreshness v-if="state.mode === 'api'" :fetched-at="state.fetchedAt" :stale="state.stale || !!state.error" label="Matchs et classement" />
    <div class="data-control-actions"><button class="text-button" :disabled="!state.controlReady || state.refreshMode === 'pause' || state.refreshing" @click="load({ manual: true })">{{ state.refreshing ? 'Actualisation…' : 'Actualiser' }}</button></div>
  </section>
</template>
