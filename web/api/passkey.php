<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
http://www.hlxcommunity.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
*/

// Passkey (WebAuthn) endpoint for includes/js/passkeys.js. POST only, with the
// session's CSRF token, answers JSON. Actions:
//   login-options / login        sign in with a passkey (anyone, counted like logins)
//   register-options / register  add a passkey to the signed-in account (needs a
//                                password or passkey check in the last 10 minutes)

declare(strict_types = 1);
const IN_HLSTATS = true;

// INCLUDE_PATH and friends are relative to the folder of hlstats.php
chdir(dirname(__DIR__));
require './config.php';
hlxStartSession();

require INCLUDE_PATH . '/class_db.php';
require INCLUDE_PATH . '/functions.php';
require INCLUDE_PATH . '/class_auth.php';
require INCLUDE_PATH . '/class_passkeys.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function passkeyAnswer(array $data, int $status = 200) : void
{
	http_response_code($status);
	echo json_encode($data, JSON_UNESCAPED_SLASHES);
	exit;
}

$db_classname = 'DB_' . DB_TYPE;
$db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
$container = require ROOT_PATH . '/bootstrap.php';
$g_options = $container->get(\Service\OptionService::class)->getAllOptions();

if (!Auth::postAllowed()) {
	passkeyAnswer(['error' => 'Your session has expired. Please reload the page.'], 403);
}
$reason = Passkeys::unavailableReason();
if ($reason !== null) {
	passkeyAnswer(['error' => $reason], 409);
}

$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$passkeys = Passkeys::repository();

try {
	switch ($action) {
		case 'login-options':
			// No allowCredentials: the browser offers the passkeys it has for this site,
			// so no username is typed and none is revealed
			$webauthn = Passkeys::webauthn();
			$args = $webauthn->getGetArgs(array(), 120, true, true, true, true, true, 'required');
			Passkeys::storeChallenge('login', $webauthn->getChallenge());
			passkeyAnswer(['options' => $args]);

		case 'login':
			$stored = Passkeys::takeChallenge('login');
			$credentialId = Passkeys::decode($_POST['id'] ?? null);
			$clientDataJSON = Passkeys::decode($_POST['clientDataJSON'] ?? null);
			$authenticatorData = Passkeys::decode($_POST['authenticatorData'] ?? null);
			$signature = Passkeys::decode($_POST['signature'] ?? null);
			$userHandle = Passkeys::decode($_POST['userHandle'] ?? '');
			if ($stored === null) {
				passkeyAnswer(['error' => 'The sign-in request has expired. Please try again.'], 400);
			}
			if ($credentialId === null || $clientDataJSON === null || $authenticatorData === null || $signature === null) {
				passkeyAnswer(['error' => 'Incomplete passkey answer.'], 400);
			}

			$row = ($credentialId !== '' && strlen($credentialId) <= 255) ? $passkeys->findByCredentialId($credentialId) : null;
			// Counted like a password attempt, before anything is checked
			$refused = $row !== null ? Auth::countPasswordAttempt($row['username']) : Auth::countAddressAttempt();
			if ($refused !== null) {
				Auth::recordSignIn('passkey', 'refused', $row['username'] ?? '');
				passkeyAnswer(['error' => $refused], 429);
			}
			$user = $row !== null ? Auth::fetchUser($row['username']) : null;
			if ($row === null || $user === null) {
				Auth::recordSignIn('passkey', 'failure', $row['username'] ?? '');
				passkeyAnswer(['error' => 'This passkey is not registered here.'], 403);
			}
			if (($userHandle !== null && $userHandle !== '' && !hash_equals($row['user_handle'], $userHandle))
				|| !Passkeys::clientDataOk($clientDataJSON, 'webauthn.get')) {
				Auth::recordSignIn('passkey', 'failure', $row['username']);
				passkeyAnswer(['error' => 'The passkey sign-in could not be verified.'], 403);
			}

			$webauthn = Passkeys::webauthn();
			$webauthn->processGet($clientDataJSON, $authenticatorData, $signature, $row['public_key'], $stored['challenge'], (int) $row['sign_count'], true, true);

			Auth::passwordAttemptSucceeded($user['username']);
			$passkeys->touch((int) $row['id'], (int) ($webauthn->getSignatureCounter() ?? 0));
			Auth::startSession($user, false, 'passkey');
			passkeyAnswer(['ok' => true]);

		case 'register-options':
		case 'register':
			if (Auth::sessionLevel() < 80) {
				passkeyAnswer(['error' => 'Please sign in again.'], 403);
			}
			if (!Passkeys::recentlyAuthenticated()) {
				passkeyAnswer(['error' => 'Confirm your password on the Passkeys page first.'], 403);
			}
			$username = (string) $_SESSION['username'];

			if ($action === 'register-options') {
				$existing = array();
				foreach ($passkeys->forUser($username) as $known) {
					$existing[] = $known['credential_id'];
				}
				$userHandle = $passkeys->userHandle($username);
				$webauthn = Passkeys::webauthn();
				// A discoverable credential (resident key), checked with the device's PIN or
				// biometrics (user verification), so the login form needs no username
				$args = $webauthn->getCreateArgs($userHandle, $username, $username, 120, true, 'required', null, $existing);
				Passkeys::storeChallenge('register', $webauthn->getChallenge(), ['handle' => bin2hex($userHandle), 'user' => $username]);
				passkeyAnswer(['options' => $args]);
			}

			$stored = Passkeys::takeChallenge('register');
			$clientDataJSON = Passkeys::decode($_POST['clientDataJSON'] ?? null);
			$attestationObject = Passkeys::decode($_POST['attestationObject'] ?? null);
			if ($stored === null || ($stored['extra']['user'] ?? '') !== $username) {
				passkeyAnswer(['error' => 'The request has expired. Please try again.'], 400);
			}
			if ($clientDataJSON === null || $attestationObject === null || !Passkeys::clientDataOk($clientDataJSON, 'webauthn.create')) {
				passkeyAnswer(['error' => 'The new passkey could not be verified.'], 400);
			}

			$data = Passkeys::webauthn()->processCreate($clientDataJSON, $attestationObject, $stored['challenge'], true, true, false);
			$label = trim(is_string($_POST['label'] ?? null) ? $_POST['label'] : '');
			$label = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $label) ?? '', 0, 64);
			if ($label === '') {
				$label = 'Passkey ' . date('Y-m-d');
			}
			if (strlen($data->credentialId) > 255
				|| !$passkeys->add($username, $data->credentialId, hex2bin($stored['extra']['handle']), $data->credentialPublicKey, (int) ($data->signatureCounter ?? 0), $label)) {
				passkeyAnswer(['error' => 'The passkey could not be saved. Has the database update been run?'], 500);
			}
			passkeyAnswer(['ok' => true]);

		default:
			passkeyAnswer(['error' => 'Unknown action.'], 400);
	}
} catch (\lbuchs\WebAuthn\WebAuthnException $e) {
	// The attempt stays counted
	error_log('HLstats passkey ' . $action . ' refused: ' . $e->getMessage());
	if ($action === 'login') {
		Auth::recordSignIn('passkey', 'failure', $row['username'] ?? '');
	}
	passkeyAnswer(['error' => 'The passkey could not be verified.'], 403);
}
