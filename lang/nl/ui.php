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

    'ai_draft' => [
        'button' => 'Opstellen met AI',
        'working' => 'Bezig met opstellen…',
        'done' => 'Concept ingevoegd. Controleer het voor je opslaat.',
        'failed' => 'Het concept is mislukt. Probeer het zo opnieuw.',
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

    'assistant' => [
        'title' => 'Assistent',
        'new' => 'Nieuw gesprek',
        'recent' => 'Recente gesprekken',
        'empty_title' => 'Nog geen gesprekken',
        'empty_body' => 'Stel de assistent een vraag over je organisatie. Hij leest je gegevens en stelt wijzigingen voor die jij bevestigt.',
        'placeholder' => 'Stel een vraag over je organisatie',
        'send' => 'Versturen',
        'sending' => 'Aan het nadenken…',
        'you' => 'Jij',
        'assistant' => 'Assistent',
        'intro' => 'Stel een vraag om te beginnen. De assistent wijzigt nooit iets zonder jouw bevestiging.',
        'failed' => 'De assistent kon niet antwoorden. Je bericht is niet kwijt, probeer het opnieuw.',
        'quota' => 'Je organisatie heeft de AI-ruimte voor nu opgebruikt. Die wordt zo weer aangevuld.',
        'back' => 'Alle gesprekken',
    ],

    'nav' => [
        'assistant' => 'Assistent',
        'admin' => 'Beheer',
        'dashboard' => 'Overzicht',
        'documentation' => 'Documentatie',
        'repository' => 'Repository',
    ],

    'site' => [
        'nav' => 'Site',
        'home' => 'Home',
        'blog' => 'Blog',
        'changelog' => 'Wijzigingslog',
        'welcome_title' => 'Welkom',
        'welcome_description' => 'Een Laravel- en React-starterkit met organisaties, een API, webhooks en een ingebouwde AI-laag.',
        'blog_description' => 'Nieuws en notities van het team.',
        'blog_empty' => 'Nog geen berichten.',
        'blog_back' => 'Alle berichten',
        'blog_by' => 'Door',
        'changelog_description' => 'Wat er in elke release is veranderd.',
    ],
];
