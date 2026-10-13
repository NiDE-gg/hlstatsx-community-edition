/*
 * Shared JS for the admin tools pages:
 *   - web/pages/admintasks/tools_reset.php
 *   - web/pages/admintasks/newserver.php
 *
 * Extracted from inline PHP-generated <script> blocks (GitHub issue #45).
 *
 * clear_all_delete_checked(), clear_all_checked(), clear_all_events_checked(),
 * name_history_checked() and checkMod() are declared on the global scope on
 * purpose: the markup on those pages still invokes them via inline
 * onclick="..." attributes (plain static HTML, not PHP-generated), so they
 * must stay callable by name.
 */

function clear_all_delete_checked() {
	if (document.resetform.clear_all_delete.checked) {
		document.resetform.clear_all.disabled = true;
		document.resetform.clear_all_events.disabled = true;
		document.resetform.clear_awards.disabled = true;
		document.resetform.clear_sessions.disabled = true;
		document.resetform.clear_names.disabled = true;
		document.resetform.clear_names_counts.disabled = true;
		document.resetform.clear_skill.disabled = true;
		document.resetform.clear_pcounts.disabled = true;
		document.resetform.clear_scounts.disabled = true;
		document.resetform.clear_wcounts.disabled = true;
		document.resetform.clear_acounts.disabled = true;
		document.resetform.clear_mcounts.disabled = true;
		document.resetform.clear_rcounts.disabled = true;
		document.resetform.clear_events_admin.disabled = true;
		document.resetform.clear_events_rcon.disabled = true;
		document.resetform.clear_events_connects.disabled = true;
		document.resetform.clear_events_disconnects.disabled = true;
		document.resetform.clear_events_entries.disabled = true;
		document.resetform.clear_events_chat.disabled = true;
		document.resetform.clear_events_changename.disabled = true;
		document.resetform.clear_events_changerole.disabled = true;
		document.resetform.clear_events_changeteam.disabled = true;
		document.resetform.clear_events_frags.disabled = true;
		document.resetform.clear_events_suicides.disabled = true;
		document.resetform.clear_events_teamkills.disabled = true;
		document.resetform.clear_events_statsme.disabled = true;
		document.resetform.clear_events_actions.disabled = true;
		document.resetform.clear_events_latency.disabled = true;
		document.resetform.clear_events_statsmetime.disabled = true;
		document.resetform.clear_all.checked = true;
		document.resetform.clear_all_events.checked = true;
		document.resetform.clear_awards.checked = true;
		document.resetform.clear_sessions.checked = true;
		document.resetform.clear_names.checked = true;
		document.resetform.clear_names_counts.checked = true;
		document.resetform.clear_skill.checked = true;
		document.resetform.clear_pcounts.checked = true;
		document.resetform.clear_scounts.checked = true;
		document.resetform.clear_wcounts.checked = true;
		document.resetform.clear_acounts.checked = true;
		document.resetform.clear_mcounts.checked = true;
		document.resetform.clear_rcounts.checked = true;
		document.resetform.clear_events_admin.checked = true;
		document.resetform.clear_events_rcon.checked = true;
		document.resetform.clear_events_connects.checked = true;
		document.resetform.clear_events_disconnects.checked = true;
		document.resetform.clear_events_entries.checked = true;
		document.resetform.clear_events_chat.checked = true;
		document.resetform.clear_events_changename.checked = true;
		document.resetform.clear_events_changerole.checked = true;
		document.resetform.clear_events_changeteam.checked = true;
		document.resetform.clear_events_frags.checked = true;
		document.resetform.clear_events_suicides.checked = true;
		document.resetform.clear_events_teamkills.checked = true;
		document.resetform.clear_events_statsme.checked = true;
		document.resetform.clear_events_actions.checked = true;
		document.resetform.clear_events_latency.checked = true;
		document.resetform.clear_events_statsmetime.checked = true;
	} else {
		document.resetform.clear_all.disabled = false;
		document.resetform.clear_all_events.disabled = false;
		document.resetform.clear_awards.disabled = false;
		document.resetform.clear_sessions.disabled = false;
		document.resetform.clear_names.disabled = false;
		document.resetform.clear_names_counts.disabled = false;
		document.resetform.clear_skill.disabled = false;
		document.resetform.clear_pcounts.disabled = false;
		document.resetform.clear_scounts.disabled = false;
		document.resetform.clear_wcounts.disabled = false;
		document.resetform.clear_acounts.disabled = false;
		document.resetform.clear_mcounts.disabled = false;
		document.resetform.clear_rcounts.disabled = false;
		document.resetform.clear_events_admin.disabled = false;
		document.resetform.clear_events_rcon.disabled = false;
		document.resetform.clear_events_connects.disabled = false;
		document.resetform.clear_events_disconnects.disabled = false;
		document.resetform.clear_events_entries.disabled = false;
		document.resetform.clear_events_chat.disabled = false;
		document.resetform.clear_events_changename.disabled = false;
		document.resetform.clear_events_changerole.disabled = false;
		document.resetform.clear_events_changeteam.disabled = false;
		document.resetform.clear_events_frags.disabled = false;
		document.resetform.clear_events_suicides.disabled = false;
		document.resetform.clear_events_teamkills.disabled = false;
		document.resetform.clear_events_statsme.disabled = false;
		document.resetform.clear_events_actions.disabled = false;
		document.resetform.clear_events_latency.disabled = false;
		document.resetform.clear_events_statsmetime.disabled = false;
		document.resetform.clear_all.checked = false;
		document.resetform.clear_all_events.checked = false;
		document.resetform.clear_awards.checked = false;
		document.resetform.clear_sessions.checked = false;
		document.resetform.clear_names.checked = false;
		document.resetform.clear_names_counts.checked = false;
		document.resetform.clear_skill.checked = false;
		document.resetform.clear_pcounts.checked = false;
		document.resetform.clear_scounts.checked = false;
		document.resetform.clear_wcounts.checked = false;
		document.resetform.clear_acounts.checked = false;
		document.resetform.clear_mcounts.checked = false;
		document.resetform.clear_rcounts.checked = false;
		document.resetform.clear_events_admin.checked = false;
		document.resetform.clear_events_rcon.checked = false;
		document.resetform.clear_events_connects.checked = false;
		document.resetform.clear_events_disconnects.checked = false;
		document.resetform.clear_events_entries.checked = false;
		document.resetform.clear_events_chat.checked = false;
		document.resetform.clear_events_changename.checked = false;
		document.resetform.clear_events_changerole.checked = false;
		document.resetform.clear_events_changeteam.checked = false;
		document.resetform.clear_events_frags.checked = false;
		document.resetform.clear_events_suicides.checked = false;
		document.resetform.clear_events_teamkills.checked = false;
		document.resetform.clear_events_statsme.checked = false;
		document.resetform.clear_events_actions.checked = false;
		document.resetform.clear_events_latency.checked = false;
		document.resetform.clear_events_statsmetime.checked = false;
	}
}

