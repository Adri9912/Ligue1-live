# Ligue 1 Live — contexte du projet

## État vérifié le 3 novembre 2026
- Projet existant : Vue 3, Vite, Tailwind, Laravel 13 ; hébergement PHP Alwaysdata.
- La version publique conserve ses fonctionnalités sportives. Highlightly fournit calendrier, résultats, classement et compositions. API-Football complète effectifs/profils/archives ; sa suspension avait été confirmée, aucune réactivation n'est supposée.
- Les clés et identifiants restent dans les fichiers privés ignorés de Git. Aucun secret ni lien de récupération dans cette documentation.
- Toujours demander l'accord explicite avant chaque push/déploiement. Le lot comptes en cours est autorisé uniquement en développement local.

## Décisions V2 validées
- Consultation sportive publique ; favoris réservés aux comptes lorsque la V2 est activée. Au clic, proposer inscription/connexion.
- Une équipe principale et plusieurs clubs suivis, avec préférences persistantes en base.
- MySQL demandé ; vérifier la variante MariaDB disponible chez Alwaysdata lors du raccordement.
- Dix clics manuels par compte et par jour, tous appareils confondus ; garder cache partagé et plafond global par fournisseur.
- Rappel e-mail deux heures avant les matchs des clubs choisis, sur activation volontaire.
- SMTP Alwaysdata retenu, adresse ligue1-live@alwaysdata.net. Les e-mails de résultat restent à préciser ; les alertes navigateur existantes sont conservées.

## Lot comptes préparé localement
- Inscription e-mail/mot de passe, rôle initial user, mots de passe hachés.
- Sessions Laravel avec cookies et protection CSRF sur les routes web /api/account ; les API sportives existantes restent distinctes.
- Vue et Laravel partagent une origine : utilisation des sessions natives, sans jeton de connexion dans localStorage et sans dépendance Sanctum supplémentaire.
- Connexion, déconnexion, vérification d'e-mail par lien signé expirant, récupération par jeton à usage unique.
- Modification d'adresse avec vérification renouvelée ; modification de mot de passe, liste des sessions, fermeture des autres sessions et suppression du compte.
- Migrations users / password_reset_tokens / sessions ; bases MySQL locales distinctes pour développement et tests.
- ACCOUNTS_ENABLED=false par défaut : aucune ouverture des inscriptions en production à ce stade.
- E-mails de comptes prêts pour SMTP local ; les tests emploient exclusivement le transport array et des notifications simulées, sans journalisation des liens.

## Lot favoris et quota préparé localement
- Visiteur : pas de favori personnel lorsque les comptes sont activés ; invitation discrète à s'inscrire ou se connecter. Les anciennes préférences locales ne sont pas appliquées au visiteur.
- Une équipe principale et plusieurs clubs suivis dans MySQL, contraintes d'unicité et modifications transactionnelles. Le principal appartient aux clubs suivis. Son retrait ne supprime pas les autres clubs.
- Identifiants internes teams stables ; identifiants Highlightly et API-Football séparés, correspondance API-Football lue seulement dans le registre déjà en cache. Aucun appel supplémentaire à ce fournisseur suspendu.
- Chaque nouveau choix est validé contre la Ligue 1 de la saison configurée. Les anciens choix restent enregistrés lors d'un changement de saison ou fournisseur, même si le club n'est plus présent.
- Gestion dans Mon compte et panneau repliable dans Mon équipe, avec prochain match par club. Un ancien favori local peut être repris par une action explicite, sans remplacement automatique d'un principal existant.
- Mise à jour des préférences à la lecture de session, au rechargement et lors de la vérification périodique du compte ; pas de synchronisation instantanée par WebSocket.
- Quota de dix clics acceptés par utilisateur et jour UTC dans manual_refresh_counts, partagé entre appareils et protégé par verrouillage du compte en base. Le 11e clic est refusé ; un clic accepté reste compté si le fournisseur échoue.
- Les visiteurs conservent les lectures publiques et la vérification automatique partagée, mais pas le bouton manuel en mode comptes. Les anciens endpoints POST /api/refresh sont refusés lorsque la V2 est activée.
- Les requêtes de comptes passent par les cookies et CSRF natifs ; une session expirée efface immédiatement le favori en mémoire et le compteur personnel.
- Le cache, la temporisation, le plafond de 90 appels par fournisseur et les modes Auto/Manuel/Pause restent appliqués. Le quota de dix porte sur les clics manuels ; les lectures sportives automatiques utilisent le budget global.
- ACCOUNTS_ENABLED=false garde le fonctionnement historique par navigateur pour la version publique jusqu'à une activation autorisée.

