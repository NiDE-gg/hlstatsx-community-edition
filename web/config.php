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

// Every entry point loads this file first. PHP releases before 8.5.12 have critical
// remote code execution bugs (CVE-2026-109618, CVE-2026-109619), so refuse to run on them.
if (PHP_VERSION_ID < 80512) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	exit('HLstats requires PHP 8.5.12 or newer (this server runs PHP ' . PHP_VERSION . '). Older releases have critical security bugs: update PHP.');
}

// Errors go to the log, never to the visitor: PHP's built-in default is to display them
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

/**
 * Legacy pages print $_SERVER values and build links from them, so only harmless
 * characters are kept in them. A request whose path does not end with one of the
 * entry points (extra path after the script name) is sent back to hlstats.php.
 * Called once by every entry point, right after this file is loaded.
 */
function hlxFilterServerVars()
{
	foreach ($_SERVER as $key => $entry) {
		if ($key === 'HTTP_COOKIE' || !is_string($entry)) {
			continue;
		}

		$_SERVER[$key] = (string) preg_replace(
			array('/<script>/', '/<\/script>/', '/[^A-Za-z0-9.\-\/=:;_?#&~]/'),
			'',
			$entry
		);
	}

	$self = (string) ($_SERVER['PHP_SELF'] ?? '');
	$last = (string) strrchr($self, '/');
	$entryPoints = array('/hlstats.php', '/ingame.php', '/sig.php', '/status.php', '/index.php', '/');

	if ($self !== '' && !in_array($last, $entryPoints, true)) {
		header('Location: ' . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/hlstats.php');
		exit;
	}
}

// Sent by every entry point: this file is always loaded first
header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Credentials and other server-specific values are read from a .env file one level
// above this folder (the repository root), outside the web root so it is never served.
// It is not tracked by git, so deployments never overwrite it. See .env.example.
$hlxEnv = [];
$hlxEnvFile = dirname(__DIR__) . '/.env';
if (is_readable($hlxEnvFile)) {
	foreach (file($hlxEnvFile, FILE_IGNORE_NEW_LINES) as $hlxEnvLine) {
		$hlxEnvLine = trim($hlxEnvLine);
		if ($hlxEnvLine === '' || $hlxEnvLine[0] === '#' || strpos($hlxEnvLine, '=') === false) {
			continue;
		}
		list($hlxEnvKey, $hlxEnvValue) = array_map('trim', explode('=', $hlxEnvLine, 2));
		if (strlen($hlxEnvValue) >= 2 && ($hlxEnvValue[0] === '"' || $hlxEnvValue[0] === "'")
			&& substr($hlxEnvValue, -1) === $hlxEnvValue[0]) {
			$hlxEnvValue = substr($hlxEnvValue, 1, -1);
		}
		$hlxEnv[$hlxEnvKey] = $hlxEnvValue;
	}
}

// DB_ADDR - The address of the database server, in host:port format.
//           (You might also try setting this to e.g. ":/tmp/mysql.sock" to
//           use a Unix domain socket, if your mysqld is on the same box as
//           your web server.)
define("DB_ADDR", $hlxEnv['DB_HOST'] ?? 'localhost');

// DB_USER - The username to connect to the database as
define("DB_USER", $hlxEnv['DB_USERNAME'] ?? '');

// DB_PASS - The password for DB_USER
define("DB_PASS", $hlxEnv['DB_PASSWORD'] ?? '');

// DB_NAME - The name of the database
define("DB_NAME", $hlxEnv['DB_NAME'] ?? '');

// DB_TYPE - The database server type. Only "mysql" is supported currently
define("DB_TYPE", 'mysql');

// default 'utf8mb4'
define("DB_CHARSET", 'utf8mb4');

// default 'utf8mb4_unicode_ci'
define("DB_COLLATE", 'utf8mb4_unicode_ci');

// DB_PCONNECT - Set to 1 to use persistent database connections. Persistent
//               connections can give better performance, but may overload
//               the database server. Set to 0 to use non-persistent
//               connections.
define("DB_PCONNECT", 0);

// INCLUDE_PATH - Filesystem path to the includes directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
define("INCLUDE_PATH", './includes');


// PAGE_PATH - Filesystem path to the pages directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
define("PAGE_PATH", './pages');


// PAGE_PATH - Filesystem path to the hlstatsimg directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
//
// 		Note: the progress directory under hlstatsimg must be writable!!
define("IMAGE_PATH", './hlstatsimg');

// How often dynamicly generated images are updated (in seconds)
define("IMAGE_UPDATE_INTERVAL", 300);

define('GOOGLE_MAPS_API_KEY', $hlxEnv['GOOGLE_MAPS_API_KEY'] ?? "");

// SITE_URL - Public address of the site (the folder holding hlstats.php), for example
//            https://stats.example.com/. Optional. When set, absolute links use it
//            instead of the request's Host header, and an https:// address marks the
//            session cookie Secure even when PHP itself is reached over plain HTTP
//            (behind a proxy that terminates TLS).
$hlxSiteUrl = trim((string) ($hlxEnv['SITE_URL'] ?? ''));
if ($hlxSiteUrl !== '' && !preg_match('#^https?://[A-Za-z0-9.:\[\]-]+(/[A-Za-z0-9._~/-]*)?$#', $hlxSiteUrl)) {
	error_log('HLstats: SITE_URL in .env is not an http(s):// address, ignored');
	$hlxSiteUrl = '';
}
define('SITE_URL', $hlxSiteUrl === '' ? '' : rtrim($hlxSiteUrl, '/') . '/');

// TRUSTED_PROXIES - Comma-separated addresses or CIDR ranges of reverse proxies whose
//                   X-Forwarded-Proto header is believed (for example 127.0.0.1,10.0.0.0/8).
//                   Optional; the header is ignored from any other address.
define('TRUSTED_PROXIES', trim((string) ($hlxEnv['TRUSTED_PROXIES'] ?? '')));

// Add a chat delay to prevent real-time tracking and provide information that can help in-game.
// Only allowed from 1 minute to CHAT_DELAY_MAX_TIME, other values ​​disable this.
// Disabled by default.
define('CHAT_DELAY_TIME', 0);
define('CHAT_DELAY_MAX_TIME', 20);

define('ROOT_PATH', __DIR__);

//define("DB_DEBUG", true);

/**
 * Whether $ip is one of the addresses or CIDR ranges in the comma-separated $list.
 */
function hlxIpInList($ip, $list)
{
	if (filter_var((string) $ip, FILTER_VALIDATE_IP) === false) {
		return false;
	}
	$addr = inet_pton((string) $ip);
	foreach (explode(',', (string) $list) as $entry) {
		$entry = trim($entry);
		if ($entry === '') {
			continue;
		}
		$parts = explode('/', $entry, 2);
		if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
			continue;
		}
		$net = inet_pton($parts[0]);
		if (strlen($net) !== strlen($addr)) {
			continue;
		}
		$bits = isset($parts[1]) ? (int) $parts[1] : strlen($net) * 8;
		if ($bits < 0 || $bits > strlen($net) * 8) {
			continue;
		}
		$bytes = intdiv($bits, 8);
		if (substr($addr, 0, $bytes) !== substr($net, 0, $bytes)) {
			continue;
		}
		$rest = $bits % 8;
		if ($rest === 0 || ((ord($addr[$bytes]) ^ ord($net[$bytes])) & (0xFF << (8 - $rest)) & 0xFF) === 0) {
			return true;
		}
	}
	return false;
}

