import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Account from './views/Account.vue'
import Admin from './views/Admin.vue'
import Matches from './views/Matches.vue'
import News from './views/News.vue'
import Standings from './views/Standings.vue'
import Team from './views/Team.vue'
import Match from './views/Match.vue'
import Retro from './views/Retro.vue'
import Clubs from './views/Clubs.vue'
import Information from './views/Information.vue'
import Statistics from './views/Statistics.vue'
import NotFound from './views/NotFound.vue'
import './style.css'
import { claimIntro } from './intro-session.mjs'
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/administration', component: Admin, meta: { title: 'Administration', account: true } },
    ...[['/connexion', 'login', 'Connexion'], ['/inscription', 'register', 'Inscription'], ['/mot-de-passe/oublie', 'forgot', 'Mot de passe oublié'], ['/mot-de-passe/reinitialiser', 'reset', 'Nouveau mot de passe'], ['/mon-compte', 'profile', 'Mon compte']].map(([path, accountMode, title]) => ({ path, component: Account, meta: { account: true, accountMode, title } })),
    { path: '/', component: Matches, meta: { title: 'Matchs' } },
    { path: '/actualites', component: News, meta: { title: 'Actualités', information: true } },
    { path: '/classement', component: Standings, meta: { title: 'Classement' } },
    { path: '/classement/:kind(attaques|defenses)', component: Standings, meta: { title: 'Classement des équipes' } },
    { path: '/classement/:kind(buteurs|passeurs)', component: Standings, meta: { title: 'Classement des joueurs' } },
    { path: '/clubs/:id(\\d+)?', component: Clubs, meta: { title: 'Clubs' } },
    { path: '/a-propos', component: Information, meta: { title: 'À propos', information: true } },
    { path: '/contact', component: Information, meta: { title: 'Contact', information: true } },
    { path: '/statistiques', component: Statistics, meta: { title: 'Statistiques' } },
    { path: '/retro', component: Retro, meta: { title: 'Rétro' } },
    { path: '/mon-equipe', component: Team, meta: { title: 'Mon équipe' } },
    { path: '/equipes/:id(\\d+)', component: Team, meta: { title: 'Équipe' } },
    { path: '/matchs/:id(\\d+)', component: Match, meta: { title: 'Match' } },
    { path: '/:pathMatch(.*)*', component: NotFound, meta: { title: 'Page introuvable' } },
  ],
  scrollBehavior: (to, from, savedPosition) => savedPosition || { top: 0 },
})
router.afterEach(to => { document.title = `${to.meta.title} · Ligue 1 Live` })
function reserveIntro() {
  try { return claimIntro(window.localStorage) } catch { return true }
}
// Web Locks serialize simultaneous openings on this origin when supported.
async function mountApp() {
  let showIntro
  try {
    showIntro = navigator.locks
      ? await navigator.locks.request('l1-intro', reserveIntro)
      : reserveIntro()
  } catch { showIntro = reserveIntro() }
  createApp(App, { showIntro }).use(router).mount('#app')
}
mountApp()
