<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 79;
    $version = "1.6.20";

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";

    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

	// Fix case 'Administrative-Territorial Units of the Left Bank of the Dniester'
    $db->query("ALTER TABLE `hlstats_Players` CHANGE `state` `state` VARCHAR(128) NOT NULL default '';");
?>