<?php

declare(strict_types=1);

/**
 * The Dutch counterpart of lang/en/ui.php. Every key the default locale defines
 * is defined here too, and no key exists here that the default lacks — a second
 * locale is only useful if it is complete.
 */
return [
    'language' => 'Taal',

    'locale' => [
        'en' => 'English',
        'nl' => 'Nederlands',
    ],

    'announcements' => [
        'region' => 'Mededelingen',
        'dismiss' => 'Mededeling sluiten',
        'title' => 'Mededelingen',
        'description' => 'Banners die leden in de app zien',
        'new' => 'Nieuwe mededeling',
        'edit' => 'Bewerken',
        'save' => 'Opslaan',
        'cancel' => 'Annuleren',
        'delete' => 'Verwijderen',
        'title_field' => 'Titel',
        'body_field' => 'Bericht',
        'level_field' => 'Niveau',
        'starts_field' => 'Begint',
        'ends_field' => 'Eindigt (optioneel)',
        'audience_field' => 'Doelgroep',
        'audience_all' => 'Alle organisaties',
        'level_info' => 'Info',
        'level_warning' => 'Waarschuwing',
    ],

    'dashboard' => [
        'activity' => [
            'title' => 'Activiteit',
            'description' => 'API-verzoeken per dag in de afgelopen 30 dagen',
            'summary' => 'verzoeken in de afgelopen 30 dagen',
            'empty' => 'Geen API-verzoeken in de afgelopen 30 dagen',
            'requests' => 'Verzoeken',
        ],
    ],

    'nav' => [
        'admin' => 'Beheer',
        'dashboard' => 'Overzicht',
        'documentation' => 'Documentatie',
        'repository' => 'Repository',
    ],
];
