#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

# Une installation PHP existante ; .env et storage restent toujours sur le serveur.
app="${LIGUE1_APP_ROOT:-/home/ligue1-live/www/ligue1-live}"
state="${LIGUE1_DEPLOY_STATE:-/home/ligue1-live/.ligue1-live-deploy}"
bundle="${1:?Archive de déploiement manquante}"
export PHP_VERSION=8.5
for command in php composer rsync tar flock; do command -v "$command" >/dev/null; done
test -f "$app/.env" && test -f "$app/artisan" && test -f "$bundle"
mkdir -p "$state"
chmod 700 "$state"
exec 9>"$state/lock"
flock -w 120 9
work=$(mktemp -d "$state/work.XXXXXX")
maintenance=0

# Le même filtre protège les secrets, le cache sportif et les dépendances locales.
protected=(--exclude=/.env --exclude='/storage/' --exclude='/bootstrap/cache/' --exclude='/.cache/' --exclude='/.git/' --exclude='/node_modules/')
finish() {
    result=$?
    trap - EXIT
    if (( result != 0 && maintenance )); then
        echo "Échec : restauration de la version précédente." >&2
        mkdir -p "$work/restore"
        if tar -xzf "$state/previous.tar.gz" -C "$work/restore" &&
            rsync -ac --delete "${protected[@]}" "$work/restore/" "$app/" &&
            (cd "$app" && php artisan config:cache && php artisan route:cache && php artisan up); then
            echo "Version précédente restaurée." >&2
        else
            echo "Restauration incomplète : intervention nécessaire, maintenance conservée." >&2
        fi
    fi
    rm -rf -- "$work"
    rm -f -- "$bundle"
    exit "$result"
}
trap finish EXIT
mkdir "$work/new"
tar -xzf "$bundle" -C "$work/new"
test -f "$work/new/artisan" && test -f "$work/new/dist/index.html"
test ! -e "$work/new/.env"
cd "$app"
# Sauvegarde privée : code, interface et vendor ; aucun cache ni clé d'application.
tar --exclude='./.env' --exclude='./storage' --exclude='./bootstrap/cache' \
    --exclude='./.cache' --exclude='./.git' --exclude='./node_modules' \
    -czf "$state/previous.next.tar.gz" .
mv "$state/previous.next.tar.gz" "$state/previous.tar.gz"
php artisan down --retry=15
maintenance=1
rsync -ac --delete "${protected[@]}" --exclude='/vendor/' "$work/new/" "$app/"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan config:cache
php artisan route:cache
php artisan up
# Le contrôle interne vérifie Laravel sans appel aux fournisseurs de football.
php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class); $response = $kernel->handle(Illuminate\Http\Request::create("/api/status")); if ($response->getStatusCode() !== 200) { exit(1); }'
maintenance=0
echo "Déploiement terminé."
