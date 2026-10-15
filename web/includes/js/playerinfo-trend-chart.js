/*
 * Player skill / session-delta trend chart (GitHub issue #57 phase 4).
 *
 * Replaces the old pChart-rendered PNG (web/trend_graph.php) on
 * pages/playerinfo_general.php's "Player Trend" panel with a client-side
 * Chart.js chart fed by web/api/player_trend.php.
 *
 * Reads the player id and the page's theme colors (graphbg_trend /
 * graphtxt_trend options) from the JSON bootstrap block the page renders
 * at #playerinfo-trend-data (same idiom as web/includes/js/admintasks.js's
 * #newserver-redirect-data block), fetches the JSON history, and draws a
 * dual-axis line chart: skill (left axis, scaled /1000, filled/smooth
 * line) and session skill_change (right axis, plotted line), against a
 * sparse date axis (first/middle/last of the returned rows), matching the
 * old PNG's label spacing. Chart.js is loaded as a page-local
 * <script src> (web/includes/js/chart.js, vendored UMD build), so it
 * exposes a global `Chart` constructor - no module bundler involved.
 * Zoom and pan come from includes/js/chart-zoom.js.
 */
(function() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const canvas = document.getElementById('playerinfo-trend-chart');
		const dataEl = document.getElementById('playerinfo-trend-data');
		if (!canvas || !dataEl) {
			return;
		}

		let config;
		try {
			config = JSON.parse(dataEl.textContent);
		} catch (e) {
			console.warn('playerinfo-trend-chart: invalid bootstrap JSON.', e);
			showFallback(canvas, '#d5d9dd');
			return;
		}

		const player = parseInt(config.player, 10);
		const bgColor = typeof config.bgColor === 'string' ? config.bgColor : '#5a5a5a';
		const textColor = typeof config.textColor === 'string' ? config.textColor : '#d5d9dd';

		if (!player || player < 1) {
			showFallback(canvas, textColor);
			return;
		}

		fetch('api/player_trend.php?player=' + encodeURIComponent(player))
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
				console.warn('playerinfo-trend-chart: failed to load trend data.', e);
				showFallback(canvas, textColor);
			});
	}

	function showFallback(canvas, textColor) {
		const p = document.createElement('p');
		p.style.color = textColor;
		p.style.textAlign = 'center';
		p.style.margin = '0';
		p.textContent = 'Not Enough Session Data';
		canvas.replaceWith(p);
	}

	function formatDate(ts) {
		const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		const d = new Date(ts * 1000);
		return months[d.getMonth()] + '-' + d.getDate();
	}

	function renderChart(canvas, data, bgColor, textColor) {
		const history = Array.isArray(data.history) ? data.history : [];

		// Mirrors the old PNG renderer's $emptyData = (count($dateArr) < 2) check.
		if (history.length < 2) {
			showFallback(canvas, textColor);
			return;
		}

		const rowCount = history.length;
		const labels = history.map(function(row, i) {
			const idx = i + 1;
			if (idx === 1 || idx === Math.round(rowCount / 2) || idx === rowCount) {
				return formatDate(row.ts);
			}
			return '';
		});

		const skill = history.map(function(row) {
			return row.skill === 0 ? 0 : row.skill / 1000;
		});
		const skillChange = history.map(function(row) {
			return row.skill_change;
		});

		canvas.parentNode.style.backgroundColor = bgColor;

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
						label: 'Skill',
						data: skill,
						yAxisID: 'y',
						borderColor: '#ffff00',
						backgroundColor: 'rgba(255, 255, 0, 0.2)',
						fill: true,
						tension: 0.4,
						pointRadius: 2,
						pointBackgroundColor: '#ffffff',
					},
					{
						label: 'Session',
						data: skillChange,
						yAxisID: 'y1',
						borderColor: '#ff3b3b',
						backgroundColor: '#3b6bff',
						fill: false,
						tension: 0.4,
						pointRadius: 2,
						pointBackgroundColor: '#ffffff',
					}
				]
			},
			plugins: [HLStatsChartZoom.plugin],
			options: {
				responsive: false,
				maintainAspectRatio: false,
				interaction: {
					mode: 'index',
					intersect: false,
				},
				plugins: {
					legend: {
						position: 'bottom',
						labels: { color: textColor },
					},
					tooltip: {
						callbacks: {
							title: function(items) {
								return items.length ? formatDate(history[items[0].dataIndex].ts) : '';
							},
						},
					},
					hlstatsZoom: { textColor: textColor, bgColor: bgColor },
				},
				scales: {
					x: {
						ticks: {
							color: textColor,
							maxTicksLimit: compact ? 5 : undefined,
							// Zoomed in, the first/middle/last labels would
							// mostly fall outside the window: date every tick.
							callback: function(value) {
								return HLStatsChartZoom.isZoomed(this.chart) ? formatDate(history[value].ts) : labels[value];
							},
						},
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y: {
						type: 'linear',
						position: 'left',
						title: { display: !compact, text: 'Skill (K)', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y1: {
						type: 'linear',
						position: 'right',
						title: { display: !compact, text: 'Session', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { display: false },
					},
				},
			},
		});
	}
})();
