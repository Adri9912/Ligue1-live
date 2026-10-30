#!/usr/bin/env bash
set -euo pipefail
root=$(cd "$(dirname "$0")/.." && pwd)
work=$(mktemp -d)
trap 'rm -rf -- "$work"' EXIT
export LIGUE1_APP_ROOT="$work/app" LIGUE1_DEPLOY_STATE="$work/state"
export DEPLOY_TEST_ROOT="$work"
mkdir -p "$work/bin" "$work/app/storage/framework/cache" "$work/app/bootstrap/cache" "$work/app/vendor" "$work/new/dist" "$work/new/bootstrap/cache"
printf 'private-config\n' > "$work/app/.env"
printf '7\n' > "$work/app/storage/framework/cache/budget"
printf 'old\n' > "$work/app/artisan"
printf 'old\n' > "$work/app/vendor/version"
touch "$work/app/obsolete.php"
cat > "$work/bin/php" <<'PHP'
#!/usr/bin/env bash
set -eu
if [[ "$*" == 'artisan down --retry=15' ]]; then touch storage/framework/down; fi
if [[ "$*" == 'artisan up' ]]; then rm -f storage/framework/down; fi
if [[ "$1" == '-r' && -f "$DEPLOY_TEST_ROOT/fail-health" ]]; then exit 1; fi
PHP
cat > "$work/bin/composer" <<'COMPOSER'
#!/usr/bin/env bash
set -eu
if [[ "$1" == install ]]; then
    cp artisan vendor/version
    if [[ -f "$DEPLOY_TEST_ROOT/fail-install" ]]; then exit 1; fi
fi
COMPOSER
chmod +x "$work/bin/php" "$work/bin/composer"
export PATH="$work/bin:$PATH"
printf 'new\n' > "$work/new/artisan"
printf 'frontend\n' > "$work/new/dist/index.html"
tar -czf "$work/release.tar.gz" -C "$work/new" .
bash "$root/scripts/deploy-alwaysdata.sh" "$work/release.tar.gz"
test "$(cat "$work/app/artisan")" = new
test "$(cat "$work/app/vendor/version")" = new
test ! -f "$work/app/obsolete.php"
test -f "$work/state/previous.tar.gz"
# Deux échecs réels du script : installation puis contrôle après remise en ligne.
for failure in fail-install fail-health; do
    touch "$work/$failure"
    printf 'broken\n' > "$work/new/artisan"
    tar -czf "$work/release.tar.gz" -C "$work/new" .
    if bash "$root/scripts/deploy-alwaysdata.sh" "$work/release.tar.gz"; then
        echo 'Un déploiement en échec ne doit pas réussir.' >&2; exit 1
    fi
    test "$(cat "$work/app/artisan")" = new
    test "$(cat "$work/app/vendor/version")" = new
    test ! -f "$work/app/storage/framework/down"
    rm "$work/$failure"
done
test "$(cat "$work/app/.env")" = private-config
test "$(cat "$work/app/storage/framework/cache/budget")" = 7
if tar -tzf "$work/state/previous.tar.gz" | grep -Eq '^\./(\.env|storage/|bootstrap/cache/)'; then
    echo 'La sauvegarde doit exclure les secrets et le cache.' >&2; exit 1
fi
echo 'Déploiement et restaurations vérifiés ; configuration et quota préservés.'
