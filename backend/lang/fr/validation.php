<?php

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être un tableau.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'file' => 'Veuillez joindre un fichier PDF ou image valide',
    'mimetypes' => 'Veuillez joindre un fichier PDF ou image valide',
    'uploaded' => 'Veuillez joindre un fichier PDF ou image valide',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier ne doit pas dépasser :max Ko.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'regex' => 'Le format du champ :attribute est invalide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',

    'custom' => [
        'telephone' => [
            'unique' => 'Ce numéro de téléphone est déjà utilisé.',
        ],
        'email' => [
            'unique' => 'Cette adresse e-mail est déjà utilisée.',
        ],
    ],

    'attributes' => [
        'nom' => 'nom',
        'prenom' => 'prénom',
        'telephone' => 'téléphone',
        'email' => 'e-mail',
        'password' => 'mot de passe',
        'mot_de_passe' => 'mot de passe',
        'nom_entreprise' => "nom de l'entreprise",
        'metier' => 'métier',
        'quartier' => 'quartier',
        'adresse' => 'adresse',
        'justificatifs' => 'justificatifs',
        'justificatifs.*.type_document' => 'type de document',
        'justificatifs.*.fichier' => 'fichier',
        'mot_de_passe_actuel' => 'mot de passe actuel',
        'nouveau_mot_de_passe' => 'nouveau mot de passe',
        'confirmation' => 'confirmation',
        'jetonPush' => 'jeton push',
    ],
];
