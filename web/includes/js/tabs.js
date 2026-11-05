/**
 * Tabs
 *
 * AJAX-loaded "sub-section" tabs on awards.php, claninfo.php and
 * playerinfo.php (when the playerinfo_tabs option is on).
 *
 * Markup contract (the CSS depends on it, do not rename):
 *   - The container the tab content goes into carries the options as JSON:
 *       <div id="main_content" data-tabs='{"mode":"playerinfo","game":"css",
 *            "defaultTab":"general_aliases","loadingImage":"...",
 *            "extra":{"player":"1"}}'></div>
 *     `togglers` (optional) is the CSS selector of the tab links, default
 *     "#main ul.subsection_tabs a".
 *   - Each tab link is an <a id="tab_<name>">. The "tab_" prefix is stripped
 *     to get the `tab` value sent to the server; the active link gets
 *     class="active", the others lose their class.
 *   - Clicking a link (or loading the page) GETs
 *     hlstats.php?mode=<mode>&type=ajax&game=<game>&tab=<name>&<extra>
 *     and inserts the HTML in a new child of the container. Loaded panels are
 *     kept (hidden), so switching back does not fetch again.
 *   - The open tab is kept in the URL hash (#tab=<name>), so a reload or a
 *     shared link opens the same tab.
 *   - Elements inside a tab can reload it with other parameters:
 *       <a href="..." data-tab-refresh='{"sort":"kills","sortorder":"asc"}'>
 *       <select data-tab-refresh-param="killLimit"
 *               data-tab-refresh='{"playerkills_page":1}'>
 *     (sort arrows, pagination and the kill-limit select). The href stays a
 *     normal link for pages without tabs.
 *   - <script> tags in a response are executed in document order; add
 *     data-once to a library <script src> to load it only once per page.
 *
 * Tabs.refreshTab(params) (static) still refreshes the page's tabs, for
 * custom templates that call it.
 */
