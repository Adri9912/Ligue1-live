# Comptes — développement local

Les comptes, favoris, quota manuel par utilisateur et rappels sportifs sont préparés localement. L’administration locale est également préparée. Les inscriptions publiques restent désactivées tant que ACCOUNTS_ENABLED n'est pas activé explicitement.

## Configuration privée

Créer deux bases MySQL distinctes, par exemple ligue1_live_local et ligue1_live_test, et des utilisateurs dédiés ayant des droits seulement sur leur base respective. Aucun compte de test ne doit pointer vers la base Alwaysdata.

Dans `.env`, configurer DB_CONNECTION=mysql, DB_HOST / DB_PORT ou DB_SOCKET, DB_DATABASE, DB_USERNAME et DB_PASSWORD ; activer ACCOUNTS_ENABLED=true et SESSION_DRIVER=database. APP_URL doit correspondre à l'origine réellement utilisée pour que les liens signés fonctionnent.

Dans `.env.testing` (ignoré par Git), indiquer les accès à ligue1_live_test. Le contrôle avant RefreshDatabase refuse un autre nom. La CI dispose de son propre service mysql:8.4 et n'accède pas au serveur de production.

Appliquer `php artisan migrate` uniquement sur la base locale confirmée, puis `npm run dev` ou LANCER.sh. Le lanceur configure l'URL locale 127.0.0.1:5173 pour les e-mails. Une prévisualisation compilée est également possible sur le serveur Laravel avec une APP_URL correspondante.

Sur la machine de cette session, un MySQL privé a été initialisé sous `~/tmp/ligue1-live/mysql`, avec accès par socket uniquement. Ce dossier est dans le répertoire personnel, dans un emplacement autorisé par le profil de sécurité MySQL ; il est distinct du `/tmp` système. LOCAL_MYSQL_DATADIR et DB_SOCKET sont privés dans `.env`. Le lanceur peut redémarrer cette instance si son dossier existe ; il ne crée pas de base et ne modifie pas le MySQL système. Conserver ce dossier privé entre les lancements. Ce stockage reste réservé au développement ; les données du site public utiliseront la base Alwaysdata et sa propre sauvegarde.

## E-mails

MAIL_MAILER=array dans les tests et lors des essais navigateur avec des comptes fictifs : aucun envoi externe et aucun lien sensible écrit dans les logs par le transport mail. La prévisualisation locale peut utiliser smtp après configuration privée, pour les inscriptions réelles du propriétaire. Les tests utilisent Notification::fake pour contrôler destinataires et jetons ; le parcours navigateur a employé des liens générés pour un compte éphémère local, sans SMTP.

Service retenu : SMTP Alwaysdata, expéditeur ligue1-live@alwaysdata.net. Configurer SMTP dans le fichier privé uniquement. Tester livraison, vérification et récupération sur une adresse autorisée avant toute ouverture publique. Éviter MAIL_MAILER=log pour les liens de connexion/récupération et configurer les logs HTTP pour ne pas conserver les paramètres sensibles des liens. Les erreurs SMTP et leur comportement devront faire partie de la recette avec le service réel.

## Vérifications

- `APP_URL=http://localhost php artisan test --compact` : API sportive et comptes ; MySQL local de test requis.
- `npm test` puis `npm run build` : interface.
- Parcours navigateur réellement exécuté : inscription, rechargement, deux sessions distinctes, vérification signée, changement d'adresse, rejet d'un mauvais mot de passe, changement/récupération du mot de passe, invalidation de l'autre connexion et suppression finale du compte de test.
- Largeurs 320 / 390 / 768 / 1024 / 1440 ; aucun appel fournisseur pendant les essais en mode pause.

## Favoris et actualisations personnelles

Migrations ajoutées : teams, user_followed_teams, users.primary_team_id et manual_refresh_counts. Les identifiants internes évitent de mélanger ceux des deux fournisseurs. La suppression d'un utilisateur supprime ses associations et compteurs ; elle ne supprime pas les clubs communs.

