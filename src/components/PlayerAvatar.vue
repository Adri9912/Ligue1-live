<script setup>
import { ref, watch } from 'vue'
const props = defineProps({ photo:String, name:String, large:Boolean })
const failed = ref(false)
watch(() => props.photo, () => { failed.value=false })
</script>
<template><span class="player-portrait" :class="{large}" aria-hidden="true"><img v-if="photo && !failed" :src="photo" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" @error="failed=true" /><svg v-else viewBox="0 0 40 40" fill="none"><circle cx="20" cy="14" r="7" fill="currentColor" /><path d="M7 37c0-9 5-14 13-14s13 5 13 14" fill="currentColor" /></svg></span></template>
