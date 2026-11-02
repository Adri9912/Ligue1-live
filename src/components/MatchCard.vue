<script setup>
import { state, isLive, isFinished, statusLabel, day, dateKey } from '../state'
import Badge from './Badge.vue'
import Icon from './Icon.vue'
defineProps({ match: { type: Object, required: true }, showDate: Boolean, archive: Boolean, horizontal: Boolean })
</script>
<template>
  <component :is="archive ? 'article' : 'RouterLink'" :to="archive ? undefined : `/matchs/${match.id}`" class="match-card" :class="{ 'live-card': isLive(match), 'home-match-card': horizontal }">
    <div v-if="horizontal" class="fixture-row">
      <span class="row-status" :class="{ 'score-live': isLive(match) }"><span v-if="isLive(match)" class="live-dot" role="img" aria-label="Match en cours"></span>{{ statusLabel(match) }}</span>
      <span class="row-team"><Badge :team="match.home" size="small" /><span>{{ match.home.name }}</span></span>
      <strong class="row-score" :class="{ 'score-live': isLive(match) }">{{ match.homeGoals ?? '–' }}<span>–</span>{{ match.awayGoals ?? '–' }}</strong>
      <span class="row-team row-away"><Badge :team="match.away" size="small" /><span>{{ match.away.name }}</span></span>
      <span class="row-venue"><span :title="match.venueSource === 'club' ? 'Stade du club recevant ; lieu de la rencontre non confirmé' : undefined">{{ match.venue || 'Stade non renseigné' }}</span></span>
    </div>
    <div class="match-top"><span class="match-status" :class="{ live: isLive(match) }"><span v-if="isLive(match)" class="live-dot" role="img" aria-label="Match en cours"></span>{{ statusLabel(match) }}</span><span v-if="showDate" class="match-date">{{ day(match.date, { month: 'short' }) }}</span><Icon v-if="!archive && [match.home.id, match.away.id].includes(state.favorite)" name="heart" :size="14" class="favorite-mark" /><Icon v-else :name="archive ? 'clock' : 'arrow'" :size="14" class="muted" /></div>
    <div class="match-team" v-for="(team, i) in [match.home, match.away]" :key="team.id"><Badge :team="team" size="small" /><span :class="{ 'font-semibold': (i === 0 ? match.homeGoals > match.awayGoals : match.awayGoals > match.homeGoals) && isFinished(match) }">{{ team.name }}</span><b :class="{ 'score-live': isLive(match) }">{{ (i === 0 ? match.homeGoals : match.awayGoals) ?? '–' }}</b></div>
    <div class="match-bottom"><span><span :title="match.venueSource === 'club' ? 'Stade du club recevant ; lieu de la rencontre non confirmé' : undefined">{{ match.venue || 'Stade non renseigné' }}</span></span><span v-if="isLive(match)">Suivre le match <span aria-hidden="true">↗</span></span><span v-else>J{{ match.round || '—' }}</span></div>
  </component>
</template>
