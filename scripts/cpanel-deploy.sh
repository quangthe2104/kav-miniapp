#!/bin/bash
# Deploy KavMiniApp from the cPanel repo checkout into the domain document root.
# Usage: scripts/cpanel-deploy.sh /home/<user>/public_html
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOYPATH="${1:-$HOME/public_html}"

log() { echo "[kav-deploy] $*"; }

find_php() {
    if [ -n "${PHP_BIN:-}" ]; then echo "$PHP_BIN"; return; fi
    for candidate in \
        /opt/cpanel/ea-php84/root/usr/bin/php \
        /opt/cpanel/ea-php83/root/usr/bin/php \
        /usr/local/bin/php \
        "$(command -v php || true)"; do
        if [ -n "$candidate" ] && [ -x "$candidate" ] \
            && "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' 2>/dev/null; then
            echo "$candidate"
            return
        fi
    done
    echo "PHP >= 8.3 not found (set PHP_BIN)" >&2
    exit 1
}

PHP="$(find_php)"
log "PHP: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

COMPOSER_PHAR="$HOME/.local/bin/composer.phar"
# Run composer under the same PHP >= 8.3 (the system composer shebang may point at an older PHP).
composer_run() {
    local sys
    sys="$(command -v composer || true)"
    if [ -n "$sys" ]; then
        sys="$(readlink -f "$sys")"
        if "$PHP" "$sys" --version >/dev/null 2>&1; then
            "$PHP" "$sys" "$@"
            return
        fi
    fi
    if [ ! -f "$COMPOSER_PHAR" ]; then
        log "Downloading composer.phar"
        mkdir -p "$(dirname "$COMPOSER_PHAR")"
        "$PHP" -r "copy('https://getcomposer.org/download/latest-stable/composer.phar', '$COMPOSER_PHAR');"
    fi
    "$PHP" "$COMPOSER_PHAR" "$@"
}

mkdir -p "$DEPLOYPATH"

# No --delete at the docroot level: public_html may hold .well-known (SSL), cgi-bin, etc.
# --no-perms: the cPanel repo dir is 0700; copying its mode would make the docroot unreadable by Apache.
RSYNC_OPTS=(-rlt --no-perms --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r)
log "Sync code -> $DEPLOYPATH"
rsync "${RSYNC_OPTS[@]}" \
    --exclude '/.git/' \
    --exclude '/.cursor/' \
    --exclude '/.githooks/' \
    --exclude '/.cpanel.yml' \
    --exclude '/docs/' \
    --exclude '/scripts/' \
    --exclude '/miniapp/' \
    --exclude '/AGENTS.md' \
    --exclude '/README.md' \
    --exclude '/.gitignore' \
    --exclude '/.gitattributes' \
    --exclude '/backend/.env' \
    --exclude '/backend/vendor/' \
    --exclude '/backend/node_modules/' \
    --exclude '/backend/public/storage' \
    "$REPO_DIR/" "$DEPLOYPATH/"

# Code folders are safe to mirror exactly (no user data inside).
for dir in app config database resources routes public/miniapp; do
    src="$REPO_DIR/backend/$dir"
    [ -d "$src" ] && rsync "${RSYNC_OPTS[@]}" --delete "$src/" "$DEPLOYPATH/backend/$dir/"
done

cd "$DEPLOYPATH/backend"

FRESH_ENV=0
if [ ! -f .env ]; then
    cp .env.example .env
    FRESH_ENV=1
    log "Created backend/.env from .env.example"
fi

log "composer install"
composer_run install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' .env; then
    "$PHP" artisan key:generate --force
fi

chmod -R u+rwX storage bootstrap/cache
chmod 600 .env

if [ "$FRESH_ENV" -eq 1 ]; then
    log "STOP: edit $DEPLOYPATH/backend/.env (APP_URL, DB_*, ZALO_*, APP_ENV=production, APP_DEBUG=false) then deploy again."
    exit 0
fi

"$PHP" artisan migrate --force
[ -L public/storage ] || "$PHP" artisan storage:link || true

"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart || true

log "Done."
