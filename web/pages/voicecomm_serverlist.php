<?php
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
		if (isset($ts_servers))
		{
			require_once(PAGE_PATH . '/teamspeak_class.php');
			foreach($ts_servers as $ts_server)
			{
				$settings = $teamspeakDisplay->getDefaultSettings();
				$settings['serveraddress'] = $ts_server['addr'];
				$settings['serverqueryport'] = $ts_server['queryPort'];
				$settings['serverudpport'] = $ts_server['UDPPort'];
				$ts_info = $teamspeakDisplay->queryTeamspeakServerEx($settings);
				if ($ts_info['queryerror'] != 0) {
					$ts_channels = 'err';
					$ts_slots = $ts_info['queryerror'];
				} else {
					$ts_channels = count($ts_info['channellist']);
					$ts_slots = count($ts_info['playerlist']).'/'.$ts_info['serverinfo']['server_maxusers'];
				}
?>
        <tr class="bg1">
			<td class="fHeading">
				<img src="<?php echo IMAGE_PATH; ?>/teamspeak/teamspeak.gif" alt="tsicon" />
				&nbsp;<a href="<?php echo $g_options['scripturl'] . "?mode=teamspeak&amp;game=$game&amp;tsId=".$ts_server['serverId']; ?>"><?php echo eHtml(trim($ts_server['name'])); ?></a>
			</td>
			<td>
				<a href="<?php echo eHtml('teamspeak://' . $ts_server['addr'] . ':' . $ts_server['UDPPort'] . '/?channel=?password=' . rawurlencode((string)$ts_server['password'])); ?>"><?php echo eHtml($ts_server['addr'] . ':' . $ts_server['UDPPort']); ?></a>
			</td>
			<td>
				<?php echo eHtml($ts_server['password']); ?>
			</td>
			<td style="text-align:right;">
				<?php echo eHtml($ts_channels); ?>
			</td>
			<td style="text-align:right;">
				<?php echo eHtml($ts_slots); ?>
			</td>
			<td>
				<?php echo eHtml($ts_server['descr']); ?>
			</td>
		</tr>
<?php
			}
		}
		if (isset($vent_servers))
		{
			require_once(PAGE_PATH . '/ventrilostatus.php');
			foreach($vent_servers as $vent_server)
			{
				$ve_info = new CVentriloStatus;
				$ve_info->m_cmdcode	= 2;					// Detail mode.
				$ve_info->m_cmdhost = $vent_server['addr'];
				$ve_info->m_cmdport = $vent_server['queryPort'];
				/////////
				$rc = $ve_info->Request();
				if ($rc) {
					// Same display as an unreachable TeamSpeak server
					$ve_channels = 'err';
					$ve_slots = $rc;
				} else {
					$ve_channels = $ve_info->m_channelcount;
					$ve_slots = $ve_info->m_clientcount.'/'.$ve_info->m_maxclients;
				}
		?>  
			<tr class="bg1">
				<td class="fHeading">
					<img src="<?php echo IMAGE_PATH; ?>/ventrilo/ventrilo.png" alt="venticon" />
					&nbsp;<a href="<?php echo $g_options['scripturl'] . "?mode=ventrilo&amp;game=$game&amp;veId=".$vent_server['serverId']; ?>"><?php echo eHtml($vent_server['name']); ?></a>
				</td>
				<td>
					<a href="<?php echo eHtml('ventrilo://' . $vent_server['addr'] . ':' . $vent_server['queryPort'] . '/servername=' . rawurlencode((string)$ve_info->m_name)); ?>">
					<?php echo eHtml($vent_server['addr'] . ':' . $vent_server['queryPort']); ?>
					</a></td>
				<td>
					<?php echo eHtml($vent_server['password']); ?>
				</td>
				<td style="text-align:right;">
					<?php echo eHtml($ve_channels); ?>
				</td>
				<td style="text-align:right;">
					<?php echo eHtml($ve_slots); ?>
				</td>
				<td>
					<?php echo eHtml($vent_server['descr']); ?>
				</td>
			</tr>
<?php
			}
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