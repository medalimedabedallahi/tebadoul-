# Exploitation Badal : deploiement, sauvegarde, restauration

Scripts bash (Linux, `bash` >= 4, `docker compose` v2). Aucun ne s'execute tout seul ; aucun ne supprime de volume.
Sur le serveur : `chmod +x ops/*.sh`.

| Fichier | Role |
| --- | --- |
| `backup-postgres.sh` | `pg_dump -Fc` horodate, verifie (`pg_restore --list`), chiffre (age/gpg), SHA-256, retention |
| `restore-postgres.sh` | Restaure vers une base **ephemere** (defaut) ou une nouvelle base ; n'ecrase la base active qu'avec `--overwrite-active --confirm-db <nom>` |
| `verify-restore.sh` | Test de restauration de la derniere sauvegarde, sortie JSON pour la supervision |
| `deploy.sh` / `rollback.sh` | Deploiement par tag d'image immuable / retour a l'image precedente |
| `smoke-test.sh` | Test de fumee des images (utilise par la CI et la release) |

## Environnements

- **Developpement** : `compose.yaml` (code monte en volume, Mailpit, Redis unique, Nginx sur 127.0.0.1).
- **Production** : `compose.prod.yaml` (projet `badal-prod`), images taguees `sha-<commit>` produites par
  `.github/workflows/release.yml`. PostgreSQL est **externe** (TLS, `DB_SSLMODE=require`). Redis est separe :
  `redis-queue` (jobs + sessions, `noeviction`, AOF) et `redis-cache` (`allkeys-lru`, sans persistance).
  Le TLS est termine en frontal ; Nginx n'ecoute que sur `127.0.0.1:8080` par defaut.

## Premiere mise en place de la production

```bash
# 1. Secrets : dossier 0700, fichiers 0444 (lisibles par l'utilisateur www-data du conteneur), valeurs hex sans espace.
sudo install -d -m 700 /etc/badal/secrets && cd /etc/badal/secrets
for name in db_password redis_queue_password redis_cache_password; do openssl rand -hex 32 | sudo tee "$name" >/dev/null; done
docker run --rm --entrypoint php <image>:<tag> artisan key:generate --show | sudo tee app_key >/dev/null   # base64:...
# db_password doit aussi etre defini comme mot de passe du role applicatif cote PostgreSQL.
sudo touch mail_password aws_secret_access_key      # puis y ecrire les valeurs reelles (vide accepte si non utilise)
sudo chmod 444 *

# 2. Configuration non secrete : copier .env.production.example en .env.production et l'adapter
#    (APP_URL, DB_HOST, TRUSTED_PROXIES = BADAL_NGINX_IP, SMS_DRIVER, S3, SMTP...). Ne jamais y mettre de secret.
# 3. docker login ghcr.io (jeton en lecture seule) ; le frontal TLS pointe vers 127.0.0.1:8080.
# 4. Premier deploiement (voir plus bas).
```

Chaque valeur de secret doit rester hexadecimale/base64 sans espace ni guillemet (elle est inseree dans une config Redis).
La base PostgreSQL doit avoir un role applicatif dedie (`DB_USERNAME`) et un role de sauvegarde ayant `CONNECT` et `SELECT`
(+ `CREATEDB` pour les tests de restauration).

## Deployer, revenir en arriere

```bash
ops/deploy.sh sha-<commit>          # interactif ; --yes pour la CI ; --skip-backup deconseille
ops/rollback.sh                     # revient au tag precedent enregistre dans .deploy-state/
ops/rollback.sh sha-<autre-commit>  # ou a un tag precis
```

`deploy.sh` : pull, migrations en attente affichees, **sauvegarde pre-deploiement**, migrations (tache one-shot
`migrate`, jamais au demarrage), bascule, `queue:restart`, controle `GET /up`, retour automatique si le controle echoue.
La bascule recree les conteneurs : coupure de quelques secondes (un seul hote, pas de blue/green).
L'historique est dans `.deploy-state/history.log`.

