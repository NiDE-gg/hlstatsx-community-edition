<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 175;
    $version = "1.19.3";

    // headerlogo option (Site Settings): file name in the style's icons folder or full URL of the
    // logo at the top of the main and in-game pages. Empty (default) keeps title.png.

    print "Adding the headerlogo option.<br />";
    $db->query("INSERT IGNORE INTO hlstats_Options (`keyname`, `value`, `opttype`) VALUES ('headerlogo', '', 2)");

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
