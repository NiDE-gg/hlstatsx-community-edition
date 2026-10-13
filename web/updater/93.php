<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 93;
    $version = "1.7.12";

    // Version bump only: removes the vendored MooTools 1.2.1 build
    // (web/includes/js/mootools.js) and its SqueezeBox.js plugin +
    // css/SqueezeBox.css stylesheet. The 3 remaining call sites that used
    // MooTools' $()/$$() selector globals (awards.php, claninfo.php,
    // playerinfo.php) now build the vanilla Tabs() constructor args with
    // document.getElementById()/document.querySelectorAll(). No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
