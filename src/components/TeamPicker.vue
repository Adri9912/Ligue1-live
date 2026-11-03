<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { account } from '../account'
import { state, chooseFavorite } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
const route = useRoute(), dialog = ref(null), query = ref(''), candidate = ref(null), reminder = ref(true)
const legacyTeam = computed(() => {
  if (!account.user || account.following.primaryTeamId) return null
  try { return localStorage.getItem('l1-favorite-source') === state.source ? state.teams.find(t => t.id === Number(localStorage.getItem('l1-favorite'))) : null } catch { return null }
})
watch(() => route.fullPath, () => { state.pickerOpen = false })
const filtered = computed(() => state.teams.filter(t => `${t.name} ${t.fullName || ''}`.toLocaleLowerCase('fr').includes(query.value.toLocaleLowerCase('fr')) || t.code.toLowerCase().includes(query.value.toLowerCase())))
function selectTeam(team) {
  const followed = account.following.source === state.source && account.following.teams.some(t => t.providerId === team.id)
  if (account.enabled && account.following.remindersAvailable && !followed) { candidate.value = team; reminder.value = true }
  else chooseFavorite(team.id)
}
watch(() => state.pickerOpen, async open => {
  await nextTick()
  if (open) {
    query.value = ''; state.favoriteError = ''; reminder.value = true
    candidate.value = state.teams.find(team => team.id === state.favoriteCandidate) || null
    state.favoriteCandidate = null; dialog.value.showModal()
  } else { candidate.value = null; state.favoriteCandidate = null; dialog.value.close() }
})
</script>
<template>
  <dialog ref="dialog" class="team-dialog" aria-labelledby="team-picker-title" @close="state.pickerOpen = false" @click="e => { if (e.target === dialog) state.pickerOpen = false }">
    <div class="dialog-head"><div><span class="eyebrow">VOTRE CLUB, VOTRE PASSION</span><h2 id="team-picker-title">{{ candidate ? 'Votre club, vos rappels' : 'Choisissez votre équipe' }}</h2></div><button class="icon-button" aria-label="Fermer" @click="state.pickerOpen = false"><Icon name="close" /></button></div>
    <p v-if="state.favoriteError" class="alert error" role="alert">{{ state.favoriteError }}</p>
    <p v-if="!account.ready" role="status">Chargement de votre espace…</p>
    <div v-else-if="account.enabled !== false && !account.user" class="favorite-signup"><h3>Suivez votre club préféré !</h3><p>Connectez-vous pour enregistrer votre équipe favorite et retrouver vos clubs sur tous vos appareils.</p><RouterLink class="button primary full" to="/connexion">Se connecter</RouterLink><div class="account-links account-entry-links"><RouterLink to="/inscription">Créer un compte</RouterLink></div><RouterLink to="/" class="guest-continue" @click="state.pickerOpen = false">Continuer sans s’inscrire</RouterLink></div>
    <form v-else-if="candidate" class="favorite-confirm" @submit.prevent="chooseFavorite(candidate.id, { remindersEnabled: reminder })">
      <div class="favorite-confirm-club"><Badge :team="candidate" size="large" /><strong>{{ candidate.name }}</strong></div>
      <label class="reminder-default"><input v-model="reminder" type="checkbox" :disabled="state.favoriteSaving" /><span><strong>Rappel par e-mail 2 h avant les matchs</strong><small>Option précochée pour ce nouveau club. Vous pouvez la décocher maintenant ou la modifier ensuite dans Mon équipe.</small></span></label>
      <p v-if="!account.user?.email_verified_at" class="small-text muted">Les envois commenceront après la vérification de votre adresse e-mail.</p>
      <button class="button primary full" :disabled="state.favoriteSaving">{{ state.favoriteSaving ? 'Enregistrement…' : 'Confirmer mon club' }}</button>
      <button class="text-button" type="button" :disabled="state.favoriteSaving" @click="candidate = null">Choisir un autre club</button>
    </form>
    <template v-else>
      <button v-if="legacyTeam" class="button full" :disabled="state.favoriteSaving" @click="selectTeam(legacyTeam)">Retrouver mon ancien favori : {{ legacyTeam.name }}</button>
      <p class="muted">Ses rencontres et ses résultats, toujours au premier plan.</p>
      <label class="search-field"><Icon name="search" /><input v-model="query" placeholder="Rechercher un club…" aria-label="Rechercher une équipe" /></label>
      <div class="team-picker-grid"><button v-for="team in filtered" :key="team.id" class="team-choice" :disabled="state.favoriteSaving" :class="{ selected: state.favorite === team.id }" @click="selectTeam(team)"><Badge :team="team" /><span>{{ team.name }}</span><Icon v-if="state.favorite === team.id" name="check" :size="16" /></button></div>
      <p v-if="!filtered.length" class="empty">Aucun club ne correspond à votre recherche.</p>
      <button v-if="state.favorite" :disabled="state.favoriteSaving" class="text-button clear-favorite" @click="chooseFavorite(null)">Retirer mon équipe principale</button>
    </template>
  </dialog>
</template>
