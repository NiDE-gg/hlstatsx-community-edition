<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 114;
    $version = "1.10.8";

    // Version bump only: Google Maps loads over HTTPS and no longer uses removed
    // API options; tab and Teamspeak JS fixes. The new version also changes the
    // ?v= of every script URL, so browsers fetch the fixed JS (GitHub issue #83).

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
