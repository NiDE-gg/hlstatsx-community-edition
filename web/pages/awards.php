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

    // Awards Info Page
    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    $container = require __DIR__ . '/../bootstrap.php';
    $gameRepo = $container->get(\Repository\GameRepository::class);
    $optionService = $container->get(\Service\OptionService::class);

    $game = filter_input(INPUT_GET, 'game', FILTER_UNSAFE_RAW) ?? '';

    $retError = "";
    $allowedGames = $gameRepo->getGameCodes();
    if (!checkValidGame($game, $allowedGames, $retError)) {
        error("{$retError}");
    }

    $gameName = $gameRepo->getGameByCode($game, 'name');

    $type = filter_input(INPUT_GET, 'type', FILTER_UNSAFE_RAW) ?? '';
    $tab = filter_input(INPUT_GET, 'tab', FILTER_UNSAFE_RAW) ?? '';

    if ($type == 'ajax') {
        $tabs = explode('|', preg_replace('/[^a-z]/', '', $tab));

        foreach ($tabs as $tab) {
            $awardPath = PAGE_PATH . '/awards_' . $tab . '.php';

            if (file_exists($awardPath)) {
                @include($awardPath);
            }
        }

        exit;
    }

    pageHeader(
            array($gameName, 'Awards Info'),
            array($gameName => "%s?game=$game", 'Awards Info' => '')
    );

    $defaultTab = 'daily';
    if ($tab) {
        $defaultTab = $tab;
    }

    $options = $optionService->getAllOptions();
    ?>

    <?php if ($options['playerinfo_tabs'] == '1') : ?>
        <div id="main">
            <ul class="subsection_tabs" id="tabs_submenu">
                <li>
                    <a href="#" id="tab_daily">Daily&nbsp;Awards</a>
                </li>
                <li>
                    <a href="#" id="tab_global">Global&nbsp;Awards</a>
                </li>
                <li>
                    <a href="#" id="tab_ranks">Ranks</a>
                </li>
                <li>
                    <a href="#" id="tab_ribbons">Ribbons</a>
                </li>
            </ul>
            <br>

            <div id="main_content"></div>

            <script type="text/javascript">
                new Tabs($('main_content'), $$('#main ul.subsection_tabs a'), {
                    'mode': 'awards',
                    'game': '<?=eHtml($game);?>',
                    'loadingImage': '<?=IMAGE_PATH;?>/ajax.gif',
                    'defaultTab': '<?=eHtml($defaultTab);?>'
                });
            </script>
        </div>
    <?php else : ?>
        <div id="daily">
            <?php include PAGE_PATH . '/awards_daily.php'; ?>
        </div>

        <div id="global">
            <?php include PAGE_PATH . '/awards_global.php'; ?>
        </div>

        <div id="ranks">
            <?php include PAGE_PATH . '/awards_ranks.php'; ?>
        </div>

        <div id="ribbons">
            <?php include PAGE_PATH . '/awards_ribbons.php'; ?>
        </div>
    <?php endif; ?>