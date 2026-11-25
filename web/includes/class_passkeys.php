<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
http://www.hlxcommunity.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
*/

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}

/**
 * Passkey (WebAuthn) sign-in for the admin accounts, on top of the vendored
 * lbuchs/WebAuthn library (includes/vendor/webauthn).
 *
 * The relying party is the site's SITE_URL from .env, never the request's Host
 * header: its host is the RP ID and its origin the only origin accepted in the
 * browser's client data. Passkeys need HTTPS (http://localhost is allowed for
 * development). Challenges are kept in the session, used once, for 5 minutes.
 */
class Passkeys
{
	const CHALLENGE_LIFETIME = 300;

	// Registering or removing a passkey needs a password or passkey check this recent
	const REAUTH_WINDOW = Auth::REAUTH_WINDOW;

	// Why passkeys can't be used on this site, or null when they can
	static function unavailableReason()
	{
		if (!function_exists('openssl_verify')) {
			return 'Passkeys need the PHP openssl extension.';
		}
		if (SITE_URL === '') {
			return 'Passkeys need the address of the site: set SITE_URL (https://...) in the .env file.';
		}
		if (parse_url(SITE_URL, PHP_URL_SCHEME) !== 'https' && self::rpId() !== 'localhost') {
			return 'Passkeys need HTTPS: SITE_URL in the .env file must start with https://.';
		}

		return null;
	}

	static function rpId()
	{
		return strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
	}

	// scheme://host[:port] of SITE_URL, as browsers write it in the client data
	static function origin()
	{
		$scheme = strtolower((string) parse_url(SITE_URL, PHP_URL_SCHEME));
		$port = parse_url(SITE_URL, PHP_URL_PORT);
		$default = ($scheme === 'https') ? 443 : 80;

		return $scheme . '://' . self::rpId() . (($port !== null && $port !== $default) ? ':' . $port : '');
	}

	static function webauthn()
	{
		static $loaded = false;

		if (!$loaded) {
			spl_autoload_register(function ($className) {
				$prefix = 'lbuchs\\WebAuthn\\';
				if (strncmp($className, $prefix, strlen($prefix)) === 0) {
					$file = __DIR__ . '/vendor/webauthn/src/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
					if (is_file($file)) {
						require $file;
					}
				}
			});
			$loaded = true;
		}

		global $g_options;

		// Attestation "none" only: who made the authenticator does not matter here,
		// and browsers send "none" when it is asked for
		return new \lbuchs\WebAuthn\WebAuthn((string) ($g_options['sitename'] ?? 'HLstatsX'), self::rpId(), array('none'), true);
	}

	static function storeChallenge($purpose, $challenge, array $extra = array())
	{
		$_SESSION['passkey_challenge'][$purpose] = array(
			'challenge' => $challenge instanceof \lbuchs\WebAuthn\Binary\ByteBuffer ? $challenge->getBinaryString() : (string) $challenge,
			'time' => time(),
			'extra' => $extra,
		);
	}

	// The challenge stored for $purpose, removed so it can't be used twice; null when
	// there is none or it is too old
	static function takeChallenge($purpose)
	{
		$stored = $_SESSION['passkey_challenge'][$purpose] ?? null;
		unset($_SESSION['passkey_challenge'][$purpose]);

		if (!is_array($stored) || (int) ($stored['time'] ?? 0) < time() - self::CHALLENGE_LIFETIME) {
			return null;
		}

		return $stored;
	}

	// The library only checks that the origin's host ends with the RP ID (so
	// "evilexample.com" would pass for "example.com"): the origin must be SITE_URL's.
	static function clientDataOk($clientDataJSON, $type)
	{
		$clientData = json_decode((string) $clientDataJSON, true);

		return is_array($clientData)
			&& ($clientData['type'] ?? '') === $type
			&& ($clientData['origin'] ?? '') === self::origin()
			&& empty($clientData['crossOrigin']);
	}

	static function decode($base64url)
	{
		if (!is_string($base64url) || !preg_match('/^[A-Za-z0-9_-]*\z/', $base64url)) {
			return null;
		}
		$decoded = base64_decode(strtr($base64url, '-_', '+/'), true);

		return $decoded === false ? null : $decoded;
	}

	static function recentlyAuthenticated()
	{
		return Auth::recentlyAuthenticated();
	}

	static function repository()
	{
		global $container;

		return $container->get(\Repository\PasskeyRepository::class);
	}

	// Whether $user (a hlstats_Users row) may only sign in with a passkey: the option
	// is on, the account is an Administrator and it has at least one passkey
	static function requiredFor(array $user)
	{
		global $g_options;

		return ($g_options['passkey_required'] ?? '0') === '1'
			&& (int) $user['acclevel'] >= 100
			&& self::repository()->countForUser($user['username']) > 0;
	}
}
