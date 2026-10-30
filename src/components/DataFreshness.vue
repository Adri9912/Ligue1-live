<script setup>
import { computed } from 'vue'
import { day, time } from '../state'
import Icon from './Icon.vue'
const props = defineProps({ fetchedAt: [Number, String], stale: Boolean, label: String })
const valid = computed(() => props.fetchedAt != null && Number.isFinite(new Date(props.fetchedAt).getTime()))
const clock = computed(() => time(props.fetchedAt).replace(':', ' h '))
</script>
<template>
  <div v-if="valid" class="data-freshness" :class="{ 'is-stale': stale }" role="status">
    <Icon :name="stale ? 'info' : 'clock'" :size="14" />
    <div><p><span v-if="label" class="freshness-label">{{ label }} · </span>Mis à jour à <strong>{{ clock }}</strong><span class="freshness-date">{{ day(fetchedAt, { month: 'short', year: 'numeric' }) }} (Paris)</span></p><p v-if="stale" class="freshness-warning">Ancienne réponse affichée · les données n’ont pas pu être actualisées.</p></div>
  </div>
</template>
