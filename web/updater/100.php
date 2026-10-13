<?php
    if ( !defined('IN_UPDATER') )
    {
        die('Do not access this file directly.');
    }

    $dbversion = 100;
    $version = "1.9.1";

    // Version bump only: extends the mobile-responsive nav fix (#63 phase 1)
    // to the three themes that redeclare .fancyNav/.header_gameslist
    // unconditionally (hlstats_final.css, hlstats_ipb5_final.css,
    // nom_nom_nom.css), so it reaches them too (#63 phase 2). No schema change.

    // Perform database schema update notification
    print "Updating database and verion schema numbers.<br />";
    $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'version'");
    $db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
