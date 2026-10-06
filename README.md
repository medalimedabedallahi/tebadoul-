# Tebadoul

Tebadoul (anciennement Badal) est une plateforme de mise en relation pour les permutations professionnelles en Mauritanie. Le MVP cible les enseignants et professionnels de sante avec une application Laravel/Livewire et une API REST versionnee.

## Socle technique

- Laravel 13 et PHP 8.5
- Livewire 4
- Laravel Sanctum
- PostgreSQL 17
- Redis 8
- Nginx et PHP-FPM
- Mailpit pour les courriels locaux

## Demarrage avec Docker

```bash
cp .env.example .env
# Remplacer DB_PASSWORD et REDIS_PASSWORD avant de lancer Compose.
npm ci
npm run build
docker compose build
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm -u www-data app php artisan db:seed --class=ReferenceDataSeeder
docker compose up -d
```

Dans Docker, `vendor/` est dans le volume `badal-vendor` (et non monte depuis l'hote, ce qui ralentissait chaque page de plusieurs secondes sous Windows). Apres une modification de `composer.lock` : `docker compose exec app composer install`.

Les assets frontend se construisent sur l'hote : l'image PHP de developpement contient Composer, mais pas Node.js.

Application : `http://localhost:8098`

API de sante : `http://localhost:8098/api/v1/health`

Mailpit : `http://localhost:8026`

## Referentiels professionnels avant utilisation

Le seeder charge les secteurs, wilayas et moughataas. Les professions, specialites, grades et etablissements doivent etre approuves par les responsables metier puis importes ; sans professions actives, aucun profil professionnel ne peut etre enregistre.

Preparer des CSV UTF-8 separes par des virgules, avec les en-tetes suivants. La colonne optionnelle `active` vaut `1` par defaut ; utiliser `0` pour desactiver une entree. Les codes sont stables et les noms FR/AR obligatoires.

| Type | En-tete obligatoire |
| --- | --- |
| professions | `code,sector_code,name_fr,name_ar` |
| specialties | `code,profession_code,name_fr,name_ar` |
| grades | `code,profession_code,name_fr,name_ar` |
| establishments | `code,sector_code,moughataa_code,type,name_fr,name_ar` |

Deposer les fichiers approuves aux chemins ci-dessous, puis les importer dans cet ordre (les parents doivent deja exister). Aucun jeu professionnel fictif n'est livre ni importe automatiquement.

```bash
docker compose run --rm -u www-data app php artisan references:import professions database/data/references/professions.csv
docker compose run --rm -u www-data app php artisan references:import specialties database/data/references/specialties.csv
docker compose run --rm -u www-data app php artisan references:import grades database/data/references/grades.csv
docker compose run --rm -u www-data app php artisan references:import establishments database/data/references/establishments.csv
```

Chaque import est atomique et idempotent. Une ligne invalide annule l'import du fichier entier. Verifier ensuite le parcours FR/AR de creation du profil pour chaque profession du pilote.

Les matrices de compatibilite, le secteur et les zones du pilote, les informations juridiques et les durees de conservation restent a valider. Conserver `MATCHING_ENABLED=false` et `LEGAL_TEXTS_DRAFT=true` pendant cette preparation. Le test avec lecteur d'ecran et l'exercice de restauration sur une vraie sauvegarde de production restent requis (voir `docs/implementation-plan.md` et `ops/README.md`).

## Verification

```bash
docker compose run --rm app php artisan test
docker compose run --rm app vendor/bin/pint --test
docker compose run --rm app composer analyse
```

Les tests PostgreSQL (source de verite avant les gates du pilote) utilisent une base dediee `badal_testing`, a creer une fois :

```bash
docker compose exec postgres createdb -U badal badal_testing
docker compose run --rm app vendor/bin/phpunit --configuration=phpunit.pgsql.xml
```

`tests/TestCase.php` refuse de tourner sur une base dont le nom ne se termine pas par `_testing` (ou qui n'est pas `:memory:`), car `RefreshDatabase` vide la base utilisee.

## Documentation

- [Decisions produit et architecture](docs/decisions/0001-product-and-architecture.md)
- [Couche metier partagee API/Livewire (acceptee)](docs/decisions/0002-shared-business-actions.md)
- [Plan d'implementation](docs/implementation-plan.md)
- [Contrat OpenAPI](docs/openapi.yaml)
- PRD source : `.prd_review/PRD_Badal_Laravel_API.pdf`

## Etat du projet

Le socle technique est termine. La phase 2 couvre maintenant l'authentification et la verification des contacts, l'administration securisee, les referentiels, les profils professionnels, les demandes de mobilite, le moteur de matching direct explicable, les invitations et le double consentement, la messagerie, le blocage et la moderation des signalements (API `/api/v1/matches`, pages `/mes-correspondances` et `/moderation/signalements`), ainsi que les notifications internes avec copie par courriel parametrable, le journal d'audit consultable et les statistiques agregees (API `/api/v1/notifications`, `/api/v1/me/preferences`, `/api/v1/admin/audit-logs` et `/api/v1/admin/statistics` ; pages `/notifications`, `/administration/journal-audit` et `/administration/statistiques`). Le matching reste desactive par defaut (`MATCHING_ENABLED=false`) tant que les regles de compatibilite Enseignement/Sante, la zone pilote et la politique de conservation ne sont pas validees. Tous les domaines MVP de la phase 2 sont en place ; la suite porte sur les gates avant pilote du plan d'implementation.
