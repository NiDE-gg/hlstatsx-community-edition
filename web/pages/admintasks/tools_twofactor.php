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

	// Every signed-in admin sets up two-factor authentication for their own account
	// here. Administrators also see which accounts have it and can turn it off for
	// them: that is the recovery path when a phone and its recovery codes are both
	// lost (or, with database access, DELETE FROM hlstats_TwoFactor WHERE username = '...').
	// Auth::requireTwoFactorSetup() includes this page too ($twoFactorForced), when the
	// site requires two-factor authentication of the account and it has none yet.
	if ($auth->userdata['acclevel'] < 80) {
		die('Access denied!');
	}

	$twoFactorForced = !empty($twoFactorForced);
	$twoFactorRepo = Auth::twoFactor();
	$isAdministrator = (int) $auth->userdata['acclevel'] >= 100;
	$requiredForMe = Auth::twoFactorRequiredFor($auth->userdata);
	$taskUrl = $g_options['scripturl'] . '?mode=admin' . ($twoFactorForced ? '' : '&task=tools_twofactor');

	// The secret being set up lives in the session until a code from it is typed
	$setupLifetime = 900;
	// Recovery codes to show once, right after they are made
	$newRecoveryCodes = array();

	$makeRecoveryCodes = function () use (&$newRecoveryCodes) {
		$newRecoveryCodes = array();
		$hashes = array();
		for ($i = 0; $i < Auth::RECOVERY_CODE_COUNT; $i++) {
			$code = \Utils\Totp::generateRecoveryCode();
			$newRecoveryCodes[] = $code;
			$hashes[] = \Utils\Totp::hashRecoveryCode(\Utils\Totp::normalizeRecoveryCode($code));
		}
		return $hashes;
	};
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat"><b>&nbsp;<?php echo $twoFactorForced ? 'Two-Factor Authentication' : $task->title; ?></b><p>

<?php
	if ((int) ($g_options['dbversion'] ?? 0) < Auth::TWO_FACTOR_DBVERSION) {
		message('warning', 'Two-factor authentication needs the database update: please run it first.');
		return;
	}

	if (Auth::postAllowed()) {
		$recent = Auth::recentlyAuthenticated();
		$current = $twoFactorRepo->find($auth->username);

		if (isset($_POST['confirm_password'])) {
			// Changing the second factor needs a recent password check, so an open
			// session alone can't put an attacker's authenticator on the account
			$auth->confirmPassword();
		} elseif (!$recent) {
			message('warning', 'Confirm your password below first.');
		} elseif (isset($_POST['twofactor_start'])) {
			$_SESSION['twofactor_setup'] = array('secret' => \Utils\Totp::generateSecret(), 'time' => time());
		} elseif (isset($_POST['twofactor_cancel'])) {
			unset($_SESSION['twofactor_setup']);
		} elseif (isset($_POST['twofactor_code'])) {
			$setup = $_SESSION['twofactor_setup'] ?? null;
			$code = str_replace(' ', '', is_string($_POST['twofactor_code']) ? $_POST['twofactor_code'] : '');
			if (!is_array($setup) || (int) $setup['time'] < time() - $setupLifetime) {
				unset($_SESSION['twofactor_setup']);
				message('warning', 'The setup has expired. Please start again.');
			} else {
				// The code proves the app holds the secret before the account depends on it
				$step = \Utils\Totp::matchingStep((string) $setup['secret'], $code, time(), 0);
				if ($step === null) {
					message('warning', 'The code is incorrect. Check that the clock of the device is right, then try the next code.');
				} elseif ($twoFactorRepo->enable($auth->username, (string) $setup['secret'], $step, $makeRecoveryCodes())) {
					unset($_SESSION['twofactor_setup'], $_SESSION['authtwofactorsetup']);
					$auth->needsTwoFactorSetup = false;
					message('success', 'Two-factor authentication is on: your next password sign-in will ask for a code.');
				} else {
					$newRecoveryCodes = array();
					message('warning', 'Two-factor authentication could not be saved. Has the database update been run?');
				}
			}
		} elseif (isset($_POST['twofactor_newcodes']) && is_array($current)) {
			if ($twoFactorRepo->replaceRecoveryCodes($auth->username, $makeRecoveryCodes())) {
				message('success', 'New recovery codes made: the previous ones no longer work.');
			} else {
				$newRecoveryCodes = array();
				message('warning', 'The recovery codes could not be saved.');
			}
		} elseif (isset($_POST['twofactor_disable']) && is_array($current)) {
			if ($requiredForMe) {
				message('warning', 'The site settings require two-factor authentication for Administrators: it can be replaced, not turned off.');
			} elseif ($twoFactorRepo->disable($auth->username)) {
				message('success', 'Two-factor authentication is off. Remove this site from your authenticator app too.');
			}
		} elseif (isset($_POST['twofactor_reset_for']) && is_string($_POST['twofactor_reset_for']) && $isAdministrator && !$twoFactorForced) {
			if (mb_strtolower($_POST['twofactor_reset_for']) === mb_strtolower($auth->username)) {
				message('warning', 'Use the buttons above for your own account.');
			} elseif ($twoFactorRepo->disable($_POST['twofactor_reset_for'])) {
				message('success', 'Two-factor authentication of ' . eHtml($_POST['twofactor_reset_for']) . ' is off. They can set it up again after their next sign-in.');
			}
		}
	}

	$recent = Auth::recentlyAuthenticated();
	$mine = $twoFactorRepo->find($auth->username);
	$setup = $_SESSION['twofactor_setup'] ?? null;
	if (is_array($setup) && (int) $setup['time'] < time() - $setupLifetime) {
		unset($_SESSION['twofactor_setup']);
		$setup = null;
	}

	if ($newRecoveryCodes) {
?>
<div class="subblock" style="margin:10px 0;">
	<strong>Your recovery codes</strong> &mdash; save them now (password manager, or printed and kept safe): they are not shown again.
	Each one signs you in once instead of a code from the app, if you lose your phone.
	<pre style="font-size:1.2em;line-height:1.5em;margin:10px 0;"><?php echo eHtml(implode("\n", $newRecoveryCodes)); ?></pre>
</div>
<?php
		if ($twoFactorForced) {
?>
<p><a href="<?php echo eHtml($taskUrl); ?>"><strong>I have saved them: continue to the admin panel</strong></a></p>
<?php
			return;
		}
	}

	if ($twoFactorForced && !is_array($mine)) {
		message('warning', 'This site requires two-factor authentication for Administrators. Set it up to continue.');
	}
