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
- [ ] Ajouter les scans de securite et de dependances.
- [x] Publier le premier contrat OpenAPI.

## Phase 2 - Domaines MVP

1. Authentification et verification des contacts.
2. Roles, permissions et administration securisee.
3. Referentiels geographiques et professionnels.
4. Profils Enseignement et Sante.
5. Demandes de mobilite et machine a etats.
6. Matching direct versionne et explicable.
7. Invitations, consentements et coordonnees.
8. Messagerie, blocage, signalement et moderation.
9. Notifications, audit et statistiques minimales.

## Gates avant pilote

- OpenAPI couvre tous les endpoints implementes.
- Tous les acces interdits sont testes.
- Aucune coordonnee n'est exposee avant consentement.
- Les jobs sont idempotents et testes sous concurrence.
- Le francais, l'arabe, le RTL et WCAG 2.2 AA sont verifies.
- La restauration PostgreSQL et le rollback applicatif sont testes.
