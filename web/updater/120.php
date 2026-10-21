<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 120;
    $version = "1.12.1";

    // Version bump only: the admin panel warns when PHP lacks the fixes for
    // CVE-2026-109618 and CVE-2026-109619 (PHP 8.3.36, 8.4.27, 8.5.12).

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
