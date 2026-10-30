# Déploiement Laravel sur alwaysdata

Le site utilise **Laravel et PHP**. Documentation de référence : [PHP](https://help.alwaysdata.com/fr/docs/hebergement-web/langages/php/configuration/) et [déploiement Laravel](https://laravel.com/docs/deployment).

## Préparer la version

1. Choisir PHP **8.5**, correspondant au verrou Composer actuel. Vérifier l’extension intl et les extensions Laravel avec `composer check-platform-reqs`.
2. Exécuter les tests JavaScript et PHP, puis `npm ci` et `npm run build`. La compilation peut être faite localement ou sur GitHub Actions ; `dist/` peut être envoyé déjà compilé sur l’hébergement.
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

Le workflow `.github/workflows/checks.yml` teste l’interface et Laravel, puis déploie uniquement `main` après leur réussite. Il peut aussi être lancé manuellement via Actions → Vérifications → Run workflow.

### Activation

1. Générer une clé SSH ED25519 dédiée au déploiement, sans réutiliser une clé personnelle.
2. Connecté en SSH à alwaysdata, ajouter sa **clé publique** dans `~/.ssh/authorized_keys`, sans remplacer les clés existantes, avec le préfixe `restrict`. Le dossier `~/.ssh` doit avoir les droits `700` et le fichier `authorized_keys` les droits `600`. Sur le Cloud Public, l’ajout passe par ce fichier ; le champ dans l’administration est réservé au Cloud Privé. Cette autorisation permet à GitHub Actions de mettre à jour le site avec les droits de cet utilisateur ; elle peut être révoquée en retirant cette ligne. Voir la [documentation SSH alwaysdata](https://help.alwaysdata.com/fr/docs/hebergement-web/acces-distant/ssh/utiliser-des-cles-ssh/).
3. Dans GitHub → Settings → Secrets and variables → Actions, ajouter la **clé privée** comme secret `ALWAYSDATA_SSH_KEY`. Ne jamais la mettre dans Git, dans le chat ou dans le README. Les clés football restent uniquement dans le `.env` du serveur.
4. Lancer le workflow depuis `main` et contrôler le job « Déploiement alwaysdata ». Tant que le secret est absent, le déploiement est désactivé sans faire échouer les tests.

Cible : `ligue1-live@ssh-ligue1-live.alwaysdata.net`, projet `/home/ligue1-live/www/ligue1-live`, site https://ligue1-live.alwaysdata.net. L’empreinte ED25519 est vérifiée avant toute authentification : `SHA256:5i/vJYokzNsnXAeHkwzEm+3kxPQWwsRzwXFPQ7oOvNI` (comparée à l’administration alwaysdata lors de la configuration). Si alwaysdata change de serveur, vérifier la nouvelle empreinte dans l’administration avant de mettre à jour le workflow.

### Fonctionnement et restauration

Le runner compile Vue et transfère uniquement les fichiers Git et `dist/`. Le script `scripts/deploy-alwaysdata.sh` vérifie PHP/Composer/rsync/flock, verrouille le déploiement, puis sauvegarde la version précédente dans `/home/ligue1-live/.ligue1-live-deploy/previous.tar.gz`, hors du web. Il installe les dépendances de production et reconstruit les caches de configuration et de routes pendant une courte maintenance.

`.env`, `storage/`, le cache sportif, les compteurs de quotas et les réglages restent en place. Aucune génération de clé ni migration de base de données n’est lancée. Les caches privés de configuration ne sont jamais transférés ou sauvegardés avec le code.

Si l’installation ou le contrôle Laravel échoue, le script restaure automatiquement le code et `vendor` précédents, puis reconstruit la configuration et les routes. Si la restauration échoue elle-même, une intervention manuelle est nécessaire. Une vérification HTTP de `/up` suit le déploiement ; une panne réseau à cette dernière étape signale un échec sans restaurer automatiquement le site.

Une seule sauvegarde est conservée pour limiter l’espace disque ; prévoir de la place pour le projet, la sauvegarde compressée et l’archive transférée. Les tests `bash tests/deploy.test.sh` vérifient le transfert, les restaurations et la préservation des secrets/quotas avec des commandes PHP et Composer simulées.

Ne pas utiliser `php artisan cache:clear` au déploiement : cela effacerait les compteurs du budget football.

## Limite de cette version

Les réglages Auto/Manuel/Pause restent partagés entre visiteurs comme dans la version d’origine. Le site est prévu pour un usage personnel ; une publication à un large public nécessitera de réserver ces réglages à l’administrateur et de revoir les limites de trafic/API.
