<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 92;
    $version = "1.7.11";

    // Version bump only: reimplements the MooTools Fx.Accordion used on
    // game.php (web/includes/js/accordion.js) in vanilla ES6+. No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
