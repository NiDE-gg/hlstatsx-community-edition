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

	// Every signed-in admin manages their own passkeys here. Administrators also see
	// which accounts have one and can remove them: that is the recovery path when a
	// device is lost (or, with database access, DELETE FROM hlstats_Passkeys).
	if ($auth->userdata['acclevel'] < 80) {
		die('Access denied!');
	}

	require_once(INCLUDE_PATH . '/class_passkeys.php');
	$passkeyRepo = Passkeys::repository();
	$isAdministrator = (int) $auth->userdata['acclevel'] >= 100;
	$taskUrl = $g_options['scripturl'] . '?mode=admin&task=tools_passkeys';
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat"><b>&nbsp;<?php echo $task->title; ?></b><p>

<?php
	$unavailable = Passkeys::unavailableReason();
	if ($unavailable !== null) {
		message('warning', eHtml($unavailable));
		return;
	}

	if (Auth::postAllowed()) {
		$postId = (int) ($_POST['passkey_id'] ?? 0);

		if (isset($_POST['confirm_password'])) {
			// Adding or removing a passkey needs a recent password check, so an open
			// session alone can't put an attacker's passkey on the account
			$refused = Auth::countPasswordAttempt($auth->username);
			if ($refused !== null) {
				message('warning', eHtml($refused));
			} elseif (!Auth::verifyPassword(is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '', $auth->userdata['password'])) {
				message('warning', 'The password is incorrect.');
			} else {
				Auth::passwordAttemptSucceeded($auth->username);
				$_SESSION['authreauthTime'] = time();
				message('success', 'Password confirmed for ' . (int) (Passkeys::REAUTH_WINDOW / 60) . ' minutes.');
			}
		} elseif (isset($_POST['rename']) && $postId > 0) {
			$label = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', is_string($_POST['label'] ?? null) ? $_POST['label'] : '') ?? ''), 0, 64);
			if ($label !== '' && $passkeyRepo->rename($postId, $auth->username, $label)) {
				message('success', 'Passkey renamed.');
			}
		} elseif (isset($_POST['delete']) || isset($_POST['delete_all_for'])) {
			if (!Passkeys::recentlyAuthenticated()) {
				message('warning', 'Confirm your password below first.');
			} elseif (isset($_POST['delete']) && $postId > 0) {
				if ($passkeyRepo->delete($postId, $auth->username)) {
					message('success', 'Passkey removed. Remove it from the device too.');
				}
			} elseif (isset($_POST['delete_all_for']) && $isAdministrator && is_string($_POST['delete_all_for'])) {
				$removed = $passkeyRepo->deleteAllForUser($_POST['delete_all_for']);
				message('success', $removed . ' passkey(s) of ' . eHtml($_POST['delete_all_for']) . ' removed.');
			}
		}
	}

	$recent = Passkeys::recentlyAuthenticated();
	$mine = $passkeyRepo->forUser($auth->username);
	$required = ($g_options['passkey_required'] ?? '0') === '1';
?>
<p>A passkey signs you in with this device's fingerprint, face, PIN or a security key, instead of the password. It works on this site only (<?php echo eHtml(Passkeys::rpId()); ?>), so it can't be phished or reused elsewhere.
<?php if ($required && $isAdministrator) { ?>
<strong>Administrators with a passkey can only sign in with it</strong> (Settings): keep more than one, or ask another Administrator to remove them if you lose your device.
<?php } ?></p>

<table class="data-table">
	<tr class="data-table-head"><td>Name</td><td>Added</td><td>Last used</td><td>&nbsp;</td></tr>
<?php if (!$mine) { ?>
	<tr class="bg1"><td colspan="4">No passkey yet.</td></tr>
<?php }
	foreach ($mine as $row) { ?>
	<tr class="bg1">
		<td><form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;margin:0;">
			<?php echo Auth::csrfField(); ?>
			<input type="hidden" name="passkey_id" value="<?php echo (int) $row['id']; ?>" />
			<input type="text" name="label" value="<?php echo eHtml($row['label']); ?>" size="24" maxlength="64" class="textbox" />
			<input type="submit" name="rename" value="Rename" class="submit" />
		</form></td>
		<td><?php echo eHtml(date('Y-m-d H:i', (int) $row['created'])); ?></td>
		<td><?php echo $row['last_used'] ? eHtml(date('Y-m-d H:i', (int) $row['last_used'])) : 'never'; ?></td>
		<td><form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;margin:0;">
			<?php echo Auth::csrfField(); ?>
			<input type="hidden" name="passkey_id" value="<?php echo (int) $row['id']; ?>" />
			<input type="submit" name="delete" value="Remove" class="submit"<?php echo $recent ? '' : ' disabled title="Confirm your password first"'; ?> />
		</form></td>
	</tr>
<?php } ?>
</table>
<br />

<?php if ($recent) { ?>
<div>
	<label for="passkey-label">Name of the new passkey:</label>
	<input type="text" id="passkey-label" size="24" maxlength="64" class="textbox" placeholder="e.g. Laptop, YubiKey" />
	<button type="button" class="submit" hidden data-passkey-register data-passkey-api="api/passkey.php" data-csrf="<?php echo eHtml(Auth::csrfToken()); ?>" data-label-input="passkey-label"> Add a passkey </button>
	<span class="fSmall" data-passkey-message></span>
</div>
<script nonce="<?php echo cspNonce(); ?>" src="<?php echo asset_url(INCLUDE_PATH . '/js/passkeys.js'); ?>" defer></script>
<?php } else { ?>
<form method="post" action="<?php echo eHtml($taskUrl); ?>">
	<?php echo Auth::csrfField(); ?>
	To add or remove a passkey, confirm your password:
	<input type="password" name="confirm_password" size="24" maxlength="<?php echo Auth::MAX_PASSWORD_LENGTH; ?>" autocomplete="current-password" class="textbox" />
	<input type="submit" value=" Confirm " class="submit" />
</form>
<?php }

	if ($isAdministrator) {
		$counts = $passkeyRepo->countsByUser();
		unset($counts[$auth->username]);
?>
<br />
<table class="data-table">
	<tr class="data-table-head"><td colspan="3">Other accounts with passkeys</td></tr>
<?php if (!$counts) { ?>
	<tr class="bg1"><td colspan="3">None.</td></tr>
<?php }
		foreach ($counts as $user => $count) { ?>
	<tr class="bg1">
		<td><?php echo eHtml($user); ?></td>
		<td><?php echo (int) $count; ?> passkey(s)</td>
		<td><form method="post" action="<?php echo eHtml($taskUrl); ?>" style="display:inline;margin:0;">
			<?php echo Auth::csrfField(); ?>
			<input type="hidden" name="delete_all_for" value="<?php echo eHtml($user); ?>" />
			<input type="submit" value="Remove all" class="submit"<?php echo $recent ? '' : ' disabled title="Confirm your password first"'; ?> />
		</form></td>
	</tr>
<?php } ?>
</table>
<?php
	}
