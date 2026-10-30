# Ligue 1 Live ⚽

Application web de suivi de la Ligue 1, développée avec **Laravel 13**, Vue 3 et Tailwind CSS. Interface responsive avec thèmes clair et sombre.

[Voir le site](https://ligue1-live.alwaysdata.net)

## Fonctionnalités

- Calendrier, résultats et scores actualisés pendant les matchs.
- Classement avec repères pour les places européennes et la relégation.
- Club favori, prochains matchs et alertes personnalisées.
- Fiches clubs : effectif, joueurs, stade, création et palmarès.
- Dernières compositions disponibles sur un terrain.
- Statistiques et événements des matchs selon les données disponibles.
- Archives des saisons précédentes.

## Technologies

- Laravel 13 et PHP 8.5 pour le serveur et les appels API.
- Vue 3, Vue Router, Vite et Tailwind CSS pour l’interface.
- Highlightly pour les matchs, le classement et les compositions.
- API-Football pour les effectifs, les profils et les archives.

Le cache et les quotas sont gérés côté serveur. Aucune base de données n’est nécessaire.

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

Renseigner `HIGHLIGHTLY_API_KEY` et `FOOTBALL_API_KEY` dans le fichier privé `.env` pour utiliser les vraies données. Sans clé du fournisseur principal, un mode démonstration clairement signalé est proposé.

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
