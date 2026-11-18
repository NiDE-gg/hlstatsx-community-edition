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

    // Player History
	$player = valid_request(intval($_GET['player'] ?? ''), true) or error('No player ID specified.');

	$db->query("
		SELECT
			hlstats_Players.lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			hlstats_Players.playerId = $player
	");

	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}

	$playerdata = $db->fetch_array();
	$pl_name = $playerdata['lastName'];

    $pl_shortname = shortName($pl_name);

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$game = $playerdata['game'];

    $db->query("
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");

	if ($db->num_rows() != 1) {
		$gamename = ucfirst($game);
	} else {
		list($gamename) = $db->fetch_row();
	}

	pageHeader
	(
		array ($gamename, 'Event History', $pl_name),
		array
		(
			$gamename=>$g_options['scripturl'] . "?game=$game",
			'Player Rankings'=>$g_options['scripturl'] . "?mode=players&game=$game",
			'Player Details'=>$g_options['scripturl'] . "?mode=playerinfo&player=$player",
			'Event History'=>''
		),
		$playername = ""
	);
	flush();
	$table = new Table
	(
		array
		(
			new TableColumn
			(
				'eventTime',
				'Date',
				'width=20'
			),
			new TableColumn
			(
				'eventType',
				'Type',
				'width=10&align=center'
			),
			new TableColumn
			(
				'eventDesc',
				'Description',
				'width=40&sort=no&append=.&embedlink=yes'
			),
			new TableColumn
			(
				'serverName',
				'Server',
				'width=20'
			),
			new TableColumn
			(
				'map',
				'Map',
				'width=10'
			)
		),
		'eventTime',
		'eventTime',
		'eventType',
		false,
		50,
		'page',
		'sort',
		'sortorder'
	);
	$surl = $g_options['scripturl'];
	// The page used to copy every event of the player (with the text of each line)
	// into a temporary table, then show 50 of them. Now a first query picks the
	// rows of this page from the player's events using only their time, type, map
	// and server, and the text is built for those rows only. The description is not
	// sortable, so the order only needs those columns.
	$game_sql = $db->escape($game);
	$player = (int) $player;
	$historySources = array(
		// kind => array(type shown, table, condition, extra joins for the text, text)
		'c' => array('Connect', 'Connects', "<t>.playerId = $player", '', "'I connected to the server'"),
		'd' => array('Disconnect', 'Disconnects', "<t>.playerId = $player", '', "'I left the game'"),
		'e' => array('Entry', 'Entries', "<t>.playerId = $player", '', "'I entered the game'"),
		'k' => array('Kill', 'Frags', "<t>.killerId = $player",
			'LEFT JOIN hlstats_Players ON hlstats_Players.playerId = <t>.victimId',
			"CONCAT('I killed %A%$surl?mode=playerinfo&player=', <t>.victimId, '%', IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%', CASE WHEN <t>.headshot = 1 THEN ' with a headshot from ' ELSE ' with ' END, <t>.weapon)"),
		'x' => array('Death', 'Frags', "<t>.victimId = $player",
			'LEFT JOIN hlstats_Players ON hlstats_Players.playerId = <t>.killerId',
			"CONCAT('%A%$surl?mode=playerinfo&player=', <t>.killerId, '%', IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%', ' killed me with ', <t>.weapon)"),
		't' => array('Team Kill', 'Teamkills', "<t>.killerId = $player",
			'LEFT JOIN hlstats_Players ON hlstats_Players.playerId = <t>.victimId',
			"CONCAT('I killed teammate %A%$surl?mode=playerinfo&player=', <t>.victimId, '%', IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%', ' with ', <t>.weapon)"),
		'f' => array('Friendly Fire', 'Teamkills', "<t>.victimId = $player",
			'LEFT JOIN hlstats_Players ON hlstats_Players.playerId = <t>.killerId',
			"CONCAT('My teammate %A%$surl?mode=playerinfo&player=', <t>.killerId, '%', IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%', ' killed me with ', <t>.weapon)"),
		'a' => array('Action', 'PlayerActions', "<t>.playerId = $player",
			'',
			"CONCAT('I received a points bonus of ', <t>.bonus, ' for triggering \"', IFNULL(hlstats_Actions.description, 'Unknown'), '\"')"),
		'p' => array('Action', 'PlayerPlayerActions', "<t>.playerId = $player",
			'LEFT JOIN hlstats_Players ON hlstats_Players.playerId = <t>.victimId',
			"CONCAT('I received a points bonus of ', <t>.bonus, ' for triggering \"', IFNULL(hlstats_Actions.description, 'Unknown'), '\" against %A%$surl?mode=playerinfo&player=', <t>.victimId, '%', IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%')"),
	);
	// Actions only count for this game (the same action code exists once per game)
	$historyActionJoin = "INNER JOIN hlstats_Actions ON hlstats_Actions.id = <t>.actionId AND hlstats_Actions.game = '$game_sql'";

	// Columns the table can be sorted by, all available before the text is built
	$historyOrder = array(
		'eventTime' => 'eventTime',
		'eventType' => 'eventType',
		'serverName' => 'serverName',
		'map' => 'map',
	);
	$historySort = ($historyOrder[$table->sort] ?? 'eventTime') . " $table->sortorder, "
		. ($historyOrder[$table->sort2] ?? 'eventTime') . " $table->sortorder";

	$historyBranches = array();
	$historyCounts = array();
	foreach ($historySources as $kind => $source) {
		$t = 'hlstats_Events_' . $source[1];
		$where = str_replace('<t>', $t, $source[2]);
		$actionJoin = ($kind === 'a' || $kind === 'p') ? str_replace('<t>', $t, $historyActionJoin) : '';
		$historyBranches[] = "SELECT '$kind' AS kind, $t.id, '{$source[0]}' AS eventType, $t.eventTime, $t.map, $t.serverId FROM $t $actionJoin WHERE $where";
		$historyCounts[] = "(SELECT COUNT(*) FROM $t $actionJoin WHERE $where)";
	}

	$resultCount = $db->query('SELECT ' . implode(' + ', $historyCounts));
	list($numitems) = $db->fetch_row($resultCount);
	$numitems = (int) $numitems;

	$pageRows = $db->query("
		SELECT
			events.kind,
			events.id,
			events.eventType,
			events.eventTime,
			events.map,
			IFNULL(hlstats_Servers.name, 'Unknown') AS serverName
		FROM
			(" . implode("\n			UNION ALL\n			", $historyBranches) . ") AS events
		LEFT JOIN
			hlstats_Servers ON hlstats_Servers.serverId = events.serverId
		ORDER BY
			$historySort
		LIMIT
			$table->startitem,
			$table->numperpage
	");
	$pageIds = array();
	while ($row = $db->fetch_array($pageRows)) {
		$pageIds[$row['kind']][] = (int) $row['id'];
	}
	$db->free_result($pageRows);

	// The text of this page's rows only, in the same order
	$detailBranches = array();
	foreach ($pageIds as $kind => $ids) {
		$source = $historySources[$kind];
		$t = 'hlstats_Events_' . $source[1];
		$joins = str_replace('<t>', $t, $source[3]) . (($kind === 'a' || $kind === 'p') ? ' ' . str_replace('<t>', $t, $historyActionJoin) : '');
		$detailBranches[] = "SELECT '{$source[0]}' AS eventType, $t.eventTime, " . str_replace('<t>', $t, $source[4]) . " AS eventDesc,"
			. " IFNULL(hlstats_Servers.name, 'Unknown') AS serverName, $t.map"
			. " FROM $t LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = $t.serverId $joins"
			. " WHERE $t.id IN (" . implode(',', $ids) . ')';
	}
	// A page past the last one has no rows: an empty result, which the table draws as such
	$result = $db->query($detailBranches
		? 'SELECT * FROM (' . implode("\n		UNION ALL\n		", $detailBranches) . ") AS events ORDER BY $historySort"
		: 'SELECT NULL AS eventType FROM DUAL WHERE 0');
?>

<div class="block">
<?php
	printSectionTitle('Player Event History (Last '.$g_options['DeleteDays'].' Days)');
	if ($numitems > 0)
	{
		$table->draw($result, $numitems, 95);
	}
?><br /><br />
	<div class="subblock">
		<div style="float:right;">
			Go to: <a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>"><?php echo $pl_name; ?>'s Statistics</a>
		</div>
	</div>
</div>
