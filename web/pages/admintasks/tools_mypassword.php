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

	// Any signed-in admin (Restricted included) changes their own password here.
	// Other accounts are managed in Admin Users (Administrators only).
	if ($auth->userdata['acclevel'] < 80) {
		die('Access denied!');
	}
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat"><b>&nbsp;<?php echo $task->title; ?></b><p>

<?php
	if (isset($_POST['current_password']) && Auth::postAllowed()) {
		$current = is_string($_POST['current_password']) ? $_POST['current_password'] : '';
		$new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
		$repeat = is_string($_POST['new_password_repeat'] ?? null) ? $_POST['new_password_repeat'] : '';

		// The current password is asked so a session left open, or a stolen session
		// cookie, can't take the account over. Its checks count like failed logins.
		$refused = Auth::countPasswordAttempt($auth->username);
		if ($refused !== null) {
			Auth::recordSignIn('confirm', 'refused', $auth->username);
			message('warning', eHtml($refused));
		} elseif (!Auth::verifyPassword($current, $auth->userdata['password'])) {
			Auth::recordSignIn('confirm', 'failure', $auth->username);
			message('warning', 'The current password is incorrect.');
		} else {
			Auth::recordSignIn('confirm', 'success', $auth->username);
			Auth::passwordAttemptSucceeded($auth->username);
			$problem = ($new === $repeat) ? $auth->changeOwnPassword($new) : 'The two new passwords do not match.';
			if ($problem === null) {
				message('success', 'Password changed. Your other sessions on this account have ended.');
			} else {
				message('warning', eHtml($problem));
			}
		}
	}
?>
<form method="post" action="<?php echo eHtml($g_options['scripturl'] . '?mode=admin&task=tools_mypassword'); ?>">
	<?php echo Auth::csrfField(); ?>
	<table class="data-table">
		<tr><td class="bg1">Account:</td><td class="bg1"><strong><?php echo eHtml($auth->username); ?></strong></td></tr>
		<tr><td class="bg1">Current password:</td>
			<td class="bg1"><input type="password" name="current_password" size="30" maxlength="<?php echo Auth::MAX_PASSWORD_LENGTH; ?>" autocomplete="current-password" class="textbox" /></td></tr>
		<tr><td class="bg1">New password (<?php echo Auth::MIN_PASSWORD_LENGTH; ?> characters or more):</td>
			<td class="bg1"><input type="password" name="new_password" size="30" maxlength="<?php echo Auth::MAX_PASSWORD_LENGTH; ?>" autocomplete="new-password" class="textbox" /></td></tr>
		<tr><td class="bg1">Repeat it:</td>
			<td class="bg1"><input type="password" name="new_password_repeat" size="30" maxlength="<?php echo Auth::MAX_PASSWORD_LENGTH; ?>" autocomplete="new-password" class="textbox" /></td></tr>
		<tr><td class="bg1">&nbsp;</td><td class="bg1"><input type="submit" value=" Change password " class="submit" /></td></tr>
	</table>
</form>
