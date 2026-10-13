<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 103;
    $version = "1.9.4";

    // Version bump only: centers the Chart.js canvases on desktop (they became
    // display:block, so the cell's text-align:center stopped applying). No
    // schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
