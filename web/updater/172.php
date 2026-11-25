<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 172;
    $version = "1.19.0";

    // Two-factor authentication (authenticator app codes) for the admin accounts:
    // secrets and recovery codes tables, and the twofactor_required option (off by default).

    print "Creating tables hlstats_TwoFactor and hlstats_RecoveryCodes.<br />";
    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_TwoFactor` (
          `username` varchar(16) NOT NULL,
          `secret` varchar(64) NOT NULL,
          `last_step` bigint(20) unsigned NOT NULL default '0',
          `created` int(10) unsigned NOT NULL default '0',
          `last_used` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY (`username`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->query("
        CREATE TABLE IF NOT EXISTS `hlstats_RecoveryCodes` (
          `id` int(10) unsigned NOT NULL auto_increment,
          `username` varchar(16) NOT NULL,
          `code_hash` char(64) NOT NULL,
          `used` int(10) unsigned NOT NULL default '0',
          PRIMARY KEY (`id`),
          UNIQUE KEY `username_code` (`username`, `code_hash`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->query("INSERT IGNORE INTO hlstats_Options (`keyname`, `value`, `opttype`) VALUES ('twofactor_required', '0', 2)");
    $db->query("INSERT IGNORE INTO hlstats_Options_Choices (`keyname`, `value`, `text`, `isDefault`) VALUES
        ('twofactor_required', '0', 'No', 1),
        ('twofactor_required', '1', 'Yes', 0)");

    // Perform database schema update notification
    print "Updating database and version schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
