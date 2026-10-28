<script setup>
import { alerts, dismissAlert } from '../notifications'
import Icon from './Icon.vue'
</script>
<template><div class="alert-toasts" aria-live="polite" aria-atomic="false"><div v-for="item in alerts.items" :key="item.id" class="alert-toast"><span class="toast-bell"><Icon name="bell" :size="19" /></span><RouterLink :to="item.url" @click="dismissAlert(item.id)"><strong>{{ item.title }}</strong><p>{{ item.body }}</p></RouterLink><button class="icon-button" aria-label="Fermer cette alerte" @click="dismissAlert(item.id)"><Icon name="close" :size="17" /></button></div></div></template>
