<script setup>
import { computed, ref } from 'vue'
import { account, accountRequest } from '../account'
import { state, chooseFavorite, isLive, isUpcoming, day, time } from '../state'
import Badge from './Badge.vue'
const selected = ref(''), newReminder = ref(true), busy = ref(false), error = ref('')
const available = computed(() => state.teams.filter(t => !account.following.teams.some(f => f.providerId === t.id && account.following.source === state.source)))
const current = team => account.following.source === state.source && state.teams.some(t => t.id === team.providerId)
const primary = team => team.id === account.following.primaryTeamId
const next = team => current(team) ? [...state.matches].filter(m => [m.home.id,m.away.id].includes(team.providerId) && (isLive(m)||isUpcoming(m)) && (isLive(m)||Date.parse(m.date)>=Date.now())).sort((a,b)=>Number(isLive(b))-Number(isLive(a))||Date.parse(a.date)-Date.parse(b.date))[0] : null
async function follow() {
  if (!selected.value || busy.value) return
  busy.value = true; error.value = ''
  try { await accountRequest('/following', { method: 'POST', body: { team_id: Number(selected.value), ...(account.following.remindersAvailable ? { reminders_enabled: newReminder.value } : {}) } }); selected.value = ''; newReminder.value = true }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function setReminder(team, event) {
  const enabled = event.target.checked
  event.target.checked = team.remindersEnabled
  if (busy.value) return
  busy.value = true; error.value = ''
  try { await accountRequest(`/following/${team.id}/reminders`, { method: 'PUT', body: { enabled } }) }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
async function remove(team) {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { await accountRequest(`/following/${team.id}`, { method: 'DELETE' }) }
  catch (e) { error.value = e.message }
  finally { busy.value = false }
}
</script>
<template>
  <section class="panel followed-clubs" aria-labelledby="followed-clubs-title">
    <h2 id="followed-clubs-title">Mes clubs suivis</h2><p class="muted">Une équipe principale et les autres clubs que vous aimez suivre. Vos choix vous accompagnent sur tous vos appareils.</p>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="!account.following.teams.length" class="followed-empty">Choisissez votre premier club : il deviendra votre équipe principale.</p>
    <ul><li v-for="team in account.following.teams" :key="team.id">
      <div class="followed-team"><Badge :team="team" size="small" /><strong>{{ team.name }}</strong><span v-if="primary(team)" class="data-pill">PRINCIPALE</span></div>
      <p v-if="state.loading" class="small-text muted">Chargement des rencontres…</p>
      <p v-else-if="state.error" class="small-text muted">Rencontres indisponibles pour le moment. Votre suivi reste enregistré.</p>
      <p v-else-if="!current(team)" class="small-text muted">Choix conservé · club absent des données de Ligue 1 actuellement reçues.</p>
      <RouterLink v-else :to="`/equipes/${team.providerId}`" class="text-button">{{ next(team) ? `${next(team).home.name} – ${next(team).away.name} · ${day(next(team).date, { month: 'short' })} à ${time(next(team).date)}` : 'Voir les matchs et résultats' }}</RouterLink>
      <label v-if="account.following.remindersAvailable" class="reminder-choice"><input type="checkbox" :checked="team.remindersEnabled" :disabled="busy" @change="setReminder(team, $event)" /><span><strong>Rappels par e-mail {{ team.remindersEnabled ? (account.user?.email_verified_at ? 'activés' : 'en attente') : 'désactivés' }}</strong><small>Un e-mail 2 h avant chaque match</small></span></label>
      <p v-if="account.following.remindersAvailable && team.remindersEnabled && !account.user?.email_verified_at" class="small-text muted">Votre choix est enregistré. Vérifiez votre adresse dans <RouterLink to="/mon-compte" class="text-button">Mon compte</RouterLink> pour recevoir les rappels.</p>
      <div class="followed-actions"><button v-if="!primary(team) && current(team)" class="text-button" :disabled="busy || state.favoriteSaving" @click="chooseFavorite(team.providerId)">Définir comme principale</button><button class="text-button" :disabled="busy || state.favoriteSaving" @click="remove(team)">Ne plus suivre</button></div>
    </li></ul>
    <p v-if="account.following.remindersAvailable" class="reminder-help">Un seul e-mail si vous suivez les deux adversaires. Vous pouvez désactiver les rappels ici ou depuis le lien présent dans chaque e-mail. Les horaires dépendent des dernières données disponibles.</p>
    <form class="followed-add" @submit.prevent="follow"><label for="follow-team">Ajouter un club de Ligue 1</label><select id="follow-team" v-model="selected" :disabled="busy || !available.length" required><option value="">{{ !state.teams.length ? 'Clubs indisponibles pour le moment' : available.length ? 'Choisir un club' : 'Tous les clubs disponibles sont suivis' }}</option><option v-for="team in available" :key="team.id" :value="team.id">{{ team.name }}</option></select><label v-if="selected && account.following.remindersAvailable" class="reminder-default"><input v-model="newReminder" type="checkbox" :disabled="busy" /><span><strong>Rappel par e-mail 2 h avant les matchs</strong><small>Précoché pour ce nouveau club. Vous pouvez décocher avant de le suivre.</small><small v-if="!account.user?.email_verified_at">Les envois commenceront après vérification de votre adresse.</small></span></label><button class="button" :disabled="busy || !selected">{{ busy ? 'Enregistrement…' : 'Suivre ce club' }}</button></form>
  </section>
</template>
