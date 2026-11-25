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
 *
 * An account with two-factor authentication signs in with its password, then a
 * code from its authenticator app (or a recovery code): between the two, the
 * session only holds the pending sign-in (authtwofactor), nothing that opens a page.
 */
class Auth
{
	// Idle time after which the admin has to log in again
	const SESSION_TIMEOUT = 3600;

	// Time after the login at which the session ends, however active it is, so a
	// stolen session cookie can't be kept alive forever
	const SESSION_MAX_AGE = 43200;

	// hlstats_Users.password holds more than 32 characters from this dbversion on
	const HASH_DBVERSION = 110;

	// Password of the "admin" account created by sql/install.sql
	const DEFAULT_PASSWORD = '123456';

	// Failed logins are counted over LOCKOUT_WINDOW seconds. One address gets
	// MAX_FAILURES_PER_IP of them, then is refused until the window has passed.
	// A username is never locked, since anyone could then keep a known account
	// locked: after USER_FREE_FAILURES its attempts wait 2, 4, 8... seconds after
	// the previous failure, at most USER_MAX_DELAY, which still stops a spread-out
	// guessing attack. A browser that has signed in to the account before (see
	// isKnownBrowser()) skips that wait and its failures don't count for it.
	const LOCKOUT_WINDOW = 900;
	const MAX_FAILURES_PER_IP = 5;
	const USER_FREE_FAILURES = 5;
	const USER_MAX_DELAY = 300;

	// How long a browser stays known after signing in to an account
	const KNOWN_BROWSER_LIFETIME = 7776000;

	// Checked against the password when the username doesn't exist, so a failed
	// login takes the same time either way and usernames can't be probed
	const DUMMY_HASH = '$2y$12$n5HhhK0sfo720WM/KKLUzOAK48CRwObZzZxibAkD82UQJXDXhREqi';

	// hlstats_TwoFactor and hlstats_RecoveryCodes exist from this dbversion on
	const TWO_FACTOR_DBVERSION = 172;

	// After the password, the code has to be typed within TWO_FACTOR_LIFETIME seconds
	// and in at most TWO_FACTOR_MAX_TRIES tries, else the password is asked again.
	// Every try is also counted like a password attempt (address and account).
	const TWO_FACTOR_LIFETIME = 300;
	const TWO_FACTOR_MAX_TRIES = 5;

	// Recovery codes given when two-factor authentication is set up
	const RECOVERY_CODE_COUNT = 10;

	// Changing the passkeys or the two-factor authentication of the account needs a
	// password (or passkey) check this recent: the login itself, or a confirmation
	const REAUTH_WINDOW = 600;

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
	// True while the password was right and the second factor is awaited
	public $twoFactorPending = false;
	// True when the site requires two-factor authentication of this account and it
	// has none yet: the session opens nothing else until it is set up
	public $needsTwoFactorSetup = false;