?>
<p>Two-factor authentication asks, after your password, for a code from an authenticator app on your phone
(Google Authenticator, Microsoft Authenticator, Aegis, 2FAS, Bitwarden, 1Password...). Someone who learns your password
still can't sign in without the phone. A passkey sign-in does not ask for a code: the passkey already checks two factors.</p>

<table class="data-table">
	<tr class="data-table-head"><td colspan="2">Your account (<?php echo eHtml($auth->username); ?>)</td></tr>
<?php if ($mine === null) { ?>
	<tr class="bg1"><td colspan="2">The two-factor settings could not be read.</td></tr>
<?php } elseif ($mine === false) { ?>
	<tr class="bg1"><td>Two-factor authentication:</td><td><strong>off</strong></td></tr>
<?php } else {
		$codesLeft = $twoFactorRepo->unusedRecoveryCodes($auth->username);
?>
	<tr class="bg1"><td>Two-factor authentication:</td><td><strong>on</strong> since <?php echo eHtml(date('Y-m-d H:i', (int) $mine['created'])); ?></td></tr>
	<tr class="bg1"><td>Last used:</td><td><?php echo $mine['last_used'] ? eHtml(date('Y-m-d H:i', (int) $mine['last_used'])) : 'never'; ?></td></tr>
	<tr class="bg1"><td>Recovery codes left:</td><td><?php echo (int) $codesLeft; ?><?php echo $codesLeft <= 2 ? ' &mdash; <strong>make new ones</strong>' : ''; ?></td></tr>
<?php } ?>
</table>
<br />

