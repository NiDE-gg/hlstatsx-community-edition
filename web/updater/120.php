<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 120;
    $version = "1.13.0";

    // Version bump only: the web frontend now requires PHP 8.5.12 or newer, the
    // first 8.5 release with the fixes for CVE-2026-109618 and CVE-2026-109619.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