**Migrations expand/contract** (regle de revue) : une migration livree avec la version N doit fonctionner avec le code
N-1 et N (ajouts nullable ou avec defaut, nouveaux index, nouvelles tables). Renommages, suppressions et `NOT NULL`
sur donnees existantes se font en deux livraisons : expand (N), puis contract (N+1, quand plus aucun code n'utilise
l'ancien schema). C'est ce qui permet `rollback.sh` sans toucher a la base.

Retour arriere de la base, dernier recours : arreter `app`, `worker`, `scheduler`, puis
`ops/restore-postgres.sh --overwrite-active --confirm-db <base> <sauvegarde pre-deploiement>` (perte des ecritures depuis le deploiement).

## Sauvegarde

```bash
# Chiffrement age (recommande) : cle privee conservee HORS du serveur (coffre), seule la cle publique est ici.
age-keygen -o badal-backup.key            # a ranger hors serveur ; en extraire la ligne "public key" dans recipients.txt
export BACKUP_AGE_RECIPIENTS_FILE=/etc/badal/backup-recipients.txt
export BACKUP_DIR=/var/backups/badal RETENTION_DAYS=14
export PGHOST=db.example.internal PGSSLMODE=require PGUSER=badal_backup PGPASSFILE=/etc/badal/pgpass   # mode direct
ops/backup-postgres.sh               # cron : 17 2 * * *  (quotidien)
```

Alternative gpg : `BACKUP_GPG_RECIPIENT=<cle>`. Sans chiffrement, le script refuse (les dumps contiennent des donnees
personnelles) sauf `BACKUP_ALLOW_UNENCRYPTED=1` (repetition locale). Copier ensuite `BACKUP_DIR` **hors de l'hote**
(stockage objet d'un autre compte, versionne / object lock) : une sauvegarde sur le meme disque n'est pas une sauvegarde.
Les dumps sont ecrits en `umask 077`, sans mot de passe en argument (`PGPASSFILE`/`PGSERVICE`).

Repetition sur la pile de developpement (lecture seule sur la base, aucun ecrasement) :

```bash
PG_MODE=compose BADAL_COMPOSE_ARGS="-f compose.yaml" BACKUP_ALLOW_UNENCRYPTED=1 ops/backup-postgres.sh
PG_MODE=compose BADAL_COMPOSE_ARGS="-f compose.yaml" ops/verify-restore.sh
```

## Restauration et verification

```bash
ops/verify-restore.sh                         # derniere sauvegarde -> base ephemere -> controles -> suppression (a planifier chaque semaine)
ops/restore-postgres.sh <fichier>             # idem sur un fichier precis
ops/restore-postgres.sh --keep <fichier>      # conserve la base de verification (a supprimer ensuite)
ops/restore-postgres.sh --target-db badal_restore <fichier>   # nouvelle base nommee, refuse si elle existe
```

Fichiers `.age` : `BACKUP_AGE_IDENTITY=<cle privee>` (montee temporairement pour le test). `verify-restore.sh` echoue si la
derniere sauvegarde a plus de `MAX_BACKUP_AGE_HOURS` (26 h) : c'est l'alerte "les sauvegardes ne tournent plus".
La duree de restauration est journalisee : c'est la mesure du RTO reel.

## Objectifs RPO / RTO (cibles proposees, A VALIDER par le metier avant le pilote)

| Objectif | Cible proposee | Moyen | A valider |
| --- | --- | --- | --- |
| RPO donnees | 24 h avec `pg_dump` quotidien ; **15 min** si le fournisseur PostgreSQL offre le PITR (archivage WAL) | dump + PITR gere | choix fournisseur, budget |
| RTO restauration base | 2 h | `restore-postgres.sh` (duree mesuree) | test trimestriel sur volumetrie reelle |
| RTO service (applicatif) | 30 min | `rollback.sh` / redeploiement d'un tag | exercice de retour arriere |
| Retention | 14 jours en ligne, mensuel plus long hors site | `RETENTION_DAYS`, stockage objet | juridique (politique de conservation, phase 0) |

Gate avant pilote : `verify-restore.sh` reussi sur une vraie sauvegarde de production et un exercice `rollback.sh` documente.

## Journal des exercices

### 2026-09-30 : repetition complete sur poste de developpement (pas encore de production)

Sauvegarde et restauration (pile `compose.yaml`, PostgreSQL 17) :

- `backup-postgres.sh` : dump de 112 Ko en 2 s, relu par `pg_restore --list`, SHA-256 ecrit.
- `verify-restore.sh` : restauration dans une base ephemere en 2 s (8 s au total), 30 tables et 29 migrations, base supprimee.
- Restauration conservee puis comparee a la source : memes nombres de lignes dans les 30 tables, memes 90 contraintes et
  107 index (dont `users_contact_required_check`).
- Refus verifies : fichier altere (somme de controle), cible = base active, `--confirm-db` inexact, base existante, base systeme.
- `--overwrite-active` sur une copie jouant le role de base active : sauvegarde `pre-restore` prise d'abord, donnees
  modifiees apres la sauvegarde bien revenues.

Deploiement et retour arriere (`compose.prod.yaml` sous le projet `badal-drill`, images `sha-drill1..3` servies par un
registre local, PostgreSQL jetable) :

- `deploy.sh sha-drill1` (premier deploiement) : sauvegarde, 29 migrations en tache one-shot, bascule, `/up` = 200 en 40 s.
- `deploy.sh sha-drill2` : bascule en 28 s ; `previous_tag` = `sha-drill1`.
- `rollback.sh` sans argument : retour a `sha-drill1` en 18 s pour `app`, `worker` et `scheduler`, donnees intactes.
- `deploy.sh sha-drill3` (image volontairement cassee) : migration en echec => arret avant bascule, l'ancienne version
  continue de tourner. Puis, avec une image dont seule la reponse HTTP est cassee : controle de sante en echec, retour
  automatique a `sha-drill1` 80 s apres la bascule (`HEALTH_TIMEOUT=45` ; compter jusqu'a ~2 min 30 avec la valeur par
  defaut de 120 s), donnees intactes.

Enseignement : un fichier de secret contenant un retour chariot Windows (`\r`) rend Redis `unhealthy` (le serveur et le
controle de sante ne lisent pas le meme mot de passe). Creer les secrets sur le serveur Linux (commandes ci-dessus), jamais
les copier depuis un poste Windows.

Reste pour le gate : le meme exercice sur l'hote de production, avec une vraie sauvegarde de production.

## Points a connaitre

- Redis cache separe : `config/database.php` lit `REDIS_CACHE_HOST` / `REDIS_CACHE_PASSWORD` (connexion `cache`), que
  `compose.prod.yaml` pointe vers `redis-cache`.
- Store de limitation/idempotence (compteurs `throttle`, cles d'idempotence) : doit rester sur l'instance Redis
  `noeviction` (`redis-queue`), jamais sur `redis-cache` (`allkeys-lru`), sous peine d'evictions silencieuses.
  `.env.production.example` fixe `CACHE_LIMITER_STORE=redis-persistent` et `REDIS_PERSISTENT_DB=2` : le store
  `redis-persistent` (`config/cache.php`) utilise la connexion `persistent` (`config/database.php`), que
  `compose.prod.yaml` pointe vers `redis-queue` (`REDIS_PERSISTENT_HOST` / `REDIS_PERSISTENT_PASSWORD_FILE`).
- IP client derriere le proxy : le conteneur `nginx` de production a une IP fixe sur le reseau interne
  (`BADAL_NGINX_IP`, `compose.prod.yaml` : `networks.badal.ipv4_address`). `TRUSTED_PROXIES` doit etre
  strictement egal a cette IP (pas tout `BADAL_NETWORK_SUBNET`, qui inclut la passerelle `.1` et les autres
  conteneurs). Cote Nginx (`docker/nginx/production.conf`), `set_real_ip_from` / `real_ip_header` /
  `real_ip_recursive` resolvent le client reel a partir de `X-Forwarded-For`, sous l'HYPOTHESE que le frontal TLS
  joint ce service via le port publie sur le meme hote (donc vu avec l'IP de la passerelle Docker, `172.29.20.1`
  par defaut) : **a verifier avant la mise en production reelle** et a ajuster (puis reconstruire l'image
  `nginx-production`) si le frontal a une autre adresse. Le frontal TLS doit par ailleurs toujours ecraser
  `X-Forwarded-*` recus du client.
- Staging : reutilise `compose.prod.yaml` avec un `.env.staging` distinct (`BADAL_ENV_FILE=.env.staging`,
  copie de `.env.production.example` adaptee : `APP_ENV=staging`, domaine, `BADAL_NETWORK_SUBNET` /
  `BADAL_NGINX_IP` differents de la production s'il tourne sur le meme hote, `COMPOSE_PROJECT_NAME` different
  de `badal-prod` pour ne pas entrer en collision). Pas de `.env.staging.example` fourni : la topologie reelle
  (hote dedie ou partage avec la production) doit etre tranchee avant de figer ces valeurs.
- Developpement : `docker/php/entrypoint.sh` recree et remet a `www-data` (pool PHP-FPM) les permissions de
  `storage/` et `bootstrap/cache/` a chaque demarrage (idempotent) : necessaire avec un bind mount (Windows y
  compris) qui peut monter ces dossiers appartenant a `root` ou avec des droits par defaut trop stricts.
- Journaux : stderr uniquement (`docker logs`), rotation `json-file` 10 Mo x 5. Prevoir une collecte centralisee.
- Supervision minimale : healthchecks Docker, `GET /up`, sortie JSON de `verify-restore.sh`, alerte sur restart en boucle
  du worker et sur la profondeur de la file (`php artisan queue:monitor`).
- CI : `.github/workflows/ci.yml` (job `migrations`) verifie a chaque build que les migrations sont annulables
  (`migrate` + `migrate:rollback` + `migrate`). `.github/workflows/restore-drill.yml` (mensuel + manuel) rejoue
  `backup-postgres.sh` puis `verify-restore.sh` sur un PostgreSQL ephemere pour detecter une regression des
  scripts ops/ ; ne remplace pas l'exercice trimestriel sur une vraie sauvegarde de production (gate avant pilote
  ci-dessus).
