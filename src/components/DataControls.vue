<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { state, syncManualRefresh, refreshManually } from '../state'
import Icon from './Icon.vue'
import DataFreshness from './DataFreshness.vue'
const route = useRoute()
const isStandings = computed(() => route.path.startsWith('/classement') || route.path === '/statistiques')
const fetchedAt = computed(() => (isStandings.value ? state.standingsFetchedAt : state.matchesFetchedAt) ?? state.fetchedAt)
const stale = computed(() => (isStandings.value ? state.standingsStale : state.matchesStale) || !!state.error)
let timer
async function checkAllowance() { try { await syncManualRefresh() } catch { state.manualRefresh = null } }
onMounted(() => {
  checkAllowance()
  timer = setInterval(() => { if (state.manualRefresh && Date.now() >= state.manualRefresh.resetAt) checkAllowance() }, 60000)
})
onUnmounted(() => clearInterval(timer))
const labels = { auto: 'Automatique', manual: 'Actualisation manuelle', pause: 'Appels en pause' }
</script>
<template>
  <section class="data-control-bar" :class="{ 'is-auto': state.refreshMode === 'auto' }" aria-label="Actualisation des données">
    <div class="data-control-state"><Icon name="refresh" :size="15" /><span>{{ labels[state.refreshMode] }}</span><small v-if="state.mode === 'demo'">Démo · aucun appel réel</small><small v-else-if="state.budget && (state.budget.remaining === 0 || state.budget.providerRemaining === 0)">Quota atteint · données en cache</small></div>
    <DataFreshness v-if="state.mode === 'api'" :fetched-at="fetchedAt" :stale="stale" :label="isStandings ? 'Classement' : 'Calendrier et scores'" />
    <div v-if="state.mode === 'api'" class="data-control-actions"><button class="button manual-refresh-button" :disabled="state.refreshing || state.refreshMode === 'pause' || !state.manualRefresh || state.manualRefresh.remaining === 0" :title="state.manualRefresh ? `10 clics par jour dans ce navigateur. Compteur renouvelé à ${new Date(state.manualRefresh.resetAt).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'})}. Les données en cache peuvent rester identiques.` : 'Chargement du compteur…'" @click="refreshManually"><Icon name="refresh" :size="13" :class="{spinning:state.refreshing}" /><span>{{ state.refreshing ? 'Actualisation…' : 'Actualiser' }}</span><small v-if="state.manualRefresh">{{ state.manualRefresh.remaining }}/{{ state.manualRefresh.limit }}</small></button></div>
    <p v-if="state.refreshNotice" class="refresh-feedback" role="status">{{ state.refreshNotice }}</p>
  </section>
</template>
