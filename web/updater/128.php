<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 128;
    $version = "1.15.0";

    // Discord servers in the voice server list (hlstats_Servers_VoiceComm.serverType = 2).
    // What Discord returns for an invite or a server widget is cached here, so pages do not
    // call discord.com on every view. `source` is "invite:<code>" or "guild:<id>"; the
    // "ratelimit" row holds the time until which Discord must not be called after an HTTP 429.
    print "Creating hlstats_DiscordCache cache table.<br />";

    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_DiscordCache` (
          `source` varchar(64) NOT NULL,
          `data` mediumtext default NULL,
          `error` varchar(32) NOT NULL default '',
          `fetched` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY  (`source`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // The home page had a hardcoded "NiDE.GG Discord" row (pages/contents.php, which called
    // discord.com on every view) and a header icon linking to it. Both now come from the
    // Discord voice servers, so that server becomes a regular one: the home page looks the
    // same after the update, and it can be edited or deleted in Manage Voice Servers.
    // Its hardcoded invite had expired; the server ID works through the server widget.
    $result = $db->query("SELECT COUNT(*) FROM hlstats_Servers_VoiceComm WHERE serverType = 2");
    list($discordServers) = $db->fetch_row($result);
    if ((int)$discordServers === 0) {
        print "Adding the NiDE.GG Discord server that was hardcoded on the home page (Manage Voice Servers).<br />";
        $db->query("
            INSERT IGNORE INTO hlstats_Servers_VoiceComm
                (name, addr, password, descr, queryPort, UDPPort, serverType)
            VALUES
                ('NiDE.GG Discord', '579962159270985733', NULL, '', 51234, 8767, 2)
        ");
    }

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