## Non implémenté / non déployé
- Administration locale implémentée ; activation et premier administrateur de production restent à faire.
- Activation des tâches planifiées et du traitement de la file en production (code local prêt).
- Activation MySQL/SMTP dans le site public et migrations Alwaysdata ; accès de préparation et sauvegarde privée validés.
- Migration et activation publiques à autoriser séparément : sauvegarde MySQL préparée, restauration locale vérifiée ; le rollback actuel restaure seulement le code.

## Vérification et fonctionnement local
100 tests Laravel (1034 assertions) et 33 tests JavaScript passent. Le parcours favoris a été testé sur deux sessions réelles et MySQL local, sans appels fournisseur. Le compteur navigateur a été préchargé à 3 clics dans la base locale pour vérifier le partage sans consommer de quota ; les clics acceptés/refusés sont testés avec des fournisseurs simulés.
Voir docs/COMPTES.md. Les tests de comptes imposent le nom de base ligue1_live_test avant de lancer les migrations de test. Les requêtes football sont simulées dans les tests backend, et la prévisualisation navigateur emploie le mode pause.

## Journal
- 2026-11-02 : création du socle comptes local, migrations MySQL, écrans intégrés aux thèmes existants et tests de sécurité/parcours. Aucun push, aucune migration Alwaysdata, aucune livraison d'e-mail réelle.

- 2026-11-03 : favoris multi-clubs et équipe principale en base, invitation d’inscription, quota quotidien par compte et contrôles d’isolation. L’utilisateur souhaite être guidé pour SMTP Alwaysdata ; l’adresse expéditrice Alwaysdata est confirmée ; paramètres SMTP enregistrés dans le fichier privé, authentification et réception d’un unique e-mail de test confirmées par l’utilisateur. Aucun push, aucune migration de production, aucun e-mail externe envoyé.

- 2026-11-03 : SMTP Alwaysdata authentifié ; un seul e-mail de test explicitement autorisé a été envoyé et sa réception a été confirmée par l’utilisateur. L’activation locale des e-mails de comptes et le développement des rappels ont ensuite été autorisés ; les essais restent simulés. Aucun secret n’est consigné ici.

## Rappels préparés localement le 3 novembre 2026
- Consentement explicite pour chaque club suivi, désactivé par défaut ; adresse vérifiée requise. Désactivation possible dans le compte et par lien signé avec confirmation dans chaque e-mail.
- Un seul rappel par utilisateur et rencontre, même avec les deux adversaires suivis. Le consentement et la vérification doivent précéder H−2 ; pas de rattrapage des nouvelles inscriptions.
- Préparation entre H−2 et H−2+10 minutes ; calendrier partagé récent de dix minutes maximum, vérification avant envoi, aucun envoi en mode Pause ou sur données périmées. En Auto, rafraîchissement partagé au moment des rappels sous budget global ; en Manuel, pas de rafraîchissement forcé.
- Tables matches, notification_preferences, notification_deliveries et file Laravel database ; jobs et livraisons insérés atomiquement. Aucun fournisseur football appelé par destinataire.
- Échec de connexion avant envoi : trois tentatives maximum. Échec incertain pendant l’envoi : pas de renvoi automatique pour éviter les doublons. Les statuts internes ne conservent aucun diagnostic SMTP brut.
- Commande football:reminders et planification Laravel préparées ; worker borné mail, sans processus permanent. Aucune tâche Alwaysdata créée.
- Tests backend : consentement, isolation, suppression, désabonnement, doublons, report/annulation, SMTP simulé, file database réellement traitée. La livraison réelle du rappel sportif n’a pas été testée.
- Aucun push ni déploiement V2 ; aucune modification de la base publique.
- Recette navigateur terminée : adresse non vérifiée bloquée, consentement par club persistant dans deux sessions, lien signé lu sans effet puis confirmation de désabonnement, largeurs 320 à 1440 et deux thèmes, aucune exception JS et aucun appel fournisseur. Correction du proxy Vite pour les routes /emails ; comptes fictifs supprimés.
- Fin de recette : MAIL_MAILER=smtp et REMINDERS_ENABLED=true activés dans le fichier privé local ; APP_URL alignée sur 127.0.0.1:5173. Prévisualisation relancée avec appels football en pause ; aucun nouveau message réel envoyé pendant la recette, aucune tâche automatique démarrée.

