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
    if (!defined('IN_HLSTATS')) {
        die('Do not access this file directly.');
    }

    $container = require ROOT_PATH . '/bootstrap.php';
    $gameRepo = $container->get(\Repository\GameRepository::class);
    $realgame = $gameRepo->getGameByCode($game, 'realgame');

	$result = $db->query("
		SELECT
			hlstats_Weapons.code,
			hlstats_Weapons.name
		FROM
			hlstats_Weapons
		WHERE
			hlstats_Weapons.game = '$game'
	");

	while ($rowdata = $db->fetch_row($result)) {
		$code = $rowdata[0];
		$fname[strToLower($code)] = htmlspecialchars($rowdata[1]);
	}

	$tblWeapons = new Table
	(
		array
		(
			new TableColumn
			(
				'weapon',
				'Weapon',
				'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&amp;weapon=%k&amp;game=$game"),
				$fname
			),
			new TableColumn
			(
				'modifier',
				'Modifier',
				'width=10&align=right'
			),
			new TableColumn
			(
				'kills',
				'Kills',
				'width=11&align=right'
			),
			new TableColumn
			(
				'kpercent',
				'%',
				'width=5&sort=no&align=right&append=' . urlencode('%')
			),
			new TableColumn
			(
				'kpercent',
				'Ratio',
				'width=18&sort=no&type=bargraph'
			),
			new TableColumn
			(
				'headshots',
				'Headshots',
				'width=8&align=right'
			),
			new TableColumn
			(
				'hpercent',
				'%',
				'width=5&sort=no&align=right&append=' . urlencode('%')
			),
			new TableColumn
			(
				'hpercent',
				'Ratio',
				'width=18&sort=no&type=bargraph'
			),
			new TableColumn
			(
				'hpk',
				'HS:K',
				'width=5&align=right'
			)
		),
		'weapon',
		'kills',
		'weapon',
		true,
		9999,
		'weap_page',
		'weap_sort',
		'weap_sortorder',
		'tabweapons',
		'desc',
		true
	);

	$result = $db->query("
		SELECT
			hlstats_Events_Frags.weapon,
			IFNULL(hlstats_Weapons.modifier, 1.00) AS modifier,
			COUNT(hlstats_Events_Frags.weapon) AS kills,
			ROUND(COUNT(hlstats_Events_Frags.weapon) / $realkills * 100, 2) AS kpercent,
			SUM(hlstats_Events_Frags.headshot = 1) AS headshots,
			ROUND(SUM(hlstats_Events_Frags.headshot = 1) / IF(COUNT(hlstats_Events_Frags.weapon) = 0, 1, COUNT(hlstats_Events_Frags.weapon)), 2) AS hpk,
			ROUND(SUM(hlstats_Events_Frags.headshot = 1) / $realheadshots * 100, 2) AS hpercent
		FROM
			hlstats_Events_Frags
		LEFT JOIN
			hlstats_Weapons
		ON
			hlstats_Weapons.code = hlstats_Events_Frags.weapon
		WHERE
			hlstats_Events_Frags.killerId = $player
			AND
			(
				hlstats_Weapons.game = '$game'
				OR hlstats_Weapons.weaponId IS NULL
			)
		GROUP BY
			hlstats_Events_Frags.weapon
		ORDER BY
			$tblWeapons->sort $tblWeapons->sortorder,
			$tblWeapons->sort2 $tblWeapons->sortorder
	");

	$numitems = $db->num_rows($result);
	if ($numitems > 0) {
		printSectionTitle('Weapon Usage *');
		$tblWeapons->draw($result, $numitems, 95); ?>
		<br /><br />
<?php
	}
?>

<!-- Begin of StatsMe Addon 1.0 by JustinHoMi@aol.com -->
<?php
	flush();

	$tblWeaponstats = new Table
	(
		array
		(
			new TableColumn
			(
				'smweapon',
				'Weapon',
				'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&amp;weapon=%k&amp;game=$game"),
				$fname
			),
			new TableColumn
			(
				'smshots',
				'Shots',
				'width=8&align=right'
			),
			new TableColumn
			(
				'smhits',
				'Hits',
				'width=8&align=right'
			),
			new TableColumn
			(
				'smdamage',
				'Damage',
				'width=8&align=right'
			),
			new TableColumn
			(
				'smheadshots',
				'Headshots',
				'width=8&align=right'
			),
			new TableColumn
			(
				'smkills',
				'Kills',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smkdr',
				'K:D',
				'width=12&align=right'
			),
			new TableColumn
			(
				'smaccuracy',
				'Accuracy',
				'width=8&align=right&append=' . urlencode('%')
			),
			new TableColumn
			(
				'smdhr',
				'Damage per Hit',
				'width=10&align=right'
			),
			new TableColumn(
				'smspk',
				'Shots per Kill',
				'width=11&align=right'
			)
		),
		'smweapon',
		'smkills',
		'smweapon',
		true,
		9999,
		'weap_page',
		'weap_sort',
		'weap_sortorder',
		'tabweapons',
		'desc',
		true
	);

	$result = $db->query("
		SELECT
			hlstats_Events_Statsme.weapon AS smweapon,
			SUM(hlstats_Events_Statsme.kills) AS smkills,
			SUM(hlstats_Events_Statsme.hits) AS smhits,
			SUM(hlstats_Events_Statsme.shots) AS smshots,
			SUM(hlstats_Events_Statsme.headshots) AS smheadshots,
			SUM(hlstats_Events_Statsme.deaths) AS smdeaths,
			SUM(hlstats_Events_Statsme.damage) AS smdamage,
			ROUND((SUM(hlstats_Events_Statsme.damage) / (IF(SUM(hlstats_Events_Statsme.hits) = 0, 1, SUM(hlstats_Events_Statsme.hits) ))), 1) as smdhr,
			SUM(hlstats_Events_Statsme.kills) / IF((SUM(hlstats_Events_Statsme.deaths) = 0), 1, (SUM(hlstats_Events_Statsme.deaths))) AS smkdr,
			ROUND((SUM(hlstats_Events_Statsme.hits) / SUM(hlstats_Events_Statsme.shots) * 100), 1) AS smaccuracy,
			ROUND(((IF(SUM(hlstats_Events_Statsme.kills) = 0, 0, SUM(hlstats_Events_Statsme.shots))) / (IF(SUM(hlstats_Events_Statsme.kills) = 0, 1, SUM(hlstats_Events_Statsme.kills) ))), 1) as smspk
		FROM
			hlstats_Events_Statsme
		WHERE
			hlstats_Events_Statsme.PlayerId = $player
		GROUP BY
			hlstats_Events_Statsme.weapon
		HAVING
			SUM(hlstats_Events_Statsme.shots) > 0
		ORDER BY
			$tblWeaponstats->sort $tblWeaponstats->sortorder,
			$tblWeaponstats->sort2 $tblWeaponstats->sortorder
	");

	$numitems = $db->num_rows($result);
	if ($numitems > 0) {
		printSectionTitle('Weapon Statistics *');
		$tblWeaponstats->draw($result, $numitems, 95); ?>
		<br /><br />
<!-- End of StatsMe Addon 1.0 by JustinHoMi@aol.com -->

<?php
	}
	flush();
	$tblWeaponstats2 = new Table
	(
		array(
			new TableColumn
			(
				'smweapon',
				'Weapon',
				'width=15&type=weaponimg&align=center&link=' . urlencode("mode=weaponinfo&amp;weapon=%k&amp;game=$game"),
				$fname
			),
			new TableColumn
			(
				'smhits',
				'Hits',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smhead',
				'Head',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smchest',
				'Chest',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smstomach',
				'Stomach',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smleftarm',
				'Left Arm',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smrightarm',
				'Right Arm',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smleftleg',
				'Left Leg',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smrightleg',
				'Right Leg',
				'width=7&align=right'
			),
			new TableColumn
			(
				'smleft',
				'Left',
				'width=8&align=right&append=' . urlencode('%')
			),
			new TableColumn
			(
				'smmiddle',
				'Middle',
				'width=8&align=right&append=' . urlencode('%')
			),
			new TableColumn
			(
				'smright',
				'Right',
				'width=8&align=right&append=' . urlencode('%')
			)
		),
		'smweapon',
		'smhits',
		'smweapon',
		true,
		9999,
		'weap_page',
		'weap_sort',
		'weap_sortorder',
		'weaponstats2',
		'desc',
		true
		);
	$query = "
		SELECT
			hlstats_Events_Statsme2.weapon AS smweapon,
			SUM(hlstats_Events_Statsme2.head) AS smhead,
			SUM(hlstats_Events_Statsme2.chest) AS smchest,
			SUM(hlstats_Events_Statsme2.stomach) AS smstomach,
			SUM(hlstats_Events_Statsme2.leftarm) AS smleftarm,
			SUM(hlstats_Events_Statsme2.rightarm) AS smrightarm,
			SUM(hlstats_Events_Statsme2.leftleg) AS smleftleg,
			SUM(hlstats_Events_Statsme2.rightleg) AS smrightleg,
			SUM(hlstats_Events_Statsme2.head)
				+ SUM(hlstats_Events_Statsme2.chest)
				+ SUM(hlstats_Events_Statsme2.stomach)
				+ SUM(hlstats_Events_Statsme2.leftarm)
				+ SUM(hlstats_Events_Statsme2.rightarm)
				+ SUM(hlstats_Events_Statsme2.leftleg)
				+ SUM(hlstats_Events_Statsme2.rightleg) AS smhits,
			IFNULL(ROUND((SUM(hlstats_Events_Statsme2.leftarm) + SUM(hlstats_Events_Statsme2.leftleg)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smleft,
			IFNULL(ROUND((SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.rightleg)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smright,
			IFNULL(ROUND((SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach)) / (SUM(hlstats_Events_Statsme2.head) + SUM(hlstats_Events_Statsme2.chest) + SUM(hlstats_Events_Statsme2.stomach) + SUM(hlstats_Events_Statsme2.leftarm ) + SUM(hlstats_Events_Statsme2.rightarm) + SUM(hlstats_Events_Statsme2.leftleg) + SUM(hlstats_Events_Statsme2.rightleg)) * 100, 1), 0.0) AS smmiddle
		FROM
			hlstats_Events_Statsme2
		WHERE
			hlstats_Events_Statsme2.PlayerId = $player
		GROUP BY
			hlstats_Events_Statsme2.weapon
		HAVING
			smhits > 0
		ORDER BY
			$tblWeaponstats2->sort $tblWeaponstats2->sortorder,
			$tblWeaponstats2->sort2 $tblWeaponstats2->sortorder
	";
	$result = $db->query($query);
	if ($db->num_rows($result) != 0)
	{
		printSectionTitle('Weapon Targets *');
		$tblWeaponstats2->draw($result, $db->num_rows($result), 95);
?>
	<br /><br />

<?php
	}
?>
