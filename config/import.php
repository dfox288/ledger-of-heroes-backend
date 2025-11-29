<?php

return [
    /*
    |--------------------------------------------------------------------------
    | XML Source Path
    |--------------------------------------------------------------------------
    |
    | Base path to the XML source files. Can be absolute or relative to the
    | project root. When using the fightclub_forked repository, this should
    | point to the WizardsOfTheCoast directory.
    |
    | For Docker: /var/www/fightclub_forked/Sources/PHB2014/WizardsOfTheCoast
    | For local:  ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast
    |
    | Default: 'import-files' (flat directory, legacy behavior)
    |
    */
    'xml_source_path' => env('XML_SOURCE_PATH', 'import-files'),

    /*
    |--------------------------------------------------------------------------
    | Source Directories
    |--------------------------------------------------------------------------
    |
    | Maps source abbreviations to their subdirectory paths within the
    | xml_source_path. Order matters - sources are processed in this order.
    |
    | Set to null or empty array to use flat directory mode (legacy).
    |
    */
    'source_directories' => env('XML_SOURCE_PATH') ? [
        // Core Rulebooks (import first - most entities depend on these)
        'phb' => '01_Core/01_Players_Handbook',
        'dmg' => '01_Core/02_Dungeon_Masters_Guide',
        'mm' => '01_Core/03_Monster_Manual',

        // Supplements (additional options, subclasses, spells)
        'xge' => '02_Supplements/Xanathars_Guide_to_Everything',
        'tce' => '02_Supplements/Tashas_Cauldron_of_Everything',
        'vgm' => '02_Supplements/Volos_Guide_to_Monsters',

        // Campaign Settings
        'scag' => '03_Campaign_Settings/Sword_Coast_Adventurers_Guide',
        'erlw' => '03_Campaign_Settings/Eberron_Rising_From_the_Last_War',

        // Adventures
        'twbtw' => '05_Adventures/The_Wild_Beyond_the_Witchlight',
    ] : null,
];
