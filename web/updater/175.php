<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 175;
    $version = "1.19.3";

    // Version bump only: the site title banner (main and in-game pages) is the shared NiDE logo https://motd.nide.gg/assets/logos/rankings.png (NiDE-gg/Game-MOTD), which picks the classic or winter variant for every NiDE site.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
