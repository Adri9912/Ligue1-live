<script setup>
import FollowedClubs from '../components/FollowedClubs.vue'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { account, syncAccount, accountRequest } from '../account'
const route = useRoute(), router = useRouter()
const mode = computed(() => route.meta.accountMode)
const form = reactive({ email: '', password: '', password_confirmation: '', current_password: '' })
const profilePassword = ref(''), securityPassword = ref(''), sessionsPassword = ref(''), deletePassword = ref('')
const initializing = ref(true)
let initializationVersion = 0
const busy = ref(false), error = ref(''), message = ref(''), sessions = ref([]), deleting = ref(false)
const titles = { login: 'Heureux de vous retrouver.', register: 'Votre place dans les tribunes.', forgot: 'Retrouver votre compte.', reset: 'Un nouveau mot de passe.', profile: 'Mon compte.' }
const title = computed(() => titles[mode.value])
function clearPasswords() { form.password = ''; form.password_confirmation = ''; form.current_password = ''; profilePassword.value = ''; securityPassword.value = ''; sessionsPassword.value = ''; deletePassword.value = '' }
async function initialize() {
  const version = ++initializationVersion
  initializing.value = true
  error.value = ''; message.value = ''; clearPasswords(); deleting.value = false
  try {
    await syncAccount()
    if (version !== initializationVersion) return
    form.email = account.user?.email || (typeof route.query.email === 'string' ? route.query.email : '')
    if (mode.value === 'profile' && account.enabled && !account.user) return router.replace('/connexion')
    if (['login', 'register'].includes(mode.value) && account.user) return finishLogin()
    if (mode.value === 'profile' && account.user) {
      await loadSessions()
      if (route.query.adresse === 'verifiee') message.value = 'Votre adresse e-mail est vérifiée.'
      if (route.query.adresse === 'lien-invalide') error.value = 'Ce lien est invalide ou a expiré. Vous pouvez demander un nouveau lien de vérification.'
    }
  } catch (e) { if (version === initializationVersion) error.value = e.message }
  finally { if (version === initializationVersion) initializing.value = false }
}
function finishLogin() {
  // Seuls les liens de vérification internes peuvent poursuivre une connexion.
  if (typeof route.query.redirect === 'string') {
    const url = new URL(route.query.redirect, window.location.origin)
    if (url.origin === window.location.origin && /^\/api\/account\/email\/verify\/\d+\/[a-f0-9]{40}$/.test(url.pathname)) { window.location.assign(url.href); return }
  }
  return router.replace('/mon-compte')
}
async function loadSessions() { sessions.value = (await accountRequest('/sessions')).sessions }
async function perform(path, method, body, after) {
  if (busy.value) return
  busy.value = true; error.value = ''; message.value = ''
  try {
    const data = await accountRequest(path, { method, body })
    message.value = data.message || ''
    if (after) await after()
  } catch (e) { error.value = e.message; if (e.status === 401 && mode.value === 'profile') await router.replace('/connexion') }
  finally { busy.value = false; clearPasswords() }
}
function submit() {
  if (mode.value === 'register') return perform('/register', 'POST', { email: form.email, password: form.password, password_confirmation: form.password_confirmation }, finishLogin)
  if (mode.value === 'login') return perform('/login', 'POST', { email: form.email, password: form.password }, finishLogin)
  if (mode.value === 'forgot') return perform('/forgot-password', 'POST', { email: form.email })
  if (mode.value === 'reset') return perform('/reset-password', 'POST', { email: form.email, token: route.query.token, password: form.password, password_confirmation: form.password_confirmation })
}
onMounted(initialize)
watch(() => route.fullPath, initialize)
</script>
<template>
  <div class="account-page">
    <div class="page-heading"><span class="eyebrow">VOTRE ESPACE LIGUE 1 LIVE</span><h1>{{ title }}</h1><p>{{ mode === 'profile' ? 'Votre adresse, votre sécurité et vos connexions.' : mode === 'login' ? 'Connectez-vous pour retrouver vos clubs et vos préférences.' : 'Un compte gratuit pour la suite de votre expérience Ligue 1 Live.' }}</p></div>
    <p v-if="error" class="alert error" role="alert">{{ error }}</p>
    <p v-if="message" class="alert warning" role="status">{{ message }}</p>
    <p v-if="!account.ready || initializing" role="status">Chargement de votre espace…</p>
    <section v-else-if="!account.enabled" class="panel account-panel"><h2>Les comptes arrivent bientôt</h2><p>Les inscriptions ne sont pas encore ouvertes.</p><RouterLink to="/" class="text-button">Retour aux matchs</RouterLink></section>
    <form v-else-if="mode !== 'profile'" class="panel account-panel" @submit.prevent="submit">
      <h2>{{ ({ login: 'Se connecter', register: 'Créer mon compte', forgot: 'Recevoir un lien', reset: 'Choisir un mot de passe' })[mode] }}</h2>
      <label>Adresse e-mail<input v-model="form.email" name="email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" /></label>
      <template v-if="mode !== 'forgot'">
        <label>{{ mode === 'login' ? 'Mot de passe' : 'Nouveau mot de passe' }}<input v-model="form.password" name="password" type="password" :autocomplete="mode === 'login' ? 'current-password' : 'new-password'" :minlength="mode === 'login' ? undefined : 12" maxlength="72" required :disabled="busy" aria-describedby="password-help" /></label>
        <p id="password-help" class="account-help">{{ mode === 'login' ? 'Votre mot de passe reste confidentiel.' : 'Au moins 12 caractères. Une phrase longue est facile à retenir.' }}</p>
      </template>
      <label v-if="['register', 'reset'].includes(mode)">Confirmer le mot de passe<input v-model="form.password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required :disabled="busy" /></label>
      <button class="button primary full" :disabled="busy">{{ busy ? 'Un instant…' : ({ login: 'Se connecter', register: 'Créer mon compte', forgot: 'Envoyer le lien', reset: 'Enregistrer le mot de passe' })[mode] }}</button>
      <p v-if="mode === 'register'" class="account-help">Vous recevrez un e-mail pour vérifier votre adresse. Les e-mails sportifs nécessiteront une activation séparée.</p>
      <div class="account-links account-entry-links"><RouterLink v-if="mode === 'login'" to="/inscription">Créer un compte</RouterLink><RouterLink v-if="mode === 'login'" to="/mot-de-passe/oublie">Mot de passe oublié ?</RouterLink><RouterLink v-if="mode !== 'login'" to="/connexion">Retour à la connexion</RouterLink></div>
      <RouterLink v-if="['login', 'register'].includes(mode)" to="/" class="guest-continue">Continuer sans s’inscrire</RouterLink>
    </form>
    <template v-else-if="account.user">
      <section class="panel account-panel account-identity"><div><h2>Votre compte</h2><p>{{ account.user.email }}</p><span class="data-pill">{{ account.user.email_verified_at ? 'ADRESSE VÉRIFIÉE' : 'ADRESSE À VÉRIFIER' }}</span></div><button class="button" :disabled="busy" @click="perform('/logout', 'POST', {}, () => router.replace('/connexion'))">Se déconnecter</button></section>
      <section v-if="!account.user.email_verified_at" class="panel account-panel"><h2>Vérifiez votre adresse</h2><p>Ouvrez le lien reçu par e-mail pour confirmer que cette adresse vous appartient.</p><button class="button" :disabled="busy" @click="perform('/email/resend', 'POST', {})">Renvoyer le lien de vérification</button></section>
      <FollowedClubs />
      <div class="account-grid">
        <form class="panel account-panel" @submit.prevent="perform('/profile', 'PATCH', { email: form.email, current_password: profilePassword })"><h2>Adresse e-mail</h2><label>Nouvelle adresse<input v-model="form.email" type="email" autocomplete="email" maxlength="254" required :disabled="busy" /></label><label>Mot de passe actuel<input v-model="profilePassword" type="password" autocomplete="current-password" required :disabled="busy" /></label><p class="account-help">Une modification demande de vérifier la nouvelle adresse.</p><button class="button" :disabled="busy">Enregistrer l’adresse</button></form>
        <form class="panel account-panel" @submit.prevent="perform('/password', 'PUT', { current_password: securityPassword, password: form.password, password_confirmation: form.password_confirmation }, loadSessions)"><h2>Mot de passe</h2><label>Mot de passe actuel<input v-model="securityPassword" type="password" autocomplete="current-password" required :disabled="busy" /></label><label>Nouveau mot de passe<input v-model="form.password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required :disabled="busy" /></label><label>Confirmer<input v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required :disabled="busy" /></label><p class="account-help">Au moins 12 caractères. Les autres connexions seront fermées.</p><button class="button" :disabled="busy">Modifier mon mot de passe</button></form>
      </div>
      <section class="panel account-panel"><h2>Connexions actives</h2><ul class="account-sessions"><li v-for="(session, i) in sessions" :key="i"><strong>{{ session.current ? 'Ce navigateur' : 'Autre connexion' }}</strong><span>{{ new Date(session.lastActiveAt).toLocaleString('fr-FR') }}</span></li></ul><form @submit.prevent="perform('/sessions', 'DELETE', { current_password: sessionsPassword }, loadSessions)"><label>Confirmer avec votre mot de passe<input v-model="sessionsPassword" type="password" autocomplete="current-password" required :disabled="busy" /></label><button class="button" :disabled="busy">Fermer les autres connexions</button></form></section>
      <section class="panel account-panel account-danger"><h2>Supprimer mon compte</h2><p>La suppression est définitive et ferme vos connexions.</p><button v-if="!deleting" class="button" :disabled="busy" @click="deleting = true">Demander la suppression</button><form v-else @submit.prevent="perform('/', 'DELETE', { current_password: deletePassword }, () => router.replace('/connexion'))"><label>Confirmer avec votre mot de passe<input v-model="deletePassword" type="password" autocomplete="current-password" required :disabled="busy" /></label><div class="account-links"><button class="button" :disabled="busy">Supprimer définitivement mon compte</button><button class="text-button" type="button" :disabled="busy" @click="deleting = false; clearPasswords()">Annuler</button></div></form></section>
    </template>
  </div>
</template>