(() => {
	'use strict';

	// Flat (or nested) object to query string, nested keys in bracket
	// notation, null/undefined skipped.
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
		return parts.filter(Boolean).join('&');
	}

	const loadedOnce = new Set();

	// Scripts inserted with innerHTML are inert, so they are re-created.
	// Created scripts are async by default, which let playerinfo-trend-chart.js
	// run before chart.js had loaded: async = false keeps document order.
	function runScripts(root) {
		root.querySelectorAll('script').forEach((oldScript) => {
			const src = oldScript.getAttribute('src');
			if (src && oldScript.hasAttribute('data-once')) {
				if (loadedOnce.has(src)) {
					oldScript.remove();
					return;
				}
				loadedOnce.add(src);
			}

			const newScript = document.createElement('script');
			for (const attr of oldScript.attributes) {
				newScript.setAttribute(attr.name, attr.value);
			}
			newScript.async = false;
			newScript.textContent = oldScript.textContent;
			oldScript.replaceWith(newScript);
		});
	}

	function tabName(toggler) {
		return toggler.id.replace(/^tab_/, '');
	}

	function parseJson(text, fallback) {
		try {
			return JSON.parse(text);
		} catch (e) {
			console.error('Tabs: invalid JSON', text, e);
			return fallback;
		}
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

			Tabs.instances.push(this);

			this.togglers.forEach((toggler, idx) => {
				toggler.addEventListener('click', (e) => {
					// href="#" used to jump to the top of the page and add "#" to the URL
					e.preventDefault();
					this.loadTab(toggler, idx, true);
				});
			});

			const fromHash = /^#tab=([a-z_|]+)$/i.exec(window.location.hash);
			const wanted = fromHash ? fromHash[1] : this.options.defaultTab;
			let start = this.togglers.findIndex((t) => tabName(t) === wanted);
			if (start < 0) {
				start = this.togglers.findIndex((t) => tabName(t) === this.options.defaultTab);
			}
			if (start >= 0) {
				this.loadTab(this.togglers[start], start, false);
			}
		}

		static refreshTab(change) {
			const tabs = Tabs.instances[Tabs.instances.length - 1];
			if (tabs) {
				tabs.refreshTab(change);
			}
		}

		static forElement(el) {
			return Tabs.instances.find((tabs) => tabs.container.contains(el)) || null;
		}

		removeLoading() {
			if (this.loading) {
				this.loading.remove();
				this.loading = null;
			}
		}

		showLoading() {
			this.loading = document.createElement('div');
			this.loading.style.textAlign = 'center';
			this.loading.append(document.createElement('br'), document.createElement('br'));
			const label = document.createElement('b');
			label.textContent = 'Loading...';
			this.loading.append(label, document.createElement('br'));
			if (this.options.loadingImage) {
				const img = document.createElement('img');
				img.src = this.options.loadingImage;
				img.alt = 'Loading...';
				this.loading.append(img);
			}
			this.container.appendChild(this.loading);
		}

		updateTab(html, idx, failed) {
			this.removeLoading();

			const div = document.createElement('div');
			div.innerHTML = html;
			if (failed) {
				// Kept so the message shows, but fetched again on the next click
				div.dataset.failed = '1';
			}
			this.container.appendChild(div);
			this.container.style.minHeight = '';
			this.elements[idx] = div;

			runScripts(div);

			this.currentRequest = null;
		}

		refreshTab(change) {
			this.options.extra = Object.assign({}, this.options.extra, change);

			const idx = this.togglers.findIndex((t) => tabName(t) === this.currentTab);
			if (idx < 0) {
				return;
			}
			if (this.elements[idx]) {
				this.elements[idx].remove();
				this.elements[idx] = null;
			}
			this.loadTab(this.togglers[idx], idx, false);
		}

		loadTab(toggler, idx, fromClick) {
			const tab = tabName(toggler);
			const cached = this.elements[idx];
			const usable = cached && !cached.dataset.failed && this.container.contains(cached);

			// Current tab, already loaded and visible
			if (this.currentTab === tab && usable && cached.style.display !== 'none') {
				return;
			}

			this.togglers.forEach((t) => { t.className = ''; });
			toggler.className = 'active';
			this.currentTab = tab;

			if (fromClick) {
				history.replaceState(null, '', '#tab=' + tab);
			}

			if (this.currentRequest) {
				this.currentRequest.abort();
				this.currentRequest = null;
			}
			this.removeLoading();

			// Keep the height while the next panel loads, so the page does not
			// shrink under the visitor and jump back up
			this.container.style.minHeight = this.container.offsetHeight + 'px';

			Array.from(this.container.children).forEach((el) => {
				el.style.display = 'none';
			});

			if (usable) {
				cached.style.display = 'block';
				this.container.style.minHeight = '';
				return;
			}
			if (cached) {
				cached.remove();
				this.elements[idx] = null;
			}

			this.showLoading();

			const controller = new AbortController();
			this.currentRequest = controller;

			const url = 'hlstats.php?mode=' + encodeURIComponent(this.options.mode)
				+ '&type=ajax&game=' + encodeURIComponent(this.options.game)
				+ '&tab=' + encodeURIComponent(tab)
				+ '&' + toQueryString(this.options.extra);

			fetch(url, { signal: controller.signal })
				.then((response) => {
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					return response.text();
				})
				.then((html) => {
					// The visitor may have switched tabs while the body was read
					if (this.currentRequest === controller) {
						this.updateTab(html, idx, false);
					}
				})
				.catch((err) => {
					if (err.name === 'AbortError' || this.currentRequest !== controller) {
						return;
					}
					console.error('Tabs: failed to load tab "' + tab + '"', err);
					// Don't insert the server's error page; show a short message instead.
					this.updateTab('<p style="text-align:center;">This section could not be loaded. Please try again later.</p>', idx, true);
				});
		}
	}

	Tabs.instances = [];

	document.addEventListener('click', (e) => {
		const link = e.target.closest('[data-tab-refresh]:not(select)');
		const tabs = link && Tabs.forElement(link);
		if (!tabs) {
			// Not inside tabs: a normal link
			return;
		}
		e.preventDefault();
		tabs.refreshTab(parseJson(link.dataset.tabRefresh, {}));
	});

	document.addEventListener('change', (e) => {
		const select = e.target.closest('select[data-tab-refresh-param]');
		const tabs = select && Tabs.forElement(select);
		if (!tabs) {
			return;
		}
		const change = parseJson(select.dataset.tabRefresh || '{}', {});
		change[select.dataset.tabRefreshParam] = select.value;
		tabs.refreshTab(change);
	});

	const init = () => {
		document.querySelectorAll('[data-tabs]').forEach((container) => {
			const options = parseJson(container.dataset.tabs, null);
			if (options) {
				new Tabs(container, document.querySelectorAll(options.togglers || '#main ul.subsection_tabs a'), options);
			}
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.Tabs = Tabs;
})();
