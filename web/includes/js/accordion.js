/**
 * Accordion
 *
 * Vanilla ES6+ reimplementation of the former MooTools `Accordion`
 * (Fx.Elements-based) behavior used on game.php's "Participating Servers"
 * table when `$g_options['slider']` is enabled.
 *
 * Markup/CSS contract (unchanged from the MooTools version, do not rename):
 *   - A `<table id="accordion">` containing, for each server:
 *       - a `<tr class="toggler">` row (the clickable header row), and
 *       - immediately after it, a sibling `<tr>` whose single `<td>`
 *         contains a `<div class="opener">` (the collapsible content).
 *     Togglers and openers are paired up by their document order, exactly
 *     as the old `new Accordion($('accordion'), 'tr.toggler', 'div.opener', ...)`
 *     call did.
 *   - Only one opener is expanded at a time (classic accordion behavior).
 *     All sections start collapsed (MooTools was configured with
 *     `display: '-1'`, i.e. no section active on load).
 *   - Clicking the toggler of the currently-open section collapses it,
 *     leaving every section closed (MooTools' `alwaysHide: true`).
 *   - The active toggler's text is colored `#ff3300`; inactive togglers are
 *     `#222` (mirrors the old `onActive`/`onBackground` callbacks).
 *   - `<img>` elements inside an opener may carry a `delaysrc` attribute
 *     (see pages/game.php: the real `show_graph.php` URL is only wired up
 *     once JS is confirmed to run, to avoid firing off graph requests for
 *     collapsed/never-opened sections). The first time a section opens,
 *     any such image gets its real `src` swapped in from `delaysrc`.
 */
(function () {
	'use strict';

	function initAccordion(table) {
		const togglers = Array.from(table.querySelectorAll('tr.toggler'));
		const openers = Array.from(table.querySelectorAll('div.opener'));

		if (togglers.length === 0 || togglers.length !== openers.length) {
			return;
		}

		openers.forEach((opener) => {
			opener.style.overflow = 'hidden';
			opener.style.maxHeight = '0px';
			opener.style.transition = 'max-height 0.3s ease-in-out';
		});

		let activeIndex = -1;

		const collapse = (index) => {
			togglers[index].style.color = '#222';
			openers[index].style.maxHeight = '0px';
		};

		const expand = (index) => {
			const toggler = togglers[index];
			const opener = openers[index];

			toggler.style.color = '#ff3300';
			opener.style.maxHeight = opener.scrollHeight + 'px';

			opener.querySelectorAll('img[delaysrc]').forEach((img) => {
				const delaysrc = img.getAttribute('delaysrc');
				if (delaysrc) {
					img.src = delaysrc;
				}
			});
		};

		togglers.forEach((toggler, index) => {
			toggler.addEventListener('click', () => {
				const wasActive = activeIndex === index;

				togglers.forEach((t, i) => collapse(i));

				if (wasActive) {
					activeIndex = -1;
				} else {
					activeIndex = index;
					expand(index);
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		const table = document.getElementById('accordion');
		if (table) {
			initAccordion(table);
		}
	});
})();
