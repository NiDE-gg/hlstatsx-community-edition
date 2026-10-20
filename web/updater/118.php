<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 118;
    $version = "1.11.0";

    // New per-server setting LogSecret (GitHub issue #85): when it matches sv_logsecret
    // on the game server, the daemon drops log lines that are not signed with it.
    // Empty by default, so nothing changes until an admin sets it.
    $db->query("INSERT IGNORE INTO hlstats_Servers_Config_Default (`parameter`, `value`, `description`) VALUES
        ('LogSecret', '', 'Source engine only. Set the same number as <b>sv_logsecret</b> on the game server: the daemon then drops every log line for this server that is not signed with it, so nobody can inject events by spoofing the server address. Empty (default) = not checked. Restart or reload the daemon after changing it.')");
    $db->query("INSERT IGNORE INTO hlstats_Servers_Config (`serverId`, `parameter`, `value`) SELECT serverId, 'LogSecret', '' FROM hlstats_Servers");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
