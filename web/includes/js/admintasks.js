/*
 * Admin tools pages:
 *   - web/pages/admintasks/tools_reset.php: an option that includes others
 *     declares them in data-controls (a CSS selector inside the form).
 *     Checking it checks and disables them; unchecking it clears and enables
 *     them again. The server applies the same rules, since disabled
 *     checkboxes are not posted.
 *   - web/pages/admintasks/newserver.php: checks the form before it is sent,
 *     and redirects to the new server's settings once it has been added.
 */
(() => {
	'use strict';

	document.addEventListener('change', (e) => {
		const master = e.target.closest('input[type="checkbox"][data-controls]');
		if (!master || !master.form) {
			return;
		}
		master.form.querySelectorAll(master.dataset.controls).forEach((box) => {
			if (box !== master) {
				box.checked = master.checked;
				box.disabled = master.checked;
			}
		});
	});

	document.addEventListener('submit', (e) => {
		const form = e.target;
		if (form.name !== 'newserverform') {
			return;
		}
		if (!/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(form.server_address.value.trim())) {
			e.preventDefault();
			alert('Server address must be a valid IP address');
			return;
		}
		if (form.game_mod.value === 'PLEASESELECT') {
			e.preventDefault();
			alert('You must make a selection for Admin Mod');
		}
	});

	// newserver.php: after a server is added, PHP stops the page right after
	// this bootstrap block, and the browser goes to that server's settings.
	const redirectDataEl = document.getElementById('newserver-redirect-data');
	if (redirectDataEl) {
		try {
			const data = JSON.parse(redirectDataEl.textContent);
			if (data && data.redirectUrl) {
				window.location.href = data.redirectUrl;
			}
		} catch (err) {
			console.error('admintasks: invalid redirect data', err);
		}
	}
})();
