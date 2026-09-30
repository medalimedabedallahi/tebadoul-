# ADR 0002 - Couche metier partagee entre l'API et Livewire

- Statut : accepte
- Date : 2026-09-21
- Acceptee le : 2026-09-22
- Depend de : ADR 0001, decision 2

## Contexte

L'ADR 0001 impose que tous les parcours metier passent par l'API REST versionnee et par l'interface Livewire. Sans regle commune, les regles de consentement, de blocage et de non-exposition des coordonnees seraient dupliquees dans les controleurs et les composants Livewire, avec un risque de divergence.

## Decision

1. Chaque cas d'usage metier est une classe d'action unique dans `app/Actions/<Domaine>` (par exemple `App\Actions\Matching\AcceptMatch`), sans dependance a la requete HTTP ni a Livewire.
2. Les controleurs `Api/V1` et les composants Livewire n'ont pas de logique metier : ils valident l'entree, appellent l'action, puis presentent le resultat.
3. Les autorisations sont dans des policies appelees par l'action, jamais uniquement par le point d'entree.
4. Toute sortie vers un client passe par une API Resource a liste blanche ; les modeles ne sont jamais serialises directement. Le modele `User` masque deja `email` et `phone`.
5. Les actions qui modifient plusieurs tables ouvrent leur propre transaction et sont idempotentes quand un job peut les rejouer.

## Consequences

- Un test de fonctionnalite par action couvre les regles metier une seule fois ; les points d'entree ne testent que la validation et la presentation.
- Le contrat OpenAPI reste la reference pour les deux clients.
- Appliquee depuis le domaine Authentification : actions dans `app/Actions/Auth`, appelees par les controleurs `Api/V1/Auth` et par les composants Livewire `app/Livewire/Auth`. Les composants Livewire appellent directement les actions, jamais l'API HTTP interne.
