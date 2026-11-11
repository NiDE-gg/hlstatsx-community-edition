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

Originally idea for sig.php by Tankster
*/

define('IN_HLSTATS', true);

// Load database classes
require ('config.php');
hlxFilterServerVars();
header("Content-Type: image/png");
require (INCLUDE_PATH . '/class_db.php');
require (INCLUDE_PATH . '/functions.php');

$container = require ROOT_PATH . '/bootstrap.php';
$playerRepo = $container->get(\Repository\PlayerRepository::class);
$optionService = $container->get(\Service\OptionService::class);

$db_classname = 'DB_' . DB_TYPE;
if (class_exists($db_classname))
{
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$g_options = $optionService->getAllOptions();
if (empty($g_options)) {
	error('Warning: Could not find any options in the database. Check HLStats configuration.');
}


function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
	$opacity=$pct;
	// getting the watermark width
	$w = imagesx($src_im);
	// getting the watermark height
	$h = imagesy($src_im);
	 
	// creating a cut resource
	$cut = imagecreatetruecolor($src_w, $src_h);
	// copying that section of the background to the cut
	imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
	// inverting the opacity
	$opacity = 100 - $opacity;
	 
	// placing the watermark now
	imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
	imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
}

// This script answers with an image: the HTML error box of error() would be sent
// as a broken PNG
function sigError($message)
{
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	exit($message);
}

	$realgame = '';
	$player_id = 0;  
	if (isset($_GET['player_id'])) {
		$player_id = valid_request($_GET['player_id'], true);
	} elseif (isset($_GET['steam_id']) && isset($_GET['game'])) {
		$steam_id = valid_request($_GET['steam_id'], false);
		$steam_id = preg_replace('/^STEAM_\d+?\:/i','',$steam_id);
		$game = valid_game_code($_GET['game']);

		$steam_id_escaped=$db->escape($steam_id);
		$game_escaped=$db->escape($game);
		
		// Obtain realgame from hlstats_Games
		$db->query("
			SELECT
				realgame
			FROM
				hlstats_Games
			WHERE
				code = '$game_escaped'
		");
		list($realgame) = $db->fetch_row() ?: array('');
		
		// Obtain player_id from the steam_id and game code
		$db->query("
			SELECT
				playerId
			FROM
				hlstats_PlayerUniqueIds
			WHERE
				uniqueId = '{$steam_id_escaped}' AND
				game = '{$game_escaped}'
		");
		
		if ($db->num_rows() != 1)
			sigError('No such player.');
		list($player_id) = $db->fetch_row();
	}
	
	$show_flags = $g_options['countrydata'];
	if (isset($_GET['show_flags']) && is_numeric($_GET['show_flags'])) {
		$show_flags = valid_request($_GET['show_flags'], true);
	}

	////
	//// Main
	////

// Optional colour overrides (RRGGBB), applied on top of the background preset below
$user_color = tryHexToRgb(is_string($_GET['color'] ?? null) ? $_GET['color'] : '');
$user_caption_color = tryHexToRgb(is_string($_GET['caption_color'] ?? null) ? $_GET['caption_color'] : '');
$user_link_color = tryHexToRgb(is_string($_GET['link_color'] ?? null) ? $_GET['link_color'] : '');

if ($player_id > 0) {
	$db->query("
		SELECT
			playerId, 
			game, 
			FROM_UNIXTIME((last_event), '%a %D %b %H:%i') as lastevent, 
			connection_time,
			last_skill_change,
                        unhex(replace(hex(lastName), 'E280AE', '')) as lastName,
			country,
			flag,
			kills, 
			deaths, 
			suicides, 
			skill, 
			shots, 
			hits, 
			headshots, IFNULL(ROUND(headshots/kills * 100), '-') AS hpk, 
			IFNULL(kills/deaths, '-') AS kpd, 
			IFNULL(ROUND((hits / shots * 100), 1), 0.0) AS acc, 
			activity, 
			hideranking
		FROM 
			hlstats_Players
		WHERE 
			playerId='$player_id'
	");
	if ($db->num_rows() != 1)
		sigError('No such player.');

	$playerdata = $db->fetch_array();
	$db->free_result();

	if ($realgame === '') {
		// player_id requests carry no game: the fallback backgrounds are those of the realgame
		$realgame = (string) $container->get(\Repository\GameRepository::class)->getGameByCode($playerdata['game'], 'realgame');
	}

	$pl_name = $playerdata['lastName'];
    
	if(function_exists('imagettftext')) {
		$pl_name = shortName($pl_name, 30, 27);
	} else {
		if (mb_strlen($pl_name, 'UTF-8') > 30) {
			$pl_shortname =	shortName($pl_name, 30, 27);
		} else {
			$pl_shortname	= $pl_name;
			$pl_name		= htmlspecialchars($pl_name, ENT_COMPAT);
			$pl_shortname	= htmlspecialchars($pl_shortname, ENT_COMPAT);
			$pl_urlname		= urlencode($playerdata['lastName']);
		}
	}

	$db->query("
		SELECT
			COUNT(*) as count
		FROM
			hlstats_Players
		WHERE
			game='" . $db->escape($playerdata['game']) . "'");
	$pl_count = $db->fetch_array();
	$db->free_result();

	$rank = 'Unknown';

	if ($playerdata['activity'] > 0 && $playerdata['hideranking'] == 0) {
		$plGame = $playerdata['game'];
		$rankType = $g_options['rankingtype'];
		$plValue = $playerdata[$rankType];
		$plKills = $playerdata['kills'];
		$playerDeaths = $playerdata['deaths'];

		$rank = $playerRepo->getPlayerRank($plGame, $rankType, $plValue, $plKills, $playerDeaths);

		if (is_null($rank)) {
			$rank = 'Unknown';
		}
	} else {
		if ($playerdata['hideranking'] == 1) {
			$rank = 'Hidden';
		} elseif ($playerdata['hideranking'] == 2) {
			$rank = 'Banned';
		} else {
			$rank = 'Not active';
		}
	}

	if ($playerdata['activity'] == -1)
		$playerdata['activity'] = 0;

	$skill_change = '0';
	if ($playerdata['last_skill_change'] > 0)
		$skill_change = $playerdata['last_skill_change'];
	else if ($playerdata['last_skill_change'] < 0)
		$skill_change = $playerdata['last_skill_change'];  
	
	$background = 'random';
	if (isset($_GET['background']) && is_string($_GET['background'])) {
		// Becomes part of a file name: only the numbers of the shipped backgrounds
		$requestedBackground = filter_var($_GET['background'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 11)));
		if ($requestedBackground !== false) {
			$background = $requestedBackground;
		}
	}

	if ($background == 'random') {
		$background = rand(1, 11);
	}

	$hlx_sig_image = getImage('/games/'.$playerdata['game'].'/sig/'.$background);
	if ($hlx_sig_image)
	{
		$hlx_sig = $hlx_sig_image['path'];
	}
	elseif ($hlx_sig_image = getImage('/games/'.$realgame.'/sig/'.$background))
	{
		$hlx_sig = $hlx_sig_image['path'];
	}
	else
	{
		$hlx_sig = IMAGE_PATH."/sig/$background.png";
	}

	switch ($background) {
		case 1:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 2:		$caption_color = array('red' => 147, 'green' => 23, 'blue' => 18);
					$link_color = array('red' => 147, 'green' => 23, 'blue' => 18);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 3:		$caption_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$link_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 4:		$caption_color = array('red' => 255, 'green' => 203, 'blue' => 4);
					$link_color = array('red' => 255, 'green' => 203, 'blue' => 4);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 5:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 102, 'blue' => 204);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 6:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 0);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 7:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 100, 'green' => 100, 'blue' => 100);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 8:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 9:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 0, 'blue' => 0);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
		case 10:		$caption_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$link_color = array('red' => 255, 'green' => 255, 'blue' => 255);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		case 11:		$caption_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$link_color = array('red' => 150, 'green' => 180, 'blue' => 99);
					$color = array('red' => 255, 'green' => 255, 'blue' => 255);
					break;
		default:		$caption_color = array('red' => 0, 'green' => 0, 'blue' => 255);
					$link_color = array('red' => 0, 'green' => 155, 'blue' => 0);
					$color = array('red' => 0, 'green' => 0, 'blue' => 0);
					break;
}

	if ($user_color) $color = $user_color;
	if ($user_caption_color) $caption_color = $user_caption_color;
	if ($user_link_color) $link_color = $user_link_color;

	$image			= imagecreatetruecolor(400, 75);

        imagealphablending($image, false);
        imagesavealpha($image, true);

	$white			= imagecolorallocate($image, 255, 255, 255); 
	$bgray			= imagecolorallocate($image, 192, 192, 192); 
	$yellow		= imagecolorallocate($image, 255, 255,   0); 
	$black			= imagecolorallocate($image,   0,   0,   0); 
	$red			= imagecolorallocate($image, 255,   0,   0); 
	$green			= imagecolorallocate($image,   0, 155,   0); 
	$blue			= imagecolorallocate($image,   0,   0, 255); 
	$grey_shade		= imagecolorallocate($image, 204, 204, 204); 
	$font_color		= imagecolorallocate($image, $color['red'], $color['green'], $color['blue']);
	$caption_color	= imagecolorallocate($image, $caption_color['red'], $caption_color['green'], $caption_color['blue']);
	$link_color		= imagecolorallocate($image, $link_color['red'], $link_color['green'], $link_color['blue']);
	//$font_colorb		= imagecolorallocate($image, $colorb['red'], $colorb['green'], $colorb['blue']);
	//$caption_colorb	= imagecolorallocate($image, $caption_colorb['red'], $caption_colorb['green'], $caption_colorb['blue']);
	//$link_colorb		= imagecolorallocate($image, $link_colorb['red'], $link_colorb['green'], $link_colorb['blue']);


	$background_img = imagecreatefrompng($hlx_sig);



	if ($background_img) {
		imagecopy($image, $background_img, 0, 0, 0, 0, 400, 75);
	}   

	if ($background == 0)
		imagerectangle($image, 0, 0, 400, 75, $bgray);

	$start_header_name = 9;
	if ($show_flags > 0)  {
		$flag = imagecreatefromgif(getFlag($playerdata['flag'], 'path'));
		if ($flag) {
			imagecopy($image, $flag, 8, 4, 0, 0, 18, 12); 
			$start_header_name += 22;
		}
	}
        imagealphablending($image, true);
	$timestamp   = $playerdata['connection_time'];
	$days        = floor($timestamp / 86400);
	$hours       = $days * 24;   
	$hours       += floor($timestamp / 3600) % 24;
	if ($hours < 10)
		$hours = '0'.$hours;
	$min         = floor($timestamp / 60) % 60;
	if ($min < 10)
		$min = '0'.$min; 
	$sec         = floor($timestamp % 60);
	if ($sec < 10)
		$sec = '0'.$sec; 
	$con_time = $hours.':'.$min.':'.$sec;

	if ($playerdata['last_skill_change'] == '')
		$playerdata['last_skill_change'] = 0;
	if ($playerdata['last_skill_change'] == 0)
		$trend_image_name = IMAGE_PATH.'/t1.gif';
	elseif ($playerdata['last_skill_change'] > 0)
		$trend_image_name = IMAGE_PATH.'/t0.gif';
	elseif ($playerdata['last_skill_change'] < 0)
		$trend_image_name = IMAGE_PATH.'/t2.gif';
	$trend = imagecreatefromgif($trend_image_name);
    
	if(function_exists('imagettftext'))
	{
		// GD only finds a font given by its full path
		$font = ROOT_PATH . '/hlstatsimg/sig/font/DejaVuSans.ttf';
		imagettftext($image, 10, 0, 30, 15, $caption_color, $font, $pl_name);
	}
	else
	{
		imagestring($image, 9, $start_header_name, 2, $playerdata['lastName'], $caption_color);
	}

	imagestring($image, 2, 15, 22, 'Position ', $font_color);
	if (is_numeric($rank)) {
		imagestring($image, 3, 70, 22, number_format($rank), $font_color);
		$start_pos_x = 71 + (imagefontwidth(3) * strlen(number_format($rank))) + 7;
	} else {
		imagestring($image, 3, 70, 22, $rank, $font_color);
		$start_pos_x = 71 + (imagefontwidth(3) * strlen($rank)) + 7;
	}
	$ranktext = 'of '.$pl_count['count'].' players with '.$playerdata['skill'].' (';
	imagestring($image, 2, $start_pos_x, 22, $ranktext, $font_color);
	
	$start_pos_x += (imagefontwidth(2) * strlen($ranktext));
	
	if ($trend) {
		imagecopy($image, $trend, $start_pos_x, 26, 0, 0, 7, 7);
		$start_header_name += 22;
		$start_pos_x += 10;
	}
	imagestring($image, 2, $start_pos_x, 22, $skill_change.') points', $font_color);
	imagestring($image, 2,  15, 34, 'Frags: '.$playerdata['kills'].' kills : '.$playerdata['deaths'].' deaths ('.$playerdata['kpd'].'), '.$playerdata['headshots'].' headshots ('.$playerdata['hpk'].'%)', $font_color);
	imagestring($image, 2,  15, 45, 'Activity: '.$playerdata['lastevent'].' ('.$playerdata['activity'].'%), Time: '.$con_time.' hours', $font_color);
	imagestring($image, 2,  15, 56, 'Statistics: ', $font_color);imagestring($image, 2,  85, 56, $g_options['siteurl'], $link_color);

	$watermark = imagecreatefrompng(IMAGE_PATH.'/watermark.png');
	imagecopymerge_alpha($image, $watermark, 334, 58, 0, 0, 60, 12, 50);
	
	// Signatures sit in forum threads and are requested on every page view there:
	// let browsers and proxies keep them for a while
	header('Cache-Control: public, max-age=' . (int) IMAGE_UPDATE_INTERVAL);
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

	imagepng($image);

} else {
	sigError('No player specified.');
}
