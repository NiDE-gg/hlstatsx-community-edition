<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 91;
    $version = "1.7.10";

    // Version bump only: extracts the remaining inline PHP-generated JS from
    // playerinfo_general.php's setForumText() and admin.php's
    // EditList::setHelp() into external JS files (GitHub issue #45).
    // No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
