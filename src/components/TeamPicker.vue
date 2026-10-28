<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { state, chooseFavorite } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
const dialog = ref(null), query = ref('')
const filtered = computed(() => state.teams.filter(t => t.name.toLocaleLowerCase('fr').includes(query.value.toLocaleLowerCase('fr')) || t.code.toLowerCase().includes(query.value.toLowerCase())))
watch(() => state.pickerOpen, async open => { await nextTick(); if (open) { query.value = ''; dialog.value.showModal() } else dialog.value.close() })
</script>
<template><dialog ref="dialog" class="team-dialog" aria-labelledby="team-picker-title" @close="state.pickerOpen = false" @click="e => { if (e.target === dialog) state.pickerOpen = false }"><div class="dialog-head"><div><span class="eyebrow">VOTRE CLUB, VOTRE PASSION</span><h2 id="team-picker-title">Choisissez votre équipe</h2></div><button class="icon-button" aria-label="Fermer" @click="state.pickerOpen = false"><Icon name="close" /></button></div><p class="muted">Ses rencontres et ses résultats, toujours au premier plan.</p><label class="search-field"><Icon name="search" /><input v-model="query" placeholder="Rechercher un club…" aria-label="Rechercher une équipe" /></label><div class="team-picker-grid"><button v-for="team in filtered" :key="team.id" class="team-choice" :class="{ selected: state.favorite === team.id }" @click="chooseFavorite(team.id)"><Badge :team="team" /><span>{{ team.name }}</span><Icon v-if="state.favorite === team.id" name="check" :size="16" /></button></div><p v-if="!filtered.length" class="empty">Aucun club ne correspond à votre recherche.</p><button v-if="state.favorite" class="text-button clear-favorite" @click="chooseFavorite(null)">Retirer mon équipe favorite</button></dialog></template>
