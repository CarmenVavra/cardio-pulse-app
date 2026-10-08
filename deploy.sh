#!/usr/bin/env bash
# Installation und Update auf dem Server (z. B. netcup-Webhosting per SSH):
#   ./deploy.sh
# Holt den neuesten Stand von GitHub, gleicht Pakete ab, baut die Assets,
# führt Migrationen aus und erneuert die Caches. Bricht beim ersten Fehler ab.
# Andere PHP-Version als die Standardversion: PHP=/usr/local/php83/bin/php ./deploy.sh
set -euo pipefail

cd "$(dirname "$0")"
PHP="${PHP:-php}"

fail() {
    echo "FEHLER: $1" >&2
    exit 1
}

"$PHP" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' \
    || fail "PHP 8.2 oder neuer nötig (gefunden: $("$PHP" -r 'echo PHP_VERSION;')). Standardversion in /conf/phpversion setzen."
node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 12) || (a === 20 && b >= 19) ? 0 : 1)' \
    || fail "Node.js 20.19+ oder 22.12+ nötig (gefunden: $(node -v))."
[ -f .env ] || fail ".env fehlt – zuerst .env.example nach .env kopieren und anpassen (siehe README, Produktivbetrieb)."

installed=false
[ -f vendor/autoload.php ] && installed=true

if $installed; then
    "$PHP" artisan down --retry=30
    # Wartungsmodus auch bei Fehlern wieder beenden.
    trap '"$PHP" artisan up' EXIT
fi

echo "==> Neuesten Stand holen"
git pull --ff-only

echo "==> PHP-Pakete"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Assets bauen"
npm ci --no-audit --no-fund
npm run build

echo "==> Datenbank"
if grep -qE '^DB_CONNECTION=sqlite' .env && [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi
grep -qE '^APP_KEY=.+' .env || "$PHP" artisan key:generate --force
"$PHP" artisan migrate --force

echo "==> Caches"
"$PHP" artisan optimize

echo "Fertig."
