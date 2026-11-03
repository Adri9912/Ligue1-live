# Effectifs et profils BALLDONTLIE

Publication validée par le propriétaire le 3 novembre 2026. Documentation fournisseur : https://ligue1.balldontlie.io/.

## Configuration

Renseigner BALLDONTLIE_API_KEY dans le .env privé du serveur pour sélectionner ce fournisseur d’effectifs. Ne pas utiliser de variable VITE_. La clé de test .cache/balldontlie-test.env est locale et ignorée de Git ; elle n’est pas déployée. Ne pas supprimer HIGHLIGHTLY_API_KEY : matchs, scores et classement restent chez Highlightly. Conserver FOOTBALL_API_KEY si les archives API-Football sont toujours utilisées.

La version locale est prête avec cette clé. En production, publier le code seulement après accord, ajouter la variable privée puis reconstruire la configuration Laravel avec php artisan config:cache. Aucune migration ni modification de comptes/favoris. Le script de déploiement conserve .env, storage et les caches/quotas ; ne pas vider le cache sportif.

## Données et cache

- Liste des clubs pour la saison configurée, puis effectif du club identifié de façon unique. Aucune confusion entre PSG et Paris FC.
- Seuls les joueurs marqués actifs pour la bonne saison sont présentés. L’exhaustivité des inscriptions en Ligue 1 n’est pas garantie par ce filtre.
- Cache partagé de 24 heures. Les fiches réutilisent les données de l’effectif et ne déclenchent pas un appel par joueur.
- Dates et sources affichées séparément pour l’effectif et les anciennes informations du club. En cas de panne, ancienne réponse conservée et signalée si disponible.
- Une page de 100 joueurs maximum par club ; si une suite est annoncée, la réponse est refusée plutôt que présentée comme complète.
- Pas de photo fournie par les réponses testées. Les anciens portraits sont réutilisés si les critères d’identité ci-dessous concordent. Sinon initiales.
- Les compositions ne chargent aucun nouvel effectif : elles réutilisent seulement les joueurs déjà en cache. Une nouvelle fiche de club permet d’alimenter ce cache.

## Limites et sécurité

Cinq appels par minute, tous visiteurs du même serveur confondus, avec verrou partagé. Le plafond quotidien FOOTBALL_DAILY_LIMIT (90 par défaut) est une limite de sécurité interne distincte pour chaque fournisseur ; ce n’est pas un quota quotidien annoncé par BALLDONTLIE. Les modes Auto/Manuel/Pause restent respectés. Les erreurs 429 suspendent les tentatives une minute ; un refus 401/403 entraîne une suspension locale de quinze minutes. Les clés ne sont ni renvoyées au navigateur ni présentes dans les messages d’erreur publics.

La source est transmise avec chaque identifiant de joueur. Un navigateur resté sur un ancien effectif doit le recharger après un changement de fournisseur, pour éviter toute confusion d’identifiants. Les équipes favorites en MySQL restent inchangées.

## Recette

Tests de réponses simulées : saison/club inattendus, joueurs inactifs et champs absents, correspondance ambiguë, anciennes réponses en cas de panne, protection des portraits, quota glissant traversant minuit UTC, 429 fournisseur, plafond quotidien et modes Manuel/Pause. Les tests Laravel utilisent une clé fictive et la base dédiée ligue1_live_test ; jamais le SMTP réel ni les vraies API.

Les effectifs réels d’Angers (28 actifs) et Lyon (38 actifs) ont été chargés en local le 3 novembre 2026. Les tests navigateur réutilisent ce cache ; les erreurs y sont simulées par interception locale.

Recette finale réussie : 116 tests Laravel (1182 assertions), 33 tests JavaScript, compilation et Chrome sur quatre largeurs (320/390/768/1440), clair/sombre. Profils et initiales réels vérifiés, fermeture au clavier et absence de débordement. Cache ancien, indisponibilité, champs manquants et profil introuvable simulés localement ; zéro exception JavaScript et aucun nouvel appel fournisseur pendant les essais navigateur.

## Portraits TheSportsDB (lot local complémentaire)

La recherche individuelle gratuite permet de demander un portrait sans utiliser la liste de dix joueurs par club. La source est https://www.thesportsdb.com/documentation ; clé publique de démonstration 123, aucune clé privée supplémentaire. Aucun portrait n’est garanti pour tous les joueurs.

Chaque joueur ayant une date de naissance valide reçoit une URL locale de recherche. L’identité est enregistrée côté serveur depuis l’effectif validé ; le navigateur ne peut pas soumettre un nom arbitraire à l’API externe. TheSportsDB est interrogé seulement quand le joueur devient visible. Le nom complet normalisé et la date de naissance doivent correspondre à un résultat unique. Les URLs d’images autorisées sont limitées aux répertoires portraits HTTPS de r2.thesportsdb.com et www.thesportsdb.com, également autorisés par la CSP.

Les résultats positifs restent 30 jours en cache partagé ; une absence ou ambiguïté reste 24 h. Le serveur autorise au maximum 25 recherches par fenêtre glissante de 60 secondes pour rester sous les 30/minute annoncées par le fournisseur. Les réponses temporaires et limites proposent une nouvelle tentative différée ; les composants visibles peuvent réessayer après 65 secondes. Aucun appel de recherche en mode Manuel/Pause, mais les portraits déjà enregistrés restent disponibles. Les requêtes identiques sont partagées entre les composants d’une page.

