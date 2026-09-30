# Bedal Agent Guide

## Start Here

- This is a Docker-first Laravel 13 application requiring PHP 8.5 and Node 24. Prefer `docker compose run --rm app ...` for PHP/Composer commands.
- Read `CLAUDE.md` before Laravel work; it contains the installed-version-specific Laravel Boost guidance. Follow sibling code before introducing a new pattern.
- Do not add dependencies or new top-level directories without approval. Use Artisan generators with `--no-interaction` when one exists.
- The worktree may contain substantial in-progress changes. Never reset or overwrite unrelated modifications.

## Local Setup

- Create `.env` from `.env.example`, then replace the placeholder `DB_PASSWORD` and `REDIS_PASSWORD`; Compose rejects missing or empty values. Containers reach services as `postgres`, `redis`, and `mailpit`.
- A fresh checkout needs both backend containers and host-built frontend assets:

```sh
cp .env.example .env
npm ci
npm run build
docker compose build
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose up -d
```

- The app is at `http://localhost:8098`, health at `/api/v1/health`, and Mailpit at `http://localhost:8026`.
- In Docker, `vendor/` lives in the `badal-vendor` named volume, not the bind mount (bind-mounted files are slow under Docker Desktop and cost seconds per request). The entrypoint fills it on first start; after a `composer.lock` change run `docker compose exec app composer install`. Run container commands as `-u www-data` so `storage/` stays writable by PHP-FPM.
- The development PHP image has Composer but no Node. Run `npm` on the host; do not expect `composer setup` to work unchanged inside that container.
- Tailwind v4 is configured in `resources/css/app.css`; there is no `tailwind.config.js`. Vite inputs are `resources/css/app.css` and `resources/js/app.js`.

## Verification

- Run the narrowest affected test first:

```sh
docker compose run --rm app php artisan test --compact tests/Feature/Path/ExampleTest.php
docker compose run --rm app php artisan test --compact --filter=test_method tests/Feature/Path/ExampleTest.php
```

- Before finishing backend work, run the applicable CI gates:

```sh
docker compose run --rm app vendor/bin/pint --dirty --format agent
docker compose run --rm app composer analyse
docker compose run --rm app php artisan test
```

- PHPUnit uses in-memory SQLite by default. Database or migration changes also require PostgreSQL:

```sh
docker compose exec postgres createdb -U badal badal_testing
docker compose run --rm app vendor/bin/phpunit --configuration=phpunit.pgsql.xml
```

- Test bootstrap refuses any database except `:memory:` or a name ending `_testing`; do not bypass that protection.
- Frontend CI is `npm ci`, `npm audit --audit-level=high`, then `npm run build`. There are no JavaScript tests, ESLint, Prettier, or TypeScript checks configured.
- API contract changes must update `docs/openapi.yaml`; CI lints it with Spectral using `.github/spectral.yaml`.

## Architecture

- Put business use cases in `app/Actions/<Domain>`. API controllers and class-based Livewire components validate/present and call the same actions directly; Livewire must not call this application's HTTP API.
- Reuse action `rules()` from Form Requests and Livewire where that pattern already exists. Return API models through allow-listed `JsonResource` classes, never direct model serialization.
- API routes live under `/api/v1`. Preserve the standard error shape `{message, code, errors?, request_id, debug?}` and existing request-ID, throttle, Sanctum ability, active-account, and idempotency middleware.
- `User` has an internal numeric ID and a public ULID; route model binding uses `public_id`. Do not expose internal identifiers or hidden contact fields.
- Livewire is currently class-based (`app/Livewire` plus `resources/views/livewire`) although its config defaults new generators to SFC. Use `php artisan make:livewire ... --class --no-interaction` to preserve the established structure.
- Supported locales are French and Arabic. UI changes must preserve RTL behavior and should use the project `<x-pagination>` component rather than framework pagination views.
- Async contact-code jobs are encrypted, dispatch after commit, and use the `notifications` queue. Workers consume `critical,matching,notifications,default`; keep worker timeout below Redis `retry_after`.

## Database And Operations

- SQLite omits the PostgreSQL-only `users_contact_required_check`; PostgreSQL is mandatory for constraint and migration verification.
- Every migration must roll back cleanly and remain expand/contract compatible with the previous release. Production migrations run as a separate deployment step, never at application startup.
- Rate limits and idempotency share `cache.limiter`. In production this must remain `redis-persistent` on the no-eviction Redis instance, not the volatile cache Redis.
- Production boot rejects `APP_DEBUG=true`, `MAIL_MAILER=log|array`, and unsupported SMS drivers. Only `log` and `null` SMS drivers currently exist; `.env.production.example`'s `SMS_DRIVER=change-me` is not runnable as-is.
- Reference data (sectors, geography, professions, specialties, grades, establishments) is keyed by a stable `code`, deactivated rather than deleted, and exposed publicly under `/api/v1/references`. Load it with `php artisan references:import <type> <file.csv>` (all or nothing, idempotent). `ReferenceDataSeeder` imports the shipped `database/data/references/*.csv` (sectors, wilayas, moughataas) and is safe to run in production with `--force`.
- Matching (step 6) is disabled until the compatibility rules are validated (ADR 0001): `MATCHING_ENABLED=false` by default. Rules are versioned classes bound to `App\Contracts\MatchingRules` (currently `StrictRulesV1`, identity of sector/profession/grade/specialty); a rules change is a new class and version, never an edit of an existing one. `RecomputeMatches` jobs run on the `matching` queue after commit; after enabling or changing rules run `php artisan matching:recompute`. A match is one row per request pair, invalidated with a reason rather than deleted, and final matches are never touched by the engine.
- Agreements (step 7): inviting counts as accepting; acceptance makes the match `mutual` and both requests `matched` through `App\Support\Matching\AgreementLifecycle`, which the engine also uses when an agreement stops holding (requests released, consents revoked). Contact details are never revealed by acceptance: each participant consents separately, both must consent, and every grant, revocation and reveal is appended to `contact_events`. Clients rely on the `allowed_actions` of a match. Never edit a migration that already ran; add a new one.
- Notifications: actions that change a match call `App\Support\Notifications\UserNotifier` inside their transaction. It writes the in-app row (Laravel `notifications` table, `type` = `App\Enums\NotificationType`, `data` = match public id only) and adds nothing while an unread one of the same type exists for that match. The optional email is queued after commit (`SendNotificationEmail`, `notifications` queue) and claimed through `mailed_at`; only active accounts with `email_notifications` on and a verified email get it, and messages are never emailed.
- Legal and account deletion: registration requires `accept_terms` and records `terms_accepted_at` / `terms_version` (`config/legal.php`; bump the version whenever `lang/*/legal.php` changes in substance, and keep the FR and AR sections aligned). `DELETE /api/v1/me` (`App\Actions\Auth\DeleteAccount`) anonymizes the account (status `deleted`, final) instead of removing the row, which the audit trail, reports and messages still reference; the PostgreSQL `users_contact_required_check` exempts that status only. Any new personal data tied to a user must be erased by that action too.
- Do not hand-edit `public/build`, `vendor`, `node_modules`, cache files, or compiled views. Production rollback changes images only and does not reverse database migrations.
