<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 127;
    $version = "1.14.5";

    // Version bump only: no inline JavaScript left in the pages (data-* attributes and
    // the new includes/js/hlstats.js instead), tab fixes (URL hash, no jump to the top),
    // Chart.js 4.5.1, and the reset tool's "Delete All Events" now also deletes admin and
    // name change events.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
