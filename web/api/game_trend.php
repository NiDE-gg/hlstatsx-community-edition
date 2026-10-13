<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

For support and installation notes visit http://www.hlxcommunity.com
*/

// JSON API equivalent of show_graph.php's type=1 (per-game kills/headshots/
// players/slots trend) PNG renderer. Read-only, same public trust level as
// the PNG endpoint. Part of issue #57 phase 3 (backend-only; no rendering/
// markup changes).

declare(strict_types = 1);
const IN_HLSTATS = true;

require __DIR__ . '/../config.php';
require ROOT_PATH . '/includes/functions.php';

$container = require ROOT_PATH . '/bootstrap.php';
$gameRepo = $container->get(\Repository\GameRepository::class);
$trendRepo = $container->get(\Repository\TrendRepository::class);
$playerRepo = $container->get(\Repository\PlayerRepository::class);

header('Content-Type: application/json');

function apiError(string $message, int $status = 400) : void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

// game: required, must be a known, non-hidden game code (stricter than
// show_graph.php, which accepts any string and just gets an empty result set).
$gameInput = filter_input(INPUT_GET, 'game', FILTER_UNSAFE_RAW);
if (!is_string($gameInput)) {
    apiError('Missing or invalid "game" parameter.');
}

$allowedGames = $gameRepo->getGameCodes();
if ($allowedGames === null) {
    apiError('Failed to load game list.', 500);
}

$gameError = '';
if (!checkValidGame($gameInput, $allowedGames, $gameError)) {
    apiError($gameError);
}

$game = $gameInput;

$samples = $trendRepo->getTrendSamples($game, 350);
if ($samples === null) {
    apiError('Failed to load trend data.', 500);
}

$totalPlayers = $playerRepo->getPlayerCountByGame($game);
if ($totalPlayers === null) {
    apiError('Failed to load player count.', 500);
}

$playersLastHour = $trendRepo->getPlayersAtOrBefore($game, time() - 3600);
$playersLastDay  = $trendRepo->getPlayersAtOrBefore($game, time() - 86400);

echo json_encode([
    'game' => $game,
    'data' => array_map(static function (array $row) : array {
        return [
            'timestamp'  => (int)$row['timestamp'],
            'players'    => (int)$row['players'],
            'kills'      => (int)$row['kills'],
            'headshots'  => (int)$row['headshots'],
            'act_slots'  => (int)$row['act_slots'],
            'max_slots'  => (int)$row['max_slots'],
        ];
    }, $samples),
    'total_players' => $totalPlayers,
    'new_players' => [
        'last_hour' => $totalPlayers - ($playersLastHour ?? 0),
        'last_day'  => $totalPlayers - ($playersLastDay ?? 0),
    ],
]);
