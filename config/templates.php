<?php

/*
 * The templates the code knows about, keyed on the template's slug in the database.
 *
 * Only what the code needs lives here: which template is preselected, which theme draws its
 * thumbnail and which views render its site. Name and description stay in the database, where
 * admins can edit them; the texts below are only used when TemplateSeeder creates a missing row.
 *
 * The order of 'list' is the order of the cards in the "Nytt magasin" modal.
 */

return [

    // Preselected in the "Nytt magasin" modal. If it is missing or inactive, the first active template is chosen.
    'default' => 'modern',

    'list' => [

        'modern' => [
            'name' => 'Modern magasin',
            'description' => 'Stor huvudartikel med bild, mest läst och ljusa kort. Lugnt och luftigt.',
            // A name from Theme::NAMES, or null for the grey thumbnail.
            'theme' => 'bondebladet',
            // The folder of the site's views (home.blade.php and so on). Not the same thing as the
            // database column "layout", which is the older generic page layout and does not pick the site.
            'site' => 'templates.news',
        ],

        'classic' => [
            'name' => 'Klassisk tidning',
            'description' => 'Nyhetsblad med spalter, dubbla linjer, titelblock och retroannonser.',
            // Grey thumbnail until the template gets its own colors.
            'theme' => null,
            // Shares the Modern site until it gets views of its own.
            'site' => 'templates.news',
        ],

    ],

];
