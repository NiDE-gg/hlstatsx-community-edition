<?php
/*
    HLstatsX Community Edition - Real-time player and clan rankings and statistics
    Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
    http://www.hlxcommunity.com

    HLstatsX Community Edition is a continuation of
    ELstatsNEO - Real-time player and clan rankings and statistics
    Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
    http://ovrsized.neo-soft.org/

    ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
    HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
    http://www.hlstatsx.com/
    Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

    HLstatsX is an enhanced version of HLstats made by Simon Garner
    HLstats - Real-time player and clan rankings and statistics for Half-Life
    http://sourceforge.net/projects/hlstats/
    Copyright (C) 2001  Simon Garner

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

    For support and installation notes visit http://www.hlxcommunity.com
*/

    // TODO:
    // 1) Replace pChart with something else
    // 2) Add rate limit?

    declare(strict_types = 1);
    const IN_HLSTATS = true;

    // Load components
    require ('config.php');
    require (INCLUDE_PATH . '/functions.php');
    require (INCLUDE_PATH . '/pChart/pData.class');
    require (INCLUDE_PATH . '/pChart/pChart.class');

    $container = require ROOT_PATH . '/bootstrap.php';
    $playerRepo = $container->get(\Repository\PlayerRepository::class);

    // Filter Input
    $player = filter_input(INPUT_GET, 'player', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (empty($player) || !is_int($player)) {
        exit();
    }

    $bgColor = ['red' => 90, 'green' => 90, 'blue' => 90];
    $bgInput = filter_input(INPUT_GET, 'bgcolor', FILTER_UNSAFE_RAW);
    if (is_string($bgInput)) {
        $checkRgb = tryHexToRgb($bgInput);

        if (is_array($checkRgb) && count($checkRgb) == 3) {
            $bgColor = $checkRgb;
        }
    }

    $colorText = ['red' => 213, 'green' => 217, 'blue' => 221];
    $colorInput = filter_input(INPUT_GET, 'color', FILTER_UNSAFE_RAW);
    if (is_string($colorInput)) {
        $checkRgb = tryHexToRgb($colorInput);

        if (is_array($checkRgb) && count($checkRgb) == 3) {
            $colorText = $checkRgb;
        }
    }

    // Get info from db
    $history = $playerRepo->getPlayerSkillHistory($player, 30);
    if ($history === null) {
        exit();
    }

    // Parse info from db
    $skillArr = array();
    $skillChange = array();
    $dateArr = array();
    $lastTime = 0;

    $rowCount = count($history);
    for ($i = 1; $i <= $rowCount; $i++) {
        $row = $history[$i - 1];

        $newSkill = ($row['skill'] == 0) ? 0 : ($row['skill'] / 1000);
        array_unshift($skillArr, $newSkill);
        array_unshift($skillChange, $row['skill_change']);

        if ($i == 1 || $i == round($rowCount / 2) || $i == $rowCount) {
            $newDate = date("M-j", $row['ts']);
            array_unshift($dateArr, $newDate);

            if ($i == $rowCount) {
                $lastTime = $row['ts'];
            }

            continue;
        }

        array_unshift($dateArr, '');
    }

    $emptyData = (count($dateArr) < 2);

    // Check cache
    if ($emptyData) {
        // We don't create a bunch of empty graphs in the cache for each player,
        // but rather create a common one in this case.
        // hlstatsimg/progress/trend_nodata_e91b5b791ae00c5eaef6aeb548de8db7.png
        $params = [
            'bgcolor' => $bgColor,
            'color' => $colorText,
        ];

        $cache_key = md5(json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $cache_image = IMAGE_PATH . "/progress/trend_nodata_{$cache_key}.png";
    } else {
        $params = [
            'bgcolor' => $bgColor,
            'color' => $colorText,
            'last_time' => $lastTime,
        ];

        $cache_key = md5(json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $cache_image = IMAGE_PATH . "/progress/trend_{$player}_{$cache_key}.png";
    }

    if (file_exists($cache_image)) {
        header('Content-type: image/png');
        readfile($cache_image);

        exit();
    }

    // Clear cache
    $cacheCleaner = $container->get(\Cache\CacheCleaner::class);
    $deleteFiles = $cacheCleaner->cleanOldTrendCache(
        ($emptyData) ? null : $player,
        TREND_CACHE_STORAGE_TIME,
        TREND_CACHE_MAX_FILES_PER_PLAYER
    );

    // Quick cleaning dir if needed.
    //die("Delete cache files: " . $cacheCleaner->clearDir());

    // Create new graph
    $Chart = new pChart(380, 200);
    $Chart->drawBackground($bgColor['red'], $bgColor['green'], $bgColor['blue']);

    $Chart->setGraphArea(50, 28, 339, 174);
    $Chart->drawGraphAreaGradient(40, 40, 40, -50);

    if ($emptyData) {
        $Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf', 11);
        $Chart->drawTextBox(100, 90, 180, 110, "Not Enough Session Data", 0, 0, 0, 0, ALIGN_LEFT, FALSE, 255, 255, 255, 0);
    } else {
        $DataSet = new pData;
        $DataSet->AddPoint($skillArr, 'SerieSkill');
        $DataSet->AddPoint($skillChange, 'SerieSession');
        $DataSet->AddPoint($dateArr, 'SerieDate');
        $DataSet->AddSerie('SerieSkill');
        $DataSet->SetAbsciseLabelSerie('SerieDate');
        $DataSet->SetSerieName('Skill', 'SerieSkill');
        $DataSet->SetSerieName('Session', 'SerieSession');

        $Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf', 7);
        $DataSet->SetYAxisName('Skill');
        $DataSet->SetYAxisUnit('K');
        $Chart->setColorPalette(0, 255, 255, 0);
        $Chart->drawRightScale($DataSet->GetData(), $DataSet->GetDataDescription(),
            SCALE_NORMAL, $colorText['red'], $colorText['green'], $colorText['blue'], TRUE, 0, 0);
        $Chart->drawGrid(1, FALSE, 55, 55, 55, 100);
        $Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
        $Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
        $Chart->clearShadow();
        $Chart->drawFilledCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription(), .1, 30);
        $Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);

        $Chart->clearScale();

        $DataSet->RemoveSerie('SerieSkill');
        $DataSet->AddSerie('SerieSession');
        $DataSet->SetYAxisName('Session');
        $DataSet->SetYAxisUnit('');
        $Chart->setColorPalette(1, 255, 0,   0);
        $Chart->setColorPalette(2,   0, 0, 255);
        $Chart->drawScale($DataSet->GetData(), $DataSet->GetDataDescription(),
            SCALE_NORMAL, $colorText['red'], $colorText['green'], $colorText['blue'], TRUE, 0, 0);
        $Chart->setShadowProperties(3, 3, 0, 0, 0, 30, 4);
        $Chart->drawCubicCurve($DataSet->GetData(), $DataSet->GetDataDescription());
        $Chart->clearShadow();
        $Chart->drawPlotGraph($DataSet->GetData(), $DataSet->GetDataDescription(), 1, 1, 255, 255, 255);

        $Chart->setFontProperties(IMAGE_PATH . '/sig/font/DejaVuSans.ttf',7);
        $Chart->drawHorizontalLegend(235, -1, $DataSet->GetDataDescription(),
            0, 0, 0, 0, 0, 0, $colorText['red'], $colorText['green'], $colorText['blue'], FALSE);
    }

    $Chart->Render($cache_image);
    header("Location: {$cache_image}");