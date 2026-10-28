<script setup>
import { ref, onMounted, watch } from 'vue'
import { api, state, day, time } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
const props = defineProps({ kind: { type: String, required: true } })
const scorers = props.kind === 'scorers'
const title = scorers ? 'Top 3 buteurs' : 'Top 3 passeurs'
const link = scorers ? '/classement/buteurs' : '/classement/passeurs'
const result = ref(null), loading = ref(true), error = ref('')
async function loadLeaders({ manual = false } = {}) {
  loading.value = true; error.value = ''
  try {
    const data = await api(`/api/players/${scorers ? 'scorers' : 'assists'}`, { manual })
    if (data.mode !== state.mode || data.season !== state.season) throw new Error('La source a changé. Actualisez la page.')
    result.value = data
  } catch (e) { error.value = e.message }
  finally { loading.value = false }
}
onMounted(() => loadLeaders())
watch(() => state.refreshSerial, () => loadLeaders({ manual: state.lastRefreshWasManual }))
</script>
<template>
  <section class="panel home-leaders" :aria-labelledby="`home-${kind}-title`">
    <div class="aside-heading"><h3 :id="`home-${kind}-title`">{{ title }}</h3><span v-if="result?.unavailable" class="data-pill">À VENIR</span><Icon v-else :name="scorers ? 'ball' : 'assist'" :size="17" /></div>
    <p class="home-leaders-caption">{{ state.mode === 'demo' ? 'Données fictives' : `Saison ${state.season}–${String(state.season + 1).slice(-2)}` }}</p>
    <p v-if="loading" class="home-leaders-message" role="status">Chargement des joueurs…</p>
    <div v-else-if="error" class="home-leaders-message" role="alert"><p>{{ error }}</p><button class="text-button" @click="loadLeaders({ manual: true })" :disabled="state.refreshMode === 'pause'">Réessayer</button></div>
    <template v-else>
      <p v-if="result?.stale" class="home-leaders-cache">En cache · {{ day(result.fetchedAt, { month: 'short' }) }} à {{ time(result.fetchedAt) }}</p>
      <ol v-if="result?.players.length" class="home-leaders-list">
        <li v-for="(player, index) in result.players.slice(0, 3)" :key="player.id">
          <RouterLink :to="`/equipes/${player.team.id}`" class="home-leader">
            <span class="home-leader-rank">{{ index + 1 }}</span><Badge :team="player.team" size="small" />
            <span class="home-leader-name"><strong>{{ player.name }}</strong><span>{{ player.team.name }}</span></span>
            <span class="home-leader-value"><b>{{ scorers ? player.goals : player.assists }}</b><small>{{ scorers ? 'buts' : 'passes' }}</small></span>
          </RouterLink>
        </li>
      </ol>
      <p v-else class="home-leaders-message">{{ result?.unavailable ? `Le top 3 des ${scorers ? 'buteurs' : 'passeurs'} du championnat est en préparation.` : 'Classement des joueurs indisponible pour le moment.' }}</p>
    </template>
    <RouterLink :to="link" class="aside-footer-link">{{ result?.unavailable ? 'Découvrir la rubrique' : scorers ? 'Tous les buteurs' : 'Tous les passeurs' }}<Icon name="arrow" :size="15" /></RouterLink>
  </section>
</template>
