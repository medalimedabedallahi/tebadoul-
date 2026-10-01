<?php

/*
 * PROJETS DE TEXTES, A FAIRE VALIDER PAR UN JURISTE avant l'ouverture au public. Les passages entre
 * crochets sont a completer. Toute modification de fond implique de changer `legal.version`
 * (config/legal.php). Les sections et paragraphes doivent rester alignes avec lang/ar/legal.php.
 */

return [
    'draft_notice' => 'Projet de texte en attente de validation juridique. Il pourra être modifié avant l’ouverture du service au public.',
    'version' => 'Version du :date',
    'other_document' => 'Voir aussi :',
    'footer_label' => 'Informations légales',

    'terms' => [
        'title' => 'Conditions d’utilisation',
        'description' => 'Les règles d’utilisation du service Tebadoul de mise en relation pour les permutations professionnelles.',
        'sections' => [
            [
                'heading' => 'Objet du service',
                'paragraphs' => [
                    'Tebadoul met en relation des agents publics, notamment des enseignants et des professionnels de santé, qui souhaitent permuter leur affectation avec un collègue.',
                    'Le service est gratuit. Il est proposé en phase pilote par [nom de l’organisme éditeur, à compléter].',
                ],
            ],
            [
                'heading' => 'Compte et accès',
                'paragraphs' => [
                    'L’inscription se fait avec un numéro de téléphone, une adresse courriel, ou les deux. Au moins un de ces contacts doit être vérifié par un code avant toute utilisation.',
                    'Vous vous engagez à fournir des informations exactes, à ne créer qu’un seul compte et à garder votre mot de passe secret. Vous êtes responsable de l’usage fait de votre compte.',
                ],
            ],
            [
                'heading' => 'Fonctionnement des correspondances',
                'paragraphs' => [
                    'Tebadoul propose des correspondances entre demandes compatibles, selon des règles publiées et versionnées. Le score affiché est indicatif.',
                    'Tebadoul ne garantit ni l’existence d’une correspondance ni l’obtention d’une permutation. La décision de permutation relève exclusivement de l’administration compétente ; Tebadoul n’intervient pas dans la procédure administrative.',
                ],
            ],
            [
                'heading' => 'Coordonnées et consentement',
                'paragraphs' => [
                    'Vos coordonnées ne sont jamais communiquées sans votre accord explicite. Elles ne sont visibles par l’autre participant que lorsque vous y avez consenti tous les deux.',
                    'Vous pouvez retirer votre consentement à tout moment pour l’avenir ; les coordonnées déjà consultées ne peuvent pas être retirées. Chaque consentement, retrait et consultation est horodaté.',
                ],
            ],
            [
                'heading' => 'Règles de conduite',
                'paragraphs' => [
                    'La messagerie sert uniquement à préparer une permutation. Sont interdits notamment le harcèlement, la fraude, les fausses informations, les contenus illicites et toute demande d’argent en échange d’une permutation.',
                    'Vous pouvez bloquer un participant ou le signaler. Un blocage empêche immédiatement les invitations, les messages et le partage de coordonnées.',
                ],
            ],
            [
                'heading' => 'Modération',
                'paragraphs' => [
                    'Les signalements sont examinés par des modérateurs. En cas de manquement, un compte peut être suspendu. Chaque décision est motivée et enregistrée dans un journal d’audit.',
                ],
            ],
            [
                'heading' => 'Suppression du compte',
                'paragraphs' => [
                    'Vous pouvez supprimer votre compte à tout moment depuis la page « Mon compte ». Vos contacts, votre profil et vos demandes sont alors effacés et votre compte est anonymisé. La suppression est définitive.',
                ],
            ],
            [
                'heading' => 'Responsabilité',
                'paragraphs' => [
                    'Le service est fourni en l’état pendant la phase pilote ; sa disponibilité n’est pas garantie. Tebadoul n’est pas partie aux accords conclus entre utilisateurs.',
                ],
            ],
            [
                'heading' => 'Modification des conditions',
                'paragraphs' => [
                    'Toute nouvelle version est publiée sur cette page avec sa date. Une modification substantielle vous sera signalée et pourra nécessiter une nouvelle acceptation.',
                ],
            ],
            [
                'heading' => 'Droit applicable et contact',
                'paragraphs' => [
                    'Les présentes conditions sont soumises au droit mauritanien. Pour toute question : [adresse de contact, à compléter].',
                ],
            ],
        ],
    ],

    'privacy' => [
        'title' => 'Politique de confidentialité',
        'description' => 'Comment Tebadoul collecte, utilise et protège vos données personnelles.',
        'sections' => [
            [
                'heading' => 'Responsable du traitement',
                'paragraphs' => [
                    'Le responsable du traitement est [nom de l’organisme, adresse et contact, à compléter]. Le traitement respecte la réglementation mauritanienne applicable à la protection des données personnelles [référence exacte à compléter par le juriste].',
                ],
            ],
            [
                'heading' => 'Données collectées',
                'paragraphs' => [
                    'Compte : nom, numéro de téléphone et/ou adresse courriel, mot de passe (enregistré uniquement sous forme chiffrée irréversible), langue et préférences de notification.',
                    'Situation professionnelle : secteur, profession, spécialité, grade, affectation actuelle et, si vous le donnez, votre identifiant professionnel.',
                    'Utilisation du service : demandes de mobilité, correspondances, invitations, messages, consentements au partage de coordonnées, blocages et signalements.',
                    'Données techniques : adresse IP pour la sécurité et le journal des actions sensibles, et journaux techniques de fonctionnement.',
                ],
            ],
            [
                'heading' => 'Finalités',
                'paragraphs' => [
                    'Vos données servent à fournir le service (correspondances, messagerie, notifications), à le sécuriser (vérification des contacts, limitation des tentatives, prévention des abus), à modérer les signalements et à produire des statistiques agrégées, sans jamais identifier personne.',
                ],
            ],
            [
                'heading' => 'Qui voit vos données',
                'paragraphs' => [
                    'Les autres utilisateurs voient uniquement les éléments nécessaires à une correspondance (profession, affectation, destinations, disponibilité), jamais votre nom ni vos coordonnées sans votre accord.',
                    'Les modérateurs voient les signalements et les échanges concernés ; les administrateurs voient l’identifiant public et le statut des comptes. Vos données ne sont ni vendues ni transmises à des tiers à des fins commerciales. Prestataires techniques (hébergement, envoi de courriels et de SMS) : [liste à compléter].',
                ],
            ],
            [
                'heading' => 'Durée de conservation',
                'paragraphs' => [
                    'Un compte dont aucun contact n’a été vérifié est supprimé après :days jours. Les demandes arrivées à échéance sont marquées comme expirées. Les autres durées de conservation sont en cours de définition avec le juridique : [à compléter].',
                ],
            ],
            [
                'heading' => 'Sécurité',
                'paragraphs' => [
                    'Les mots de passe et les codes à usage unique sont chiffrés ; les coordonnées sont masquées à l’affichage ; les actions sensibles et chaque consultation de coordonnées sont journalisées ; les sauvegardes sont chiffrées.',
                ],
            ],
            [
                'heading' => 'Vos droits',
                'paragraphs' => [
                    'Vous pouvez consulter et corriger votre profil, retirer à tout moment votre consentement au partage de coordonnées, désactiver les courriels de notification et supprimer votre compte depuis « Mon compte ». Pour toute autre demande : [adresse de contact, à compléter].',
                ],
            ],
            [
                'heading' => 'Cookies',
                'paragraphs' => [
                    'Tebadoul n’utilise que des cookies strictement nécessaires : session de connexion, protection contre la falsification des formulaires et langue choisie. Aucun cookie publicitaire ni de mesure d’audience. Les espaces publicitaires affichent des annonces choisies par l’équipe, sans cookie ni traceur : aucune donnée vous concernant n’est transmise aux annonceurs.',
                ],
            ],
            [
                'heading' => 'Suppression de votre compte',
                'paragraphs' => [
                    'À la suppression, vos contacts, votre profil, vos demandes, vos notifications et vos accès sont effacés, et votre compte est anonymisé. Les messages déjà envoyés restent visibles par leur destinataire ; les signalements et le journal d’audit sont conservés pour la sécurité du service.',
                ],
            ],
            [
                'heading' => 'Modifications',
                'paragraphs' => [
                    'Toute nouvelle version de cette politique est publiée sur cette page avec sa date.',
                ],
            ],
        ],
    ],
];
