<?php

declare(strict_types=1);

return [
    // Directory where the downloaded source files are kept, one folder per source and version.
    'directory' => storage_path('app/sources'),

    'insee' => [
        // data.gouv.fr API of the INSEE "Code officiel géographique" dataset (one resource per file and vintage).
        'dataset_url' => env('INSEE_COG_DATASET_URL', 'https://www.data.gouv.fr/api/1/datasets/58c984b088ee386cdb1261f3/'),
    ],

    'laposte' => [
        'file_url' => env('LAPOSTE_FILE_URL', 'https://data.laposte.fr/data-fair/api/v1/datasets/laposte-hexasmal/raw'),
    ],

    'geo' => [
        'communes_url' => env('GEO_COMMUNES_URL', 'https://geo.api.gouv.fr/communes'),
    ],

    'ban' => [
        'base_url' => env('BAN_BASE_URL', 'https://adresse.data.gouv.fr/data/ban/adresses/latest/csv'),
        // A postal code with fewer addresses than this keeps its fallback point.
        'minimum_addresses' => 1,
    ],

    'nominatim' => [
        'search_url' => env('NOMINATIM_SEARCH_URL', 'https://nominatim.openstreetmap.org/search'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'French-zip-code'),
        // A point farther than this from the centre of the commune is considered as a wrong match.
        'max_distance_km' => 30,
    ],
];
