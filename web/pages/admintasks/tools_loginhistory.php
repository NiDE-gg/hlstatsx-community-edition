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

	// Admin sign-in history (hlstats_LoginLog, kept 90 days). Administrators see every
	// account, unknown usernames included, and can filter by account or address; other
	// admins see their own account only.
	if ($auth->userdata['acclevel'] < 80) {
		die('Access denied!');
	}

	$isAdministrator = (int) $auth->userdata['acclevel'] >= 100;
	$filterUser = $isAdministrator ? mb_substr(trim(is_string($_GET['user'] ?? null) ? $_GET['user'] : ''), 0, 64) : $auth->username;
	$filterAddress = $isAdministrator ? substr(trim(is_string($_GET['address'] ?? null) ? $_GET['address'] : ''), 0, 45) : '';
	$taskUrl = $g_options['scripturl'] . '?mode=admin&task=tools_loginhistory';

	$methods = array(
		'password' => 'Password',
		'code'     => 'Authenticator code',
		'recovery' => 'Recovery code',
		'passkey'  => 'Passkey',
		'confirm'  => 'Password confirmation',
	);
	$results = array(
		'success' => 'Success',
		'pending' => 'Password right, code asked',
		'failure' => '<strong>Failed</strong>',
		'refused' => '<strong>Refused</strong> (too many attempts, or passkey required)',
	);
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width="9" height="6" class="imageformat"><b>&nbsp;<?php echo $task->title; ?></b><p>

<?php
	if ((int) ($g_options['dbversion'] ?? 0) < Auth::LOGIN_LOG_DBVERSION) {
		message('warning', 'The sign-in history needs the database update: please run it first.');
		return;
	}

	$rows = Auth::loginLog()->latest($filterUser, $filterAddress, 200);
?>
<p>The latest 200 entries<?php echo $isAdministrator ? '' : ' of your account'; ?>, kept 90 days. A refused address is listed once a minute however often it tries (an IPv6 /64 as one address).
Failed and refused attempts are also written to the PHP error log, one line each, for fail2ban (see the README).</p>

<?php if ($isAdministrator) { ?>
<form method="get" action="<?php echo eHtml($g_options['scripturl']); ?>">
	<input type="hidden" name="mode" value="admin" />
	<input type="hidden" name="task" value="tools_loginhistory" />
	Account: <input type="text" name="user" value="<?php echo eHtml($filterUser); ?>" size="16" maxlength="64" class="textbox" />
	Address: <input type="text" name="address" value="<?php echo eHtml($filterAddress); ?>" size="20" maxlength="45" class="textbox" />
	<input type="submit" value=" Filter " class="submit" />
	<?php if ($filterUser !== '' || $filterAddress !== '') { ?><a href="<?php echo eHtml($taskUrl); ?>">Show all</a><?php } ?>
</form>
<br />
<?php } ?>

<table class="data-table">
	<tr class="data-table-head"><td>Time</td><td>Account</td><td>Address</td><td>Method</td><td>Result</td></tr>
<?php if (!$rows) { ?>
	<tr class="bg1"><td colspan="5">Nothing recorded.</td></tr>
<?php }
	foreach ($rows as $row) {
		$account = (string) $row['username'];
		$address = (string) $row['address'];
?>
	<tr class="bg1">
		<td><?php echo eHtml(date('Y-m-d H:i:s', (int) $row['time'])); ?></td>
		<td><?php
		if ($account === '') {
			echo '<em>unknown passkey</em>';
		} elseif ($isAdministrator) {
			echo '<a href="' . eHtml($taskUrl . '&user=' . rawurlencode($account)) . '">' . eHtml($account) . '</a>';
		} else {
			echo eHtml($account);
		}
		?></td>
		<td><?php echo $isAdministrator ? '<a href="' . eHtml($taskUrl . '&address=' . rawurlencode($address)) . '">' . eHtml($address) . '</a>' : eHtml($address); ?></td>
		<td><?php echo eHtml($methods[$row['method']] ?? $row['method']); ?></td>
		<td><?php echo $results[$row['result']] ?? eHtml($row['result']); ?></td>
	</tr>
<?php } ?>
</table>
