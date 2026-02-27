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

	global $scripttime, $db, $mode, $redirect_to_game;

	// calculate the scripttime
	$scripttime = round(microtime(true) - $scripttime, 4);

	$footerImage = eHtml(IMAGE_PATH . "/footer-small.png");
	$versionStats = eHtml($g_options['version']);
	$debugText = eHtml("Executed {$db->querycount} queries, generated this page in {$scripttime} Seconds");
	$scriptUrl = eHtml($g_options['scripturl']);

	$mapFile = INCLUDE_PATH . '/google_maps.php';
	$enableGoogleMaps = (
		$g_options["show_google_map"] == 1
		&& ($mode == "contents")
		&& ($redirect_to_game > 0)
		&& file_exists($mapFile)
	);

	if ($enableGoogleMaps) {
		include($mapFile);
	}
?>

<!-- html header here -->
	<!-- body here -->
		<!-- div body here? -->

			<div style="clear:both;"></div>
			<br><br>

			<div id="footer">
				<a href="http://www.hlxce.com" target="_blank">
					<img src="<?=$footerImage;?>" alt="HLstatsX Community Edition" border="0">
				</a>
			</div>
			<br>

			<div class="fSmall" style="text-align:center;">
					<?php if (isset($_SESSION['nojs']) && $_SESSION['nojs'] == 1) : ?>
						You are currently viewing the basic version of this page, please enable JavaScript and reload the page to access full functionality.
						<br>
					<?php endif; ?>

					Generated in real-time by
					<a href="http://www.hlxce.com" target="_blank">
						HLstatsX Community Edition <?=$versionStats;?>
					</a>

					<?php if ($g_options['showqueries'] == 1) : ?>
						<br>
						<?=$debugText;?>
					<?php endif; ?>
				<br>
				All images are copyrighted by their respective owners.
				<br><br>
				[<a href="<?=$scriptUrl;?>?mode=admin">Admin</a>]

				<?php if (isset($_SESSION['loggedin'])) : ?>
					&nbsp;[<a href="hlstats.php?logout=1">Logout</a>]
				<?php endif; ?>
			</div>

		</div>

		<?php if ($enableGoogleMaps && function_exists('printMap')) : ?>
			<?php printMap(); ?>
		<?php endif; ?>
	</body>
</html>