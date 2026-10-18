<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 110;
    $version = "1.10.4";

    // Admin passwords move from unsalted MD5 to password_hash() (GitHub issue #79).
    // Existing MD5 hashes keep working and are replaced on the next login.
    print "Widening hlstats_Users.password for password_hash().<br />";

    $db->query("
        ALTER TABLE `hlstats_Users`
            MODIFY `password` varchar(255) NOT NULL default ''
    ");

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
