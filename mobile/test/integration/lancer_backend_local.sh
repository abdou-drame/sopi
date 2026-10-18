#!/usr/bin/env bash
# Lance un backend Laravel LOCAL et jetable pour les tests d'intégration du mobile.
# - base SQLite dans un dossier temporaire, recréée à chaque lancement ;
# - PAYMENT_DRIVER=fake : aucun appel réseau, aucune clé DexPay ;
# - e-mail et push en mode « log » ;
# - écoute uniquement sur 127.0.0.1.
# Usage : bash lancer_backend_local.sh [port]   (port 8765 par défaut)
# Les valeurs ci-dessous sont factices et ne servent qu'en local. backend/.env est ignoré par Git.
set -euo pipefail
PORT="${1:-8765}"
RACINE="$(cd "$(dirname "$0")/../../.." && pwd)"
BACKEND="$RACINE/backend"
DOSSIER_JETABLE="${SOPI_INTEGRATION_DIR:-${TMPDIR:-/tmp}/sopi-integration}"
mkdir -p "$DOSSIER_JETABLE"
rm -f "$DOSSIER_JETABLE/base.sqlite"
: > "$DOSSIER_JETABLE/base.sqlite"
SQLITE_WIN="$(cd "$DOSSIER_JETABLE" && pwd -W 2>/dev/null || pwd)/base.sqlite"

cd "$BACKEND"
if [ -e .env ] && ! grep -q "SOPI_INTEGRATION_LOCAL=1" .env; then
  echo "backend/.env existe déjà et n'est pas celui des tests d'intégration : je ne l'écrase pas." >&2
  exit 1
fi
CLE="$(php artisan key:generate --show --no-ansi 2>/dev/null | tail -1)"
cat > .env <<ENV
SOPI_INTEGRATION_LOCAL=1
APP_NAME=Sopi
APP_ENV=local
APP_KEY=$CLE
APP_DEBUG=false
APP_URL=http://127.0.0.1:$PORT
APP_TIMEZONE=Africa/Dakar
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
DB_CONNECTION=sqlite
DB_DATABASE=$SQLITE_WIN
SESSION_DRIVER=array
CACHE_STORE=file
QUEUE_CONNECTION=sync
PAYMENT_DRIVER=fake
DEXPAY_SECRET_KEY=secret-factice-local-uniquement
PAYMENT_SIGNATURE_HEADER=X-Webhook-Signature
MAIL_MAILER=log
NOTIFICATION_EMAIL_DRIVER=log
NOTIFICATION_PUSH_DRIVER=log
ADMIN_NOM=Admin
ADMIN_PRENOM=Local
ADMIN_TELEPHONE=770000000
ADMIN_EMAIL=admin-local@sopi.invalid
ADMIN_PASSWORD=MotDePasseLocal-123
ENV
php artisan config:clear >/dev/null
php artisan migrate:fresh --force --no-ansi
php artisan sopi:creer-admin --no-ansi || true
echo "Backend local prêt sur http://127.0.0.1:$PORT (base : $SQLITE_WIN)"
exec php artisan serve --host=127.0.0.1 --port="$PORT" --no-reload