function clear_all_checked() {
	if (document.resetform.clear_all.checked) {
		document.resetform.clear_all_events.disabled = true;
		document.resetform.clear_awards.disabled = true;
		document.resetform.clear_sessions.disabled = true;
		document.resetform.clear_names.disabled = true;
		document.resetform.clear_names_counts.disabled = true;
		document.resetform.clear_skill.disabled = true;
		document.resetform.clear_pcounts.disabled = true;
		document.resetform.clear_scounts.disabled = true;
		document.resetform.clear_wcounts.disabled = true;
		document.resetform.clear_acounts.disabled = true;
		document.resetform.clear_mcounts.disabled = true;
		document.resetform.clear_rcounts.disabled = true;
		document.resetform.clear_events_admin.disabled = true;
		document.resetform.clear_events_rcon.disabled = true;
		document.resetform.clear_events_connects.disabled = true;
		document.resetform.clear_events_disconnects.disabled = true;
		document.resetform.clear_events_entries.disabled = true;
		document.resetform.clear_events_chat.disabled = true;
		document.resetform.clear_events_changename.disabled = true;
		document.resetform.clear_events_changerole.disabled = true;
		document.resetform.clear_events_changeteam.disabled = true;
		document.resetform.clear_events_frags.disabled = true;
		document.resetform.clear_events_suicides.disabled = true;
		document.resetform.clear_events_teamkills.disabled = true;
		document.resetform.clear_events_statsme.disabled = true;
		document.resetform.clear_events_actions.disabled = true;
		document.resetform.clear_events_latency.disabled = true;
		document.resetform.clear_events_statsmetime.disabled = true;
		document.resetform.clear_all_events.checked = true;
		document.resetform.clear_awards.checked = true;
		document.resetform.clear_sessions.checked = true;
		document.resetform.clear_names.checked = true;
		document.resetform.clear_names_counts.checked = true;
		document.resetform.clear_skill.checked = true;
		document.resetform.clear_pcounts.checked = true;
		document.resetform.clear_scounts.checked = true;
		document.resetform.clear_wcounts.checked = true;
		document.resetform.clear_acounts.checked = true;
		document.resetform.clear_mcounts.checked = true;
		document.resetform.clear_rcounts.checked = true;
		document.resetform.clear_events_admin.checked = true;
		document.resetform.clear_events_rcon.checked = true;
		document.resetform.clear_events_connects.checked = true;
		document.resetform.clear_events_disconnects.checked = true;
		document.resetform.clear_events_entries.checked = true;
		document.resetform.clear_events_chat.checked = true;
		document.resetform.clear_events_changename.checked = true;
		document.resetform.clear_events_changerole.checked = true;
		document.resetform.clear_events_changeteam.checked = true;
		document.resetform.clear_events_frags.checked = true;
		document.resetform.clear_events_suicides.checked = true;
		document.resetform.clear_events_teamkills.checked = true;
		document.resetform.clear_events_statsme.checked = true;
		document.resetform.clear_events_actions.checked = true;
		document.resetform.clear_events_latency.checked = true;
		document.resetform.clear_events_statsmetime.checked = true;
	} else {
		document.resetform.clear_all_events.disabled = false;
		document.resetform.clear_awards.disabled = false;
		document.resetform.clear_sessions.disabled = false;
		document.resetform.clear_names.disabled = false;
		document.resetform.clear_names_counts.disabled = false;
		document.resetform.clear_skill.disabled = false;
		document.resetform.clear_pcounts.disabled = false;
		document.resetform.clear_scounts.disabled = false;
		document.resetform.clear_wcounts.disabled = false;
		document.resetform.clear_acounts.disabled = false;
		document.resetform.clear_mcounts.disabled = false;
		document.resetform.clear_rcounts.disabled = false;
		document.resetform.clear_events_admin.disabled = false;
		document.resetform.clear_events_rcon.disabled = false;
		document.resetform.clear_events_connects.disabled = false;
		document.resetform.clear_events_disconnects.disabled = false;
		document.resetform.clear_events_entries.disabled = false;
		document.resetform.clear_events_chat.disabled = false;
		document.resetform.clear_events_changename.disabled = false;
		document.resetform.clear_events_changerole.disabled = false;
		document.resetform.clear_events_changeteam.disabled = false;
		document.resetform.clear_events_frags.disabled = false;
		document.resetform.clear_events_suicides.disabled = false;
		document.resetform.clear_events_teamkills.disabled = false;
		document.resetform.clear_events_statsme.disabled = false;
		document.resetform.clear_events_actions.disabled = false;
		document.resetform.clear_events_latency.disabled = false;
		document.resetform.clear_events_statsmetime.disabled = false;
		document.resetform.clear_all_events.checked = false;
		document.resetform.clear_awards.checked = false;
		document.resetform.clear_sessions.checked = false;
		document.resetform.clear_names.checked = false;
		document.resetform.clear_names_counts.checked = false;
		document.resetform.clear_skill.checked = false;
		document.resetform.clear_pcounts.checked = false;
		document.resetform.clear_scounts.checked = false;
		document.resetform.clear_wcounts.checked = false;
		document.resetform.clear_acounts.checked = false;
		document.resetform.clear_mcounts.checked = false;
		document.resetform.clear_rcounts.checked = false;
		document.resetform.clear_events_admin.checked = false;
		document.resetform.clear_events_rcon.checked = false;
		document.resetform.clear_events_connects.checked = false;
		document.resetform.clear_events_disconnects.checked = false;
		document.resetform.clear_events_entries.checked = false;
		document.resetform.clear_events_chat.checked = false;
		document.resetform.clear_events_changename.checked = false;
		document.resetform.clear_events_changerole.checked = false;
		document.resetform.clear_events_changeteam.checked = false;
		document.resetform.clear_events_frags.checked = false;
		document.resetform.clear_events_suicides.checked = false;
		document.resetform.clear_events_teamkills.checked = false;
		document.resetform.clear_events_statsme.checked = false;
		document.resetform.clear_events_actions.checked = false;
		document.resetform.clear_events_latency.checked = false;
		document.resetform.clear_events_statsmetime.checked = false;
	}
}

