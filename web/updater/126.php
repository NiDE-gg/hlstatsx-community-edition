<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 126;
    $version = "1.14.4";

    // Version bump only: web and daemon bug fixes (player homepage links escaped, hlx_set
    // values checked by the daemon, chat search with operator characters, links that forced
    // http://, multibyte player names in titles, weapon lists, Ventrilo status). Restart
    // hlstats.pl after updating the daemon files.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
