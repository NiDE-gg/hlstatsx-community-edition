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

// JSON API equivalent of show_graph.php's type=0 (per-server load/uptime/fps
// trend) PNG renderer. Read-only, same public trust level as the PNG endpoint.
// Part of issue #57 phase 3 (backend-only; no rendering/markup changes).

declare(strict_types = 1);
const IN_HLSTATS = true;

require __DIR__ . '/../config.php';

$container = require ROOT_PATH . '/bootstrap.php';
$serverLoadRepo = $container->get(\Repository\ServerLoadRepository::class);

header('Content-Type: application/json');

function apiError(string $message, int $status = 400) : void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

// server_id: required, positive integer.
$serverId = filter_input(INPUT_GET, 'server_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($serverId === false || $serverId === null) {
    apiError('Missing or invalid "server_id" parameter; expected a positive integer.');
}

// range: optional (defaults to 1), but if present must be an integer from 1 to 4,
// same as show_graph.php's type=0 branch.
$range = 1;
if (isset($_GET['range'])) {
    $parsedRange = filter_input(INPUT_GET, 'range', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
    if ($parsedRange === false || $parsedRange === null) {
        apiError('Invalid "range" parameter; expected an integer from 1 to 4.');
    }
    $range = $parsedRange;
}

switch ($range) {
    case 2:
        $avgStep = 7;
        break;
    case 3:
        $avgStep = 33;
        break;
    case 4:
        $avgStep = 400;
        break;
    default:
        $avgStep = 1;
        break;
}

$samples = $serverLoadRepo->getServerLoadSamples($serverId, $avgStep < 10);
if ($samples === null) {
    apiError('Failed to load server load data.', 500);
}

// Average rows into buckets of $avgStep samples each, newest first, same
// bucketing show_graph.php's type=0 branch performs for the PNG.
$dataArray = [];
$bucket = [];
foreach ($samples as $row) {
    $bucket[] = $row;

    if (count($bucket) === $avgStep) {
        $sumActPlayers = 0;
        $sumMinPlayers = 0;
        $sumMaxPlayers = 0;
        $sumUptime = 0;
        $sumFps = 0;
        $map = '';

        foreach ($bucket as $entry) {
            $sumActPlayers += (int)$entry['act_players'];
            $sumMinPlayers += (int)$entry['min_players'];
            $sumMaxPlayers += (int)$entry['max_players'];
            $sumUptime += (int)$entry['uptime'];
            $sumFps += (int)$entry['fps'];
            $map = $entry['map'];
        }

        $dataArray[] = [
            'timestamp'   => (int)$bucket[(int)ceil($avgStep / 2) - 1]['timestamp'],
            'act_players' => (int)round($sumActPlayers / $avgStep),
            'min_players' => (int)round($sumMinPlayers / $avgStep),
            'max_players' => (int)round($sumMaxPlayers / $avgStep),
            'uptime'      => (int)round($sumUptime / $avgStep),
            'fps'         => (int)round($sumFps / $avgStep),
            'map'         => $map,
        ];

        $bucket = [];
    }
}

// At the finest granularity, prepend a "right now" point straight from
// hlstats_Servers, same as show_graph.php's type=0 branch does.
if ($avgStep === 1) {
    $current = $serverLoadRepo->getCurrentServerPlayers($serverId);
    if ($current !== null) {
        array_unshift($dataArray, [
            'timestamp'   => time(),
            'act_players' => (int)$current['act_players'],
            'min_players' => $dataArray[0]['min_players'] ?? null,
            'max_players' => (int)$current['max_players'],
            'uptime'      => 0,
            'fps'         => 0,
            'map'         => '',
        ]);
    }
}

// Average players over the last hour/day, only meaningful (and only computed
// by show_graph.php) at the finest granularity.
$averages = null;
if ($avgStep === 1) {
    $lastHour = $serverLoadRepo->getAveragePlayersSince($serverId, time() - 3600);
    $lastDay  = $serverLoadRepo->getAveragePlayersSince($serverId, time() - 86400);

    $averages = [
        'last_hour' => ($lastHour === null) ? null : round($lastHour, 1),
        'last_day'  => ($lastDay === null) ? null : round($lastDay, 1),
    ];
}

echo json_encode([
    'server_id' => $serverId,
    'range'     => $range,
    'avg_step'  => $avgStep,
    'data'      => $dataArray,
    'averages'  => $averages,
]);
