<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 134;
    $version = "1.16.4";

    // Version bump only: SITE_URL and TRUSTED_PROXIES for HTTPS detection behind a TLS-terminating proxy.

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
