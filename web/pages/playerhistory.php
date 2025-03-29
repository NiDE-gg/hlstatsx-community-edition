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
	$player = valid_request(intval($_GET['player']), true) or error('No player ID specified.');

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

    if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

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
// This would be better done with a UNION query, I think, but MySQL doesn't
// support them yet. (NOTE you need MySQL 3.23 for temporary table support.)
	$db->query("DROP TABLE IF EXISTS hlstats_EventHistory");

	$sql_create_temp_table = "
		CREATE TEMPORARY TABLE hlstats_EventHistory
		(
			eventType VARCHAR(32) NOT NULL,
			eventTime DATETIME NOT NULL,
			eventDesc VARCHAR(255) NOT NULL,
			serverName VARCHAR(255) NOT NULL,
			map VARCHAR(64) NOT NULL,
			INDEX idx_event_time (eventTime)
		) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
	";

	$db->query($sql_create_temp_table);

	function insertEvents ($table, $select)
	{
		global $db;
		$select = str_replace("<table>", "hlstats_Events_$table", $select);
		$db->query("
			INSERT INTO hlstats_EventHistory (eventType, eventTime, eventDesc, serverName, map)
			$select
		");
	}

	// Regrouper les insertions pour les événements de base
	$db->query("
		INSERT INTO hlstats_EventHistory (eventType, eventTime, eventDesc, serverName, map)
		SELECT 
			'Connect',
			eventTime,
			'I connected to the server',
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Connects
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Connects.serverId
		WHERE playerId = $player
		UNION ALL
		SELECT 
			'Disconnect',
			eventTime,
			'I left the game',
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Disconnects
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Disconnects.serverId
		WHERE playerId = $player
		UNION ALL
		SELECT 
			'Entry',
			eventTime,
			'I entered the game',
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Entries
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Entries.serverId
		WHERE playerId = $player
	");

	// Optimiser les requêtes de frags avec une seule requête
	$db->query("
		INSERT INTO hlstats_EventHistory (eventType, eventTime, eventDesc, serverName, map)
		SELECT 
			'Kill',
			eventTime,
			CONCAT('I killed %A%$surl?mode=playerinfo&player=', victimId, '%', 
				   IFNULL(hlstats_Players.lastName,'Unknown'), '%/A%', 
				   CASE WHEN headshot = 1 THEN ' with a headshot from ' ELSE ' with ' END,
				   weapon),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Frags
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Frags.serverId
		LEFT JOIN hlstats_Players ON hlstats_Players.playerId = hlstats_Events_Frags.victimId
		WHERE killerId = $player
		UNION ALL
		SELECT 
			'Death',
			eventTime,
			CONCAT('%A%$surl?mode=playerinfo&player=', killerId, '%', 
				   IFNULL(hlstats_Players.lastName,'Unknown'), '%/A%', ' killed me with ', weapon),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Frags
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Frags.serverId
		LEFT JOIN hlstats_Players ON hlstats_Players.playerId = hlstats_Events_Frags.killerId
		WHERE victimId = $player
	");

	// Optimiser les requêtes de teamkills
	$db->query("
		INSERT INTO hlstats_EventHistory (eventType, eventTime, eventDesc, serverName, map)
		SELECT 
			'Team Kill',
			eventTime,
			CONCAT('I killed teammate %A%$surl?mode=playerinfo&player=', victimId, '%', 
				   IFNULL(hlstats_Players.lastName,'Unknown'), '%/A%', ' with ', weapon),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Teamkills
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Teamkills.serverId
		LEFT JOIN hlstats_Players ON hlstats_Players.playerId = hlstats_Events_Teamkills.victimId
		WHERE killerId = $player
		UNION ALL
		SELECT 
			'Friendly Fire',
			eventTime,
			CONCAT('My teammate %A%$surl?mode=playerinfo&player=', killerId, '%', 
				   IFNULL(hlstats_Players.lastName, 'Unknown'), '%/A%', ' killed me with ', weapon),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_Teamkills
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_Teamkills.serverId
		LEFT JOIN hlstats_Players ON hlstats_Players.playerId = hlstats_Events_Teamkills.killerId
		WHERE victimId = $player
	");

	// Optimiser les requêtes d'actions
	$db->query("
		INSERT INTO hlstats_EventHistory (eventType, eventTime, eventDesc, serverName, map)
		SELECT 
			'Action',
			eventTime,
			CONCAT('I received a points bonus of ', bonus, ' for triggering \"', 
				   IFNULL(hlstats_Actions.description,'Unknown'), '\"'),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_PlayerActions
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_PlayerActions.serverId
		LEFT JOIN hlstats_Actions ON hlstats_Actions.id = hlstats_Events_PlayerActions.actionId
		WHERE playerId = $player AND hlstats_Actions.game = '$game'
		UNION ALL
		SELECT 
			'Action',
			eventTime,
			CONCAT('I received a points bonus of ', bonus, ' for triggering \"', 
				   IFNULL(hlstats_Actions.description,'Unknown'), '\" against %A%$surl?mode=playerinfo&player=', 
				   victimId, '%', IFNULL(hlstats_Players.lastName,'Unknown'), '%/A%'),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			map
		FROM hlstats_Events_PlayerPlayerActions
		LEFT JOIN hlstats_Servers ON hlstats_Servers.serverId = hlstats_Events_PlayerPlayerActions.serverId
		LEFT JOIN hlstats_Actions ON hlstats_Actions.id = hlstats_Events_PlayerPlayerActions.actionId
		LEFT JOIN hlstats_Players ON hlstats_Players.playerId = hlstats_Events_PlayerPlayerActions.victimId
		WHERE playerId = $player AND hlstats_Actions.game = '$game'
	");

	$result = $db->query
	("
		SELECT
			hlstats_EventHistory.eventTime,
			hlstats_EventHistory.eventType,
			hlstats_EventHistory.eventDesc,
			hlstats_EventHistory.serverName,
			hlstats_EventHistory.map
		FROM
			hlstats_EventHistory
		ORDER BY
			$table->sort $table->sortorder,
			$table->sort2 $table->sortorder
		LIMIT
			$table->startitem,
			$table->numperpage
	");
	$resultCount = $db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_EventHistory
	");
	list($numitems) = $db->fetch_row($resultCount);
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
