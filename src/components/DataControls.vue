<script setup>
import { ref, watch } from 'vue'
import { state, load, setRefreshMode, time, day } from '../state'
import Icon from './Icon.vue'
import DataFreshness from './DataFreshness.vue'
const dialog = ref(null), selected = ref('auto'), saving = ref(false), error = ref('')
const labels = { auto: 'Automatique', manual: 'Actualisation manuelle', pause: 'Appels en pause' }
function openSettings() { selected.value = state.refreshMode; error.value = ''; dialog.value.showModal() }
async function save() {
  saving.value = true; error.value = ''
  try { await setRefreshMode(selected.value); dialog.value.close() }
  catch (e) { error.value = e.message }
  finally { saving.value = false }
}
watch(() => state.refreshMode, value => { if (!dialog.value?.open) selected.value = value })
</script>
<template>
  <section class="data-control-bar" aria-label="Gestion des appels API">
    <div class="data-control-state"><Icon name="refresh" :size="15" /><span>{{ labels[state.refreshMode] }}</span><small v-if="state.mode === 'demo'">Démo · aucun appel réel</small><small v-else-if="state.budget && (state.budget.remaining === 0 || state.budget.providerRemaining === 0)">Quota atteint · données en cache</small></div>
    <DataFreshness v-if="state.mode === 'api'" :fetched-at="state.fetchedAt" :stale="state.stale || !!state.error" label="Matchs et classement" />
    <div class="data-control-actions"><button class="text-button" :disabled="!state.controlReady || state.refreshMode === 'pause' || state.refreshing" @click="load({ manual: true })">{{ state.refreshing ? 'Actualisation…' : 'Actualiser' }}</button><button class="button settings-trigger" :disabled="!state.controlReady" @click="openSettings">Réglages</button></div>
  </section>
  <dialog ref="dialog" class="team-dialog data-settings-dialog" aria-labelledby="data-settings-title"><div class="dialog-head"><div><span class="eyebrow">ÉCONOMISER LES APPELS</span><h2 id="data-settings-title">Actualisation des données</h2></div><button class="icon-button" aria-label="Fermer les réglages" :disabled="saving" @click="dialog.close()"><Icon name="close" /></button></div>
    <p class="muted">Le réglage est commun aux onglets de ce serveur et reste mémorisé après son redémarrage.</p>
    <fieldset class="refresh-mode-options"><legend class="sr-only">Choisir le mode d’actualisation</legend><label><input v-model="selected" type="radio" value="auto" name="refresh-mode" /><span><strong>Automatique pendant les matchs</strong><small>Calendrier quotidien ; scores toutes les 10 minutes pendant la plage d’un match.</small></span></label><label><input v-model="selected" type="radio" value="manual" name="refresh-mode" /><span><strong>Manuel</strong><small>Les données en cache s’affichent. Un appel au fournisseur nécessite un clic d’actualisation.</small></span></label><label><input v-model="selected" type="radio" value="pause" name="refresh-mode" /><span><strong>Pause</strong><small>Aucun nouvel appel au fournisseur, même en ouvrant une rubrique. Les données en cache restent consultables.</small></span></label></fieldset>
    <div class="data-schedule"><h3>Fréquences économes</h3><dl><div><dt>Calendrier, horaires et équipes</dt><dd>Une fois par jour</dd></div><div><dt>Scores pendant les matchs</dt><dd>Toutes les 10 minutes</dd></div><div><dt>Classement</dt><dd>Chaque jour et après les matchs · au plus une fois par heure</dd></div><div><dt>Buteurs et passeurs</dt><dd>Une fois par jour</dd></div><div><dt>Détails d’un match</dt><dd>À l’ouverture · cache de 15 minutes</dd></div></dl></div>
    <div v-if="state.budget" class="quota-summary"><h3>{{ state.mode === 'demo' ? 'Aucun appel réel en démonstration' : `${state.budget.remaining} appels disponibles sur le budget local` }}</h3><p v-if="state.mode === 'api'">{{ state.budget.used }} utilisés sur {{ state.budget.limit }}. Remise à zéro le {{ day(state.budget.resetAt, { month: 'short' }) }} à {{ time(state.budget.resetAt) }} (heure de Paris).</p><p v-if="state.budget.providerRemaining !== null">Quota restant annoncé par le fournisseur : {{ state.budget.providerRemaining }}<span v-if="state.budget.observedAt">, observé à {{ time(state.budget.observedAt) }}</span>.</p><p v-else class="muted">Le budget local ne compte pas les appels effectués avec la même clé dans d’autres applications.</p></div>
    <p class="data-settings-note">Site fermé : aucun appel automatique. Les minuteurs peuvent être retardés en arrière-plan. Un appel déjà envoyé peut se terminer après une pause. Les boutons manuels respectent les délais minimaux du cache et le budget.</p>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p><div class="data-settings-footer"><button class="button" :disabled="saving" @click="dialog.close()">Annuler</button><button class="button primary" :disabled="saving" @click="save">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button></div>
  </dialog>
</template>