Les favoris demandent un compte connecté ; la vérification d'adresse sera nécessaire aux futurs e-mails, pas au simple choix d'un club. Un visiteur qui tente de choisir un favori reçoit une invitation d'inscription. Le favori précédent du navigateur reste une proposition à reprendre explicitement après connexion.

Les dix clics sont par utilisateur et jour UTC, partagés entre ses appareils. Les nouveaux GET/POST /api/account/refresh sont authentifiés ; les écritures utilisent CSRF et la vérification d'origine. Le compteur est réservé atomiquement en MySQL avant les appels, y compris si le fournisseur échoue. Le plafond global et le cache partagé restent la protection de consommation ; une lecture sportive publique n'est pas un clic manuel. Le mode Pause n'utilise aucun clic.

La version historique reste utilisée avec ACCOUNTS_ENABLED=false. Ne pas effacer le cache sportif ou ses compteurs lors des migrations.

Tests complémentaires : accès visiteur refusé, principal toujours suivi, absence de doublons, données d'autrui inaccessibles, préférences historiques conservées, dixième clic accepté / onzième refusé, renouvellement UTC et maintien du plafond fournisseur. Le test navigateur réel utilise deux sessions, vérifie ajout/promotion/retrait/rechargement/déconnexion et un compteur préchargé en base. Aucun fournisseur réel n'est appelé pour tester la limite.

## Avant toute mise en ligne

Valider séparément la livraison. Vérifier la base et l'utilisateur dans le panneau Alwaysdata ; ne recopier aucun mot de passe dans Git ou le chat. Sauvegarder et tester la restauration de la base. Adapter le déploiement aux migrations compatibles : le script actuel restaure uniquement le code et ne doit pas prétendre restaurer une base.

Conserver ACCOUNTS_ENABLED=false et SESSION_DRIVER=file tant que tables et SMTP ne sont pas prêts. Les nouvelles migrations peuvent ensuite être exécutées lors d'une intervention autorisée, puis configurer les sessions en base et ouvrir les comptes. Tester les cookies Secure/HttpOnly/SameSite, HTTPS, la limitation des tentatives et la livraison SMTP. Ne jamais lancer migrate:fresh en production.

## Validation SMTP du 3 novembre 2026

Connexion et authentification Alwaysdata réussies. Un unique e-mail de test autorisé a été accepté par SMTP, puis sa réception a été confirmée dans le webmail par le propriétaire. Les tests gardent array. La livraison réelle des rappels sportifs n’a pas été testée : ses cas métier utilisent un transport simulé.

## Rappels deux heures avant le match

Activer REMINDERS_ENABLED=true uniquement lorsque les migrations locales sont présentes. Chaque club suivi affiche sa case de consentement, décochée par défaut. L’adresse doit être vérifiée pour activer un rappel ; sa désactivation reste possible après un changement d’adresse. Le consentement et la vérification doivent précéder H−2 : aucun rattrapage tardif.

Le collecteur prépare les rencontres de la saison entre H−2 et H−2+10 minutes, avec un calendrier reçu il y a dix minutes au plus. En mode Auto, une vérification partagée au moment des rappels respecte le cache et les budgets existants ; elle peut consommer des appels fournisseur, indépendamment du nombre d’inscrits. Le mode Manuel utilise les données disponibles sans forcer leur actualisation ; Pause suspend la préparation et les envois. Un rappel peut donc être omis si les données ne sont pas fiables ou si le quota est épuisé.

La file database/mail et notification_deliveries sont alimentées dans une même transaction. L’unicité utilisateur/rencontre empêche deux rappels pour les deux clubs suivis. Le worker revérifie l’horaire, le statut, le compte, l’adresse et le consentement avant l’envoi. Il annule les rappels obsolètes. Après un report, un rappel annulé peut être préparé à nouveau pour le nouvel horaire ; un rappel déjà envoyé n’est pas renvoyé.

Une connexion SMTP échouée avant envoi peut être retentée trois fois. Un échec après le début d’envoi reste incertain : le serveur peut avoir accepté le message, donc aucun renvoi aveugle. Les traitements interrompus restent également incertains après cinq minutes. Consulter ces statuts lors d’un diagnostic, sans promettre une livraison garantie.

