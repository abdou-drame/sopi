# Application mobile : construction et distribution de l'APK

Ce document décrit comment l'APK Android de Sopi est construit, signé et publié. Il ne contient aucun secret.

## Construire l'APK en local

Prérequis : Flutter 3.41.2 (Dart 3.11), JDK 17, Android SDK avec les licences acceptées (`flutter doctor --android-licenses`).

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://sopi-api.duckdns.org
```

L'APK est produit dans `mobile/build/app/outputs/flutter-apk/app-release.apk`.

- `API_BASE_URL` est injectée au build. Sans elle, la valeur par défaut est `https://sopi-api.duckdns.org`.
- Sans `mobile/android/key.properties`, l'APK release est signé avec la clé de debug : il convient pour un essai, pas pour une publication.

## Signature

La clé de signature release est un keystore (alias `sopi`, RSA 2048, validité 10 000 jours). Il est stocké hors du dépôt, dans le dossier `.secrets/` (ignoré par Git). Gradle lit `mobile/android/key.properties` (ignoré par Git) ; en CI, ce fichier et le keystore sont recréés pendant le build uniquement, à partir des secrets GitHub.

> **Sauvegarde obligatoire.** Sauvegardez `.secrets/sopi-release.jks` et ses mots de passe dans un endroit sûr et distinct (gestionnaire de mots de passe, coffre chiffré), en plus de GitHub. **Si cette clé est perdue, il est impossible de publier une mise à jour installable par-dessus l'application existante** : les utilisateurs devraient la désinstaller. Ne la committez jamais, ne la partagez jamais.

## Secrets GitHub à créer

Dans le dépôt GitHub : *Settings → Secrets and variables → Actions → New repository secret*. Les valeurs se trouvent dans `.secrets/mobile-secrets.txt` (local, jamais committé).

| Nom du secret | Contenu |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | Le keystore encodé en base64, sur une seule ligne |
| `ANDROID_KEYSTORE_PASSWORD` | Mot de passe du keystore |
| `ANDROID_KEY_ALIAS` | Alias de la clé (`sopi`) |
| `ANDROID_KEY_PASSWORD` | Mot de passe de la clé |

## Workflow GitHub Actions

Fichier : `.github/workflows/mobile.yml`. Il se déclenche sur les push et pull requests qui touchent `mobile/**` ou le workflow lui-même.

| Contexte | Tests | APK | Signature | Release |
|---|---|---|---|---|
| Branche `feature/*`, pull request | oui | artefact du workflow | release si les secrets existent, sinon debug (avertissement) | non |
| `main` | oui | artefact du workflow | release **obligatoire** (le build échoue avec un message clair si les secrets manquent) | oui |

Chaque build produit `sopi-v0.1.<numéro de run>.apk`. Sur `main`, une release GitHub `mobile-v0.1.<numéro de run>` est créée avec cet APK ; le numéro de run est unique, donc le tag aussi.

## Vérification

1. Onglet **Actions** du dépôt : le workflow « Mobile (APK) » est vert.
2. Sur une branche `feature/*`, télécharger l'artefact depuis la page de l'exécution.
3. Sur `main`, l'APK apparaît dans **Releases**.
4. Vérifier la signature (outil `apksigner` du SDK Android) :
   ```bash
   apksigner verify --print-certs sopi-v0.1.N.apk
   ```
   Le certificat doit être celui de la clé `sopi`, et non « Android Debug ».

## Télécharger et installer

1. Sur le téléphone Android, ouvrir la page **Releases** du dépôt et télécharger le fichier `.apk` de la dernière version.
2. Ouvrir le fichier ; autoriser l'installation depuis cette source si Android le demande.
3. Lancer **Sopi** : la page d'accueil affiche l'état de l'API et de la base de données.

Une version signée avec une autre clé (debug ou autre) ne peut pas être installée par-dessus une version signée avec la clé release : désinstaller d'abord.
