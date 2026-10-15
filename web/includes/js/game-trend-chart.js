/*
 * Per-game kills/headshots/players/slots trend chart (GitHub issue #57
 * phase 5, final phase of the pChart/GD -> client-side chart migration).
 *
 * Replaces the old GD-rendered PNG (web/show_graph.php?type=1, now removed)
 * with a client-side Chart.js chart fed by web/api/game_trend.php. Used on
 * pages/game.php's "Server Load Graph" panel (shown when the
 * show_server_load_image option is enabled).
 *
 * Follows the same querySelectorAll+forEach idiom as server-load-chart.js
 * so that it keeps working correctly if more than one
 * `canvas.game-trend-chart` ever ends up on the same page. Zoom and pan
 * come from includes/js/chart-zoom.js.
 */
(function() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		document.querySelectorAll('canvas.game-trend-chart').forEach(initChart);
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

	// Several samples share a day, so once zoomed in (and in the tooltip)
	// the time is added to tell them apart.
	function formatLabel(ts, withTime) {
		const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		const d = new Date(ts * 1000);
		const day = months[d.getMonth()] + '-' + d.getDate();
		return withTime ? day + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : day;
	}

	function initChart(canvas) {
		const game = canvas.dataset.game || '';
		const bgColor = toColor(canvas.dataset.bgColor, '#282828');
		const textColor = toColor(canvas.dataset.textColor, '#ffffff');

		if (canvas.parentNode) {
			canvas.parentNode.style.backgroundColor = bgColor;
		}

		if (!game) {
			showFallback(canvas, textColor, 'Invalid Game');
			return;
		}

		fetch('api/game_trend.php?game=' + encodeURIComponent(game))
			.then(function(response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function(data) {
				renderChart(canvas, data, bgColor, textColor);
			})
			.catch(function(e) {
				console.warn('game-trend-chart: failed to load game trend data.', e);
				showFallback(canvas, textColor, 'Not Enough Data');
			});
	}

	function newPlayersTitle(newPlayers, textColor) {
		if (!newPlayers) {
			return { display: false };
		}
		return {
			display: true,
			position: 'top',
			align: 'end',
			color: textColor,
			font: { size: 13, weight: 'normal' },
			text: 'New Players Last 24h: ' + newPlayers.last_day + '  Last 1h: ' + newPlayers.last_hour,
		};
	}

	function renderChart(canvas, data, bgColor, textColor) {
		// API rows are newest-first; reverse to chronological order so the
		// chart reads left-to-right with "now" on the right, like the old
		// PNG did.
		const rows = Array.isArray(data.data) ? data.data.slice().reverse() : [];

		// Mirrors show_graph.php's `if (count($data_array) > 1)` guard.
		if (rows.length < 2) {
			showFallback(canvas, textColor, 'Not Enough Data');
			return;
		}

		const labels = rows.map(function(row) {
			return formatLabel(row.timestamp);
		});

		// css/mobile.css caps the canvas at its container's width on small
		// screens; draw at that width instead of scaling an 870px bitmap down.
		if (canvas.clientWidth > 0 && canvas.clientWidth < canvas.width) {
			canvas.width = canvas.clientWidth;
		}

		// Narrow screens: taller plot, fewer ticks, no axis titles (the legend
		// already names every series) and a larger font so the text stays legible.
		const compact = canvas.width < 600;
		if (compact) {
			canvas.height = 280;
		}
		Chart.defaults.font.size = 13;

		new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Kills',
						data: rows.map(function(r) { return r.kills; }),
						yAxisID: 'y',
						borderColor: '#ffa500',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'Headshots',
						data: rows.map(function(r) { return r.headshots; }),
						yAxisID: 'y',
						borderColor: '#99aaaa',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'Players',
						data: rows.map(function(r) { return r.players; }),
						yAxisID: 'y',
						borderColor: '#ff3b3b',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 2,
					},
					{
						label: 'Max Slots',
						data: rows.map(function(r) { return r.max_slots; }),
						yAxisID: 'y1',
						borderColor: '#999999',
						backgroundColor: 'rgba(153, 153, 153, 0.15)',
						fill: true,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
					{
						label: 'Active Slots',
						data: rows.map(function(r) { return r.act_slots; }),
						yAxisID: 'y1',
						borderColor: '#3b6bff',
						fill: false,
						tension: 0.3,
						pointRadius: 0,
						borderWidth: 1,
					},
				],
			},
			plugins: [HLStatsChartZoom.plugin],
			options: {
				responsive: false,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 14 } },
					title: newPlayersTitle(data.new_players, textColor),
					tooltip: {
						callbacks: {
							title: function(items) {
								return items.length ? formatLabel(rows[items[0].dataIndex].timestamp, true) : '';
							},
						},
					},
					hlstatsZoom: { textColor: textColor, bgColor: bgColor },
				},
				scales: {
					x: {
						ticks: {
							color: textColor,
							autoSkip: true,
							maxTicksLimit: compact ? 5 : 10,
							maxRotation: 0,
							callback: function(value) {
								return formatLabel(rows[value].timestamp, HLStatsChartZoom.isZoomed(this.chart));
							},
						},
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y: {
						type: 'linear',
						position: 'left',
						beginAtZero: true,
						title: { display: !compact, text: 'Kills / Headshots / Players', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y1: {
						type: 'linear',
						position: 'right',
						beginAtZero: true,
						title: { display: !compact, text: 'Slots', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { display: false },
					},
				},
			},
		});
	}
})();
