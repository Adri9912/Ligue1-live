<script setup>
import NewsFeed from '../components/NewsFeed.vue'
</script>
<template><div class="news-page"><div class="page-heading"><h1>Actualités<span class="heading-dot">.</span></h1><p>Les dernières 24 heures de la Ligue 1 et des clubs français, par Maxifoot.</p></div><NewsFeed /></div></template>
<style scoped>
.news-page { max-width:880px; margin:0 auto; padding-bottom:12px; }
.news-page .page-heading { padding:40px 0 30px; }
.news-page .page-heading h1 { font-size:clamp(36px,5vw,52px); line-height:1.1; letter-spacing:-.035em; }
.news-page .page-heading p { margin-top:14px; font-size:15px; line-height:1.7; max-width:55ch; }
@media(max-width:600px) { .news-page .page-heading { padding:28px 0 24px; } .news-page .page-heading p { font-size:14px; margin-top:12px; } }
</style>
