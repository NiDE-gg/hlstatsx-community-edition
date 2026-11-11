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

define('IN_HLSTATS', true);
require('config.php');

define('TITLE_IMAGE', IMAGE_PATH . "/downarrow.gif");

// Refuse session ids this server did not hand out
ini_set('session.use_strict_mode', '1');

// An admin session stays valid for an hour without activity (Auth::SESSION_TIMEOUT).
// PHP's default is to delete session files after 24 minutes, which ended them early.
if ((int) ini_get('session.gc_maxlifetime') < 3600) {
	ini_set('session.gc_maxlifetime', '3600');
}

session_set_cookie_params([
	'httponly' => true,
	'samesite' => 'Lax',
	'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// Logout is a POST carrying the CSRF token, so another site can't log the admin out
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['logout'] ?? '') === '1') {
	require_once(INCLUDE_PATH . '/class_auth.php');
	if (Auth::checkCsrf()) {
		Auth::logout();
		session_regenerate_id(true);
	}
	header('Location: ' . basename($_SERVER['SCRIPT_NAME']));
	die;
}

// Several stuff added by Malte Bayer
global $scripttime, $siteurlneo;
$scripttime = microtime(true);
$siteurlneo=((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://').preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')).rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/').'/';

// Several Stuff end

hlxFilterServerVars();

header('Content-Type: text/html; charset=utf-8');

////
//// Initialisation
////

define('PAGE', 'HLSTATS');

///
/// Classes
///

// Load required files
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/class_table.php');
require(INCLUDE_PATH . '/functions.php');

$db_classname = 'DB_' . DB_TYPE;
if ( class_exists($db_classname) )
{
	$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
}
else
{
	error('Database class does not exist.  Please check your config.php file for DB_TYPE');
}

$container = require ROOT_PATH . '/bootstrap.php';
$optionService = $container->get(\Service\OptionService::class);

$g_options = $optionService->getAllOptions();
if (empty($g_options)) {
	error('Warning: Could not find any options in the database. Check HLStats configuration.');
}

////
//// Main
////

$game = valid_game_code($_GET['game'] ?? '');

if (!$game)
{
	$game = valid_game_code($_SESSION['game'] ?? '');
}
else
{
	$_SESSION['game'] = $game;
}

// realgame is cached per game: switching games must not keep the previous one
$realgame = null;
if ($game && ($_SESSION['realgame_game'] ?? null) === $game)
{
	$realgame = $_SESSION['realgame'] ?? null;
}

if (!$realgame && $game)
{
	$gameRepo = $container->get(\Repository\GameRepository::class);
	$realgame = $gameRepo->getGameByCode($game, 'realgame');

	$_SESSION['realgame'] = $realgame;
	$_SESSION['realgame_game'] = $game;
}

$mode = (isset($_GET['mode']) && is_string($_GET['mode'])) ? $_GET['mode'] : '';

$valid_modes = array(
	'players',
	'clans',
	'weapons',
	'roles',
	'rolesinfo',
	'maps',
	'actions',
	'claninfo',
	'playerinfo',
	'weaponinfo',
	'mapinfo',
	'actioninfo',
	'playerhistory',
	'playersessions',
	'playerawards',
	'search',
	'admin',
	'help',
	'bans',
	'servers',
	'chathistory',
	'rankinfo',
	'ribboninfo',
	'chat',
	'awards',
	'dailyawardinfo',
	'countryclans',
	'countryclansinfo',
	'discord',
	'updater',
	'profile'
);
   
if ( !in_array($mode, $valid_modes, true) )
{
	$mode = 'contents';
}

// The Google map is only shown on the game page ("contents") and the clan page
sendPageSecurityHeaders(
	!in_array($mode, array('admin', 'chat', 'updater', 'profile'), true),
	in_array($mode, array('contents', 'claninfo'), true)
);

// The updater folder is deployed with the site, so only block pages while an update is pending
$updatePending = version_compare($g_options['version'], '1.6.2', '<')
	|| file_exists('./updater/' . ((int) $g_options['dbversion'] + 1) . '.php');

if ($updatePending && file_exists('./updater') && $mode != 'updater')
{
	pageHeader(array('Update Notice'), array('Update Notice' => ''));
	echo "<div class=\"warning\">\n" . 
	"<span class=\"warning-heading\"><img src=\"".IMAGE_PATH."/warning.gif\" alt=\"Warning\"> Warning:</span><br />\n" .
	"<span class=\"warning-text\">The updater folder was detected in your web directory.<br />
	To perform a Database Update, please go to <strong><a href=\"{$g_options['scripturl']}?mode=updater\">HLX:CE Database Updater</a></strong> to perform the database update.</span>\n</div>";
	pageFooter();
	die();
}
   
if ( file_exists(PAGE_PATH . "/$mode.php") )
{
	include(PAGE_PATH . "/$mode.php");
	pageFooter();
}
else
{
	header('HTTP/1.1 404 File Not Found', false, 404);
	error('Page not found.');
	pageFooter();
}

?>