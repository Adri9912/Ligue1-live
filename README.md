# Ligue 1 Live ⚽

Application responsive en français : **Vue 3, Vite, Tailwind CSS et Laravel 13**. Laravel protège les clés football, limite les appels et conserve les réponses sur disque. Vue garde ses composants, ses thèmes et sa navigation.

La migration remplace le backend Node ; **Node reste utilisé uniquement pour compiler et tester le frontend**. L’ancienne version est conservée dans l’historique Git (dernier commit avant migration : `2b58ced`). La branche `main` n’est pas modifiée tant que la migration n’est pas intégrée.

## Installation locale

Prérequis : PHP **8.5** pour les dépendances verrouillées, Composer 2, Node **22.12 minimum** (24 recommandé). Extensions PHP : celles de Laravel, dont cURL, mbstring, XML/DOM, fileinfo et intl. Aucune base de données n’est nécessaire.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
npm run dev
```

**Ne pas recopier `.env.example` sur un `.env` existant** : cela effacerait les clés privées déjà configurées. Pour une copie existante, `composer install` puis `npm ci` suffisent ; générer `APP_KEY` uniquement si elle est absente.

Ouvrir **http://127.0.0.1:5173**. Le script lance Laravel sur **8000** et Vite sur **5173** ; les deux ports doivent être libres. `Ctrl+C` arrête les serveurs. Le fichier `LANCER.sh` simplifie le lancement.

Pour tester la version compilée, sans Vite :

```bash
npm run build
npm start
```

Ouvrir **http://127.0.0.1:8000**. `npm start` est une commande de prévisualisation locale qui lance PHP, pas un serveur Node. En hébergement, utiliser le serveur PHP de l’hébergeur avec `public/` comme racine web.

## Clés et données

Le fichier privé `.env` contient :

- `FOOTBALL_PROVIDER=highlightly` : saison actuelle via Highlightly.
- `HIGHLIGHTLY_API_KEY` : clé Football API directe Highlightly.
- `FOOTBALL_API_KEY` : clé API-Football pour les effectifs, les profils et les archives.
- `FOOTBALL_SEASON` : année de début ; vide = saison calculée automatiquement.
- `FOOTBALL_DAILY_LIMIT=90` : marge sous le quota gratuit de chaque fournisseur.
- `APP_KEY` : clé de chiffrement Laravel, générée localement.
- `APP_DEBUG=false` : les réponses ne révèlent pas de détails internes.

**Aucune clé ne doit porter le préfixe `VITE_`.** Ne pas versionner `.env`, `vendor`, `node_modules`, `dist`, `.cache` ou les fichiers de runtime Laravel.

Sans clé du fournisseur actif, les matchs et le classement utilisent une **démonstration explicitement signalée**. Aucun échec API ne bascule silencieusement en démo. Le scénario est fictif et figé ; ses dates sont rapprochées du jour courant. Les archives et profils réels nécessitent API-Football.

## Fonctionnalités

- Calendrier, résultats par journée, filtres, cartes ou liste compacte mémorisée.
- Classement, repères colorés et projection provisoire pendant les matchs.
- Club favori, prochain rendez-vous, forme récente et dernière composition disponible.
- Clubs : effectif trié par poste et numéro, portraits, profils cliquables, stade, création et palmarès sourcé.
- Matchs : événements et statistiques selon leur disponibilité ; aperçu avant-match avec forme et confrontations de la saison chargée.
- Rétro : saisons 2024, 2023 et 2022, classements, résultats, buteurs et passeurs.
- Thèmes clair/sombre, navigation mobile et horodatage des données.
- Alertes personnalisées dans le site, aperçu test et notifications système sur autorisation.

Les tops buteurs et passeurs de la saison actuelle restent « À venir » avec Highlightly. La composition habituelle n’est pas calculée ; la dernière composition complète précise les matchs consultés. Les portraits ne sont liés entre fournisseurs qu’avec une correspondance sûre côté Vue.

Le favori et les préférences sont conservés dans ce navigateur, indépendamment du cache sportif serveur. Un changement de fournisseur peut nécessiter une nouvelle sélection du club.

## Cache, quotas et actualisation

Le cache Laravel utilise des fichiers dans `storage/framework/cache`. Un **verrou par fournisseur** sérialise les envois et le budget. Une relecture après acquisition du verrou évite deux appels simultanés identiques. Ce choix convient à un seul serveur ; plusieurs serveurs nécessiteraient un cache partagé.

- Calendrier : une fois par jour de Paris, ou toutes les 10 minutes pendant la plage d’un match ; un clic respecte aussi un minimum de 10 minutes.
- Classement : chaque jour et après les résultats, au plus un appel par heure.
- Détails : cache de 15 minutes.
- Clubs et correspondances : 30 jours ; effectifs : 24 h ; profils : 7 jours.
- Compositions complètes : 30 jours ; réponses vides : 6 h ; trois matchs consultés au maximum par recherche.
- Archives : 30 jours.
- Budgets remis à zéro à minuit UTC, séparés entre Highlightly et API-Football. Clubs et Rétro partagent le budget API-Football.

Les tentatives échouées comptent aussi dans le budget. Le quota restant du fournisseur est observé lorsqu’il est transmis. Il peut inclure des appels faits ailleurs avec la même clé.

Les réglages **Auto / Manuel / Pause** sont communs aux visiteurs du même serveur et sont persistants. En Pause, aucune requête fournisseur n’est envoyée ; les anciennes réponses restent consultables. En Manuel, un clic explicite est nécessaire. Le mode est relu après les attentes ; un appel déjà envoyé peut encore se terminer.

Le site n’effectue pas d’actualisation planifiée lorsqu’aucun onglet n’est ouvert. Les minuteurs peuvent être retardés en arrière-plan. Les notifications actuelles nécessitent un onglet ouvert ; ce ne sont pas des notifications push après fermeture.

### Réutiliser le cache de la version Node

```bash
php artisan football:import-node-cache
```

La commande lit `.cache/` localement, sans appel API, réutilise les réponses et préserve le plus grand compteur du jour déjà enregistré. Elle ne remet jamais le budget à zéro. Le cache Node reste privé et exclu de Git. Ne pas exploiter simultanément les anciens et nouveaux backends avec la même clé : leurs compteurs locaux sont distincts.

## Tests

```bash
npm test
php artisan test
npm run build
```

Les tests PHP utilisent des réponses HTTP simulées et interdisent les requêtes non prévues. Ils couvrent les fournisseurs, les routes, les données absentes, le cache, les quotas, les modes, les horaires de Paris, les clubs, les compositions et les archives. Un test lance deux processus PHP pour contrôler les verrous et le quota en concurrence. Les tests JavaScript concernent le classement provisoire, les alertes et les calculs du frontend.

GitHub Actions exécute ces vérifications à chaque envoi. Aucun déploiement automatique n’est encore configuré.

## Structure

- `src/` : interface Vue.
- `app/Football/` : transport HTTP, règles métier et normalisation PHP.
- `app/Http/Controllers/FootballController.php` : réponses JSON pour Vue.
- `routes/api.php` : routes football ; `routes/web.php` : interface compilée.
- `config/football.php` : configuration privée des fournisseurs.
- `resources/football/demo.json` : scénario fictif conservé pour la démo.
- `tests/Feature/` : tests Laravel ; `tests/*.test.mjs` : tests frontend.

## Déploiement

Voir [le guide alwaysdata](docs/ALWAYSDATA.md). Le dépôt peut rester privé. La racine web doit être **`public/`**, jamais la racine du projet ; les clés et le cache restent hors du web.

## Identité visuelle et sources

Le filigrane, l’en-tête et le pied de page utilisent `public/images/ligue1-logo.png`, conservé localement. Source : [Wikimedia Commons — Ligue 1 2024 Logo](https://commons.wikimedia.org/wiki/File:Ligue_1_2024_Logo.png), auteur Ligue 1, fiche PD-textlogo. L’image originale est conservée, la couleur et l’opacité sont adaptées par CSS. Identité présentée par la [LFP le 27 mars 2024](https://www.lfp.fr/article/un-nouveau-logo-pour-la-ligue-1-a-compter-de-2024-2025).

Palmarès : [Ligue 1](https://ligue1.com/en/articles/l1_article_289-le-palmares-des-champions-de-ligue-1), vérifié le 27 octobre 2026. Les corrections de création et de stade conservent leurs sources dans `Clubs.php`. Les données sont limitées par les fournisseurs et leurs offres. Projet indépendant.
