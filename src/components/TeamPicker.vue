<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { account } from '../account'
import { state, chooseFavorite } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
const route = useRoute(), dialog = ref(null), query = ref('')
const legacyTeam = computed(() => {
  if (!account.user || account.following.primaryTeamId) return null
  try { return localStorage.getItem('l1-favorite-source') === state.source ? state.teams.find(t => t.id === Number(localStorage.getItem('l1-favorite'))) : null } catch { return null }
})
watch(() => route.fullPath, () => { state.pickerOpen = false })
const filtered = computed(() => state.teams.filter(t => `${t.name} ${t.fullName || ''}`.toLocaleLowerCase('fr').includes(query.value.toLocaleLowerCase('fr')) || t.code.toLowerCase().includes(query.value.toLowerCase())))
watch(() => state.pickerOpen, async open => { await nextTick(); if (open) { query.value = ''; state.favoriteError = ''; dialog.value.showModal() } else dialog.value.close() })
</script>
<template><dialog ref="dialog" class="team-dialog" aria-labelledby="team-picker-title" @close="state.pickerOpen = false" @click="e => { if (e.target === dialog) state.pickerOpen = false }"><div class="dialog-head"><div><span class="eyebrow">VOTRE CLUB, VOTRE PASSION</span><h2 id="team-picker-title">Choisissez votre équipe</h2></div><button class="icon-button" aria-label="Fermer" @click="state.pickerOpen = false"><Icon name="close" /></button></div><p v-if="state.favoriteError" class="alert error" role="alert">{{ state.favoriteError }}</p><p v-if="!account.ready" role="status">Chargement de votre espace…</p><div v-else-if="account.enabled !== false && !account.user" class="favorite-signup"><h3>Suivez votre club préféré !</h3><p>Connectez-vous pour enregistrer votre équipe favorite et retrouver vos clubs sur tous vos appareils.</p><RouterLink class="button primary full" to="/connexion">Se connecter</RouterLink><div class="account-links account-entry-links"><RouterLink to="/inscription">Créer un compte</RouterLink></div><RouterLink to="/" class="guest-continue" @click="state.pickerOpen = false">Continuer sans s’inscrire</RouterLink></div><template v-else><button v-if="legacyTeam" class="button full" :disabled="state.favoriteSaving" @click="chooseFavorite(legacyTeam.id)">Retrouver mon ancien favori : {{ legacyTeam.name }}</button><p class="muted">Ses rencontres et ses résultats, toujours au premier plan.</p><label class="search-field"><Icon name="search" /><input v-model="query" placeholder="Rechercher un club…" aria-label="Rechercher une équipe" /></label><div class="team-picker-grid"><button v-for="team in filtered" :key="team.id" class="team-choice" :disabled="state.favoriteSaving" :class="{ selected: state.favorite === team.id }" @click="chooseFavorite(team.id)"><Badge :team="team" /><span>{{ team.name }}</span><Icon v-if="state.favorite === team.id" name="check" :size="16" /></button></div><p v-if="!filtered.length" class="empty">Aucun club ne correspond à votre recherche.</p><button v-if="state.favorite" :disabled="state.favoriteSaving" class="text-button clear-favorite" @click="chooseFavorite(null)">Retirer mon équipe principale</button></template></dialog></template>