Chaque e-mail contient un lien signé de désabonnement valable trente jours. Sa lecture GET ouvre une confirmation ; seul le POST désactive les rappels sportifs, afin de résister aux robots d’analyse des e-mails. Les e-mails de sécurité ne sont pas désactivés. La gestion dans Mon compte reste disponible après expiration du lien.

Pour une recette sans livraison réelle, lancer le serveur avec MAIL_MAILER=array, REMINDERS_ENABLED=true et FOOTBALL_REFRESH_MODE=pause ; les traitements d’envoi utilisent des fixtures et un transport simulés dans les tests. Ne pas employer de faux destinataires avec smtp activé. Les liens des e-mails utilisent APP_URL : 127.0.0.1:5173 pour le lanceur, domaine HTTPS réel en production.

100 tests Laravel et 33 tests JavaScript passent. Le worker database a été réellement exécuté dans la base de test, avec envoi simulé. Aucun cron de production n’est encore installé.

Prévisualisation locale après recette : SMTP activé pour les inscriptions réelles ; rappels visibles, appels football en pause et aucun planificateur démarré. Repasser explicitement à array avant toute nouvelle recette avec des adresses fictives.

## Administration

/administration est accessible uniquement aux comptes actifs ayant le rôle admin et une adresse vérifiée. Le menu montre son lien à ces comptes. La protection est appliquée à chaque endpoint Laravel, indépendamment du menu Vue.

La liste expose seulement e-mail, statut, rôle, date de création et nombre de clubs suivis ; aucun mot de passe, jeton, identifiant de session ou diagnostic SMTP. Recherche et historique des rappels sont paginés par vingt. La consultation des budgets ne déclenche aucun appel football.

Les changements de statut exigent le mot de passe administrateur. La désactivation ferme les sessions, invalide les liens de récupération existants et annule les rappels en attente, sans effacer les favoris. Elle ne peut pas arrêter un message déjà accepté par SMTP. La réactivation permet une nouvelle connexion, sans renvoyer d’anciens rappels. Les comptes admin ne peuvent pas être désactivés dans cette interface.

Créer d’abord le compte par le parcours ordinaire et vérifier son adresse, puis, depuis un terminal privé :

```bash
php artisan account:make-admin adresse@example.com
```

La commande demande confirmation. Aucun formulaire d’inscription ni endpoint HTTP ne peut attribuer ce rôle. Un deuxième administrateur peut être ajouté plus tard par la même méthode ; toute révocation de rôle se fait également lors d’une intervention privée, pas par une inscription publique.

Le compte propriétaire local est prêt avec l’adresse du projet. Pour choisir son propre mot de passe, utiliser « Mot de passe oublié » sur l’instance locale. Aucun mot de passe n’est affiché dans cette documentation. Les trois messages de recette SMTP ont été reçus et leurs liens de compte ont été utilisés dans Chrome ; le rappel contient explicitement une rencontre fictive de test.

## Dernière activité des rappels

L’administration montre séparément football:reminders (préparation) et football:send-reminders (worker mail borné), avec lancement, fin, dernier succès, résultat et nombre préparé. Le cache fichier partagé entre commandes et web conserve ces deux témoins ; aucune table nouvelle n’est nécessaire. Si le cache est supprimé, l’état devient « Aucun passage enregistré », sans inventer un succès.

Chaque commande planifiée doit passer chaque minute. Une dernière fin datant de plus de cinq minutes, ou un lancement interrompu sans fin depuis plus de cinq minutes, déclenche « Activité en retard ». Un échec ne remplace pas le dernier succès. Pause et désactivation volontaires n’engendrent pas d’alerte ; après reprise, un nouveau passage réussi est attendu. La file peut avoir été traitée correctement alors qu’un message individuel a échoué : lire aussi l’historique des livraisons.

Le panneau vérifie ses données toutes les minutes dans l’onglet visible, sans appel fournisseur ni envoi d’e-mail, et indique l’heure de vérification en heure de Paris. Une panne de lecture est affichée et ne fait pas passer un ancien état pour un contrôle récent. Les autorisations administrateur existantes s’appliquent.
