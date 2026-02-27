<?php

    // Code to autocomplete the search bar on the player list page,
    // displays something like tooltips.
    declare(strict_types = 1);

    const IN_HLSTATS = true;

    // Load required files
    require('config.php');
    $container = require ROOT_PATH . '/bootstrap.php';
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

    // Filter game input
    $game = filter_input(INPUT_GET, 'game', FILTER_UNSAFE_RAW);

    $gameRepo = $container->get(\Repository\GameRepository::class);
    $allowedGames = $gameRepo->getGameCodes();

    $retError = "";
    if (!checkValidGame($game, $allowedGames, $retError)) {
        print $retError;
        exit;
    }

    $playerRepo = $container->get(\Repository\PlayerRepository::class);
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