# ADR 0001 - Decisions produit et architecture du MVP

- Statut : accepte
- Date : 2026-09-21
- Source : PRD Badal v1.0 et audit consolide

## Decisions

1. Le produit conserve le nom **Badal** pendant le MVP.
2. Le web est construit avec Laravel et Livewire. Tous les parcours metier passent aussi par une API REST versionnee sous `/api/v1`.
3. L'identite accepte le telephone, le courriel, ou les deux. Au moins un moyen de contact doit etre verifie avant publication d'une demande.
4. Le MVP implemente uniquement le matching direct entre deux demandes. Le schema devra rester extensible sans activer les cycles.
5. L'acceptation d'un match ne revele jamais automatiquement les coordonnees.
6. Le partage exige un consentement explicite de chaque participant. Le consentement est horodate, audite et revocable pour les futurs acces.
7. Le blocage prend priorite sur les invitations, la messagerie et la revelation des coordonnees.
8. PostgreSQL est la base principale, Redis gere cache, limites, verrous et files, et les documents prives utilisent un stockage compatible S3.
9. Les traitements de matching et de notification sont asynchrones et idempotents.
10. Les interfaces et donnees de reference sont preparees en francais et arabe, avec prise en charge RTL.

## Consequences

- Les regles exactes de compatibilite doivent etre versionnees et validees par des experts metier avant l'activation du matching.
- L'API expose les actions autorisees par etat ; le client ne reconstitue pas seul les permissions.
- Les coordonnees ne sont jamais incluses dans une ressource API avant le consentement requis.
- Le pilote reste bloque tant que les matrices de compatibilite, la politique de conservation et la zone pilote ne sont pas validees.