<?php
	if (!$recent) {
?>
<form method="post" action="<?php echo eHtml($taskUrl); ?>">
	<?php echo Auth::csrfField(); ?>
	To change your two-factor authentication, confirm your password:
	<input type="password" name="confirm_password" size="24" maxlength="<?php echo Auth::MAX_PASSWORD_LENGTH; ?>" autocomplete="current-password" class="textbox" />
	<input type="submit" value=" Confirm " class="submit" />
</form>
<?php
	} elseif (is_array($setup)) {
		$secret = (string) $setup['secret'];
		$issuer = trim((string) ($g_options['sitename'] ?? '')) ?: 'HLstatsX';
?>
<div class="subblock">
	<p><strong>1.</strong> Scan this QR code with your authenticator app:</p>
	<div data-totp-uri="<?php echo eHtml(\Utils\Totp::uri($issuer, $auth->username, $secret)); ?>" style="margin:10px 0;"></div>
	<p>or enter this key by hand (time-based, 6 digits):<br />
	<code style="font-size:1.2em;letter-spacing:1px;"><?php echo eHtml(implode(' ', str_split($secret, 4))); ?></code></p>

	<form method="post" action="<?php echo eHtml($taskUrl); ?>">
		<?php echo Auth::csrfField(); ?>
		<p><strong>2.</strong> Type the code the app shows:
		<input type="text" name="twofactor_code" size="10" maxlength="10" inputmode="numeric" autocomplete="one-time-code" spellcheck="false" class="textbox" />
		<input type="submit" value=" Turn on " class="submit" /></p>
	</form>
	<form method="post" action="<?php echo eHtml($taskUrl); ?>">
		<?php echo Auth::csrfField(); ?>
		<input type="hidden" name="twofactor_cancel" value="1" />
		<input type="submit" value=" Cancel " class="submit" />
	</form>
</div>
<script nonce="<?php echo cspNonce(); ?>" src="<?php echo asset_url(INCLUDE_PATH . '/js/vendor/qrcode-generator/qrcode.js'); ?>" defer></script>
<script nonce="<?php echo cspNonce(); ?>" src="<?php echo asset_url(INCLUDE_PATH . '/js/twofactor.js'); ?>" defer></script>
<?php
	} else {
?>
<form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;">
	<?php echo Auth::csrfField(); ?>
	<input type="hidden" name="twofactor_start" value="1" />
	<input type="submit" value="<?php echo is_array($mine) ? ' Move to another authenticator app ' : ' Set up two-factor authentication '; ?>" class="submit" />
</form>
<?php
		if (is_array($mine)) {
?>
<form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;">
	<?php echo Auth::csrfField(); ?>
	<input type="hidden" name="twofactor_newcodes" value="1" />
	<input type="submit" value=" Make new recovery codes " class="submit" />
</form>
<?php
			if (!$requiredForMe) {
?>
<form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;">
	<?php echo Auth::csrfField(); ?>
	<input type="hidden" name="twofactor_disable" value="1" />
	<input type="submit" value=" Turn off " class="submit" />
</form>
<?php
			}
		}
	}

	if ($isAdministrator && !$twoFactorForced) {
		$accounts = $twoFactorRepo->accounts();
		$users = array();
		$result = $db->query("SELECT username, acclevel FROM hlstats_Users ORDER BY username");
		while ($row = $db->fetch_array($result)) {
			if (mb_strtolower($row['username']) !== mb_strtolower($auth->username)) {
				$users[$row['username']] = (int) $row['acclevel'];
			}
		}
		$db->free_result($result);
?>
<br /><br />
<table class="data-table">
	<tr class="data-table-head"><td>Other accounts</td><td>Two-factor authentication</td><td>Last used</td><td>&nbsp;</td></tr>
<?php if (!$users) { ?>
	<tr class="bg1"><td colspan="4">None.</td></tr>
<?php }
		foreach ($users as $user => $level) {
			$info = $accounts[$user] ?? null;
?>
	<tr class="bg1">
		<td><?php echo eHtml($user); ?></td>
		<td><?php echo $info ? 'on since ' . eHtml(date('Y-m-d', $info['created'])) : 'off'; ?></td>
		<td><?php echo ($info && $info['last_used']) ? eHtml(date('Y-m-d H:i', $info['last_used'])) : '&nbsp;'; ?></td>
		<td><?php if ($info) { ?><form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;margin:0;">
			<?php echo Auth::csrfField(); ?>
			<input type="hidden" name="twofactor_reset_for" value="<?php echo eHtml($user); ?>" />
			<input type="submit" value="Turn off" class="submit"<?php echo $recent ? '' : ' disabled title="Confirm your password first"'; ?> />
		</form><?php } else { echo '&nbsp;'; } ?></td>
	</tr>
<?php } ?>
</table>
<p class="fSmall">Turn it off for someone who lost both their phone and their recovery codes; they set it up again after signing in.</p>
<?php
	}
