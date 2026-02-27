<?php
	// Code to autocomplete the search bar on the player list page,
	// displays something like tooltips.
	define('IN_HLSTATS', true);

	// Load required files
	require('config.php');
	require(INCLUDE_PATH . '/class_db.php');
	require(INCLUDE_PATH . '/functions.php');
	require_once __DIR__ . '/includes/autoload.php';

	use Repository\PlayerRepository;
	use Utils\Logger;

	// Create db class
	$dbClassname = 'DB_' . DB_TYPE;
	if (!class_exists($dbClassname)) {
		//print "Database class does not exist.  Please check your config.php file for DB_TYPE";
		die();
	}

	$db = new $dbClassname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);

	// Filter input
	$game = filter_input(INPUT_GET, 'game', FILTER_UNSAFE_RAW);
	$search = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW);

	$retError = "";
	if (!checkValidGame($db, $game, $retError)) {
		//print $retError;
		die();
	}

	if (empty($search) || !is_string($search)) {
		//print "Invalid search string.";
		die();
	}

	$search = trim($search);
	$searchLen = strlen($search);
	if ($searchLen < 2) {
		//print "The search string is too short.";
		die();
	}

	if ($searchLen > 64) {
		//echo "The search string is too long.";
		die();
	}

	$logger = new Logger();
	$playerRepo = new PlayerRepository($db, $logger);
	$arrayNames = $playerRepo->getPlayerSuggestions($game, $search, 30);

	$retHtml = "";
	foreach ($arrayNames as $name) {
		$retHtml .= '<li class="playersearch">';
		$retHtml .= eHtml($name);
		$retHtml .= "</li>" . "\n";
	}

	print $retHtml;
?>