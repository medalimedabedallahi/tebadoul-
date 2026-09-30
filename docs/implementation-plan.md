# Plan d'implementation Badal

## Phase 0 - Cadrage

- [x] Valider Laravel + Livewire et une API REST versionnee.
- [x] Valider telephone ou courriel comme identifiants possibles.
- [x] Valider le double consentement revocable.
- [x] Limiter le MVP au matching direct.
- [ ] Valider les matrices de compatibilite Enseignement et Sante.
- [ ] Choisir le secteur et les zones du pilote.
- [ ] Valider la politique de conservation avec le juridique local.

## Phase 1 - Fondation

- [x] Initialiser Laravel 13.
- [x] Installer Livewire, Sanctum et Laravel Boost.
- [x] Ajouter l'API `/api/v1` et un identifiant de requete.
- [x] Ajouter Docker pour PHP, Nginx, PostgreSQL, Redis et Mailpit.
- [x] Ajouter la CI et l'analyse statique.
- [x] Ajouter les scans de securite et de dependances.
- [x] Publier le premier contrat OpenAPI.

## Phase 2 - Domaines MVP

- [x] Authentification et verification des contacts.
- [x] Roles, permissions et administration securisee.
- [x] Referentiels geographiques et professionnels.
- [x] Profils Enseignement et Sante.
- [x] Demandes de mobilite et machine a etats.
- [x] Matching direct versionne et explicable.
- [x] Invitations, consentements et coordonnees.
- [x] Messagerie, blocage, signalement et moderation.
- [x] Notifications, audit et statistiques minimales.

## Gates avant pilote

- [x] OpenAPI couvre tous les endpoints implementes (`tests/Feature/Api/V1/OpenApiCoverageTest.php`).
- [x] Tous les acces interdits sont testes (`tests/Feature/Api/V1/AccessControlTest.php`, `tests/Feature/WebAccessControlTest.php`).
- [x] Aucune coordonnee n'est exposee avant consentement (`tests/Feature/ContactConfidentialityTest.php`).
- [x] Les jobs sont idempotents et testes sous concurrence (`tests/Feature/Concurrency/ConcurrentJobsTest.php`, processus paralleles sur PostgreSQL).
- [ ] Le francais, l'arabe, le RTL et WCAG 2.2 AA sont verifies. Fait : parite des traductions et pages d'erreur localisees testees (`TranslationParityTest`, `ErrorPagesTest`) ; audit axe-core WCAG 2.2 AA sans violation sur les 19 pages en francais et en arabe (visiteur, utilisateur, administrateur), navigation clavier avec focus visible, RTL et affichage mobile verifies dans un navigateur. Reste : un test manuel avec un lecteur d'ecran (NVDA, TalkBack) par une personne.
- [ ] La restauration PostgreSQL et le rollback applicatif sont testes.
