#!/usr/bin/env bash
# Installation und Update auf dem Server (z. B. netcup-Webhosting per SSH):
#   ./deploy.sh
# Holt den neuesten Stand von GitHub, gleicht Pakete ab, baut die Assets,
# führt Migrationen aus und erneuert die Caches. Bricht beim ersten Fehler ab.
# Andere PHP-Version als die Standardversion: PHP=/usr/local/php83/bin/php ./deploy.sh
set -euo pipefail

PHP="${PHP:-php}"

fail() {
    echo "FEHLER: $1" >&2
    exit 1
}

# Composer verwenden, falls installiert – sonst composer.phar ins Projekt laden
# und gegen die offizielle Prüfsumme verifizieren.
composer_cmd() {
    if command -v composer > /dev/null 2>&1; then
        COMPOSER=(composer)
        return
    fi

    if [ ! -f composer.phar ]; then
        echo "==> Composer herunterladen"
        "$PHP" -r '
            $url = "https://getcomposer.org/download/latest-stable/composer.phar";
            $phar = file_get_contents($url);
            $sum = trim(explode(" ", file_get_contents($url.".sha256sum"))[0]);
            if ($phar === false || ! hash_equals($sum, hash("sha256", $phar))) {
                fwrite(STDERR, "Prüfsumme von composer.phar stimmt nicht.\n");
                exit(1);
            }
            file_put_contents("composer.phar", $phar);
        ' || fail "Composer konnte nicht geladen werden."
    fi

    COMPOSER=("$PHP" composer.phar)
}

# Alles steht in einer Funktion: Bash liest sie vollständig ein, bevor sie läuft –
# so bleibt der Ablauf stabil, auch wenn „git pull“ diese Datei aktualisiert.
main() {
    cd "$(dirname "$0")"

    "$PHP" -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' \
        || fail "PHP 8.2 oder neuer nötig (gefunden: $("$PHP" -r 'echo PHP_VERSION;')). Standardversion in /conf/phpversion setzen."
    # Ohne Node.js (z. B. netcup-Webhosting) baut upload-assets.bat die Assets lokal und lädt sie hoch.
    build_assets=false
    if command -v node > /dev/null 2>&1; then
        node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 12) || (a === 20 && b >= 19) ? 0 : 1)' \
            || fail "Node.js 20.19+ oder 22.12+ nötig (gefunden: $(node -v))."
        build_assets=true
    elif [ ! -f public/build/manifest.json ]; then
        fail "Node.js fehlt und es gibt noch keine Assets – zuerst auf dem eigenen PC upload-assets.bat ausführen."
    fi
    [ -f .env ] || fail ".env fehlt – zuerst .env.example nach .env kopieren und anpassen (siehe README, Produktivbetrieb)."

    if [ -f vendor/autoload.php ]; then
        "$PHP" artisan down --retry=30
        # Wartungsmodus auch bei Fehlern wieder beenden.
        trap '"$PHP" artisan up' EXIT
    fi

    echo "==> Neuesten Stand holen"
    git pull --ff-only

    echo "==> PHP-Pakete"
    composer_cmd
    "${COMPOSER[@]}" install --no-dev --optimize-autoloader --no-interaction --no-progress

    if $build_assets; then
        echo "==> Assets bauen"
        npm ci --no-audit --no-fund
        npm run build
    else
        echo "==> Assets: Node.js fehlt – verwende die mit upload-assets.bat hochgeladenen Dateien"
    fi

    echo "==> Datenbank"
    if grep -qE '^DB_CONNECTION=sqlite' .env && [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
    fi
    grep -qE '^APP_KEY=.+' .env || "$PHP" artisan key:generate --force
    "$PHP" artisan migrate --force

    echo "==> Caches"
    "$PHP" artisan optimize

    echo "Fertig."
}

main "$@"
