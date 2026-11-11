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

	// Password of the "admin" account created by sql/install.sql
	const DEFAULT_PASSWORD = '123456';

	// Failed logins allowed within LOCKOUT_WINDOW seconds before logins are refused.
	// The per-IP limit stops a single host; the higher per-username limit slows a
	// spread-out attack without letting a few failures lock the real admin out.
	const LOCKOUT_WINDOW = 900;
	const MAX_FAILURES_PER_IP = 5;
	const MAX_FAILURES_PER_USER = 20;

	// Checked against the password when the username doesn't exist, so a failed
	// login takes the same time either way and usernames can't be probed
	const DUMMY_HASH = '$2y$12$n5HhhK0sfo720WM/KKLUzOAK48CRwObZzZxibAkD82UQJXDXhREqi';

	// bcrypt ignores everything past 72 bytes
	const MIN_PASSWORD_LENGTH = 12;
	const MAX_PASSWORD_LENGTH = 72;

	public $ok = false;
	public $error = false;
	public $username = '';
	public $userdata = array();
	// True when a POST was dropped because its CSRF token was missing or wrong
	public $csrfRejected = false;
	// True when the logged-in user signed in with DEFAULT_PASSWORD
	public $usesDefaultPassword = false;

	function __construct()
	{
		$authUsername = (isset($_POST['authusername']) && is_string($_POST['authusername'])) ? trim($_POST['authusername']) : '';
		$authPassword = (isset($_POST['authpassword']) && is_string($_POST['authpassword'])) ? $_POST['authpassword'] : '';

		if ($authUsername !== '') {
			$csrfOk = self::checkCsrf();
			// clear POST vars so as not to confuse the receiving page
			$_POST = array();

			if (!$csrfOk) {
				// Another site can't sign the visitor in to an account of its choosing
				$this->username = $authUsername;
				$this->error = 'Your session has expired. Please try again.';
				$this->printAuth();
				return;
			}

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

		// The username collation ignores case and accents, so "ADMIN" and "ádmin" reach
		// the same account: count their failures under the name it is stored with
		$user = self::fetchUser($username);
		$failureKeys = self::failureKeys($user !== null ? $user['username'] : $username);
		$failures = self::loginFailures();
		$now = time();

		// Each attempt is counted before the password is checked, in one statement per
		// key, so requests sent in parallel can't all pass a check made before any of
		// them is counted. A refused attempt is checked before the password too, so
		// guesses made while locked tell nothing.
		$counted = array();
		foreach ($failureKeys as $key => $limit) {
			if (!$failures->take($key, $limit, $now, $now - self::LOCKOUT_WINDOW)) {
				foreach ($counted as $countedKey) {
					$failures->giveBack($countedKey);
				}
				$lockedFor = max(1, self::lockedFor($failureKeys));
				$this->error = 'Too many failed logins. Please try again in ' . (int) ceil($lockedFor / 60) . ' minute(s).';
				$this->printAuth();
				return;
			}
			$counted[] = $key;
		}

		// Unknown usernames are counted too, so a lockout doesn't reveal which ones exist
		$passwordOk = self::verifyPassword($password, $user !== null ? $user['password'] : self::DUMMY_HASH);
		if ($user === null || !$passwordOk) {
			// Same message for both cases, so valid usernames can't be guessed
			$this->error = 'The username or password you supplied is incorrect.';
			$this->printAuth();
			return;
		}

		// A good login ends the count for its own account. The address only gets this one
		// attempt back: any account holder could otherwise reset the count for an address
		// that is guessing another account's password.
		foreach ($failureKeys as $key => $limit) {
			if (strncmp($key, 'user:', 5) === 0) {
				$failures->clear(array($key));
			} else {
				$failures->giveBack($key);
			}
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
		$_SESSION['authdefaultpassword'] = ($password === self::DEFAULT_PASSWORD);

		$this->userdata = $user;
		$this->usesDefaultPassword = $_SESSION['authdefaultpassword'];
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
		$this->usesDefaultPassword = !empty($_SESSION['authdefaultpassword']);
		$this->ok = true;
	}

	// Access level of the signed-in admin, or 0. For pages that only need the level:
	// the same checks as resume() (account deleted, password changed, session idle).
	// A session opened with the install password counts for nothing until it is changed.
	static function sessionLevel()
	{
		if (empty($_SESSION['loggedin']) || !empty($_SESSION['authdefaultpassword'])) {
			return 0;
		}

		$user = self::fetchUser((string) ($_SESSION['username'] ?? ''));
		$sessionHash = (string) ($_SESSION['authhash'] ?? '');
		$sessionStart = (int) ($_SESSION['authsessionStart'] ?? 0);

		if ($user === null || !hash_equals(self::fingerprint($user['password']), $sessionHash)
			|| $sessionStart < time() - self::SESSION_TIMEOUT) {
			return 0;
		}

		return (int) $user['acclevel'];
	}

	// hlstats_LoginFailures keys for this request and username, with their limits.
	// Behind a reverse proxy, REMOTE_ADDR must be the client's address (Caddy's
	// php_fastcgi does this; Apache needs mod_remoteip, nginx real_ip), or every
	// visitor shares the proxy's IP limit.
	static function failureKeys($username)
	{
		return array(
			'ip:' . self::addressKey((string) ($_SERVER['REMOTE_ADDR'] ?? '')) => self::MAX_FAILURES_PER_IP,
			'user:' . mb_strtolower(mb_substr($username, 0, 64)) => self::MAX_FAILURES_PER_USER,
		);
	}

	// An IPv6 client usually owns a whole /64 and can use any address in it, so those
	// are counted together. IPv4 addresses are counted one by one.
	static function addressKey($address)
	{
		$packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($address) : false;
		if ($packed === false || strlen($packed) !== 16) {
			return $address;
		}

		return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
	}

	// Seconds until a login is allowed again, 0 when it is allowed now
	static function lockedFor(array $failureKeys)
	{
		$now = time();
		$wait = 0;

		foreach ($failureKeys as $key => $limit) {
			$row = self::loginFailures()->find($key, $now - self::LOCKOUT_WINDOW);
			if ($row !== null && $row['failures'] >= $limit) {
				$wait = max($wait, $row['last_failure'] + self::LOCKOUT_WINDOW - $now);
			}
		}

		return $wait;
	}

	static function loginFailures()
	{
		global $container;

		return $container->get(\Repository\LoginFailureRepository::class);
	}

	// Accounts still stored with the legacy hash of DEFAULT_PASSWORD (never
	// logged in since install). Accounts already rehashed are caught at login.
	static function usersWithDefaultPassword()
	{
		global $db;

		$result = $db->query("
			SELECT
				username
			FROM
				hlstats_Users
			WHERE
				password = '" . md5(self::DEFAULT_PASSWORD) . "'
			ORDER BY
				username
		");

		$users = array();
		while ($row = $db->fetch_array($result)) {
			$users[] = $row['username'];
		}
		$db->free_result($result);

		return $users;
	}

	// Why $password can't be used for $username, or null when it can
	static function passwordProblem($username, $password)
	{
		$password = (string) $password;

		if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
			return 'Passwords must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long.';
		}
		if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
			return 'Passwords can be at most ' . self::MAX_PASSWORD_LENGTH . ' bytes long.';
		}
		if ($password === self::DEFAULT_PASSWORD || strcasecmp($password, (string) $username) === 0) {
			return 'This password is too easy to guess.';
		}

		return null;
	}

	// Replaces the signed-in user's password and keeps their session valid.
	// Returns an error message, or null on success.
	function changeOwnPassword($password)
	{
		global $db;

		$problem = self::passwordProblem($this->username, $password);
		if ($problem !== null) {
			return $problem;
		}

		if (!self::canStoreHash()) {
			return 'This database cannot store a new password yet. Please run the database update first.';
		}

		$newHash = self::hashPassword($password);
		$updated = $db->query("
			UPDATE
				hlstats_Users
			SET
				password = '" . $db->escape($newHash) . "'
			WHERE
				username = '" . $db->escape($this->username) . "'
		", false);

		if (!$updated) {
			return 'The password could not be saved. Has the database update been run?';
		}

		$_SESSION['authhash'] = self::fingerprint($newHash);
		$_SESSION['authdefaultpassword'] = false;
		$this->userdata['password'] = $newHash;
		$this->usesDefaultPassword = false;

		return null;
	}

	// The install password is public: an account using it can do nothing else until it
	// has a new one. Prints the form for that while it is needed, so call it after
	// pageHeader(). Returns true when the page can go on.
	function requireNewPassword($formAction)
	{
		if (!$this->usesDefaultPassword) {
			return true;
		}

		if (isset($_POST['new_password']) && is_string($_POST['new_password'])) {
			$repeat = (isset($_POST['new_password_repeat']) && is_string($_POST['new_password_repeat'])) ? $_POST['new_password_repeat'] : '';
			$passwordError = ($_POST['new_password'] === $repeat)
				? $this->changeOwnPassword($_POST['new_password'])
				: 'The two passwords do not match.';

			if ($passwordError === null) {
				$_POST = array();
				message('success', 'Password changed.');
				return true;
			}

			message('warning', eHtml($passwordError));
		}

		message('warning', 'This account still uses the default password. Choose a new one to continue.');
?>
<form method="post" action="<?php echo eHtml($formAction); ?>">
	<?php echo self::csrfField(); ?>
	<table class="data-table">
		<tr><td class="bg1">New password (<?php echo self::MIN_PASSWORD_LENGTH; ?> characters or more):</td>
			<td class="bg1"><input type="password" name="new_password" size="30" maxlength="<?php echo self::MAX_PASSWORD_LENGTH; ?>" autocomplete="new-password" class="textbox" /></td></tr>
		<tr><td class="bg1">Repeat it:</td>
			<td class="bg1"><input type="password" name="new_password_repeat" size="30" maxlength="<?php echo self::MAX_PASSWORD_LENGTH; ?>" autocomplete="new-password" class="textbox" /></td></tr>
		<tr><td class="bg1">&nbsp;</td><td class="bg1"><input type="submit" value=" Change password " class="submit" /></td></tr>
	</table>
</form>
<?php
		return false;
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
			$_SESSION['csrf_token'],
			$_SESSION['authdefaultpassword']
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
			// As slow as a bcrypt check, so failed logins can't single out the accounts
			// still on MD5, the likeliest to have the default password
			password_verify($password, self::DUMMY_HASH);

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

	// Before updater 110 the column is varchar(32) and would truncate a new hash,
	// which locks the account out
	static function canStoreHash()
	{
		global $g_options;

		return (int) ($g_options['dbversion'] ?? 0) >= self::HASH_DBVERSION;
	}

	// Returns the hash now stored for the user
	static function upgradeHash($username, $password, $oldHash)
	{
		global $db;

		if (!self::canStoreHash()) {
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
