#!/usr/bin/env bash
# Test de bout en bout de l'intégration DexPay contre le BAC À SABLE.
#
# NE JAMAIS lancer contre la production : ce script crée une session de paiement, demande un remboursement et un versement.
# Il lit tout dans des variables d'environnement et n'affiche jamais une clé ni un numéro complet.
#
# Variables obligatoires :
#   DEXPAY_BASE_URL      adresse du bac à sable (doit contenir « sandbox »), ex. https://api-sandbox.dexpay.africa/api/v1
#   DEXPAY_PUBLIC_KEY    clé publique du bac à sable
#   DEXPAY_SECRET_KEY    clé secrète du bac à sable (signe aussi les webhooks)
#   E2E_APP_URL          adresse publique du backend testé (pour webhook_url), ex. https://sopi-api.example.org
#   E2E_PAYOUT_PHONE     numéro de test du bac à sable pour le versement, au format +221…
# Variables facultatives :
#   E2E_PAYOUT_OPERATOR  code opérateur du versement (défaut : wave_sn_payout)
#   E2E_AMOUNT           montant en FCFA entier (défaut : 1000)
#   E2E_WEBHOOK_URL      adresse du webhook de Sopi où envoyer l'événement signé simulé (défaut : aucune, vérification locale seulement)
#
# Étapes : 1 création de session, 2 lecture de payment_url, 3 signature d'un webhook simulé, 4 remboursement d'un paiement terminé
# (à confirmer à la main), 5 versement minimal.

set -u

manquantes=()
for variable in DEXPAY_BASE_URL DEXPAY_PUBLIC_KEY DEXPAY_SECRET_KEY E2E_APP_URL E2E_PAYOUT_PHONE; do
  if [ -z "${!variable:-}" ]; then
    manquantes+=("$variable")
  fi
done
if [ "${#manquantes[@]}" -gt 0 ]; then
  echo "ERREUR : variable(s) d'environnement manquante(s) : ${manquantes[*]}" >&2
  echo "Voir l'en-tête de ce script pour la liste et le rôle de chaque variable." >&2
  exit 2
fi

for outil in curl openssl; do
  command -v "$outil" > /dev/null 2>&1 || { echo "ERREUR : l'outil « $outil » est introuvable." >&2; exit 2; }
done

case "$DEXPAY_BASE_URL" in
  *sandbox*) ;;
  *) echo "ERREUR : DEXPAY_BASE_URL ne ressemble pas à un bac à sable (« sandbox » absent). Arrêt par sécurité." >&2; exit 2 ;;
esac

BASE="${DEXPAY_BASE_URL%/}"
MONTANT="${E2E_AMOUNT:-1000}"
OPERATEUR="${E2E_PAYOUT_OPERATOR:-wave_sn_payout}"
SUFFIXE="$(date +%s)"
REFERENCE="SOPI-E2E-$SUFFIXE"
echec=0

etape() { printf '\n== %s\n' "$1"; }
ok() { printf '   OK  : %s\n' "$1"; }
ko() { printf '   KO  : %s\n' "$1"; echec=1; }

# Extrait un champ JSON texte (premier trouvé) sans dépendre de jq.
champ() { sed -n "s/.*\"$1\"[[:space:]]*:[[:space:]]*\"\([^\"]*\)\".*/\1/p" | head -n 1; }

appel() { # appel METHODE CHEMIN CORPS [ENTETE_SUPPLEMENTAIRE]
  local methode="$1" chemin="$2" corps="${3:-}" extra="${4:-}"
  local args=(-sS --max-time 30 -o /tmp/e2e-dexpay-corps.$$ -w '%{http_code}' -X "$methode"
    -H "x-api-key: $DEXPAY_PUBLIC_KEY" -H "x-api-secret: $DEXPAY_SECRET_KEY" -H 'Accept: application/json')
  [ -n "$corps" ] && args+=(-H 'Content-Type: application/json' --data "$corps")
  [ -n "$extra" ] && args+=(-H "$extra")
  curl "${args[@]}" "$BASE$chemin" 2> /dev/null || echo "000"
}
trap 'rm -f /tmp/e2e-dexpay-corps.$$' EXIT

