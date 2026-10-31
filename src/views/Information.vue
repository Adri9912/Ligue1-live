<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const isContact = computed(() => route.path === '/contact')
// Adresse publique à renseigner après le choix du propriétaire.
const contactEmail = ''
</script>
<template>
  <div class="page-heading"><span class="eyebrow">LIGUE 1 LIVE · PROJET INDÉPENDANT</span><h1>{{ isContact ? 'Contact' : 'À propos' }}<span class="heading-dot">.</span></h1><p>{{ isContact ? 'Une remarque, une idée ou un problème à signaler ?' : 'Le championnat, au plus près.' }}</p></div>
  <section v-if="isContact" class="panel information-panel"><h2>Parlons du site</h2><p>Vos idées et vos retours permettent d’améliorer Ligue 1 Live.</p><a v-if="contactEmail" :href="`mailto:${contactEmail}`" class="button">Écrire un e-mail</a><p v-else class="contact-pending">Le contact par e-mail sera disponible prochainement.</p><p class="muted">Aucun formulaire ni compte n’est nécessaire.</p></section>
  <div v-else class="information-sections">
    <section class="panel information-panel"><h2>Une seule compétition. Toute votre passion.</h2><p>Ligue 1 Live est un projet personnel consacré au championnat de France : rencontres, résultats, classement, clubs et suivi de votre équipe favorite.</p><p>Le site est développé avec Vue et Laravel. Il s’agit d’un projet indépendant, sans affiliation officielle avec la Ligue ou les clubs.</p></section>
    <section class="panel information-panel"><h2>Données et sources</h2><ul><li><a href="https://highlightly.net/football-api/" target="_blank" rel="noopener noreferrer">Highlightly</a> : matchs, classement et compositions de la saison en cours.</li><li><a href="https://www.api-football.com/" target="_blank" rel="noopener noreferrer">API-Football</a> : effectifs, profils et portraits des joueurs, informations des clubs et archives.</li><li><a href="https://ligue1.com/en/articles/l1_article_289-le-palmares-des-champions-de-ligue-1" target="_blank" rel="noopener noreferrer">Site officiel de la Ligue</a> : source des palmarès présentés dans les fiches des clubs.</li></ul><p>Les données sont conservées en cache pour limiter les appels. Les scores et alertes peuvent donc être différés ; la date de mise à jour et les anciennes réponses sont signalées dans le site.</p><p>Lorsque le lieu d’une rencontre manque, le stade connu du club recevant peut être indiqué comme « stade habituel ». Les informations absentes ne sont pas inventées.</p><p>Les noms, logos et portraits affichés identifient les compétitions, clubs et joueurs concernés ; ils ne sont pas des créations de ce projet.</p></section>
    <section class="panel information-panel"><h2>Vos préférences</h2><p>Le club favori, le thème et les préférences d’alertes sont mémorisés dans votre navigateur. Ils ne sont pas synchronisés entre vos appareils. Les notifications nécessitent votre autorisation et un onglet ouvert.</p><p>Les données fictives, lorsqu’elles sont utilisées, sont explicitement signalées comme démonstration.</p></section>
  </div>
  <RouterLink to="/" class="text-button information-back">Revenir aux matchs</RouterLink>
</template>
