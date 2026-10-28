<script setup>
import { ref, watch } from 'vue'
const props = defineProps({ team: { type: Object, required: true }, size: { type: String, default: '' } })
const failed = ref(false)
watch(() => props.team.id, () => { failed.value = false })
</script>
<template>
  <span class="club-badge" :class="size" :style="{ '--club': team.color || '#365d88' }" aria-hidden="true">
    <img v-if="team.logo && !failed" :src="team.logo" alt="" @error="failed = true" />
    <span v-else class="club-crest"><span class="crest-star">✦</span><b>{{ team.code || team.name.slice(0, 3) }}</b><span class="crest-line"></span></span>
  </span>
</template>
