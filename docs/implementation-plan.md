# Plan d'implementation Tebadoul

## Phase 0 - Cadrage

- [x] Valider Laravel + Livewire et une API REST versionnee.
- [x] Exiger un courriel a l'inscription et verifier les contacts uniquement par courriel ; telephone facultatif non verifie (connexion compatible avec les anciens numeros deja verifies).
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

- [ ] Recevoir et importer les referentiels professionnels approuves : professions, specialites, grades et etablissements. Le seeder livre seulement les secteurs et la geographie ; formats et ordre d'import dans `README.md`.
- [ ] Completer les textes juridiques FR/AR avec l'organisme responsable, son adresse et son contact, les prestataires techniques, la reference juridique et les durees de conservation validees. Les textes restent des projets (`LEGAL_TEXTS_DRAFT=true`) ; toute modification de fond met a jour `config/legal.php`.
- [ ] Valider les matrices de compatibilite et le secteur/les zones du pilote avant de passer `MATCHING_ENABLED=true` et d'executer `matching:recompute`.
- [x] OpenAPI couvre tous les endpoints implementes (`tests/Feature/Api/V1/OpenApiCoverageTest.php`).
- [x] Tous les acces interdits sont testes (`tests/Feature/Api/V1/AccessControlTest.php`, `tests/Feature/WebAccessControlTest.php`).
- [x] Aucune coordonnee n'est exposee avant consentement (`tests/Feature/ContactConfidentialityTest.php`).
- [x] Les jobs sont idempotents et testes sous concurrence (`tests/Feature/Concurrency/ConcurrentJobsTest.php`, processus paralleles sur PostgreSQL).
- [ ] Le francais, l'arabe, le RTL et WCAG 2.2 AA sont verifies. Fait : parite des traductions et pages d'erreur localisees testees (`TranslationParityTest`, `ErrorPagesTest`) ; audit axe-core WCAG 2.2 AA sans violation sur les 19 pages en francais et en arabe (visiteur, utilisateur, administrateur), navigation clavier avec focus visible, RTL et affichage mobile verifies dans un navigateur. Reste : un test manuel avec un lecteur d'ecran (NVDA, TalkBack) par une personne.
- [ ] La restauration PostgreSQL et le rollback applicatif sont testes. Repetition complete reussie en local le 2026-09-30 (sauvegarde, restauration verifiee table par table, deploiement, retour arriere manuel et automatique ; voir `ops/README.md`, Journal des exercices). Reste : le meme exercice sur l'hote de production avec une vraie sauvegarde.

Corrections du 2026-10-06 : l'acceptation d'une invitation depassee est refusee avant le passage du scheduler, et `allowed_actions` retire `accept`. Le workflow de restauration prepare `.env` avant le hook Composer `package:discover`. Les textes d'inscription et les mentions de livraison des codes sont alignes en FR/AR ; version des textes juridiques : `2026-10-06`, toujours en attente de validation juridique.
