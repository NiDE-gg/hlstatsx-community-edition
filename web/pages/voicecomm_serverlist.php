<?php
	if (!defined('IN_HLSTATS')) {
		die('Do not access this file directly.');
	}

	// VOICECOMM MODULE
	global $db, $container;
	
	// game.php and contents.php both include this file
	if (!defined('TS')) {
		define('TS', 0);
		define('VENT', 1);
		define('DISCORD', 2);
	}
	
	// contents.php shows the Discord servers in its games table instead
	$voicecommWhere = !empty($voicecommSkipDiscord) ? 'WHERE serverType <> ' . DISCORD : '';

	$result = $db->query("
		SELECT
			serverId,
			name,
			addr,
			password,
			descr,
			queryPort,
			UDPPort,
			serverType
		FROM
			hlstats_Servers_VoiceComm
		$voicecommWhere
		ORDER BY
			serverType,
			serverId
        ");
  
	if ($db->num_rows($result) >= 1) {
		printSectionTitle('Voice Server');
?>
	<div class="subblock">
		<table class="data-table">
			<tr class="data-table-head">
				<td class="fSmall">Server Name</td>
				<td class="fSmall">Server Address</td>
				<td class="fSmall">Password</td>
				<td class="fSmall" style="text-align:right;">Channels</td>
				<td class="fSmall" style="text-align:right;">Slots&nbsp;used</td>
				<td class="fSmall">Notes</td>
			</tr> 
<?php
		$i = 0;
		$j = 0;
		while ($row = $db->fetch_array()) {
			if ($row['serverType'] == TS) {
				$ts_servers[$i]['serverId'] = $row['serverId'];
				$ts_servers[$i]['name'] = $row['name'];
				$ts_servers[$i]['addr'] = $row['addr'];
				$ts_servers[$i]['password'] = $row['password'];
				$ts_servers[$i]['descr'] = $row['descr'];
				$ts_servers[$i]['queryPort'] = $row['queryPort'];
				$ts_servers[$i]['UDPPort'] = $row['UDPPort'];
				$i++;
			} else if ($row['serverType'] == VENT) {
				$vent_servers[$j]['serverId'] = $row['serverId'];
				$vent_servers[$j]['name'] = $row['name'];
				$vent_servers[$j]['addr'] = $row['addr'];
				$vent_servers[$j]['password'] = $row['password'];
				$vent_servers[$j]['descr'] = $row['descr'];
				$vent_servers[$j]['queryPort'] = $row['queryPort'];
				$j++;
			} else if ($row['serverType'] == DISCORD) {
				$discord_servers[] = $row;
			}
		}
		// TeamSpeak and Ventrilo servers are listed with their connect link only. Their
		// live viewers were removed: they spoke the TeamSpeak 2 and Ventrilo 3 status
		// protocols, queried the server on every page view, and had been a fatal error
		// since PHP 8 (one Ventrilo row cut the whole game page short).
		$static_servers = array();
		foreach ($ts_servers ?? array() as $ts_server) {
			$static_servers[] = array(
				'icon' => '/teamspeak/teamspeak.gif',
				'name' => trim((string) $ts_server['name']),
				'link' => 'teamspeak://' . $ts_server['addr'] . ':' . $ts_server['UDPPort'] . '/?channel=?password=' . rawurlencode((string) $ts_server['password']),
				'addr' => $ts_server['addr'] . ':' . $ts_server['UDPPort'],
				'password' => $ts_server['password'],
				'descr' => $ts_server['descr'],
			);
		}
		foreach ($vent_servers ?? array() as $vent_server) {
			$static_servers[] = array(
				'icon' => '/ventrilo/ventrilo.png',
				'name' => trim((string) $vent_server['name']),
				'link' => 'ventrilo://' . $vent_server['addr'] . ':' . $vent_server['queryPort'] . '/servername=' . rawurlencode((string) $vent_server['name']),
				'addr' => $vent_server['addr'] . ':' . $vent_server['queryPort'],
				'password' => $vent_server['password'],
				'descr' => $vent_server['descr'],
			);
		}
		foreach ($static_servers as $static_server)
		{
?>
        <tr class="bg1">
			<td class="fHeading">
				<img src="<?php echo IMAGE_PATH . $static_server['icon']; ?>" alt="" />
				&nbsp;<?php echo eHtml($static_server['name']); ?>
			</td>
			<td>
				<a href="<?php echo eHtml($static_server['link']); ?>"><?php echo eHtml($static_server['addr']); ?></a>
			</td>
			<td>
				<?php echo eHtml($static_server['password']); ?>
			</td>
			<td style="text-align:right;">-</td>
			<td style="text-align:right;">-</td>
			<td>
				<?php echo eHtml($static_server['descr']); ?>
			</td>
		</tr>
<?php
		}
		if (isset($discord_servers))
		{
			// Cached by DiscordService: no call to discord.com on most page views
			$discordService = $container->get(\Service\DiscordService::class);
			foreach ($discord_servers as $dc_server)
			{
				$dc = $discordService->getServer((string)$dc_server['addr']);
				$dcData = $dc['data'];
				$dcInvite = $dcData['invite'] ?? null;
				if ($dcData === null) {
					$dc_channels = 'err';
					$dc_slots = \Service\DiscordService::errorText($dc['error']);
				} else {
					// Voice channels are only known with the server widget on
					$dc_channels = $dcData['widget'] ? count($dcData['channels']) : '-';
					$dc_slots = ($dcData['online'] ?? '?') . ($dcData['members'] !== null ? '/' . $dcData['members'] : ' online');
				}
?>
			<tr class="bg1">
				<td class="fHeading">
					<img src="<?php echo IMAGE_PATH; ?>/discord.png" alt="Discord" />
					&nbsp;<a href="<?php echo $g_options['scripturl'] . '?mode=discord&amp;game=' . urlencode((string)$game) . '&amp;discordId=' . (int)$dc_server['serverId']; ?>"><?php echo eHtml(trim($dc_server['name'])); ?></a>
				</td>
				<td>
					<?php if ($dcInvite) { ?>
					<a href="<?php echo eHtml($dcInvite); ?>" target="_blank" rel="noopener noreferrer"><?php echo eHtml(preg_replace('#^https://#', '', $dcInvite)); ?></a>
					<?php } else { ?>-<?php } ?>
				</td>
				<td></td>
				<td style="text-align:right;">
					<?php echo eHtml($dc_channels); ?>
				</td>
				<td style="text-align:right;">
					<?php echo eHtml($dc_slots); ?>
				</td>
				<td>
					<?php echo eHtml($dc_server['descr']); ?>
				</td>
			</tr>
<?php
			}
		}
?>
    </table>
	</div>
<br /><br />
<?php
	}
	// VOICECOMM MODULE END
?>