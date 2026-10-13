/*
 * Per-server load/uptime/fps trend chart (GitHub issue #57 phase 5, final
 * phase of the pChart/GD -> client-side chart migration).
 *
 * Replaces the old GD-rendered PNG (web/show_graph.php?type=0, now removed)
 * with a client-side Chart.js chart fed by web/api/server_load.php. Used on:
 *   - pages/game.php   - "Participating Servers" accordion (one canvas per
 *                         time range per server) and the non-slider server
 *                         list (one canvas per server, default range)
 *   - pages/servers.php - single-server "Server Load History" panel, one
 *                         canvas per time range (1-4)
 *   - pages/ingame/load.php - one canvas, default range
 *
 * A single page can render many of these canvases at once (multiple
 * servers x multiple ranges), so this script queries every
 * `canvas.server-load-chart` element on the page and initializes each
 * independently, reading its own server id / range / colors from data-*
 * attributes set on the canvas itself - no shared global state, no id
 * collisions, matching the querySelectorAll+forEach idiom already used by
 * includes/js/accordion.js.
 */
(function() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		document.querySelectorAll('canvas.server-load-chart').forEach(initChart);
	}

	function showFallback(canvas, textColor, message) {
		const p = document.createElement('p');
		p.style.color = textColor;
		p.style.textAlign = 'center';
		p.style.margin = '0';
		p.textContent = message;
		canvas.replaceWith(p);
	}

	function toColor(value, fallback) {
		if (typeof value !== 'string' || value === '') {
			return fallback;
		}
		return value.charAt(0) === '#' ? value : '#' + value;
	}

	function pad(n) {
		return n < 10 ? '0' + n : '' + n;
	}

	// Mirrors show_graph.php's per-range axis label formatting
	// (drawItems()'s $server_load_type switch: HH:MM for the 24h view,
	// weekday for the week view, day-of-month for the month view, month
	// name for the year view).
	function formatLabel(ts, range) {
		const d = new Date(ts * 1000);
		switch (range) {
			case 2:
				return ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()];
			case 3:
				return pad(d.getDate());
			case 4:
				return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][d.getMonth()];
			case 1:
			default:
				return pad(d.getHours()) + ':' + pad(d.getMinutes());
		}
	}

	function initChart(canvas) {
		const serverId = parseInt(canvas.dataset.serverId, 10);
		const range = parseInt(canvas.dataset.range, 10) || 1;
		const bgColor = toColor(canvas.dataset.bgColor, '#282828');
		const textColor = toColor(canvas.dataset.textColor, '#ffffff');

		if (canvas.parentNode) {
			canvas.parentNode.style.backgroundColor = bgColor;
		}

		if (!serverId || serverId < 1) {
			showFallback(canvas, textColor, 'Invalid Server');
			return;
		}

		fetch('api/server_load.php?server_id=' + encodeURIComponent(serverId) + '&range=' + encodeURIComponent(range))
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				renderChart(canvas, data, range, textColor);
			})
			.catch(function(e) {
				console.warn('server-load-chart: failed to load server load data.', e);
				showFallback(canvas, textColor, 'Not Enough Data');
			});
	}

	function averagesTitle(averages, textColor) {
		if (!averages || (averages.last_hour === null && averages.last_day === null)) {
			return { display: false };
		}
		const lastHour = averages.last_hour === null ? 'n/a' : averages.last_hour;
		const lastDay = averages.last_day === null ? 'n/a' : averages.last_day;
		return {
			display: true,
			position: 'top',
			align: 'end',
			color: textColor,
			font: { size: 11, weight: 'normal' },
			text: 'Average Players Last 24h: ' + lastDay + '  Last 1h: ' + lastHour,
		};
	}

	function renderChart(canvas, data, range, textColor) {
		// API rows are newest-first (same order show_graph.php queried in);
		// reverse to chronological order so the chart reads left-to-right
		// with "now" on the right, like the old PNG did.
		const rows = Array.isArray(data.data) ? data.data.slice().reverse() : [];

		// Mirrors show_graph.php's `if (count($data_array) > 1)` guard.
		if (rows.length < 2) {
			showFallback(canvas, textColor, 'Not Enough Data');
			return;
		}

		const labels = rows.map(function(row) {
			return formatLabel(row.timestamp, range);
		});

		new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Max Players',
						data: rows.map(function(r) { return r.max_players; }),
						yAxisID: 'y',
						borderColor: '#999999',
						backgroundColor: 'rgba(153, 153, 153, 0.15)',
						fill: true,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'Min Players',
						data: rows.map(function(r) { return r.min_players; }),
						yAxisID: 'y',
						borderColor: '#99aaaa',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'Active Players',
						data: rows.map(function(r) { return r.act_players; }),
						yAxisID: 'y',
						borderColor: '#3b6bff',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 2,
					},
					{
						label: 'Uptime',
						data: rows.map(function(r) { return r.uptime; }),
						yAxisID: 'y1',
						borderColor: '#ffa500',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'FPS',
						data: rows.map(function(r) { return r.fps; }),
						yAxisID: 'y1',
						borderColor: '#ff3b3b',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
				],
			},
			options: {
				responsive: false,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12 } },
					title: averagesTitle(data.averages, textColor),
				},
				scales: {
					x: {
						ticks: { color: textColor, autoSkip: true, maxTicksLimit: 10, maxRotation: 0 },
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y: {
						type: 'linear',
						position: 'left',
						beginAtZero: true,
						title: { display: true, text: 'Players', color: textColor },
						ticks: { color: textColor },
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y1: {
						type: 'linear',
						position: 'right',
						beginAtZero: true,
						title: { display: true, text: 'Uptime / FPS', color: textColor },
						ticks: { color: textColor },
						grid: { display: false },
					},
				},
			},
		});
	}
})();
