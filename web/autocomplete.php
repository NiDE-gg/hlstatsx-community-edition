<?php
	// Code to autocomplete the search bar on the player list page,
	// displays something like tooltips.
	declare(strict_types = 1);

	const IN_HLSTATS = true;

	// Load required files
	require('config.php');
	require_once __DIR__ . '/includes/autoload.php';
	require(INCLUDE_PATH . '/functions.php');

	// Filter search input
	$search = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW);
	if (empty($search) || !is_string($search)) {
		exit;
	}

	$search = trim($search);
	$searchLen = strlen($search);
	if ($searchLen < 2 || $searchLen > 64) {
		exit;
	}

	use Config\DatabaseOptions;
	use Database\PDODriver;
	use Repository\PlayerRepository;
	use Repository\GameRepository;
	use Utils\Logger;

	$logger = new Logger();

	// Create db class
	$dbOptions = new DatabaseOptions([
		'host'     => DB_ADDR,
		'user'     => DB_USER,
		'pass'     => DB_PASS,
		'name'     => DB_NAME,
		'pconnect' => DB_PCONNECT,
		'charset'  => DB_CHARSET,
	]);

	$pdoClass = new PDODriver($dbOptions, $logger);
	$pdo = $pdoClass->getPDO();

	// Filter game input
	$game = filter_input(INPUT_GET, 'game', FILTER_UNSAFE_RAW);

	$gameRepo = new GameRepository($pdo, $logger);
	$allowedGames = $gameRepo->getGameCodes();

	$retError = "";
	if (!checkValidGame($game, $allowedGames, $retError)) {
		//print $retError;
		exit;
	}

	$playerRepo = new PlayerRepository($pdo, $logger);
	$arrayNames = $playerRepo->getPlayerSuggestions($game, $search, 30);

	if (empty($arrayNames)) {
		exit;
	}

	$retHtml = "";
	foreach ($arrayNames as $name) {
		$retHtml .= '<li class="playersearch">';
		$retHtml .= eHtml($name);
		$retHtml .= "</li>" . "\n";
	}

	print $retHtml;