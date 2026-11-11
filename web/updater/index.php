<?php
	// SCRIPT_NAME holds no PATH_INFO, and the single leading slash keeps the redirect on this site
	$base = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');
	header('Location: /' . ($base === '' ? '' : "$base/") . 'hlstats.php?mode=updater');
	exit;
?>
