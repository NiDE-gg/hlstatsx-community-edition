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

	if (!defined('IN_HLSTATS')) {
		die('Do not access this file directly.');
	}

	// Details of a Discord server of the voice server list (?mode=discord&discordId=N):
	// counts, join link, voice channels with who is in them, online members.
	// Everything comes from DiscordService's cache, and everything from Discord is escaped.

	$discordId = (int)($_GET['discordId'] ?? 0);

	$db->query("
		SELECT
			serverId,
			name,
			addr,
			descr
		FROM
			hlstats_Servers_VoiceComm
		WHERE
			serverId = $discordId
			AND serverType = 2
	");
	$server = $db->fetch_array();

	pageHeader(
		array('Discord'),
		array('Discord' => '')
	);

	if (!$server) {
		error('No such Discord server.', false);
		return;
	}

	$status = $container->get(\Service\DiscordService::class)->getServer((string)$server['addr']);
	$data = $status['data'];
	$error = $status['error'];

	$statusColors = array('online' => '#23a55a', 'idle' => '#f0b232', 'dnd' => '#f23f43');

	$avatarHtml = function (?string $url, int $size) : string {
		if (!$url) {
			return '';
		}
		return '<img src="' . eHtml($url) . '" width="' . $size . '" height="' . $size . '" alt="" loading="lazy" style="border-radius:50%;vertical-align:middle;" /> ';
	};

	$userHtml = function (array $user) use ($statusColors, $avatarHtml) : string {
		$color = $statusColors[$user['status']] ?? $statusColors['online'];
		return '<span style="display:inline-block;margin:2px 12px 2px 0;white-space:nowrap;">'
			. $avatarHtml($user['avatar'] ?? null, 20)
			. '<span title="' . eHtml($user['status']) . '" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $color . ';vertical-align:middle;"></span> '
			. eHtml($user['name'])
			. '</span>';
	};

	$errorMessages = array(
		\Service\DiscordService::ERROR_INVALID_ADDRESS => 'The address of this server is not a Discord invite link or server ID. An admin can fix it in Manage Voice Servers.',
		\Service\DiscordService::ERROR_UNKNOWN_INVITE => 'The invite of this server is invalid or has expired.',
		\Service\DiscordService::ERROR_UNKNOWN_GUILD => 'Discord does not know this server ID.',
		\Service\DiscordService::ERROR_WIDGET_DISABLED => 'This server is set up with its server ID, which needs the server widget: turn on Server Settings → Widget → Enable Server Widget in Discord, or use an invite link instead.',
		\Service\DiscordService::ERROR_RATE_LIMITED => 'Discord is busy right now. Please try again in a few minutes.',
		\Service\DiscordService::ERROR_UNAVAILABLE => 'Discord could not be reached. Please try again in a few minutes.',
	);
?>
<div class="block">
	<?php printSectionTitle('Discord: ' . $server['name']); ?>
	<div class="subblock">
<?php
	if ($data === null) {
		echo '<p>' . eHtml($errorMessages[$error] ?? 'The server information is being loaded. Please reload the page in a moment.') . '</p>';
	} else {
		$invite = $data['invite'] ?? null;
?>
		<table class="data-table">
			<tr class="data-table-head">
				<td colspan="2">Server</td>
			</tr>
			<tr class="bg1">
				<td style="width:30%;">Name</td>
				<td><?php echo $avatarHtml($data['icon'] ?? null, 32) . '<strong>' . eHtml($data['name'] !== '' ? $data['name'] : $server['name']) . '</strong>'; ?></td>
			</tr>
			<tr class="bg2">
				<td>Online</td>
				<td><?php echo $data['online'] !== null ? number_format((int)$data['online']) : '-'; ?></td>
			</tr>
			<tr class="bg1">
				<td>Members</td>
				<td><?php echo $data['members'] !== null ? number_format((int)$data['members']) : '-'; ?></td>
			</tr>
			<tr class="bg2">
				<td>Join</td>
				<td>
					<?php if ($invite) { ?>
					<a href="<?php echo eHtml($invite); ?>" target="_blank" rel="noopener noreferrer"><strong>Join on Discord</strong></a>
					(<?php echo eHtml(preg_replace('#^https://#', '', $invite)); ?>)
					<?php } else { ?>
					No invite link: set an invite channel in the server widget settings, or enter an invite link as the address.
					<?php } ?>
				</td>
			</tr>
<?php
		if (!empty($server['descr'])) {
?>
			<tr class="bg1">
				<td>Notes</td>
				<td><?php echo eHtml($server['descr']); ?></td>
			</tr>
<?php
		}
?>
		</table>
		<p class="fSmall">
<?php
		if ($error !== '') {
			echo eHtml(($errorMessages[$error] ?? 'Discord could not be reached.') . ' ');
		}
		$minutes = max(0, (int)floor((time() - (int)($data['time'] ?? time())) / 60));
		echo 'Updated ' . ($minutes === 0 ? 'less than a minute' : $minutes . ' minute' . ($minutes > 1 ? 's' : '')) . ' ago.';
?>
		</p>
	</div>
	<br />
<?php
		if (empty($data['widget'])) {
?>
	<div class="subblock">
		<p>Voice channels and online members are shown when the server widget is on (Discord: Server Settings → Widget → Enable Server Widget).</p>
	</div>
<?php
		} else {
			$byChannel = array();
			foreach ($data['users'] as $user) {
				if (!empty($user['channel'])) {
					$byChannel[$user['channel']][] = $user;
				}
			}

			printSectionTitle('Voice Channels');
?>
	<div class="subblock">
		<table class="data-table">
			<tr class="data-table-head">
				<td style="width:30%;">Channel</td>
				<td>In the channel</td>
			</tr>
<?php
			if (empty($data['channels'])) {
				echo '<tr class="bg1"><td colspan="2">The widget shows no voice channel.</td></tr>';
			}
			$i = 0;
			foreach ($data['channels'] as $channel) {
				$c = ($i++ % 2) + 1;
				$inChannel = $byChannel[$channel['id']] ?? array();
				echo '<tr class="bg' . $c . '"><td>&#128266; ' . eHtml($channel['name']) . '</td><td>'
					. ($inChannel ? implode('', array_map($userHtml, $inChannel)) : '-')
					. "</td></tr>\n";
			}
?>
		</table>
	</div>
	<br />
<?php
			printSectionTitle('Online Members');
?>
	<div class="subblock">
<?php
			if (empty($data['users'])) {
				echo '<p>Nobody is online.</p>';
			} else {
				echo '<div>' . implode('', array_map($userHtml, $data['users'])) . '</div>';
				if (($data['online'] ?? 0) > count($data['users'])) {
					echo '<p class="fSmall">Discord lists at most ' . count($data['users']) . ' of the ' . number_format((int)$data['online']) . ' online members.</p>';
				}
			}
?>
	</div>
<?php
		}
	}
?>
</div>