etape "1. Création d'une session de paiement ($REFERENCE, $MONTANT XOF)"
code=$(appel POST /checkout-sessions "{\"reference\":\"$REFERENCE\",\"item_name\":\"Test E2E Sopi\",\"amount\":$MONTANT,\"currency\":\"XOF\",\"success_url\":\"$E2E_APP_URL/paiement/succes\",\"failure_url\":\"$E2E_APP_URL/paiement/echec\",\"webhook_url\":\"$E2E_APP_URL/api/v1/webhooks/paiement\",\"is_one_shot_payment\":true,\"metadata\":{\"e2e\":\"true\"}}")
if [ "$code" = "200" ] || [ "$code" = "201" ]; then ok "HTTP $code"; else ko "HTTP $code (session non créée)"; fi

etape "2. Lecture de l'adresse de paiement"
url=$(champ sandbox_payment_url < /tmp/e2e-dexpay-corps.$$)
[ -z "$url" ] && url=$(champ payment_url < /tmp/e2e-dexpay-corps.$$)
if [ -n "$url" ]; then
  ok "adresse reçue (ouvrir dans un navigateur pour payer avec un numéro de test) : $url"
else
  ko "aucune adresse de paiement dans la réponse"
fi

etape "3. Signature d'un webhook simulé"
evenement="{\"event\":\"checkout.completed\",\"reference\":\"$REFERENCE\",\"amount\":$MONTANT,\"currency\":\"XOF\"}"
signature=$(printf '%s' "$evenement" | openssl dgst -sha256 -hmac "$DEXPAY_SECRET_KEY" | sed 's/^.* //')
if [ "${#signature}" -eq 64 ]; then ok "signature HMAC-SHA256 calculée (64 caractères hexadécimaux)"; else ko "signature inattendue"; fi
if [ -n "${E2E_WEBHOOK_URL:-}" ]; then
  code=$(curl -sS --max-time 30 -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-Webhook-Signature: $signature" --data "$evenement" "$E2E_WEBHOOK_URL" 2> /dev/null || echo "000")
  # La référence est inconnue de Sopi : un 200 « ignoré » prouve que la signature est acceptée, un 401 qu'elle est refusée.
  if [ "$code" = "200" ]; then ok "webhook accepté par Sopi (HTTP 200)"; else ko "webhook refusé ou injoignable (HTTP $code)"; fi
else
  echo "   --  : E2E_WEBHOOK_URL absent, envoi à Sopi sauté"
fi

etape "4. Remboursement d'un paiement terminé"
echo "   Pour tester le remboursement, payez d'abord la session de l'étape 2 avec un numéro de test, puis :"
if [ -n "${E2E_REFUND_REFERENCE:-}" ]; then
  code=$(appel POST "/checkout-sessions/refund/$E2E_REFUND_REFERENCE")
  if [ "$code" = "200" ] || [ "$code" = "201" ]; then ok "remboursement demandé (HTTP $code)"; else ko "remboursement refusé (HTTP $code) ; le remboursement exige peut-être des clés live validées"; fi
else
  echo "   --  : définir E2E_REFUND_REFERENCE=<référence d'un paiement terminé> pour exécuter cette étape (sautée)"
fi

etape "5. Versement minimal ($MONTANT XOF)"
ref_versement="RTR-E2E-$SUFFIXE"
code=$(appel POST /payouts "{\"reference\":\"$ref_versement\",\"amount\":$MONTANT,\"currency\":\"XOF\",\"destination_phone\":\"$E2E_PAYOUT_PHONE\",\"destination_details\":{\"operator\":\"$OPERATEUR\",\"countryISO\":\"SN\"},\"webhook_url\":\"$E2E_APP_URL/api/v1/webhooks/paiement\",\"metadata\":{\"e2e\":\"true\"}}" "Idempotency-Key: $ref_versement")
if [ "$code" = "200" ] || [ "$code" = "201" ]; then
  ok "versement accepté (HTTP $code), statut : $(champ status < /tmp/e2e-dexpay-corps.$$)"
else
  ko "versement refusé (HTTP $code)"
fi
code=$(appel GET "/payouts/$ref_versement")
if [ "$code" = "200" ]; then ok "lecture du versement (HTTP 200), statut : $(champ status < /tmp/e2e-dexpay-corps.$$)"; else ko "lecture du versement impossible (HTTP $code)"; fi

echo
if [ "$echec" -eq 0 ]; then echo "Résultat : toutes les étapes exécutées sont OK."; else echo "Résultat : au moins une étape a échoué (voir KO ci-dessus)." >&2; fi
exit "$echec"
