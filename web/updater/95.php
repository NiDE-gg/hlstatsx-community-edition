<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 95;
    $version = "1.7.14";

    // Version bump only: vendors the Chart.js v4.4.4 UMD build at
    // web/includes/js/chart.js (not yet referenced from any page/template).
    // Phase 2 of #57. No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
