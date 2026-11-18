<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 147;
    $version = "1.18.1";

    // Player history: the disconnects of a player and the team kills where they were
    // the victim had no index, so every view of the page read those whole tables.
    // Measured 2026-11-18 on 400,000 disconnects and 200,000 team kills: adding both
    // took about a second, and the history page went from 0.18 s to 0.08 s.
    print "Adding player indexes to the disconnect and team kill events (this may take a while on large databases).<br />";
    foreach (array('hlstats_Events_Disconnects' => 'playerId', 'hlstats_Events_Teamkills' => 'victimId') as $table => $column) {
        $result = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$column'");
        if ($db->num_rows($result) == 0) {
            $db->query("ALTER TABLE `$table` ADD INDEX `$column` (`$column`)");
        }
    }

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
