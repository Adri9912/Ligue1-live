<script setup>
import { install } from '../install'
import Icon from './Icon.vue'
</script>
<template>
  <div v-if="!install.state.installed && (install.state.available || install.state.pending)" class="install-shortcut">
    <button class="drawer-preference" :disabled="install.state.pending" @click="install.prompt">
      <img src="/icons/icon-192.png" width="36" height="36" alt="" />
      <span><strong>Ajouter à l’écran d’accueil</strong><small>Une icône pour ouvrir Ligue 1 Live</small></span>
      <Icon name="plus" :size="18" />
    </button>
  </div>
</template>