/**
 * Whether the visitor reached the site over HTTPS: PHP's own HTTPS flag, an
 * https:// SITE_URL, or X-Forwarded-Proto: https from a trusted proxy.
 */
function hlxIsHttps()
{
	if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
		return true;
	}
	if (strncmp(SITE_URL, 'https://', 8) === 0) {
		return true;
	}
	return TRUSTED_PROXIES !== ''
		&& strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https'
		&& hlxIpInList($_SERVER['REMOTE_ADDR'] ?? '', TRUSTED_PROXIES);
}

/**
 * Absolute address of the folder holding the entry point, ending with "/":
 * SITE_URL when set, else built from the request.
 */
function hlxSiteUrl()
{
	if (SITE_URL !== '') {
		return SITE_URL;
	}
	return (hlxIsHttps() ? 'https://' : 'http://')
		. preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))
		. rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/';
}

/**
 * Starts the PHP session with the cookie settings every entry point that signs an
 * admin in must share (hlstats.php, api/passkey.php).
 */
function hlxStartSession()
{
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
		'secure'   => hlxIsHttps(),
	]);
	session_start();
}

unset($hlxEnv, $hlxEnvFile, $hlxEnvLine, $hlxEnvKey, $hlxEnvValue, $hlxSiteUrl);

?>
