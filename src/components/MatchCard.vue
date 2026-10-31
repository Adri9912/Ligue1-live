<script setup>
import { state, isLive, isFinished, statusLabel, day, dateKey } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
defineProps({ match: { type: Object, required: true }, showDate: Boolean, archive: Boolean, compact: Boolean })
</script>
<template>
  <component :is="archive ? 'article' : 'RouterLink'" :to="archive ? undefined : `/matchs/${match.id}`" class="match-card" :class="{ 'live-card': isLive(match), 'compact-match': compact }">
    <div class="match-top"><span class="match-status" :class="{ live: isLive(match) }"><span v-if="isLive(match)" class="live-dot" role="img" aria-label="Match en cours"></span>{{ statusLabel(match) }}</span><span v-if="showDate" class="match-date">{{ day(match.date, { month: 'short' }) }}</span><Icon v-if="!archive && [match.home.id, match.away.id].includes(state.favorite)" name="heart" :size="14" class="favorite-mark" /><Icon v-else :name="archive ? 'clock' : 'arrow'" :size="14" class="muted" /></div>
    <div class="match-team" v-for="(team, i) in [match.home, match.away]" :key="team.id"><Badge :team="team" size="small" /><span :class="{ 'font-semibold': (i === 0 ? match.homeGoals > match.awayGoals : match.awayGoals > match.homeGoals) && isFinished(match) }">{{ team.name }}</span><b :class="{ 'score-live': isLive(match) }">{{ (i === 0 ? match.homeGoals : match.awayGoals) ?? '–' }}</b></div>
    <div class="match-bottom"><span>{{ match.venue || 'Stade non renseigné' }}<span v-if="match.venueSource === 'club'"> (stade habituel)</span></span><span v-if="isLive(match)">Suivre le match <span aria-hidden="true">↗</span></span><span v-else>J{{ match.round || '—' }}</span></div>
  </component>
</template>
