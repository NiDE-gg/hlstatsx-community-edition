// Two-factor authentication setup (Tools > Two-Factor Authentication): draws the
// QR code of the otpauth:// address with the vendored qrcode-generator.
//
// Markup (no inline script, see CLAUDE.md):
//   <div data-totp-uri="otpauth://totp/..."></div>
// The key is also printed as text next to it, for apps that can't scan.
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		if (typeof qrcode !== 'function') {
			return;
		}
		document.querySelectorAll('[data-totp-uri]').forEach(function (box) {
			// Type 0: the smallest QR version that holds the address; level M
			var qr = qrcode(0, 'M');
			qr.addData(box.getAttribute('data-totp-uri'));
			qr.make();

			var img = document.createElement('img');
			img.src = qr.createDataURL(5, 4);
			img.alt = 'QR code for the authenticator app';
			img.style.imageRendering = 'pixelated';
			box.appendChild(img);
		});
	});
})();
