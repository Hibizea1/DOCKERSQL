# DOCKERSQL — Installation et exécution

Ce dépôt contient une stack Docker pour servir une application PHP/Apache + MySQL, phpMyAdmin et MailHog. Le service `php` expose le frontend sur `/public` et les endpoints backend sur `/php`.

**Accès principaux**

- **Site (HTTPS)**: `https://localhost:8443/`
- **Site (HTTP)**: `http://localhost:8080/` (redirection vers HTTPS)
- **phpMyAdmin**: `http://localhost:8899/` (création des identifiants décrite ci-dessous)
- **MailHog UI**: `http://localhost:8025/`

**Ports exposés (par défaut)**

- Apache: `8080` -> 80, `8443` -> 443
- MySQL: `3307` -> 3306
- phpMyAdmin: `8899` -> 80
- MailHog: `1025`, `8025`

**Prérequis**

- Docker Engine (Windows: Docker Desktop) installé et démarré
- Docker Compose v2 (inclus avec Docker Desktop)
- (Optionnel) `mkcert` pour un certificat local sans warnings

**Préparer un certificat local (optionnel mais recommandé)**

1. Installer `mkcert` (Windows):
   - `winget install FiloSottile.mkcert`
2. Générer le certificat et la clé (le projet propose un script PowerShell):
   - `powershell -ExecutionPolicy Bypass -File .\scripts\generate-local-cert.ps1`
3. Les fichiers générés doivent être présents dans `certs/server.crt` et `certs/server.key`.

Si vous ne fournissez pas de certificats, Apache démarrera quand même (mais le navigateur affichera un avertissement).

**Gestion des mots de passe et secrets**

Les mots de passe ne sont pas fournis dans ce dépôt. Vous devez créer vos propres secrets avant de démarrer la stack. Deux méthodes conseillées :

- Créer un fichier `.env` à la racine du projet (non commité) contenant les variables listées ci-dessous.
- Ou définir les variables d'environnement sur votre machine / dans votre orchestrateur avant d'exécuter `docker compose up`.

Exemple minimal de fichier `.env` (à adapter) :

```env
# Base de données
MYSQL_ROOT_PASSWORD=change_me_secure
MYSQL_DATABASE=unreal_game

# phpMyAdmin
PMA_USER=admin
PMA_PASSWORD=change_me_secure

# Mail (exemple)
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@example.com
MAIL_PASSWORD=change_me_secure
```

Ne commitez jamais `.env` dans le dépôt. Vous pouvez également modifier directement les valeurs `environment:` dans [docker-compose.yml](docker-compose.yml#L1).

**Installation & lancement (build + run)**
Depuis la racine du projet, exécutez:

```bash
docker compose up -d --build
```

Le service `php` est construit depuis le `Dockerfile` du projet. L'image contient un petit `entrypoint` qui vérifie la présence d'un `composer.json` dans `web/php` et lance `composer install` si `vendor/` est manquant.

**Notes sur `composer` et volumes (Windows)**

- Le répertoire `web/php` est monté en volume dans le conteneur. Si `composer install` échoue à l'intérieur du conteneur à cause des permissions sur Windows, vous pouvez exécuter `composer install` localement sur votre machine dans `web/php` (ou ajuster les permissions sur le dossier monté).

Exemples de commandes utiles:

```bash
# Rebuild et relancer
docker compose up -d --build

# Voir les logs du service PHP
docker compose logs -f php

# Exécuter une commande bash dans le conteneur PHP
docker compose exec php bash

# Lancer composer manuellement (si besoin)
cd web/php && composer install --no-dev --optimize-autoloader
```

**Variables d'environnement importantes**

- Mail: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME` (définies dans `docker-compose.yml` ou via `.env` si vous en ajoutez un)
- Base de données: `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE` (définies pour le service `db`)
- phpMyAdmin: `PMA_USER`, `PMA_PASSWORD`, `PMA_HOST` (définies pour le service `phpma` / dans `docker-compose.yml`)

**Endpoints utiles (synchronisation jeu/wiki)**

- `POST https://localhost:8443/php/sync_wiki_game_data.php` — endpoint principal pour synchroniser données jeu/wiki.
- Headers recommandés: `Content-Type: application/json`, `X-Client-Type: game`, `X-Sync-Token: <secret>` (si `WIKI_SYNC_SECRET` est configuré).

**Dépannage rapide**

- Si Apache ne démarre pas: vérifiez `docker compose logs php` pour les erreurs de configuration Apache (chemins de certificats, modules activés).
- Si MySQL ne démarre pas: supprimez les containers et reconstruisez, ou vérifiez les permissions du dossier `mysql-data`.
- Pour éviter que les mounts Windows bloquent l'installation composer, installez les dépendances sur l'hôte puis relancez le conteneur.

**Prochaine étape suggérée**

- Voulez-vous que je lance la construction et le test de l'image Docker depuis cet environnement (nécessite Docker local) ? Sinon je peux ajouter des vérifications supplémentaires dans l'entrypoint (ex: gestion fine des permissions Windows).