	function __construct()
	{
		$authUsername = (isset($_POST['authusername']) && is_string($_POST['authusername'])) ? trim($_POST['authusername']) : '';
		$authPassword = (isset($_POST['authpassword']) && is_string($_POST['authpassword'])) ? $_POST['authpassword'] : '';
		$authCode = (isset($_POST['authcode']) && is_string($_POST['authcode'])) ? $_POST['authcode'] : null;
		$authCancel = !empty($_POST['authcancel']);

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
		} elseif (($authCode !== null || $authCancel) && !empty($_SESSION['authtwofactor'])) {
			$csrfOk = self::checkCsrf();
			$_POST = array();

			if (!$csrfOk) {
				$this->error = 'Your session has expired. Please try again.';
				$this->showPendingTwoFactor();
				return;
			}
			if ($authCancel) {
				unset($_SESSION['authtwofactor']);
				$this->printAuth();
				return;
			}

			$this->loginSecondFactor($authCode);
		} elseif (!empty($_SESSION['loggedin'])) {
			$this->resume();
		} else {
			$this->showPendingTwoFactor();
		}
	}

	// The code form while a sign-in waits for its second factor, else the login form
	function showPendingTwoFactor()
	{
		$pending = self::pendingTwoFactor();
		if ($pending !== null) {
			$this->username = $pending['username'];
			$this->twoFactorPending = true;
		}
		$this->printAuth();
	}

	function login($username, $password)
	{
		$this->username = $username;

		// The username collation ignores case and accents, so "ADMIN" and "ádmin" reach
		// the same account: count their failures under the name it is stored with
		$user = self::fetchUser($username);
		$accountName = $user !== null ? $user['username'] : $username;

		$refused = self::countPasswordAttempt($accountName);
		if ($refused !== null) {
			$this->error = $refused;
			$this->printAuth();
			return;
		}

		// Checked before the password, so a guess tells nothing about it
		if ($user !== null && self::passkeyRequired($user)) {
			$this->error = 'This account signs in with a passkey: use "Sign in with a passkey".';
			$this->printAuth();
			return;
		}

		// Unknown usernames are counted too, so a lockout doesn't reveal which ones exist
		$passwordOk = self::verifyPassword($password, $user !== null ? $user['password'] : self::DUMMY_HASH);
		if ($user === null || !$passwordOk) {
			// Same message for both cases, so valid usernames can't be guessed
			$this->error = 'The username or password you supplied is incorrect.';
			$this->printAuth();
			return;
		}

		$this->username = $user['username'];

		if (self::needsRehash($user['password'])) {
			$user['password'] = self::upgradeHash($user['username'], $password, $user['password']);
		}

		$twoFactor = self::twoFactorFor($user['username']);
		if ($twoFactor === null) {
			// The second factor can't be skipped because its table can't be read
			$this->error = 'Two-factor authentication could not be checked. Please try again later.';
			$this->printAuth();
			return;
		}

		if ($twoFactor !== false) {
			// The password was right: the address gets this attempt back, but the account's
			// count goes on until the code is right too, so a known password doesn't reset
			// the delay between code guesses. Any session already open here ends.
			self::loginFailures()->giveBack(self::ipFailureKey());
			self::logout();
			session_regenerate_id(true);
			$_SESSION['authtwofactor'] = array(
				'username' => $user['username'],
				'hash'     => self::fingerprint($user['password']),
				'time'     => time(),
				'tries'    => 0,
				'default'  => $password === self::DEFAULT_PASSWORD,
			);
			$this->twoFactorPending = true;
			$this->printAuth();
			return;
		}

		self::passwordAttemptSucceeded($user['username']);
		self::startSession($user, $password === self::DEFAULT_PASSWORD, self::twoFactorRequiredFor($user));

		$this->userdata = $user;
		$this->usesDefaultPassword = $_SESSION['authdefaultpassword'];
		$this->needsTwoFactorSetup = $_SESSION['authtwofactorsetup'];
		$this->ok = true;
	}

	// Second step of a password login on an account with two-factor authentication:
	// $code is a code from the authenticator app or a recovery code
	function loginSecondFactor($code)
	{
		$pending = self::pendingTwoFactor();
		$user = $pending !== null ? self::fetchUser($pending['username']) : null;

		if ($user === null || !hash_equals(self::fingerprint($user['password']), $pending['hash'])) {
			// Too late, or the account was deleted or its password changed meanwhile
			unset($_SESSION['authtwofactor']);
			if ($pending !== null) {
				$this->username = $pending['username'];
			}
			$this->error = 'The sign-in has expired. Please enter your password again.';
			$this->printAuth();
			return;
		}

		$this->username = $user['username'];

		$refused = self::countPasswordAttempt($user['username']);
		if ($refused !== null) {
			$this->error = $refused;
			$this->twoFactorPending = true;
			$this->printAuth();
			return;
		}

		$_SESSION['authtwofactor']['tries'] = (int) $pending['tries'] + 1;

		if (!self::checkSecondFactor($user['username'], (string) $code)) {
			if ($_SESSION['authtwofactor']['tries'] >= self::TWO_FACTOR_MAX_TRIES) {
				unset($_SESSION['authtwofactor']);
				$this->error = 'Too many wrong codes. Please enter your password again.';
			} else {
				$this->error = 'The code is incorrect.';
				$this->twoFactorPending = true;
			}
			$this->printAuth();
			return;
		}

		self::passwordAttemptSucceeded($user['username']);
		self::startSession($user, !empty($pending['default']));

		$this->userdata = $user;
		$this->usesDefaultPassword = $_SESSION['authdefaultpassword'];
		$this->ok = true;
	}

	// The sign-in waiting for its second factor, or null (none, or too old: then dropped)
	static function pendingTwoFactor()
	{
		$pending = $_SESSION['authtwofactor'] ?? null;
		if (!is_array($pending) || (int) ($pending['time'] ?? 0) < time() - self::TWO_FACTOR_LIFETIME
			|| (int) ($pending['tries'] ?? 0) >= self::TWO_FACTOR_MAX_TRIES) {
			unset($_SESSION['authtwofactor']);
			return null;
		}

		return $pending;
	}

	// Whether $code (from the authenticator app, or a recovery code) is right for
	// $username. Either kind is used up by a match, so it can't sign in twice.
	static function checkSecondFactor($username, $code)
	{
		$code = trim((string) $code);
		$digits = str_replace(' ', '', $code);
		$repository = self::twoFactor();

		if (strlen($digits) === \Utils\Totp::DIGITS && ctype_digit($digits)) {
			$row = $repository->find($username);
			if (!is_array($row)) {
				return false;
			}
			$step = \Utils\Totp::matchingStep((string) $row['secret'], $digits, time(), (int) $row['last_step']);

			return $step !== null && $repository->useStep($username, $step);
		}

		$recovery = \Utils\Totp::normalizeRecoveryCode($code);

		return $recovery !== null && $repository->useRecoveryCode($username, \Utils\Totp::hashRecoveryCode($recovery));
	}

	// The account's hlstats_TwoFactor row, false when it has none (or the database is
	// from before the tables), null when it can't be read
	static function twoFactorFor($username)
	{
		global $g_options;

		if ((int) ($g_options['dbversion'] ?? 0) < self::TWO_FACTOR_DBVERSION) {
			return false;
		}

		return self::twoFactor()->find((string) $username);
	}

	// Whether the site requires two-factor authentication of $user (a hlstats_Users
	// row): the twofactor_required option is on and the account is an Administrator.
	// A passkey sign-in already checks two factors (the device and its PIN or
	// biometrics), so this only applies to password sign-ins.
	static function twoFactorRequiredFor(array $user)
	{
		global $g_options;

		return ($g_options['twofactor_required'] ?? '0') === '1'
			&& (int) $user['acclevel'] >= 100
			&& (int) ($g_options['dbversion'] ?? 0) >= self::TWO_FACTOR_DBVERSION;
	}

	static function twoFactor()
	{
		global $container;

		return $container->get(\Repository\TwoFactorRepository::class);
	}

	// Until the account required to have two-factor authentication has it, prints its
	// setup (Tools > Two-Factor Authentication) instead of the page. Call it after
	// pageHeader() and requireNewPassword(). Returns true when the page can go on.
	function requireTwoFactorSetup()
	{
		global $g_options, $db;

		if (!$this->needsTwoFactorSetup) {
			return true;
		}
		if (is_array(self::twoFactorFor($this->username))) {
			// Set up meanwhile (another tab)
			unset($_SESSION['authtwofactorsetup']);
			$this->needsTwoFactorSetup = false;
			return true;
		}

		$auth = $this;
		$twoFactorForced = true;
		include(PAGE_PATH . '/admintasks/tools_twofactor.php');

		return false;
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

		if ($sessionStart < time() - self::SESSION_TIMEOUT || self::sessionTooOld()) {
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
		$this->needsTwoFactorSetup = !empty($_SESSION['authtwofactorsetup']);
		$this->ok = true;
	}

	// Access level of the signed-in admin, or 0. For pages that only need the level:
	// the same checks as resume() (account deleted, password changed, session idle).
	// A session opened with the install password counts for nothing until it is changed,
	// nor one that has to set up two-factor authentication first.
	static function sessionLevel()
	{
		if (empty($_SESSION['loggedin']) || !empty($_SESSION['authdefaultpassword']) || !empty($_SESSION['authtwofactorsetup'])) {
			return 0;
		}

		$user = self::fetchUser((string) ($_SESSION['username'] ?? ''));
		$sessionHash = (string) ($_SESSION['authhash'] ?? '');
		$sessionStart = (int) ($_SESSION['authsessionStart'] ?? 0);

		if ($user === null || !hash_equals(self::fingerprint($user['password']), $sessionHash)
			|| $sessionStart < time() - self::SESSION_TIMEOUT || self::sessionTooOld()) {
			return 0;
		}

		return (int) $user['acclevel'];
	}

	// Signs $user (a hlstats_Users row) in, after a password (and second factor) or a
	// passkey check: a new session id, a new CSRF token, and the times the session
	// limits are counted from. $twoFactorSetup: the account has to set up two-factor
	// authentication before it can do anything else.
	static function startSession(array $user, $defaultPassword, $twoFactorSetup = false)
	{
		self::rememberBrowser($user['username']);

		session_regenerate_id(true);
		unset($_SESSION['password'], $_SESSION['passkey_challenge'], $_SESSION['authtwofactor'], $_SESSION['twofactor_setup']);
		$_SESSION['loggedin'] = 1;
		$_SESSION['username'] = $user['username'];
		$_SESSION['acclevel'] = $user['acclevel'];
		$_SESSION['authsessionStart'] = time();
		$_SESSION['authloginTime'] = time();
		$_SESSION['authreauthTime'] = time();
		$_SESSION['authhash'] = self::fingerprint($user['password']);
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		$_SESSION['authdefaultpassword'] = (bool) $defaultPassword;
		$_SESSION['authtwofactorsetup'] = (bool) $twoFactorSetup;
	}

	// Administrators with a passkey only sign in with it when the passkey_required
	// option is on. Errors (no table before the update) count as no passkey.
	static function passkeyRequired(array $user)
	{
		require_once(INCLUDE_PATH . '/class_passkeys.php');

		return Passkeys::requiredFor($user);
	}

	// Counts a sign-in attempt for the address only (a passkey nobody has registered)
	static function countAddressAttempt()
	{
		$now = time();
		if (!self::loginFailures()->take(self::ipFailureKey(), self::MAX_FAILURES_PER_IP, $now, $now - self::LOCKOUT_WINDOW)) {
			$lockedFor = max(1, self::lockedFor(self::ipFailureKey(), self::MAX_FAILURES_PER_IP));
			return 'Too many failed logins. Please try again in ' . (int) ceil($lockedFor / 60) . ' minute(s).';
		}

		return null;
	}

	// Counts a password check for $accountName (a login, or the current password asked
	// before a change). Each attempt is counted before the password is checked, in one
	// statement per key, so requests sent in parallel can't all pass a check made
	// before any of them is counted. A refused attempt is refused before the password
	// is checked too, so guesses made while refused tell nothing. Returns the message
	// to show when the attempt is refused, or null when the password can be checked.
	static function countPasswordAttempt($accountName)
	{
		$failures = self::loginFailures();
		$ipKey = self::ipFailureKey();
		$now = time();

		if (!$failures->take($ipKey, self::MAX_FAILURES_PER_IP, $now, $now - self::LOCKOUT_WINDOW)) {
			$lockedFor = max(1, self::lockedFor($ipKey, self::MAX_FAILURES_PER_IP));
			return 'Too many failed logins. Please try again in ' . (int) ceil($lockedFor / 60) . ' minute(s).';
		}
		if (!self::isKnownBrowser($accountName)) {
			$wait = $failures->takeDelayed(self::userFailureKey($accountName), self::USER_FREE_FAILURES, self::USER_MAX_DELAY, $now, $now - self::LOCKOUT_WINDOW);
			if ($wait > 0) {
				$failures->giveBack($ipKey);
				return 'Too many failed logins for this account. Please try again in ' . $wait . ' second(s).';
			}
		}

		return null;
	}

	// The password was right: the count for its own account ends. The address only gets
	// this one attempt back: any account holder could otherwise reset the count for an
	// address that is guessing another account's password.
	static function passwordAttemptSucceeded($accountName)
	{
		self::loginFailures()->clear(array(self::userFailureKey($accountName)));
		self::loginFailures()->giveBack(self::ipFailureKey());
	}

	static function recentlyAuthenticated()
	{
		return (int) ($_SESSION['authreauthTime'] ?? 0) >= time() - self::REAUTH_WINDOW;
	}

	// Handles the "confirm your password" form of the pages that need a recent check
	// (recentlyAuthenticated()), counted like a login. Call it for a POST that carries
	// confirm_password; prints the outcome.
	function confirmPassword()
	{
		$refused = self::countPasswordAttempt($this->username);
		if ($refused !== null) {
			message('warning', eHtml($refused));
		} elseif (!self::verifyPassword(is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '', $this->userdata['password'])) {
			message('warning', 'The password is incorrect.');
		} else {
			self::passwordAttemptSucceeded($this->username);
			$_SESSION['authreauthTime'] = time();
			message('success', 'Password confirmed for ' . (int) (self::REAUTH_WINDOW / 60) . ' minutes.');
		}
	}

	// Whether the signed-in session is older than SESSION_MAX_AGE. A session opened
	// before this check existed has no login time: it gets one now, so it still
	// ends SESSION_MAX_AGE from here.
	static function sessionTooOld()
	{
		if (!isset($_SESSION['authloginTime'])) {
			$_SESSION['authloginTime'] = time();
		}

		return (int) $_SESSION['authloginTime'] < time() - self::SESSION_MAX_AGE;
	}

	// hlstats_LoginFailures key of this request's address. Behind a reverse proxy,
	// REMOTE_ADDR must be the client's address (Caddy's php_fastcgi does this; Apache
	// needs mod_remoteip, nginx real_ip), or every visitor shares the proxy's limit.
	static function ipFailureKey()
	{
		return 'ip:' . self::addressKey((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	}

	// hlstats_LoginFailures key of a username (unknown usernames are counted too)
	static function userFailureKey($username)
	{
		return 'user:' . mb_strtolower(mb_substr((string) $username, 0, 64));
	}

	// An IPv6 client usually owns a whole /64 and can use any address in it, so those
	// are counted together. IPv4 addresses are counted one by one.
	static function addressKey($address)
	{
		$packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($address) : false;
		if ($packed === false || strlen($packed) !== 16) {
			return $address;
		}

		// IPv4-mapped (::ffff:a.b.c.d, what a dual-stack listener reports for IPv4
		// clients) and IPv4-compatible (::a.b.c.d) addresses carry an IPv4 address in
		// their last 4 bytes, with zeros in the first 8: as a /64 they would all share
		// one key, so one client could use up the limit of every IPv4 client.
		$prefix = substr($packed, 0, 12);
		if ($prefix === str_repeat("\0", 10) . "\xff\xff" || $prefix === str_repeat("\0", 12)) {
			return inet_ntop(substr($packed, 12));
		}

		return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
	}

	// Seconds until $key is below $limit again, 0 when it is now
	static function lockedFor($key, $limit)
	{
		$now = time();
		$row = self::loginFailures()->find($key, $now - self::LOCKOUT_WINDOW);
		if ($row !== null && $row['failures'] >= $limit) {
			return $row['last_failure'] + self::LOCKOUT_WINDOW - $now;
		}

		return 0;
	}

	// Secret that signs the known-browser cookies, created on first use. Deleting the
	// auth_browser_key row of hlstats_Options forgets every known browser.
	// opttype 3: read by the web pages, never by the daemon (it reads opttype <= 1).
	static function browserKey()
	{
		global $db;

		for ($try = 0; $try < 2; $try++) {
			$result = $db->query("SELECT value FROM hlstats_Options WHERE keyname = 'auth_browser_key'");
			$row = $db->fetch_row($result);
			$db->free_result($result);
			if ($row && strlen((string) $row[0]) >= 64) {
				return (string) $row[0];
			}
			// INSERT IGNORE then read again: two first logins at once end up with the same key
			$db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('auth_browser_key', '"
				. bin2hex(random_bytes(32)) . "', 3)");
		}

		return '';
	}

	static function browserCookieName($username)
	{
		return 'hlx_known_' . substr(hash('sha256', mb_strtolower((string) $username)), 0, 16);
	}

	static function browserCookieMac($username, $expires, $key)
	{
		return hash_hmac('sha256', mb_strtolower((string) $username) . '|' . $expires, $key);
	}

	// Whether this browser has signed in to $username before: it then carries a
	// cookie signed with browserKey() for that account, valid KNOWN_BROWSER_LIFETIME.
	static function isKnownBrowser($username)
	{
		$value = $_COOKIE[self::browserCookieName($username)] ?? '';
		if (!is_string($value) || !preg_match('/^(\d{1,12})\.([0-9a-f]{64})\z/', $value, $m) || (int) $m[1] < time()) {
			return false;
		}
		$key = self::browserKey();

		return $key !== '' && hash_equals(self::browserCookieMac($username, $m[1], $key), $m[2]);
	}

	static function rememberBrowser($username)
	{
		$key = self::browserKey();
		if ($key === '' || headers_sent()) {
			return;
		}
		$expires = time() + self::KNOWN_BROWSER_LIFETIME;
		setcookie(self::browserCookieName($username), $expires . '.' . self::browserCookieMac($username, $expires, $key), array(
			'expires'  => $expires,
			'path'     => rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/',
			'secure'   => hlxIsHttps(),
			'httponly' => true,
			'samesite' => 'Strict',
		));
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

	// For the code that writes: a POST carrying this session's token. Auth::resume()
	// already empties $_POST without it; every write checks again on its own, so a
	// page that reaches a task some other way (or a GET) can't change anything.
	static function postAllowed()
	{
		return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && self::checkCsrf();
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
			$_SESSION['authloginTime'],
			$_SESSION['authreauthTime'],
			$_SESSION['authhash'],
			$_SESSION['csrf_token'],
			$_SESSION['authdefaultpassword'],
			$_SESSION['authtwofactorsetup'],
			$_SESSION['authtwofactor'],
			$_SESSION['twofactor_setup']
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