Une ancienne photo API-Football reste prioritaire si son identité a été vérifiée ; si son image échoue, le portrait TheSportsDB est essayé. Les joueurs sans correspondance certaine gardent leurs initiales. Les portraits sont utilisables dans la liste, la fiche, le terrain et le banc ; les liens de fiche sur le terrain conservent les règles de correspondance de nom/numéro existantes.

Les effectifs réels des 18 clubs ont été reçus le 3 novembre 2026 : 620 lignes marquées actives par le fournisseur, sans garantie que chaque ligne corresponde à une inscription en Ligue 1. Vérification navigateur compilée : vrai portrait dans la liste, la fiche et le terrain d’Angers, deux thèmes, quatre largeurs, sans exception JavaScript. Les compositions des 18 clubs ont ensuite été vérifiées : toutes présentes, avec huit nouveaux appels Highlightly grâce au cache partagé. Angers a transmis un 5-3-2. Aucune modification du site public.

Validation finale : 121 tests Laravel (1241 assertions), 35 tests JavaScript et compilation réussis. Secours d’une ancienne photo cassée vérifié dans Chrome via une image locale volontairement absente. Recherche TheSportsDB depuis Alwaysdata : HTTP 200 et portrait correspondant à Anthony Lopes ; aucun déploiement.

## Correction PSG : prénoms abrégés dans le cache historique

La récupération prioritaire par nom complet/date de naissance reste appliquée lorsqu’un profil historique est connu. Pour un ancien effectif reçu il y a moins de 90 jours, un secours peut reconnaître un joueur du même club par nom complet identique, prénom initial abrégé avec même nom de famille, ou surnom égal au nom de famille explicitement transmis par BALLDONTLIE. Le numéro doit être présent et identique, l’âge historique doit être connu et différer d’au plus un an de l’âge calculé depuis la naissance actuelle. Une date de naissance historique contradictoire interdit ce secours. Les correspondances multiples restent refusées. Les identifiants des fournisseurs restent distincts.

Un nom reconnu du cache devient un alias pour le terrain et le banc, toujours sous contrôle du numéro et de l’unicité. Cela réutilise les anciens portraits sans nouvelle recherche ni appel API-Football.

Vérification réelle PSG : 22 portraits historiques reconnus sur 29 joueurs ; Chrome a chargé 10 portraits sur les 11 titulaires du match 1321434435, quatre largeurs sans débordement ni exception JavaScript. Le joueur restant n’est pas remplacé par un portrait incertain. TheSportsDB a renvoyé temporairement HTTP 429 pendant l’investigation ; ne pas affirmer que toute photo manquante est absente de son catalogue.

Tests après correction : 122 Laravel (1253 assertions), 36 JavaScript et compilation réussis. Le défaut de photo, les identités ambiguës, âges ou numéros incohérents, cache trop ancien et dates contradictoires sont contrôlés. Aucune publication de ces lots sans validation.

## Vérification des portraits sur les 18 équipes

La règle de réutilisation n’est pas spécifique au PSG : elle est commune à tous les clubs. Contrôle réel du cache local : les anciens effectifs API-Football existent seulement pour PSG et Lyon, sur la copie locale comme sur le serveur public (lecture sans appel fournisseur). Les autres clubs utilisent donc les résultats de recherche TheSportsDB.

Le backend renvoie désormais les URLs TheSportsDB déjà validées directement dans l’effectif, en plus du point de recherche progressive. Les recherches différées sont distinctes d’un résultat réellement absent. Jusqu’à trois joueurs par équipe ont été vérifiés en alternance, puis quelques joueurs confirmés des clubs encore sans portrait après expiration du délai fournisseur. Une image correspondant à un joueur a été trouvée pour chacun des 18 clubs. Le bilan local contient 91 liens de portraits pour 620 joueurs ; ce n’est pas une garantie de 91 images actuellement accessibles, certains anciens liens peuvent échouer et utiliser le secours. Onze images échantillons des nouveaux fournisseurs ont été contrôlées HTTP 200 ; les échantillons historiques PSG/Lyon ont signalé deux images absentes, tandis que la recette PSG précédente avait chargé dix portraits de titulaires.

Ne pas annoncer une couverture complète des photos : les clubs sans historique n’ont pas automatiquement la richesse du cache PSG. Les recherches se poursuivent pour les joueurs visibles dans le navigateur dans la limite prévue. Un portrait non confirmé reste une initiale.

123 tests Laravel (1347 assertions), 36 tests JavaScript et build passent. Le test de parcours des 18 noms de clubs vérifie chaque effectif et la séparation des identifiants, sans requête de portrait pendant le chargement de la liste. Aucune publication autorisée pour ce lot à ce stade.

Accord explicite reçu pour publier ce lot, configurer la clé privée en production et vérifier le résultat. Le cache des effectifs et portraits vérifiés peut être amorcé sans nouvel appel fournisseur, sans remplacer de données plus récentes ni les compteurs de quotas.
