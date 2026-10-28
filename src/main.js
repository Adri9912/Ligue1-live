import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Matches from './views/Matches.vue'
import Standings from './views/Standings.vue'
import Team from './views/Team.vue'
import Match from './views/Match.vue'
import Retro from './views/Retro.vue'
import Clubs from './views/Clubs.vue'
import './style.css'
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: Matches, meta: { title: 'Matchs' } },
    { path: '/classement', component: Standings, meta: { title: 'Classement' } },
    { path: '/classement/:kind(buteurs|passeurs)', component: Standings, meta: { title: 'Classement des joueurs' } },
    { path: '/clubs/:id(\\d+)?', component: Clubs, meta: { title: 'Clubs' } },
    { path: '/retro', component: Retro, meta: { title: 'Rétro' } },
    { path: '/mon-equipe', component: Team, meta: { title: 'Mon équipe' } },
    { path: '/equipes/:id(\\d+)', component: Team, meta: { title: 'Équipe' } },
    { path: '/matchs/:id(\\d+)', component: Match, meta: { title: 'Match' } },
    { path: '/:pathMatch(.*)*', component: { template: '<section class="empty panel"><h1>Cette page a quitté le terrain.</h1><p>Retrouvez les rencontres de Ligue 1.</p><RouterLink to="/" class="button primary">Voir les matchs</RouterLink></section>' }, meta: { title: 'Page introuvable' } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
router.afterEach(to => { document.title = `${to.meta.title} · Ligue 1 Live` })
createApp(App).use(router).mount('#app')
