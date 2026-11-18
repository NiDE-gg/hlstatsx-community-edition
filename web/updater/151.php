<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 151;
    $version = "1.18.5";

    // hlstats_Players.mmrank (CS:GO rank image, csgo_elorank plugin) only came from
    // sql/migrations/2019_02_22_0000.sql, run by hand: a database kept up to date with
    // these updaters may not have it, and the player page reads it. That folder is
    // removed with the other legacy SQL scripts, so the column is added here if missing.
    $result = $db->query("SHOW COLUMNS FROM hlstats_Players LIKE 'mmrank'");
    if ($db->num_rows($result) == 0) {
        print "Adding hlstats_Players.mmrank.<br />";
        $db->query("ALTER TABLE hlstats_Players ADD `mmrank` TINYINT NULL DEFAULT NULL AFTER `icq`");
    }

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
