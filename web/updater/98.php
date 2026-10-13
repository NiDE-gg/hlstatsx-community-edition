<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 98;
    $version = "1.8.2";

    // Version bump only: replaces the server-rendered GD PNG graphs
    // (web/show_graph.php, type=0 per-server load/uptime/fps and type=1
    // per-game kills/headshots/players/slots trend) with client-side
    // Chart.js charts fed by the JSON endpoints added in phase 3
    // (web/api/server_load.php, web/api/game_trend.php). Issue #57 phase 5
    // (final phase). web/show_graph.php and web/includes/functions_graph.php
    // are removed entirely; the old mtime+TTL PNG file cache under
    // web/hlstatsimg/progress/ for these two graph types is no longer read
    // or written (sig.php's unrelated signature image cache under the same
    // directory is untouched). No schema changes.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
