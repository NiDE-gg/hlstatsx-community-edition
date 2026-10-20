<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

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

/**
 * Admin login, shared by pages/admin.php and pages/chat.php.
 *
 * Passwords are stored with password_hash(). Legacy unsalted MD5 hashes are
 * still accepted and replaced on the next successful login. The session only
 * keeps the username, access level, last activity time and a fingerprint of
 * the stored hash, so changing or deleting a user ends their sessions.
 */
class Auth
{
	// Idle time after which the admin has to log in again
	const SESSION_TIMEOUT = 3600;

	// hlstats_Users.password holds more than 32 characters from this dbversion on
	const HASH_DBVERSION = 110;

	public $ok = false;
	public $error = false;
	public $username = '';
	public $userdata = array();
	// True when a POST was dropped because its CSRF token was missing or wrong
	public $csrfRejected = false;

	function __construct()
	{
		$authUsername = (isset($_POST['authusername']) && is_string($_POST['authusername'])) ? trim($_POST['authusername']) : '';
		$authPassword = (isset($_POST['authpassword']) && is_string($_POST['authpassword'])) ? $_POST['authpassword'] : '';

		if ($authUsername !== '') {
			// clear POST vars so as not to confuse the receiving page
			$_POST = array();
			$this->login($authUsername, $authPassword);
		} elseif (!empty($_SESSION['loggedin'])) {
			$this->resume();
		} else {
			$this->printAuth();
		}
	}

	function login($username, $password)
	{
		$this->username = $username;

		$user = self::fetchUser($username);
		if ($user === null || !self::verifyPassword($password, $user['password'])) {
			// Same message for both cases, so valid usernames can't be guessed
			$this->error = 'The username or password you supplied is incorrect.';
			$this->printAuth();
			return;
		}

		if (self::needsRehash($user['password'])) {
			$user['password'] = self::upgradeHash($user['username'], $password, $user['password']);
		}

		session_regenerate_id(true);
		unset($_SESSION['password']);
		$_SESSION['loggedin'] = 1;
		$_SESSION['username'] = $user['username'];
		$_SESSION['acclevel'] = $user['acclevel'];
		$_SESSION['authsessionStart'] = time();
		$_SESSION['authhash'] = self::fingerprint($user['password']);
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

		$this->userdata = $user;
		$this->ok = true;
	}

	function resume()
	{
		$user = self::fetchUser(isset($_SESSION['username']) ? (string) $_SESSION['username'] : '');
		$sessionHash = isset($_SESSION['authhash']) ? (string) $_SESSION['authhash'] : '';
		$sessionStart = isset($_SESSION['authsessionStart']) ? (int) $_SESSION['authsessionStart'] : 0;

		if ($user === null || !hash_equals(self::fingerprint($user['password']), $sessionHash)) {
			// User deleted, password changed, or a session from before this login code
			self::logout();
			$this->printAuth();
			return;
		}

		if ($sessionStart < time() - self::SESSION_TIMEOUT) {
			self::logout();
			$this->username = $user['username'];
			$this->error = 'Your session has expired. Please try again.';
			$this->printAuth();
			return;
		}

		$_SESSION['authsessionStart'] = time();
		$_SESSION['acclevel'] = $user['acclevel'];

		// Admin pages only act on POST data, so a POST without this session's
		// token is emptied before any task sees it.
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !self::checkCsrf()) {
			$_POST = array();
			$this->csrfRejected = true;
		}

		$this->username = $user['username'];
		$this->userdata = $user;
		$this->ok = true;
	}

	static function csrfToken()
	{
		if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}

		return $_SESSION['csrf_token'];
	}

	// Hidden input carrying the token; every admin form that posts must include it
	static function csrfField()
	{
		return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '" />';
	}

	static function checkCsrf()
	{
		$sent = (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) ? $_POST['csrf_token'] : '';

		return $sent !== ''
			&& !empty($_SESSION['csrf_token'])
			&& is_string($_SESSION['csrf_token'])
			&& hash_equals($_SESSION['csrf_token'], $sent);
	}

	function printAuth()
	{
		global $g_options;

		include (PAGE_PATH . '/adminauth.php');
	}

	static function logout()
	{
		unset(
			$_SESSION['loggedin'],
			$_SESSION['username'],
			$_SESSION['password'],
			$_SESSION['acclevel'],
			$_SESSION['authsessionStart'],
			$_SESSION['authhash'],
			$_SESSION['csrf_token']
		);
	}

	static function fetchUser($username)
	{
		global $db;

		if ($username === '' || strlen($username) > 64) {
			return null;
		}

		$result = $db->query("
			SELECT
				username,
				password,
				acclevel,
				playerId
			FROM
				hlstats_Users
			WHERE
				username = '" . $db->escape($username) . "'
			LIMIT 1
		");

		$row = $db->fetch_array($result);
		$db->free_result($result);

		return $row ? $row : null;
	}

	static function hashPassword($password)
	{
		return password_hash($password, PASSWORD_DEFAULT);
	}

	static function verifyPassword($password, $stored)
	{
		$stored = (string) $stored;

		if (self::isLegacyHash($stored)) {
			return hash_equals(strtolower($stored), md5($password));
		}

		return password_verify($password, $stored);
	}

	static function needsRehash($stored)
	{
		return self::isLegacyHash($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT);
	}

	static function isLegacyHash($stored)
	{
		return (bool) preg_match('/^[0-9a-f]{32}$/i', (string) $stored);
	}

	// Returns the hash now stored for the user
	static function upgradeHash($username, $password, $oldHash)
	{
		global $db, $g_options;

		// Before updater 110 the column is varchar(32) and would truncate the new hash
		if ((int) ($g_options['dbversion'] ?? 0) < self::HASH_DBVERSION) {
			return $oldHash;
		}

		$newHash = self::hashPassword($password);
		$updated = $db->query("
			UPDATE
				hlstats_Users
			SET
				password = '" . $db->escape($newHash) . "'
			WHERE
				username = '" . $db->escape($username) . "'
		", false);

		return $updated ? $newHash : $oldHash;
	}

	static function fingerprint($storedHash)
	{
		return hash('sha256', (string) $storedHash);
	}
}
