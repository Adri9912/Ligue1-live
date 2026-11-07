<script setup>
import { computed, onMounted } from 'vue'
import { news, loadNews } from '../news'
import Icon from './Icon.vue'

const props = defineProps({ compact: Boolean })
const articles = computed(() => props.compact ? news.articles.slice(0, 2) : news.articles)
const date = value => new Intl.DateTimeFormat('fr-FR', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }).format(new Date(value))
onMounted(loadNews)
</script>
<template>
  <section class="news-feed" :class="{ 'news-feed-compact': compact }" :aria-label="compact ? 'Les deux dernières actualités' : 'Le fil des actualités'" :aria-busy="news.loading">
    <div v-if="compact" class="news-heading"><h2>Actualités<span class="heading-dot">.</span></h2><RouterLink to="/actualites">Tout voir <Icon name="arrow" :size="16" /></RouterLink></div>
    <p v-if="news.loading && !news.loaded" class="news-message" role="status">Chargement des actualités…</p>
    <div v-else-if="news.error" class="news-message" role="status"><p>{{ news.articles.length ? 'Le fil n’a pas pu être actualisé. Les dernières brèves reçues restent disponibles.' : news.error }}</p><button class="text-button" :disabled="news.loading" @click="loadNews({ force: true })">Réessayer</button></div>
    <p v-else-if="!articles.length" class="news-message">Aucune brève disponible pour le moment.</p>
    <div v-if="!compact && articles.length" class="news-toolbar"><span>{{ articles.length }} brèves à lire</span><span v-if="news.fetchedAt">Mis à jour le {{ date(news.fetchedAt * 1000) }}</span></div>
    <ul v-if="articles.length" class="news-list">
      <li v-for="article in articles" :key="article.id"><a :href="article.url" target="_blank" rel="noopener noreferrer"><span class="news-meta">{{ article.source }} <span aria-hidden="true">·</span> <time :datetime="article.publishedAt">{{ date(article.publishedAt) }}</time></span><component :is="compact ? 'h3' : 'h2'" class="news-title">{{ article.title }}<span class="sr-only"> (nouvel onglet)</span></component><span class="news-open" aria-hidden="true"><Icon name="external" :size="compact ? 15 : 19" /></span></a></li>
    </ul>
    <p class="news-source">Titres : <a href="https://www.maxifoot.fr/football-webmaster-rss.html" target="_blank" rel="noopener noreferrer">Maxifoot</a> · Ligue 1 et clubs français. Les articles s’ouvrent chez l’éditeur.</p>
  </section>
</template>
<style scoped>
.news-feed { min-width:0; }
.news-feed-compact { padding:20px; border:1px solid var(--line); border-radius:14px; background:var(--surface); }
.news-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
.news-heading h2 { font-size:22px; }
.news-heading>a { display:inline-flex; align-items:center; gap:6px; min-height:44px; font-size:13px; font-weight:650; color:var(--green-ink); }
.news-list { list-style:none; margin:0; padding:0; }
.news-list li+li { border-top:1px solid var(--line); }
.news-list a { position:relative; isolation:isolate; display:grid; grid-template-columns:minmax(0,1fr) 40px; column-gap:16px; align-items:center; min-height:44px; padding:24px; touch-action:manipulation; }
.news-list a::before { content:''; position:absolute; z-index:-1; inset:0; background:var(--soft); opacity:0; transition:opacity 160ms ease; pointer-events:none; }
.news-feed:not(.news-feed-compact) .news-list { border:1px solid var(--line); border-radius:16px; overflow:hidden; background:var(--surface); transition:opacity 200ms cubic-bezier(.23,1,.32,1); }
.news-feed:not(.news-feed-compact) .news-list li:first-child a { padding-top:30px; padding-bottom:30px; }
.news-feed:not(.news-feed-compact) .news-list li:first-child a::before { opacity:.6; }
.news-feed:not(.news-feed-compact) .news-list li:first-child .news-title { font-size:27px; line-height:1.35; letter-spacing:-.025em; }
.news-list a:focus-visible { outline-offset:-4px; }
.news-toolbar { display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px 20px; margin-bottom:14px; color:var(--muted); font-size:12px; line-height:1.5; }
.news-meta { display:flex; flex-wrap:wrap; gap:6px; font-size:12px; line-height:1.6; color:var(--muted); }
.news-title { grid-column:1; display:block; margin-top:7px; font-size:19px; line-height:1.5; letter-spacing:-.015em; font-weight:650; overflow-wrap:anywhere; }
.news-open { grid-column:2; grid-row:1 / 3; display:flex; align-items:center; justify-content:center; width:40px; height:40px; color:var(--green-ink); border:1px solid var(--line); border-radius:50%; }
.news-open svg { transition:transform 160ms cubic-bezier(.23,1,.32,1); }
.news-feed-compact .news-title { font-size:15px; }
.news-feed-compact .news-list a { grid-template-columns:minmax(0,1fr) 24px; padding:13px 0; }
.news-feed-compact .news-open { width:24px; height:24px; border:0; border-radius:0; }
.news-source { margin-top:12px; color:var(--muted); font-size:12px; }
.news-source a { text-decoration:underline; text-underline-offset:3px; }
.news-message { padding:16px 0; font-size:14px; color:var(--muted); }
.news-list a:active::before { opacity:1; }
@starting-style { .news-feed:not(.news-feed-compact) .news-list { opacity:0; } }
@media(hover:hover) and (pointer:fine) { .news-list a:hover::before { opacity:1; } .news-list a:hover .news-title { color:var(--green-ink); } .news-list a:hover .news-open svg { transform:translate(2px,-2px); } }
@media(prefers-reduced-motion:reduce) { .news-list a::before { transition:none; } .news-open svg { transition:none; transform:none !important; } .news-feed:not(.news-feed-compact) .news-list { transition-duration:100ms; } }
@media(max-width:600px) { .news-feed-compact { padding:16px; } .news-list a { padding:20px 16px; grid-template-columns:minmax(0,1fr) 32px; column-gap:12px; } .news-open { width:32px; height:32px; } .news-title { font-size:17px; } .news-feed:not(.news-feed-compact) .news-list li:first-child .news-title { font-size:23px; } .news-toolbar { font-size:12px; } }
</style>