## Administration et préparation de mise en ligne — 3 novembre 2026
- Rôle admin vérifié côté Laravel pour chaque route ; compte actif et adresse vérifiée obligatoires. Inscription publique toujours limitée au rôle user.
- Page /administration et lien dans le menu réservé aux administrateurs : statistiques de comptes, recherche littérale/pagination, budgets des deux fournisseurs sans appels football, historique paginé des rappels filtré par statut.
- Désactivation/réactivation des comptes ordinaires avec confirmation du mot de passe administrateur ; fermeture des sessions, invalidation de la récupération et annulation des rappels pending à la désactivation. Favoris conservés. Les comptes admin sont protégés contre ces opérations dans cette interface.
- Promotion uniquement par la commande privée account:make-admin, sur un compte existant, actif et vérifié, avec confirmation. Aucun changement de rôle par HTTP.
- Compte propriétaire local choisi : ligue1-live@alwaysdata.net. Trois e-mails réels marqués TEST envoyés (confirmation, récupération, rappel avec rencontre fictive) et réception des trois confirmée par l’utilisateur. Liens effectivement présents dans les corps envoyés utilisés avec succès dans Chrome ; aucune lecture de boîte mail par l’agent. Compte local vérifié et promu après la recette. Le propriétaire peut choisir son mot de passe par récupération.
- Recette admin réelle : compte ordinaire refusé, recherche MySQL, mot de passe erroné refusé, désactivation et fermeture d’une autre session, réactivation et reconnexion, menu privé ; largeurs 320/390/768/1440 et thèmes clair/sombre ; aucun appel football. Compte fictif supprimé.
- Commandes privées app:check-v2 (lecture seule) et app:backup-db (SQL compressé, permissions privées, identifiants temporaires supprimés, aucun mot de passe en argument) préparées. Sauvegarde locale réellement restaurée dans une base isolée ; tables et nombres de lignes comparés puis base de restauration supprimée. Tests reproductibles des permissions/nettoyage ajoutés.
- Accès SSH existant refusé en mode automatique. Clé de maintenance dédiée créée dans le dossier privé ignoré ; ajout de sa clé publique demandé au propriétaire. Vérifications, sauvegarde et préparation réelles sur Alwaysdata en attente de cet accès. Aucune clé privée ni empreinte personnelle n’est consignée ici.
- Aucun push, déploiement, migration ni activation de tâche de production pour ce lot.

- Mise à jour de préparation serveur : accès SSH rétabli avec la clé dédiée ; PHP 8.5.12 et Laravel 13.39.0 confirmés. Le site public garde comptes/rappels désactivés, sessions fichier, aucune base raccordée. Outils mysql/mysqldump disponibles. Configuration V2 séparée déposée dans un dossier privé hors du site ; connexion MySQL refusée (1045), identifiant ou mot de passe à corriger. Aucune sauvegarde de production créée pour l’instant. Aucune migration ni tâche installée. Le mot de passe temporaire de recette du propriétaire local a été supprimé du disque ; choix d’un mot de passe personnel via récupération.
- SMTP authentifié avec succès depuis Alwaysdata et site public /up HTTP 200 après préparation privée. Aucun message supplémentaire envoyé depuis le serveur.

