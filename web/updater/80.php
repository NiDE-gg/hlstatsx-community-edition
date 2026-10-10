<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 80;
    $version = "1.6.20";

    // Covering indexes for per-player / per-action aggregates (claninfo, actioninfo, playerinfo pages).
    // Each replaces the single-column index of the same name, so the index count does not grow.
    print "Replacing event indexes with covering indexes (this may take a while on large databases).<br />";

    $db->query("ALTER TABLE hlstats_Events_Statsme DROP INDEX playerId, ADD INDEX playerId (playerId, weapon, shots, hits, headshots, damage, kills, deaths)");
    $db->query("ALTER TABLE hlstats_Events_Statsme2 DROP INDEX playerId, ADD INDEX playerId (playerId, weapon, head, chest, stomach, leftarm, rightarm, leftleg, rightleg)");
    $db->query("ALTER TABLE hlstats_Events_PlayerActions DROP INDEX playerId, ADD INDEX playerId (playerId, actionId, bonus), DROP INDEX actionId, ADD INDEX actionId (actionId, playerId)");
    $db->query("ALTER TABLE hlstats_Events_Frags DROP INDEX killerId, ADD INDEX killerId (killerId, weapon, headshot, map)");
    $db->query("ALTER TABLE hlstats_Events_Entries DROP INDEX playerId, ADD INDEX playerId (playerId, serverId)");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
