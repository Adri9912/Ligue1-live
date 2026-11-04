<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { lookupPortrait } from '../portraits'
const props = defineProps({ photo:String, lookup:String, name:String, large:Boolean })
const failed = ref(false), portrait = ref(null), root = ref(null)
let visible = false, mounted = true, observer, timer, version = 0
async function load() {
  const current = ++version
  if (!visible || (props.photo && !failed.value) || !props.lookup) return
  clearTimeout(timer)
  const result = await lookupPortrait(props.lookup)
  if (!mounted || current !== version) return
  portrait.value = result.photo
  if (result.retryAt) timer = setTimeout(load, Math.max(1000, result.retryAt - Date.now()))
}
function imageError() {
  if (props.photo && !failed.value) { failed.value=true; load() }
  else { portrait.value=null; failed.value=true }
}
onMounted(() => { observer = new IntersectionObserver(entries => { visible = entries[0].isIntersecting; if (visible) load() }, { rootMargin: '100px' }); observer.observe(root.value) })
onUnmounted(() => { mounted = false; version++; observer?.disconnect(); clearTimeout(timer) })
const initials = computed(() => (props.name || '').trim().split(/\s+/).filter(Boolean).map(word => word[0]).slice(0, 2).join('').toLocaleUpperCase('fr'))
watch(() => [props.photo, props.lookup], () => { version++; clearTimeout(timer); portrait.value=null; failed.value=false; load() })
</script>
<template><span ref="root" class="player-portrait" :class="{large}" aria-hidden="true"><img v-if="(!failed && photo) || portrait" :src="(!failed && photo) || portrait" :width="large ? 96 : 38" :height="large ? 96 : 38" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" @error="imageError" /><span v-else class="player-initials">{{ initials || '?' }}</span></span></template>
