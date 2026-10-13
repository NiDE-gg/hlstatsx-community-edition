<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 94;
    $version = "1.7.13";

    // Version bump only: removes the unused pChart pCache file cache class
    // (web/includes/pChart/pCache.class, never required/included anywhere)
    // and the dead type=2 ("player history graph") branch from
    // show_graph.php. No live caller passes type=2 or type=3; the only
    // reachable types remain 0 and 1, whose behavior (including file
    // caching) is unchanged. Phase 1 of #57. No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
