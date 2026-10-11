# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {VOTRE_JETON}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Le jeton est renvoyé par <code>POST /api/v1/auth/connexion</code> ou <code>/auth/inscription</code>.
