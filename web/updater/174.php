<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 174;
    $version = "1.19.2";

    // Version bump only: second-factor tries are rate limited per account even from a known browser, refused IPv6 sign-ins are logged once per /64, and two-factor settings of deleted or renamed admin accounts are dropped in every case.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
