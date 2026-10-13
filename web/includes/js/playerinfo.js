/*
 * JS for web/pages/playerinfo_general.php's forum-signature BBCode snippets.
 *
 * Extracted from an inline PHP-generated <script> block (GitHub issue #45).
 * The pre-built, jsEscape()'d snippet text is bootstrapped as JSON
 * (#playerinfo-forum-text-data) instead of being interpolated into JS
 * source.
 *
 * setForumText() is declared on the global scope on purpose: the markup
 * still invokes it via inline onclick="setForumText(...)" attributes, so it
 * must stay callable by name.
 */
(function() {
	const dataEl = document.getElementById('playerinfo-forum-text-data');
	if (!dataEl) {
		return;
	}

	const forumTexts = JSON.parse(dataEl.textContent);

	window.setForumText = function(val) {
		const txtArea = document.getElementById('siglink');
		if (!txtArea) {
			return;
		}
		txtArea.value = forumTexts[val];
	};
})();
