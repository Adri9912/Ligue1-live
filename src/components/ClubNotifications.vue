<script setup>
import { ref, watch } from 'vue'
import { state } from '../state'
import { alerts, toggleAlerts, saveAlertSettings, allowSystemAlerts, testAlert } from '../notifications'
import Icon from './Icon.vue'
const props = defineProps({ team: { type: Object, required: true } })
const dialog = ref(null)
watch(() => props.team.id, () => { if (dialog.value?.open) dialog.value.close() })
</script>
<template>
  <section class="panel notification-banner" aria-labelledby="notifications-banner-title">
    <span class="notification-icon"><Icon name="bell" /></span>
    <div class="notification-banner-copy"><h2 id="notifications-banner-title">Les alertes de votre club</h2><p>{{ alerts.enabled ? `Alertes activées pour ${team.name}.` : 'Début de match, buts et résultat final.' }}</p></div>
    <button class="button" aria-haspopup="dialog" aria-controls="notifications-dialog" @click="dialog.showModal()">{{ alerts.enabled ? 'Gérer mes alertes' : 'Activer les alertes' }}<Icon name="arrow" :size="14" /></button>
  </section>
  <dialog ref="dialog" id="notifications-dialog" class="team-dialog notification-settings notification-dialog" aria-labelledby="notifications-title">
    <div class="dialog-head"><div><span class="eyebrow">VOTRE CLUB, AU PLUS PRÈS</span><h2 id="notifications-title">Configurer mes alertes</h2></div><button class="icon-button" aria-label="Fermer les réglages des alertes" @click="dialog.close()"><Icon name="close" /></button></div>
    <p class="muted">Choisissez les événements à suivre pour {{ team.name }}.</p>
    <div class="notification-heading notification-dialog-switch"><div><h3>Alertes dans le site</h3><p>Début de match, évolution du score et résultat final.</p></div><button class="button" :class="{ primary: !alerts.enabled }" :aria-pressed="alerts.enabled" @click="toggleAlerts(state.favorite, state.matches)">{{ alerts.enabled ? 'Désactiver les alertes' : 'Activer les alertes' }}</button></div>
    <div v-if="alerts.enabled" class="notification-options"><label><input v-model="alerts.start" type="checkbox" @change="saveAlertSettings" />Début du match</label><label><input v-model="alerts.goals" type="checkbox" @change="saveAlertSettings" />Buts et score</label><label><input v-model="alerts.final" type="checkbox" @change="saveAlertSettings" />Résultat final</label></div>
    <p class="notification-limit"><Icon name="info" :size="14" /><span>Gardez le site ouvert en mode Automatique. En mode Manuel, les événements ne sont vérifiés que lors de vos actualisations ; en Pause, aucune nouvelle donnée n’est récupérée. Les alertes suivent les mises à jour des données (10 minutes avec le réglage gratuit) ; le navigateur peut les retarder en arrière-plan. {{ state.mode === 'demo' ? 'En démo, aucun vrai événement n’est envoyé.' : '' }}</span></p>
    <div class="notification-actions"><button class="text-button" @click="testAlert(team, state.mode === 'demo')">Tester l’aperçu d’une alerte</button><button v-if="alerts.enabled && alerts.permission !== 'granted'" class="text-button" :disabled="alerts.busy || alerts.permission === 'denied' || alerts.permission === 'unsupported'" @click="allowSystemAlerts">{{ alerts.permission === 'denied' ? 'Notifications bloquées dans le navigateur' : alerts.permission === 'unsupported' ? 'Notifications système indisponibles ici' : alerts.busy ? 'Autorisation en cours…' : 'Autoriser les notifications système' }}</button><span v-else-if="alerts.enabled" class="system-alert-status"><Icon name="check" :size="14" />Notifications système autorisées</span></div>
    <p v-if="alerts.message" class="small-text muted" role="status">{{ alerts.message }}</p>
  </dialog>
</template>
