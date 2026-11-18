// Passkey (WebAuthn) sign-in and registration for the admin pages.
//
// Markup (no inline script, see CLAUDE.md):
//   <button data-passkey-login data-passkey-api="api/passkey.php" data-csrf="...">
//   <button data-passkey-register data-passkey-api="..." data-csrf="..." data-label-input="id">
//   <span data-passkey-message></span>  (next to the button, for errors)
// The server (api/passkey.php) sends the WebAuthn options with binary values as
// base64url strings and expects the browser's answer the same way.
(function () {
	'use strict';

	function fromB64url(value) {
		var b64 = value.replace(/-/g, '+').replace(/_/g, '/');
		while (b64.length % 4) {
			b64 += '=';
		}
		var bin = atob(b64);
		var bytes = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) {
			bytes[i] = bin.charCodeAt(i);
		}
		return bytes.buffer;
	}

	function toB64url(buffer) {
		if (!buffer) {
			return '';
		}
		var bytes = new Uint8Array(buffer);
		var bin = '';
		for (var i = 0; i < bytes.length; i++) {
			bin += String.fromCharCode(bytes[i]);
		}
		return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	}

	function post(button, fields) {
		var body = new FormData();
		body.append('csrf_token', button.getAttribute('data-csrf'));
		Object.keys(fields).forEach(function (key) {
			body.append(key, fields[key]);
		});
		return fetch(button.getAttribute('data-passkey-api'), {
			method: 'POST',
			body: body,
			credentials: 'same-origin'
		}).then(function (response) {
			return response.json().then(function (json) {
				if (!response.ok || json.error) {
					throw new Error(json.error || ('HTTP ' + response.status));
				}
				return json;
			});
		});
	}

	function showMessage(button, text) {
		var box = button.parentNode.querySelector('[data-passkey-message]');
		if (box) {
			box.textContent = text;
		}
	}

	// A refused or cancelled browser prompt is not an error worth more than a line
	function describe(error) {
		if (error && error.name === 'NotAllowedError') {
			return 'The passkey request was cancelled or timed out.';
		}
		if (error && error.name === 'InvalidStateError') {
			return 'This device already holds a passkey for this account.';
		}
		return (error && error.message) ? error.message : String(error);
	}

	function signIn(button) {
		showMessage(button, '');
		post(button, { action: 'login-options' }).then(function (json) {
			var options = json.options.publicKey;
			options.challenge = fromB64url(options.challenge);
			(options.allowCredentials || []).forEach(function (cred) {
				cred.id = fromB64url(cred.id);
			});
			return navigator.credentials.get({ publicKey: options });
		}).then(function (credential) {
			return post(button, {
				action: 'login',
				id: toB64url(credential.rawId),
				clientDataJSON: toB64url(credential.response.clientDataJSON),
				authenticatorData: toB64url(credential.response.authenticatorData),
				signature: toB64url(credential.response.signature),
				userHandle: toB64url(credential.response.userHandle)
			});
		}).then(function () {
			// The page reloads signed in (a GET, so nothing is posted again)
			window.location.href = window.location.href.split('#')[0];
		}).catch(function (error) {
			showMessage(button, describe(error));
		});
	}

	function register(button) {
		showMessage(button, '');
		var labelInput = document.getElementById(button.getAttribute('data-label-input') || '');
		post(button, { action: 'register-options' }).then(function (json) {
			var options = json.options.publicKey;
			options.challenge = fromB64url(options.challenge);
			options.user.id = fromB64url(options.user.id);
			(options.excludeCredentials || []).forEach(function (cred) {
				cred.id = fromB64url(cred.id);
			});
			return navigator.credentials.create({ publicKey: options });
		}).then(function (credential) {
			return post(button, {
				action: 'register',
				clientDataJSON: toB64url(credential.response.clientDataJSON),
				attestationObject: toB64url(credential.response.attestationObject),
				label: labelInput ? labelInput.value : ''
			});
		}).then(function () {
			window.location.href = window.location.href.split('#')[0];
		}).catch(function (error) {
			showMessage(button, describe(error));
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var supported = !!(window.PublicKeyCredential && navigator.credentials);
		document.querySelectorAll('[data-passkey-login], [data-passkey-register]').forEach(function (button) {
			if (!supported) {
				button.disabled = true;
				showMessage(button, 'This browser does not support passkeys.');
				return;
			}
			button.hidden = false;
			button.addEventListener('click', function (event) {
				event.preventDefault();
				if (button.hasAttribute('data-passkey-login')) {
					signIn(button);
				} else {
					register(button);
				}
			});
		});
	});
})();
