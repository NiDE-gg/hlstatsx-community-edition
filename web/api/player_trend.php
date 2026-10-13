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

// JSON API equivalent of trend_graph.php (per-player skill/session-delta
// trend) PNG renderer. Read-only, same public trust level as the PNG
// endpoint. Part of issue #57 phase 3 (backend-only; no rendering/markup
// changes). Reuses Repository\PlayerRepository::getPlayerSkillHistory() as-is.

declare(strict_types = 1);
const IN_HLSTATS = true;

require __DIR__ . '/../config.php';

$container = require ROOT_PATH . '/bootstrap.php';
$playerRepo = $container->get(\Repository\PlayerRepository::class);

header('Content-Type: application/json');

function apiError(string $message, int $status = 400) : void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

// player: required, positive integer.
$player = filter_input(INPUT_GET, 'player', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($player === false || $player === null) {
    apiError('Missing or invalid "player" parameter; expected a positive integer.');
}

$history = $playerRepo->getPlayerSkillHistory($player, 30);
if ($history === null) {
    apiError('Failed to load player skill history.', 500);
}

echo json_encode([
    'player'  => $player,
    'history' => array_map(static function (array $row) : array {
        return [
            'ts'           => (int)$row['ts'],
            'skill'        => (int)$row['skill'],
            'skill_change' => (int)$row['skill_change'],
        ];
    }, $history),
]);
