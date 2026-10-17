/**
 * Tabs
 *
 * Vanilla ES6+ reimplementation of the former MooTools `Tabs` class, used for
 * AJAX-loaded "sub-section" tabs on awards.php, claninfo.php and
 * playerinfo.php (see pages/*.php where `playerinfo_tabs` is enabled).
 *
 * Markup/CSS contract (unchanged from the MooTools version, do not rename):
 *   - `container`: the element the loaded tab content is injected into
 *     (e.g. `#main_content`).
 *   - `togglers`: a list of `<a id="tab_<name>">` elements (e.g.
 *     `#main ul.subsection_tabs a`). The leading `tab_` prefix is stripped to
 *     get the `tab` value sent to the server; the currently active toggler
 *     gets `class="active"` and every other toggler's `class` attribute is
 *     cleared.
 *   - Clicking a toggler (or auto-loading `options.defaultTab` on init) GETs
 *     `hlstats.php?mode=<options.mode>&type=ajax&game=<options.game>&tab=<name>&<options.extra as querystring>`
 *     and injects the raw HTML response into a new child of `container`,
 *     hiding any previously loaded tab panels (they're cached, not removed,
 *     so switching back to a tab already loaded doesn't re-fetch it).
 *   - Any `<script>` tags in the AJAX response are re-executed (as the old
 *     MooTools `stripScripts(true)` call did), since e.g.
 *     playerinfo_weapons.php / claninfo_weapons.php define a global
 *     `switch_weapon()` function this way.
 *
 * Public API kept identical on purpose so no PHP template needs to change:
 *   - `new Tabs(container, togglers, options)`
 *   - `instance.refreshTab(extraParams)` - merges `extraParams` into
 *     `options.extra` and reloads the currently active tab. Called from
 *     generated `onclick="Tabs.refreshTab({...})"` attributes
 *     (includes/functions.php, includes/class_table.php) and from
 *     pages/playerinfo_killstats.php's killLimit <select>.
 *   - The global `Tabs` identifier is assigned with `var` (not `let`/`const`)
 *     so that `var Tabs = new Tabs(...)` in claninfo.php/playerinfo.php can
 *     legally re-bind it to the page's live instance, which is what makes
 *     `Tabs.refreshTab(...)` (above) resolve to the right object.
 */
var Tabs = (function () {
	'use strict';

	// Mirrors MooTools' Hash.toQueryString: encodes a flat (or nested)
	// object into a query string, recursing into nested objects/arrays with
	// bracket notation, and skipping null/undefined values.
	function toQueryString(data, base) {
		const parts = [];
		Object.keys(data || {}).forEach((key) => {
			const value = data[key];
			if (value === undefined || value === null) {
				return;
			}
			const paramKey = base ? `${base}[${key}]` : key;
			if (typeof value === 'object') {
				parts.push(toQueryString(value, paramKey));
			} else {
				parts.push(`${paramKey}=${encodeURIComponent(value)}`);
			}
		});
		return parts.join('&');
	}

	// Re-creates <script> elements found in a freshly-inserted DOM subtree so
	// the browser actually executes them (scripts inserted via innerHTML are
	// inert). Declarations like `function switch_weapon(){}` end up global,
	// same as the old stripScripts(true)+eval behavior.
	function runScripts(root) {
		root.querySelectorAll('script').forEach((oldScript) => {
			const newScript = document.createElement('script');
			for (const attr of oldScript.attributes) {
				newScript.setAttribute(attr.name, attr.value);
			}
			newScript.textContent = oldScript.textContent;
			oldScript.replaceWith(newScript);
		});
	}

	class Tabs {
		constructor(container, togglers, options) {
			this.options = Object.assign({
				defaultTab: false,
				loadingImage: false,
				game: null,
				mode: null,
				extra: {}
			}, options);

			this.container = typeof container === 'string' ? document.getElementById(container) : container;
			this.togglers = Array.from(togglers || []);
			this.elements = [];
			this.currentRequest = null;
			this.currentTab = null;
			this.loading = null;

			this.togglers.forEach((toggler, idx) => this.addTab(toggler, idx));
		}

		addTab(toggler, idx) {
			toggler.addEventListener('click', () => this.loadTab(toggler, idx));
			if (toggler.id.replace(/^tab_/, '') === this.options.defaultTab) {
				this.loadTab(toggler, idx);
			}
		}

		updateTab(txt, idx) {
			if (this.loading) {
				this.loading.remove();
				this.loading = null;
			}

			const div = document.createElement('div');
			div.innerHTML = txt;
			this.container.appendChild(div);
			this.elements[idx] = div;

			// Evaluate the response AFTER it's been inserted, same ordering
			// as the previous stripScripts(true) call.
			runScripts(div);

			this.currentRequest = null;
		}

		refreshTab(change) {
			this.options.extra = Object.assign({}, this.options.extra, change);

			for (let i = 0; i < this.togglers.length; i++) {
				if (this.togglers[i].id.replace(/^tab_/, '') === this.currentTab) {
					if (this.elements[i]) {
						this.elements[i].remove();
					}
					this.loadTab(this.togglers[i], i);
					return;
				}
			}
		}

		loadTab(toggler, idx) {
			const tab = toggler.id.replace(/^tab_/, '');

			// Current tab, and already loaded/visible? Just return.
			if (this.currentTab === tab && this.elements[idx] && this.container.contains(this.elements[idx])) {
				return;
			}

			// Mark the active toggler.
			this.togglers.forEach((t) => { t.className = ''; });
			toggler.className = 'active';
			this.currentTab = tab;

			// Cancel any in-flight request.
			if (this.currentRequest) {
				if (this.loading) {
					this.loading.remove();
					this.loading = null;
				}
				this.currentRequest.abort();
				this.currentRequest = null;
			}

			// Hide the currently shown tab panel(s).
			Array.from(this.container.children).forEach((el) => {
				el.style.display = 'none';
			});

			// Already cached? Just show it.
			if (this.elements[idx] && this.container.contains(this.elements[idx])) {
				this.elements[idx].style.display = 'block';
				return;
			}

			// Loading indicator (may change in the future).
			this.loading = document.createElement('div');
			this.loading.innerHTML = '<br /><br /><center><b>Loading...</b><br /><img src="' + this.options.loadingImage + '" alt="Loading..." /></center>';
			this.container.appendChild(this.loading);

			// AJAX FTW
			const controller = new AbortController();
			this.currentRequest = controller;

			const url = 'hlstats.php?mode=' + encodeURIComponent(this.options.mode) + '&type=ajax&game=' + encodeURIComponent(this.options.game) + '&tab=' + encodeURIComponent(tab) + '&' + toQueryString(this.options.extra);

			fetch(url, { signal: controller.signal })
				.then((response) => {
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					return response.text();
				})
				.then((txt) => this.updateTab(txt, idx))
				.catch((err) => {
					if (err.name !== 'AbortError') {
						console.error('Tabs: failed to load tab "' + tab + '"', err);
						// Don't inject the server's error page; show a short message instead.
						this.updateTab('<p style="text-align:center;">This section could not be loaded. Please try again later.</p>', idx);
						this.elements[idx] = null;
					}
				});
		}
	}

	return Tabs;
})();