## Préparation Alwaysdata validée le 3 novembre 2026
- Nouveaux identifiants MySQL privés retestés depuis le serveur : connexion réussie à ligue1-live_ligue1, version effective 11.8.9-MariaDB, base vide (0 table). Les valeurs restent hors Git et des réponses.
- Sauvegarde SQL compressée créée dans le dossier privé .ligue1-v2-preparation/backups, hors du site web. Aucune migration ; aucune table créée. La restauration des tables V2 a été testée localement dans une base isolée, pas sur la base active.
- Authentification SMTP depuis Alwaysdata réussie avec les paramètres de préparation. Aucun nouvel e-mail envoyé à cette étape ; les trois e-mails de recette précédents ont été reçus et confirmés par l’utilisateur.
- Étapes 1/2/3 autorisées : administration locale testée, recette réelle e-mails terminée et préparation serveur vérifiée. Étape 4 (push, déploiement, migrations et activation des tâches/comptes publics) attend toujours une validation distincte.

## Activité des traitements de rappels — 3 novembre 2026
- Administration : témoins distincts pour la préparation des rappels et le traitement de la file mail, dernière date de lancement/fin/succès, résultat et nombre de rappels préparés (y compris zéro).
- Enregistrement dans le cache serveur existant, deux clés privées sans TTL ni données personnelles ; aucun historique volumineux, aucune migration ou dépendance supplémentaire. Un effacement du cache remet explicitement le témoin à « aucun passage » ; ne pas effacer le cache sportif lors du déploiement.
- Au-delà de cinq minutes sans fin ou nouveau lancement, état en retard ; échec marqué sans diagnostic brut. Mode Pause et rappels désactivés sont distingués de l’inactivité. Une reprise attend un nouveau passage ; un processus interrompu reste visible comme ancien lancement sans fin.
- La commande football:reminders enregistre la préparation. Nouvelle enveloppe football:send-reminders autour du worker database/mail avec les mêmes limites (45 s, 25 jobs, arrêt file vide). Ces deux commandes sont planifiées chaque minute par Laravel ; aucune tâche de production n’a été activée.
- Panneau relu chaque minute tant que l’onglet admin est visible ; bouton de vérification manuelle. Une lecture indisponible est signalée avec la date du dernier état connu. Aucune lecture ne simule un passage ni ne déclenche d’appel football.
- Tests backend sur base dédiée : commandes réelles, worker réellement vide, sorties non nulles/exceptions simulées, conservation du dernier succès, seuil d’inactivité et reprise sans faux positif. Recette Chrome : huit états simulés du panneau, panne de lecture/reprise, relecture après minute virtuelle, thèmes et largeurs 320/390/768/1440 ; aucune requête fournisseur, aucun envoi. Les témoins attestent l’exécution des commandes, pas la réception SMTP ; consulter l’historique pour les erreurs d’envoi.
- Nouveau lot local seulement ; accord de push/déploiement toujours requis.

## Mise en ligne V2 autorisée le 3 novembre 2026
- Le propriétaire a explicitement validé le push sur main, le déploiement et l’activation progressive : sauvegardes, code avec comptes fermés, migrations, contrôles, puis activation.
- Authentification GitHub et secret de déploiement présents ; origin/main correspond au dernier commit local avant le lot V2. Sauvegarde pré-déploiement de la configuration active conservée hors du web et nouvel export de la base vide réalisés.
- La commande crontab est interdite sur l’offre Alwaysdata actuelle ; utiliser la tâche planifiée du panneau, conformément à sa documentation, pas un processus permanent.
- Publication et activation en cours ; ne pas interpréter les entrées antérieures « non déployé » comme un retrait de l’autorisation ci-dessus.
