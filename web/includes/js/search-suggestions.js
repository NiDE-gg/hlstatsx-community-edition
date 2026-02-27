(function() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const input = document.getElementById('playersearch');
		if (!input) {
			console.warn('Element #playersearch not found.');
			return;
		}

		const container = document.createElement('div');
		container.className = 'autocomplete-items';
		input.parentNode.style.position = 'relative';
		input.parentNode.appendChild(container);

		let timeoutId = null;
		let currentController = null;
		const cache = new Map();

		function setLoading(loading) {
			if (loading) {
				input.classList.add('autocomplete-loading');
			} else {
				input.classList.remove('autocomplete-loading');
			}
		}

		input.addEventListener('input', function(e) {
			const query = e.target.value.trim();
			if (query.length < 2) {
				container.innerHTML = '';
				setLoading(false);
				return;
			}

			if (timeoutId) clearTimeout(timeoutId);
			timeoutId = setTimeout(() => {
				const urlParams = new URLSearchParams(window.location.search);
				const game = urlParams.get('game');
				if (!game) {
					console.warn('Game parameter missing');
					return;
				}

				const cacheKey = `${game}|${query}`;

				if (cache.has(cacheKey)) {
					container.innerHTML = cache.get(cacheKey);
					setLoading(false);
					return;
				}

				if (currentController) {
					currentController.abort();
				}

				currentController = new AbortController();

				setLoading(true);

				fetch('autocomplete.php?game=' + encodeURIComponent(game) + '&search=' + encodeURIComponent(query), {
					signal: currentController.signal
				})
					.then(response => response.text())
					.then(html => {
						cache.set(cacheKey, html);
						container.innerHTML = html;
						setLoading(false);
						currentController = null;
					})
					.catch(err => {
						if (err.name === 'AbortError') {
							console.log('Request aborted');
						} else {
							console.error('Autocomplete error:', err);
							container.innerHTML = '';
						}
						setLoading(false);
						currentController = null;
					});
			}, 300);
		});

		document.addEventListener('click', function(e) {
			if (!input.contains(e.target) && !container.contains(e.target)) {
				container.innerHTML = '';
				setLoading(false);
			}
		});

		container.addEventListener('click', function(e) {
			const li = e.target.closest('li');
			if (li) {
				input.value = li.textContent;
				container.innerHTML = '';
				setLoading(false);
			}
		});
	}
})();