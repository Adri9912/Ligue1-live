<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { account, accountRequest, syncAccount } from '../account'
const loading=ref(true), busy=ref(false), error=ref(''), message=ref(''), overview=ref(null), users=ref(null), deliveries=ref(null), query=ref(''), status=ref(''), target=ref(null), password=ref('')
const activityError=ref('')
let activityTimer
const activityLabels={disabled:'Désactivé',paused:'En pause',never:'Aucun passage enregistré',failed:'Dernier passage en échec',late:'Activité en retard',running:'Traitement en cours',waiting:'En attente de reprise',healthy:'Activité récente'}
const resultLabels={running:'En cours',success:'Réussi',failed:'Échec',skipped:'Suspendu volontairement'}
async function readOverview() {
  try { overview.value=await accountRequest('/admin');activityError.value='' }
  catch(e) { activityError.value='Contrôle indisponible : l’état affiché correspond à la dernière vérification réussie.';throw e }
}
const labels={pending:'En attente',sending:'En cours',sent:'Envoyé',failed:'Échec',uncertain:'À vérifier',cancelled:'Annulé'}
const date=value=>value?new Date(value).toLocaleString('fr-FR',{timeZone:'Europe/Paris'}):'—'
async function search(page=1) { users.value=(await accountRequest(`/admin/users?${new URLSearchParams({q:query.value,page})}`)).users }
async function history(page=1) { deliveries.value=(await accountRequest(`/admin/deliveries?${new URLSearchParams({status:status.value,page})}`)).deliveries }
async function run(action) {
  if(busy.value)return
  busy.value=true;error.value='';message.value=''
  try { await action() } catch(e) { error.value=e.message } finally { busy.value=false }
}
async function refresh() { await readOverview();await search();await history() }
async function changeStatus() {
  await run(async()=>{
    const result=await accountRequest(`/admin/users/${target.value.id}/status`,{method:'PATCH',body:{active:!target.value.active,current_password:password.value}})
    password.value='';target.value=null;await refresh();message.value=result.message
  })
  password.value=''
}
onMounted(async()=>{
  try {await syncAccount();if(account.user?.role==='admin')await refresh()}catch(e){error.value=e.message}finally{loading.value=false}
  activityTimer=setInterval(()=>{if(!document.hidden&&!busy.value&&account.user?.role==='admin'&&account.user.email_verified_at)readOverview().catch(()=>{})},60000)
})
onUnmounted(()=>clearInterval(activityTimer))
</script>
<template>
  <div class="admin-page">
    <div class="page-heading"><span class="eyebrow">ESPACE PRIVÉ</span><h1>Administration<span class="heading-dot">.</span></h1><p>Les comptes, les rappels et la consommation des données.</p></div>
    <p v-if="loading" role="status">Chargement de l’administration…</p>
    <section v-else-if="!account.user" class="panel account-panel"><h2>Connexion nécessaire</h2><RouterLink to="/connexion" class="text-button">Se connecter</RouterLink></section>
    <section v-else-if="account.user.role!=='admin' || !account.user.email_verified_at" class="panel account-panel"><h2>Accès réservé</h2><p>Un compte administrateur avec une adresse vérifiée est nécessaire.</p><RouterLink to="/" class="text-button">Retour aux matchs</RouterLink></section>
    <template v-else>
      <p v-if="error" class="alert error" role="alert">{{ error }}</p><p v-if="message" class="alert warning" role="status">{{ message }}</p>
      <template v-if="overview">
        <section class="admin-stats"><article v-for="item in [[overview.stats.users,'Comptes'],[overview.stats.active,'Comptes actifs'],[overview.stats.verified,'Adresses vérifiées'],[overview.stats.subscribers,'Comptes avec rappels']]" :key="item[1]" class="panel"><strong>{{ item[0] }}</strong><span>{{ item[1] }}</span></article></section>
        <section class="panel account-panel"><div class="panel-title"><h2>Budget API partagé</h2><button class="text-button" :disabled="busy" @click="run(refresh)">Relire les compteurs</button></div><p>Mode {{ ({auto:'automatique',manual:'manuel',pause:'pause'})[overview.refreshMode] }} · Rappels {{ overview.remindersEnabled?'ouverts':'désactivés' }}. Cette lecture ne déclenche aucun appel football.</p><ul class="admin-budget"><li v-for="item in overview.budgets" :key="item.provider"><strong>{{ item.provider==='highlightly'?'Highlightly':'API-Football' }}</strong><span>{{ item.configured?`${item.budget.used} / ${item.budget.limit} appels aujourd’hui (UTC)`:'Clé non configurée' }}</span></li></ul><p class="small-text muted">Les réglages globaux restent dans la configuration privée du serveur.</p></section>
        <section v-if="overview.reminderActivity" class="panel account-panel reminder-activity" aria-labelledby="reminder-activity-title">
          <div class="panel-title"><h2 id="reminder-activity-title">Activité des rappels</h2><button class="text-button" :disabled="busy" @click="run(readOverview)">Vérifier l’activité</button></div>
          <p>Exécution attendue chaque minute. Une absence d’activité pendant plus de {{ overview.reminderActivity.lateAfterMinutes }} minutes est signalée. Heures de Paris.</p>
          <p v-if="activityError" class="alert warning" role="status">{{ activityError }}</p>
          <ul class="reminder-activity-list"><li v-for="task in overview.reminderActivity.tasks" :key="task.task" :data-state="task.state"><div class="reminder-activity-heading"><h3>{{ task.label }}</h3><strong class="reminder-activity-state">{{ activityLabels[task.state] }}</strong></div>
            <p v-if="task.state==='never'">Aucun lancement connu. Vérifiez la tâche planifiée après son activation.</p>
            <p v-else-if="task.state==='late'">Le traitement ne donne plus de signe d’activité. Vérifiez la tâche planifiée et son accès à l’application.</p>
            <p v-else-if="task.state==='failed'">La dernière exécution a échoué. Vérifiez les accès et les traitements du serveur ; les détails privés ne sont pas affichés ici.</p>
            <p v-else-if="['disabled','paused'].includes(task.state)">Arrêt volontaire : aucune alerte d’inactivité.</p>
            <dl><div><dt>Dernier lancement</dt><dd>{{ date(task.startedAt) }}</dd></div><div><dt>Dernière fin</dt><dd>{{ date(task.finishedAt) }}</dd></div><div><dt>Dernier succès</dt><dd>{{ date(task.lastSuccessAt) }}</dd></div><div><dt>Dernier résultat</dt><dd>{{ resultLabels[task.result] || '—' }}<template v-if="task.prepared!==null"> · {{ task.prepared }} rappel(s) préparé(s)</template></dd></div></dl>
          </li></ul><p class="small-text muted">État vérifié le {{ date(overview.reminderActivity.checkedAt) }}. Relu chaque minute quand cette page est visible. Ces témoins confirment l’exécution des traitements ; les envois se consultent dans l’historique ci-dessous.</p>
        </section>
      </template>
      <section class="panel account-panel"><h2>Utilisateurs</h2><form class="admin-search" @submit.prevent="run(()=>search())"><label for="admin-query">Rechercher par adresse e-mail</label><input id="admin-query" v-model="query" type="search" maxlength="100" /><button class="button" :disabled="busy">Rechercher</button></form>
        <ul class="admin-users"><li v-for="user in users?.data" :key="user.id"><div><strong>{{ user.email }}</strong><p>{{ user.role==='admin'?'Administrateur':'Utilisateur' }} · {{ user.active?'Actif':'Désactivé' }} · {{ user.email_verified_at?'Adresse vérifiée':'Adresse à vérifier' }}</p><p>{{ user.followed_teams_count }} club(s) suivi(s) · Inscrit le {{ date(user.created_at) }}</p></div><button v-if="user.role!=='admin'" class="button" :disabled="busy" @click="target=user;password=''">{{ user.active?'Désactiver':'Réactiver' }}</button></li></ul><p v-if="users && !users.total" class="muted">Aucun compte trouvé.</p>
        <nav v-if="users?.last_page>1" class="admin-pagination" aria-label="Pages des utilisateurs"><button class="button" :disabled="busy || users.current_page===1" @click="run(()=>search(users.current_page-1))">Précédente</button><span>{{ users.current_page }} / {{ users.last_page }}</span><button class="button" :disabled="busy || users.current_page===users.last_page" @click="run(()=>search(users.current_page+1))">Suivante</button></nav>
        <form v-if="target" class="admin-confirm" @submit.prevent="changeStatus"><h3>{{ target.active?'Désactiver':'Réactiver' }} {{ target.email }} ?</h3><p>{{ target.active?'Ses connexions seront fermées et ses rappels en attente annulés. Ses favoris seront conservés.':'La connexion sera à nouveau possible. Les anciens rappels annulés ne seront pas renvoyés.' }}</p><label>Votre mot de passe administrateur<input v-model="password" type="password" autocomplete="current-password" required :disabled="busy" /></label><div class="account-links"><button class="button" :disabled="busy">Confirmer</button><button class="text-button" type="button" :disabled="busy" @click="target=null;password=''">Annuler</button></div></form>
      </section>
      <section class="panel account-panel"><h2>Historique des rappels</h2><p class="small-text muted">« Envoyé » signifie accepté par le serveur d’envoi. « À vérifier » indique un envoi incertain : aucun renvoi automatique pour éviter les doublons.</p><label for="delivery-status">Afficher<select id="delivery-status" v-model="status" :disabled="busy" @change="run(()=>history())"><option value="">Tous les statuts</option><option v-for="(label,key) in labels" :key="key" :value="key">{{ label }}</option></select></label>
        <ul class="admin-deliveries"><li v-for="delivery in deliveries?.data" :key="delivery.id"><strong>{{ delivery.match }}</strong><span>{{ delivery.email }}</span><span>{{ labels[delivery.status] }} · {{ delivery.attempts }} tentative(s)</span><small>Match : {{ date(delivery.kickoffAt) }} (Paris) · {{ delivery.sentAt?'Envoi : '+date(delivery.sentAt):'Préparé : '+date(delivery.createdAt) }}</small></li></ul><p v-if="deliveries && !deliveries.total" class="muted">Aucun rappel pour ce filtre.</p>
        <nav v-if="deliveries?.last_page>1" class="admin-pagination" aria-label="Pages des rappels"><button class="button" :disabled="busy || deliveries.current_page===1" @click="run(()=>history(deliveries.current_page-1))">Précédente</button><span>{{ deliveries.current_page }} / {{ deliveries.last_page }}</span><button class="button" :disabled="busy || deliveries.current_page===deliveries.last_page" @click="run(()=>history(deliveries.current_page+1))">Suivante</button></nav>
      </section>
    </template>
  </div>
</template>
