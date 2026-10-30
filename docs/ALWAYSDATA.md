# Déploiement Laravel sur alwaysdata

La migration utilise désormais **PHP**, pas le type de site Node.js. Documentation de référence : [PHP](https://help.alwaysdata.com/fr/docs/hebergement-web/langages/php/configuration/) et [déploiement Laravel](https://laravel.com/docs/deployment).

## Préparer la version

1. Choisir PHP **8.5**, correspondant au verrou Composer actuel. Vérifier l’extension intl et les extensions Laravel avec `composer check-platform-reqs`.
2. Exécuter les tests JavaScript et PHP, puis `npm ci` et `npm run build`. La compilation peut être faite localement ou sur GitHub Actions ; Node n’est pas nécessaire sur l’hébergement si `dist/` est déjà construit.
3. Envoyer les sources, `dist/` et les ressources publiques dans un dossier dédié, par exemple `/home/COMPTE/www/ligue1-live`. Ne pas envoyer `.git`, `node_modules` ou le `.env` local.
4. Installer les dépendances PHP avec `composer install --no-dev --prefer-dist --optimize-autoloader`. Ne pas exécuter de migration de base de données : l’application n’en utilise pas.

## Configurer le site

Dans **Web → Sites**, choisir un site **PHP**. Définir sa racine sur :

```
/home/COMPTE/www/ligue1-live/public
```

Le serveur doit rediriger les routes vers `public/index.php` (le fichier `.htaccess` est inclus). Ne jamais publier la racine du dépôt, `storage/` ou `.env`.

Créer le fichier `.env` directement sur l’hébergement ou via un transfert sécurisé autorisé :

```
APP_NAME="Ligue 1 Live"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://COMPTE.alwaysdata.net
APP_KEY=
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=single
FOOTBALL_PROVIDER=highlightly
HIGHLIGHTLY_API_KEY=
FOOTBALL_API_KEY=
FOOTBALL_SEASON=
FOOTBALL_DAILY_LIMIT=90
```

Renseigner les clés privées sans les mettre dans GitHub, puis générer `APP_KEY` une seule fois avec `php artisan key:generate --force`. Utiliser des droits privés pour `.env` et autoriser PHP à écrire dans `storage/` et `bootstrap/cache/`.

Après configuration :

```bash
php artisan config:cache
php artisan route:cache
```

Vérifier `/up`, `/api/status` et les pages. Une lecture de `/api/status` ne consomme pas de quota football. L’absence de clés déclenche une démo explicite, pas des vraies données.

## Mises à jour automatiques

La CI de tests est prête ; le déploiement automatique ne l’est pas encore. Pour le configurer, il faudra le nom du compte alwaysdata, le nom du site et un accès de déploiement autorisé. Les identifiants seront stockés dans les secrets GitHub, jamais dans un fichier versionné ni dans le chat.

Le futur déploiement devra :

- s’exécuter uniquement après réussite des tests de `main` ;
- compiler Vue et installer les dépendances PHP de production ;
- vérifier l’identité du serveur SSH ;
- préserver `.env`, `storage/` et les quotas/cache existants lors des transferts ;
- actualiser les caches de configuration/routes, puis vérifier `/up` ;
- conserver une version précédente pour pouvoir revenir en arrière.

Ne pas utiliser `php artisan cache:clear` dans le déploiement courant : cela effacerait aussi les compteurs du budget football. Ne pas remplacer les dossiers de runtime par une copie vide.

## Limite de cette version

Les réglages Auto/Manuel/Pause restent partagés entre visiteurs comme dans la version d’origine. Le site est prévu pour un usage personnel ; une publication à un large public nécessitera de réserver ces réglages à l’administrateur et de revoir les limites de trafic/API.
