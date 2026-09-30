<?php

return [
    'home' => 'Retour à l’accueil',
    'codes' => [
        '401' => [
            'title' => 'Connexion requise',
            'description' => 'Connectez-vous pour accéder à cette page.',
        ],
        '403' => [
            'title' => 'Accès refusé',
            'description' => 'Votre compte ne permet pas d’ouvrir cette page.',
        ],
        '404' => [
            'title' => 'Page introuvable',
            'description' => 'Cette page n’existe pas, n’existe plus, ou n’est pas disponible dans l’état actuel de votre demande.',
        ],
        '419' => [
            'title' => 'Page expirée',
            'description' => 'Votre session a expiré par sécurité. Revenez à la page précédente, actualisez-la, puis recommencez.',
        ],
        '429' => [
            'title' => 'Trop de tentatives',
            'description' => 'Veuillez patienter quelques instants avant de réessayer.',
        ],
        '500' => [
            'title' => 'Erreur du serveur',
            'description' => 'Un problème inattendu est survenu. Réessayez dans quelques minutes.',
        ],
        '503' => [
            'title' => 'Service momentanément indisponible',
            'description' => 'Badal est en maintenance. Réessayez dans quelques minutes.',
        ],
        'default' => [
            'title' => 'Une erreur est survenue',
            'description' => 'La page demandée ne peut pas être affichée.',
        ],
    ],
];
