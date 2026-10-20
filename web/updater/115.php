<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 115;
    $version = "1.10.9";

    // Chat history lists one player's messages ordered by time: extend the playerId
    // index with eventTime so MySQL reads them in index order instead of sorting.
    // It replaces the single-column index of the same name (see 80.php).
    print "Replacing the chat playerId index with (playerId, eventTime) (this may take a while on large databases).<br />";
    $db->query("ALTER TABLE hlstats_Events_Chat DROP INDEX playerId, ADD INDEX playerId (playerId, eventTime)");

    // Same GeoIP overflow as hlstats_Players city/state/country (79.php/80.php),
    // on the live players table.
    $db->query("ALTER TABLE hlstats_Livestats MODIFY cli_city VARCHAR(128) NOT NULL DEFAULT '', MODIFY cli_country VARCHAR(128) NOT NULL DEFAULT '', MODIFY cli_state VARCHAR(128) NOT NULL DEFAULT ''");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
