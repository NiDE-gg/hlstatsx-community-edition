<?php

	if (!defined('IN_HLSTATS')) {
		die('Do not access this file directly.');
	}
	
	if (!file_exists("./updater")) {
		die('Updater directory is missing.');
	}
	
	// Database changes are started by a full administrator, never by a visitor
	require_once(INCLUDE_PATH . '/class_auth.php');

	$auth = new Auth;
	if ($auth->ok === false) {
		return;
	}

	define('IN_UPDATER', true);
	
	pageHeader
	(
		array ('Updater')
	);

	if ($auth->userdata['acclevel'] < 100) {
		error('Access denied!', false);
		return;
	}

	if (empty($_POST['runupdate'])) {
		echo "<div class=\"warning\">\n<form method=\"post\" action=\"" . $g_options['scripturl'] . "?mode=updater\">\n"
			. Auth::csrfField()
			. "<input type=\"hidden\" name=\"runupdate\" value=\"1\" />\n"
			. "<input type=\"submit\" value=\" Run the database update \" class=\"submit\" />\n</form>\n</div>\n";
		return;
	}

	// Two updates at the same time would run the same ALTERs twice
	$lockResult = $db->query("SELECT GET_LOCK('hlstats_updater', 0)");
	list($gotLock) = $db->fetch_row($lockResult);
	if (!$gotLock) {
		error('A database update is already running.', false);
		return;
	}

	echo "<div class=\"warning\">\n" .
	"<span id=\"warning-header\"><strong>HLX:CE Database Updater log</span></strong><br /><br />\n";
	// Check version since updater wasn't implemented until version 1.6.2
	$versioncomp = version_compare($g_options['version'], '1.6.1');
	
	if ($versioncomp === -1)
	{
		// not yet at 1.6.1
		echo "You cannot upgrade from this version (".$g_options['version']."). You can only upgrade from 1.6.1.  Please manually apply the SQL updates found in the SQL folder through 1.6.1, then re-run this updater.\n";
	}
	else if ($versioncomp === 0)
	{
		// at 1.6.1, up to 1.6.2
		include ("./updater/update161-162.php");		
	}
	else
	{
		// at 1.6.2 or higher, can update normally
		echo "Currently on database version ".$g_options['dbversion']."<br />\n";
		$i = $g_options['dbversion']+1;
		
		while (file_exists ("./updater/$i.php"))
		{
			echo "<br /><em>Running database update $i</em><br />\n";
			include ("./updater/$i.php");
			
			echo "<em>Database update for DB Version $i complete.</em><br />";
			$i++;
			
		}
		
		if ($i == $g_options['dbversion']+1)
		{
			echo "<strong>Your database is already up to date (".$g_options['dbversion'].")</strong>\n";
		}
		else
		{
			echo "<br /><strong>Successfully updated to database version ".($i-1)."!</strong>\n";
		}
	}
	
	echo "<br /><br /><a href=\"" . $g_options['scripturl'] . "\">Back to the site</a>\n</div>\n";
?>