<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 97;
    $version = "1.8.1";

    // Version bump only: replaces the server-rendered pChart PNG player trend
    // graph (web/trend_graph.php) with a client-side Chart.js chart fed by
    // the JSON endpoint added in phase 3 (web/api/player_trend.php). Issue
    // #57 phase 4. web/includes/pChart/ (pChart.class, pData.class) and the
    // now-unused PNG trend cache (Cache\CacheCleaner, TREND_CACHE_* config
    // constants) are removed. No schema changes.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
