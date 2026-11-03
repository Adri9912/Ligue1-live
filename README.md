# Ligue 1 Live ⚽

Application web de suivi de la Ligue 1, développée avec **Laravel 13**, Vue 3 et Tailwind CSS. Interface responsive avec thèmes clair et sombre.

[Voir le site](https://ligue1-live.alwaysdata.net)

## Fonctionnalités

- Calendrier, résultats et scores actualisés pendant les matchs.
- Classement avec repères pour les places européennes et la relégation.
- Club favori, prochains matchs et alertes personnalisées.
- Actualisation automatique et bouton manuel limité à 10 clics par jour et par compte, sous le budget API partagé.
- Fiches clubs : effectif, joueurs, stade, création et palmarès.
- Dernières compositions disponibles sur un terrain.
- Statistiques et événements des matchs selon les données disponibles.
- Archives des saisons précédentes.

## Technologies

- Laravel 13 et PHP 8.5 pour le serveur et les appels API.
- Vue 3, Vue Router, Vite et Tailwind CSS pour l’interface.
- Highlightly pour les matchs, le classement et les compositions.
- BALLDONTLIE pour les effectifs et les profils actuels.
- TheSportsDB pour les portraits disponibles, associés par nom et date de naissance.
- API-Football pour les informations historiques des clubs et les archives disponibles ; portraits conservés lorsque leur correspondance est vérifiée.

Le cache et les quotas sont gérés côté serveur. MySQL conserve les comptes, les favoris et les préférences de rappels lorsque ces fonctionnalités sont activées.

## Installation

Avec PHP 8.5, Composer et npm installés, depuis une nouvelle copie du projet :

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
npm run dev
```

Ouvrir http://127.0.0.1:5173.

Renseigner `HIGHLIGHTLY_API_KEY`, `BALLDONTLIE_API_KEY` et, pour les archives, `FOOTBALL_API_KEY` dans le fichier privé `.env` pour utiliser les vraies données. Sans clé du fournisseur principal, un mode démonstration clairement signalé est proposé.

Ne jamais publier le fichier `.env` ou les clés API. Pour une installation existante, conserver son `.env` et sa clé `APP_KEY`.

## Vérifications

```bash
php artisan test
npm test
npm run build
```

GitHub Actions lance les tests et la compilation à chaque envoi de code. Une fois la clé SSH configurée, les modifications de `main` sont ensuite déployées automatiquement sur alwaysdata.

## Hébergement

Le site est hébergé sur alwaysdata. La racine web pointe vers `public/` afin de protéger la configuration privée. Voir le [guide de déploiement](docs/ALWAYSDATA.md).

## Limites et sources

La disponibilité et la fréquence des données dépendent des API et de leurs quotas. Les meilleurs buteurs et passeurs de la saison actuelle restent à venir. Les notifications nécessitent un onglet ouvert et, pour les notifications système, l’autorisation du navigateur.

Projet indépendant, sans affiliation avec la LFP ou les clubs. Les marques et logos appartiennent à leurs propriétaires. Logo Ligue 1 : [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Ligue_1_2024_Logo.png). Palmarès : [Ligue 1](https://ligue1.com/en/articles/l1_article_289-le-palmares-des-champions-de-ligue-1).


### Introduction cinématique

L’introduction validée est intégrée au démarrage de Vue : quatre secondes en plein écran, puis la page demandée apparaît, y compris pour un lien direct vers un match ou le classement. La page et ses données se chargent en parallèle. « Passer » ou la touche Échap terminent immédiatement l’introduction ; le focus revient au contenu. Le défilement et les interactions derrière l’introduction sont bloqués pendant sa lecture. Le réglage système « réduire les animations » remplace le mouvement et l’éclair par un fondu du logo.

La clé `l1-intro-last-shown-v1` dans `localStorage` mémorise le début de lecture pour **30 minutes**, même lorsque l’utilisateur passe l’animation. Les actualisations, changements de page et nouveaux onglets du même site n’entraînent pas de nouvelle lecture pendant ce délai. Les visites ne prolongent pas ce délai. Web Locks sérialise les ouvertures simultanées lorsque le navigateur le permet. La mémorisation reste propre au navigateur, au profil et à l’origine (protocole, domaine, port) ; si le stockage est bloqué, l’intro peut se rejouer à chaque chargement mais n’empêche jamais l’accès.

Le composant `src/components/IntroAnimation.vue` contient les styles isolés et réutilise l’icône ballon de l’application. Le rendu du ballon est dans `src/intro/ball.mjs` et la règle des 30 minutes dans `src/intro-session.mjs`, couverte par `tests/intro-session.test.mjs`. Aucun changement au backend ni aux fournisseurs de données. Le push sur une branche de travail ne déploie pas cette fonctionnalité ; le workflow existant déploie uniquement depuis `main`.

## Comptes et favoris

Comptes, favoris multi-clubs, rappels par e-mail et administration. Activation contrôlée avec ACCOUNTS_ENABLED et REMINDERS_ENABLED après configuration de MySQL et SMTP. Configuration MySQL, portée des tests et conditions de mise en ligne : [docs/COMPTES.md](docs/COMPTES.md). Décisions et fonctionnalités restantes : [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md).
