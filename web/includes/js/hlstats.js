/**
 * Site-wide behaviors, loaded (deferred) by pages/header.php on every page.
 *
 * The markup carries no inline JavaScript (no on*="" attributes, no inline
 * <script>), so the site can run under a strict Content-Security-Policy.
 * Instead, elements opt in to a behavior with a data attribute, handled
 * here with delegated listeners (they also work for content inserted later,
 * e.g. AJAX tabs):
 *
 *   <select data-autosubmit>             submits its form on change
 *   <button data-href="url">             navigates to url on click
 *   <button data-clear-field="id">       empties #id, then submits the form
 *   <textarea data-select-on-click>      selects its whole content on click
 *   <input data-clear-value="(text)">    is emptied on focus while it still
 *                                        holds exactly that placeholder text
 *   <tr data-hover-class="cls">          has class "cls" (only) while hovered
 *   <div id="hlx-js-check">              see init() below
 */
(() => {
	'use strict';

	document.addEventListener('change', (e) => {
		const select = e.target.closest('select[data-autosubmit]');
		if (!select || !select.form) {
			return;
		}
		// Inside AJAX tabs, tabs.js reloads the tab instead (data-tab-refresh-param)
		if (select.dataset.tabRefreshParam && window.Tabs && window.Tabs.forElement(select)) {
			return;
		}
		select.form.submit();
	});

	document.addEventListener('click', (e) => {
		const link = e.target.closest('[data-href]');
		if (link) {
			e.preventDefault();
			window.location.href = link.dataset.href;
			return;
		}

		const clear = e.target.closest('[data-clear-field]');
		if (clear) {
			e.preventDefault();
			const field = document.getElementById(clear.dataset.clearField);
			if (field) {
				field.value = '';
			}
			if (clear.form) {
				clear.form.submit();
			}
			return;
		}

		const selectable = e.target.closest('[data-select-on-click]');
		if (selectable && typeof selectable.select === 'function') {
			selectable.select();
		}
	});

	document.addEventListener('focusin', (e) => {
		const input = e.target.closest('input[data-clear-value]');
		if (input && input.value === input.dataset.clearValue) {
			input.value = '';
		}
	});

	// The hover class replaces the row's classes while hovered (the themes
	// style "game-table-row-hover" on its own), as the old inline handlers did.
	// mouseover/mouseout bubble (mouseenter/mouseleave don't); moves between
	// cells of the same row are ignored.
	document.addEventListener('mouseover', (e) => {
		const row = e.target.closest('[data-hover-class]');
		if (row && !row.contains(e.relatedTarget) && row.dataset.hoverSaved === undefined) {
			row.dataset.hoverSaved = row.className;
			row.className = row.dataset.hoverClass;
		}
	});

	document.addEventListener('mouseout', (e) => {
		const row = e.target.closest('[data-hover-class]');
		if (row && !row.contains(e.relatedTarget) && row.dataset.hoverSaved !== undefined) {
			row.className = row.dataset.hoverSaved;
			delete row.dataset.hoverSaved;
		}
	});

	// header.php renders the no-JS version of the page (sections inline, no
	// tabs or maps) until it gets the hlx_js cookie.
	const init = () => {
		if (!document.getElementById('hlx-js-check')) {
			return;
		}
		document.cookie = 'hlx_js=1; path=/; max-age=31536000; SameSite=Lax';
		// Reload only if the cookie was accepted, otherwise every page would
		// reload forever. The marker is only sent on GET pages.
		if (/(?:^|;\s*)hlx_js=1(?:;|$)/.test(document.cookie)) {
			window.location.reload();
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
