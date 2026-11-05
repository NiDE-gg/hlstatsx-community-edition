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

	if ($auth->userdata['acclevel'] < 80) {
        die ('Access denied!');
	}

	$edlist = new EditList('serverId', 'hlstats_Servers_VoiceComm', '', false);
	$edlist->columns[] = new EditListColumn('name', 'Server Name', 45, true, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('addr', 'Server IP or Hostname (Discord: invite link or server ID)', 20, true, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('password', 'Password', 20, false, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('UDPPort', 'UDP Port (TS only)', 6, false, 'text', '8767', 64);
	$edlist->columns[] = new EditListColumn('queryPort', 'Query Port (TS)/Connect Port (Vent)', 6, true, 'text', '51234', 64);
	$edlist->columns[] = new EditListColumn('descr', 'Notes', 40, false, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('serverType', 'Server Type', 20, true, 'select', '0/Teamspeak;1/Ventrilo;2/Discord');

	if ($_POST) {
		if ($edlist->update())
			message('success', 'Operation successful.');
		else
			message('warning', $edlist->error());

		// Tell the admin right away about a Discord address that can never work
		$discordResult = $db->query("SELECT name, addr FROM hlstats_Servers_VoiceComm WHERE serverType = 2");
		while ($discordRow = $db->fetch_array($discordResult)) {
			if (\Service\DiscordService::parseAddress((string)$discordRow['addr']) === null) {
				message('warning', 'Discord server "' . eHtml($discordRow['name']) . '": the address must be an invite link (https://discord.gg/...) or a server ID.');
			}
		}
	}

?>
<p>
	<strong>Discord</strong>: enter an invite link (<code>https://discord.gg/abc</code>, ideally one that never expires) or the server ID.
	The ports and the password are not used. The member and online counts come from the invite.
	To also show voice channels and online members, turn on <em>Server Settings &rarr; Widget &rarr; Enable Server Widget</em> in Discord;
	with only a server ID, the widget is required and the join link is the widget's invite channel.
	Discord data is refreshed every 5 minutes.
</p>

<?php
	
	$result = $db->query("
		SELECT
			serverId,
			name,
			addr,
			password,
			UDPPort,
			queryPort,
			descr,
			serverType
		FROM
			hlstats_Servers_VoiceComm
		ORDER BY
			serverType,
			name
	");
	
	$edlist->draw($result);
?>

<table width="75%" border="0" cellspacing="0" cellpadding="0">
<tr>
	<td align="center"><input type="submit" value="  Apply  " class="submit"></td>
</tr>
</table>

