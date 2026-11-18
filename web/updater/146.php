<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 146;
    $version = "1.18.0";

    // Passkey (WebAuthn) login for the admin accounts: credentials table and the
    // passkey_required option (off by default).

    print "Creating table hlstats_Passkeys.<br />";
    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_Passkeys` (
          `id` int(10) unsigned NOT NULL auto_increment,
          `username` varchar(16) NOT NULL,
          `credential_id` varbinary(255) NOT NULL,
          `user_handle` varbinary(64) NOT NULL,
          `public_key` text NOT NULL,
          `sign_count` int(10) unsigned NOT NULL default '0',
          `label` varchar(64) NOT NULL default '',
          `created` int(10) unsigned NOT NULL default '0',
          `last_used` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY (`id`),
          UNIQUE KEY `credential_id` (`credential_id`),
          KEY `username` (`username`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("INSERT IGNORE INTO hlstats_Options (`keyname`, `value`, `opttype`) VALUES ('passkey_required', '0', 2)");
    $db->query("INSERT IGNORE INTO hlstats_Options_Choices (`keyname`, `value`, `text`, `isDefault`) VALUES
        ('passkey_required', '0', 'No', 1),
        ('passkey_required', '1', 'Yes', 0)");

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
