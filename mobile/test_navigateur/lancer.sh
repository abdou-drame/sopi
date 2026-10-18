#!/usr/bin/env bash
# Test navigateur : backend local jetable + app Flutter web + comptes de test. Tout reste sur 127.0.0.1.
# Usage :  bash test_navigateur/lancer.sh demarrer [--sans-build]   |   bash test_navigateur/lancer.sh arreter
# Ports (modifiables) : PORT_API (8765), PORT_WEB (8099).
# Le parcours Playwright se lance ensuite avec : node test_navigateur/parcours.mjs
set -euo pipefail
PORT_API="${PORT_API:-8765}"
PORT_WEB="${PORT_WEB:-8099}"
ICI="$(cd "$(dirname "$0")" && pwd)"
MOBILE="$(cd "$ICI/.." && pwd)"
RACINE="$(cd "$MOBILE/.." && pwd)"
JETABLE="$ICI/.jetable"
API_URL="http://127.0.0.1:${PORT_API}/api/v1"

arreter() {
  local demarre=0
  [ -f "$JETABLE/backend.pid" ] && demarre=1
  for f in backend web; do
    if [ -f "$JETABLE/$f.pid" ]; then
      kill "$(cat "$JETABLE/$f.pid")" 2>/dev/null || true
      rm -f "$JETABLE/$f.pid"
    fi
  done
  # php artisan serve lance un processus fils : on libère aussi le port.
  [ "$demarre" = 1 ] && for p in "$PORT_API" "$PORT_WEB"; do
    pid="$(netstat -ano 2>/dev/null | grep -E "127\.0\.0\.1:$p .*LISTENING" | awk '{print $5}' | head -1 || true)"
    [ -n "${pid:-}" ] && taskkill //PID "$pid" //F >/dev/null 2>&1 || true
  done
  if [ -f "$RACINE/backend/.env" ] && grep -q "SOPI_INTEGRATION_LOCAL=1" "$RACINE/backend/.env"; then
    rm -f "$RACINE/backend/.env"
  fi
  rm -f "$JETABLE/comptes.json"
  echo "Backend local arrêté, backend/.env jetable et comptes supprimés."
}

if [ "${1:-}" = "arreter" ]; then arreter; exit 0; fi
[ "${1:-}" = "demarrer" ] || { echo "Usage : lancer.sh demarrer|arreter" >&2; exit 2; }

# Garde-fou : jamais d'appel à la production.
case "$API_URL" in
  *sopi-api.duckdns.org*) echo "Refus : l'URL de l'API vise la production ($API_URL)." >&2; exit 1 ;;
  http://127.0.0.1:*) ;;
  *) echo "Refus : l'URL de l'API doit être http://127.0.0.1:<port>/api/v1 ($API_URL)." >&2; exit 1 ;;
esac

mkdir -p "$JETABLE"
# Refuse de démarrer si un port est déjà pris (par exemple par un autre serveur de développement) : on ne tue jamais un processus étranger.
for p in "$PORT_API" "$PORT_WEB"; do
  if netstat -ano 2>/dev/null | grep -qE "[:.]$p +[0-9.:*]+ +LISTENING"; then
    echo "Le port $p est déjà utilisé : choisissez un autre port (PORT_API / PORT_WEB) ou libérez-le." >&2; exit 1
  fi
done
# CORS : variable d'environnement du processus (prioritaire sur .env), rien n'est écrit dans le dépôt.
export CORS_ALLOWED_ORIGINS="http://127.0.0.1:${PORT_WEB}"
export SOPI_INTEGRATION_DIR="$JETABLE/base"

echo "== Backend local sur 127.0.0.1:${PORT_API}"
nohup bash "$MOBILE/test/integration/lancer_backend_local.sh" "$PORT_API" > "$JETABLE/backend.log" 2>&1 &
echo $! > "$JETABLE/backend.pid"
for i in $(seq 1 60); do
  if curl -s -o /dev/null "http://127.0.0.1:${PORT_API}/api/v1/metiers"; then break; fi
  sleep 1
  [ "$i" = 60 ] && { echo "Le backend ne répond pas (voir $JETABLE/backend.log)." >&2; exit 1; }
done

if [ "${2:-}" != "--sans-build" ]; then
  echo "== Construction de l'app web (API_URL=$API_URL)"
  (cd "$MOBILE" && flutter build web --release --no-wasm-dry-run --dart-define=API_URL="$API_URL" 2>&1 | tail -3)
fi

echo "== Serveur statique sur 127.0.0.1:${PORT_WEB}"
nohup node "$ICI/serveur_statique.mjs" "$MOBILE/build/web" "$PORT_WEB" > "$JETABLE/web.log" 2>&1 &
echo $! > "$JETABLE/web.pid"
for i in $(seq 1 20); do
  curl -s -o /dev/null "http://127.0.0.1:${PORT_WEB}/" && break
  sleep 0.5
  [ "$i" = 20 ] && { echo "Le serveur web ne répond pas (voir $JETABLE/web.log)." >&2; exit 1; }
done

# Remet à zéro les limites de débit (le cache fichier survit à migrate:fresh).
(cd "$RACINE/backend" && php artisan cache:clear --no-ansi >/dev/null)

echo "== Comptes de test (mots de passe jetables dans .jetable/comptes.json, ignoré par Git)"
PORT_API="$PORT_API" node "$ICI/preparer.mjs"
echo "Prêt : app http://127.0.0.1:${PORT_WEB}  |  API $API_URL"
