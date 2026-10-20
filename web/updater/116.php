<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 116;
    $version = "1.10.10";

    // The hit/visit counters write to hlstats_Options on every page view; this
    // option turns them off (they stay on by default, as before). See issue #86.
    $db->query("INSERT IGNORE INTO hlstats_Options (`keyname`, `value`, `opttype`) VALUES ('counter_enabled', '1', 2)");
    $db->query("INSERT IGNORE INTO hlstats_Options_Choices (`keyname`, `value`, `text`, `isDefault`) VALUES ('counter_enabled', '1', 'Yes', 1), ('counter_enabled', '0', 'No', 0)");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
