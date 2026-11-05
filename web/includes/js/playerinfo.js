/*
 * Forum signature snippets on web/pages/playerinfo_general.php.
 *
 * The snippet texts are bootstrapped as JSON (#playerinfo-forum-text-data);
 * links with data-forum-text="<index>" put one of them in #siglink. The data
 * is read on each click, since the General tab can be reloaded by tabs.js.
 */
(() => {
	'use strict';

	document.addEventListener('click', (e) => {
		const link = e.target.closest('[data-forum-text]');
		if (!link) {
			return;
		}
		e.preventDefault();

		const dataEl = document.getElementById('playerinfo-forum-text-data');
		const txtArea = document.getElementById('siglink');
		if (!dataEl || !txtArea) {
			return;
		}

		try {
			const forumTexts = JSON.parse(dataEl.textContent);
			txtArea.value = forumTexts[link.dataset.forumText] ?? '';
		} catch (err) {
			console.error('playerinfo: invalid forum text data', err);
		}
	});
})();
