/*
 * Help popups of web/pages/admin.php's EditList::setHelp() (used by
 * web/pages/admintasks/serversettings.php).
 *
 * The help texts are bootstrapped as JSON in #admin-help-data; the fields
 * carry data-help-key="<parameter>". The text shows while a field is hovered
 * or has the keyboard focus.
 */
(() => {
	'use strict';

	const dataEl = document.getElementById('admin-help-data');
	if (!dataEl) {
		return;
	}

	let data;
	try {
		data = JSON.parse(dataEl.textContent);
	} catch (e) {
		console.error('admin-help: invalid JSON', e);
		return;
	}

	const texts = data.texts || {};
	const target = () => document.getElementById(data.div);

	const show = (keyname) => {
		const el = target();
		if (!el) {
			return;
		}
		// The texts are written by the HLstatsX authors and may contain markup
		el.innerHTML = texts[keyname] || 'No help text available';
		el.style.visibility = 'visible';
	};

	const hide = () => {
		const el = target();
		if (el) {
			el.style.visibility = 'hidden';
		}
	};

	const onEnter = (e) => {
		const field = e.target.closest('[data-help-key]');
		if (field) {
			show(field.dataset.helpKey);
		}
	};

	const onLeave = (e) => {
		if (e.target.closest('[data-help-key]')) {
			hide();
		}
	};

	document.addEventListener('mouseover', onEnter);
	document.addEventListener('mouseout', onLeave);
	document.addEventListener('focusin', onEnter);
	document.addEventListener('focusout', onLeave);
})();
