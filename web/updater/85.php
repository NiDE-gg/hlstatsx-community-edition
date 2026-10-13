<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 85;
    $version = "1.7.4";

    // Version bump only: removes the dead Flash-based weapon hit-location UI
    // (playerinfo_weapons.php / claninfo_weapons.php / admin options toggle).
    // The show_weapon_target_flash option row is intentionally left in
    // hlstats_Options; it is simply no longer read or exposed in the admin UI.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
