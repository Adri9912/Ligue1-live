<script setup>
import { state } from '../state'
import ClubLeaders from '../components/ClubLeaders.vue'
</script>
<template>
 <div class="page-heading"><span class="eyebrow">LE CHAMPIONNAT EN CHIFFRES</span><h1>Les statistiques<span class="heading-dot">.</span></h1><p>Les équipes les plus efficaces et les classements des joueurs.</p></div>
 <nav class="standings-subnav statistics-nav" aria-label="Statistiques de Ligue 1"><a href="#attaques">Attaques</a><a href="#defenses">Défenses</a><RouterLink to="/classement/buteurs">Buteurs<small v-if="state.source === 'highlightly'" class="coming-soon-badge">À venir</small></RouterLink><RouterLink to="/classement/passeurs">Passeurs<small v-if="state.source === 'highlightly'" class="coming-soon-badge">À venir</small></RouterLink></nav>
 <div class="club-leaders-grid"><ClubLeaders id="attaques" kind="attack" :limit="18" /><ClubLeaders id="defenses" kind="defense" :limit="18" /></div>
 <p class="update-note">Statistiques calculées à partir du classement publié. Les classements buteurs et passeurs restent dépendants des données disponibles chez le fournisseur.</p>
</template>