function clear_all_events_checked() {
	if (document.resetform.clear_all_events.checked) {
		document.resetform.clear_events_admin.disabled = true;
		document.resetform.clear_events_rcon.disabled = true;
		document.resetform.clear_events_connects.disabled = true;
		document.resetform.clear_events_disconnects.disabled = true;
		document.resetform.clear_events_entries.disabled = true;
		document.resetform.clear_events_chat.disabled = true;
		document.resetform.clear_events_changename.disabled = true;
		document.resetform.clear_events_changerole.disabled = true;
		document.resetform.clear_events_changeteam.disabled = true;
		document.resetform.clear_events_frags.disabled = true;
		document.resetform.clear_events_suicides.disabled = true;
		document.resetform.clear_events_teamkills.disabled = true;
		document.resetform.clear_events_statsme.disabled = true;
		document.resetform.clear_events_actions.disabled = true;
		document.resetform.clear_events_latency.disabled = true;
		document.resetform.clear_events_statsmetime.disabled = true;
		document.resetform.clear_events_admin.checked = true;
		document.resetform.clear_events_rcon.checked = true;
		document.resetform.clear_events_connects.checked = true;
		document.resetform.clear_events_disconnects.checked = true;
		document.resetform.clear_events_entries.checked = true;
		document.resetform.clear_events_chat.checked = true;
		document.resetform.clear_events_changename.checked = true;
		document.resetform.clear_events_changerole.checked = true;
		document.resetform.clear_events_changeteam.checked = true;
		document.resetform.clear_events_frags.checked = true;
		document.resetform.clear_events_suicides.checked = true;
		document.resetform.clear_events_teamkills.checked = true;
		document.resetform.clear_events_statsme.checked = true;
		document.resetform.clear_events_actions.checked = true;
		document.resetform.clear_events_latency.checked = true;
		document.resetform.clear_events_statsmetime.checked = true;
	} else {
		document.resetform.clear_events_admin.disabled = false;
		document.resetform.clear_events_rcon.disabled = false;
		document.resetform.clear_events_connects.disabled = false;
		document.resetform.clear_events_disconnects.disabled = false;
		document.resetform.clear_events_entries.disabled = false;
		document.resetform.clear_events_chat.disabled = false;
		document.resetform.clear_events_changename.disabled = false;
		document.resetform.clear_events_changerole.disabled = false;
		document.resetform.clear_events_changeteam.disabled = false;
		document.resetform.clear_events_frags.disabled = false;
		document.resetform.clear_events_suicides.disabled = false;
		document.resetform.clear_events_teamkills.disabled = false;
		document.resetform.clear_events_statsme.disabled = false;
		document.resetform.clear_events_actions.disabled = false;
		document.resetform.clear_events_latency.disabled = false;
		document.resetform.clear_events_statsmetime.disabled = false;
		document.resetform.clear_events_admin.checked = false;
		document.resetform.clear_events_rcon.checked = false;
		document.resetform.clear_events_connects.checked = false;
		document.resetform.clear_events_disconnects.checked = false;
		document.resetform.clear_events_entries.checked = false;
		document.resetform.clear_events_chat.checked = false;
		document.resetform.clear_events_changename.checked = false;
		document.resetform.clear_events_changerole.checked = false;
		document.resetform.clear_events_changeteam.checked = false;
		document.resetform.clear_events_frags.checked = false;
		document.resetform.clear_events_suicides.checked = false;
		document.resetform.clear_events_teamkills.checked = false;
		document.resetform.clear_events_statsme.checked = false;
		document.resetform.clear_events_actions.checked = false;
		document.resetform.clear_events_latency.checked = false;
		document.resetform.clear_events_statsmetime.checked = false;
	}
}

function name_history_checked() {
	if (document.resetform.clear_names.checked) {
		document.resetform.clear_names_counts.disabled = true;
		document.resetform.clear_names_counts.checked = true;
	} else {
		document.resetform.clear_names_counts.disabled = false;
		document.resetform.clear_names_counts.checked = false;
	}
}

function checkMod() {
	if (!document.newserverform.server_address.value.match(/^\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b$/)) {
		alert('Server address must be a valid IP address');
		return false;
	}
	if (document.newserverform.game_mod.value == 'PLEASESELECT') {
		alert('You must make a selection for Admin Mod');
		return false;
	}
	document.newserverform.submit();
}

// newserver.php: after successfully adding a server, the page is short-circuited
// (PHP calls exit() right after echoing its output) and redirected straight to
// that server's settings page. The target URL is passed in as JSON instead of
// being interpolated into JS source.
(function() {
	const redirectDataEl = document.getElementById('newserver-redirect-data');
	if (!redirectDataEl) {
		return;
	}

	const data = JSON.parse(redirectDataEl.textContent);
	if (data && data.redirectUrl) {
		window.location.href = data.redirectUrl;
	}
})();
