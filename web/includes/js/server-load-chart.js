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
 *
 * The 24h view also brings back the old PNG's map overlay: each map played
 * gets an alternating background band, a separator and its name written
 * vertically at the top of the plot (names that don't fit are skipped until
 * the chart is zoomed in). Zoom and pan come from includes/js/chart-zoom.js.
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
	// name for the year view). Once zoomed in, the coarser views add the
	// next unit down so neighbouring ticks stay distinguishable.
	const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

	function formatTime(d) {
		return pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function formatLabel(ts, range, zoomed) {
		const d = new Date(ts * 1000);
		switch (range) {
			case 2:
				return zoomed ? DAYS[d.getDay()] + ' ' + formatTime(d) : DAYS[d.getDay()];
			case 3:
				return zoomed ? pad(d.getDate()) + ' ' + formatTime(d) : pad(d.getDate());
			case 4:
				return zoomed ? MONTHS[d.getMonth()] + ' ' + d.getDate() : MONTHS[d.getMonth()];
			case 1:
			default:
				return formatTime(d);
		}
	}

	// Tooltip title: the full date, whatever the range.
	function formatFull(ts) {
		const d = new Date(ts * 1000);
		return DAYS[d.getDay()] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ', ' + formatTime(d);
	}

	// Group consecutive rows by map. Rows without a map (the "right now"
	// point the API prepends) belong to the map before them.
	function mapSegments(rows) {
		const segments = [];
		let current = null;
		rows.forEach(function(row, i) {
			const map = row.map || (current ? current.map : '');
			if (!current || map !== current.map) {
				current = { map: map, start: i, end: i };
				segments.push(current);
			} else {
				current.end = i;
			}
		});
		return segments;
	}

	// Chart.js inline plugin drawing the map bands, separators and names
	// behind the data lines, like show_graph.php's "TSGK MAP TEXT" did.
	function mapNamesPlugin(segments, textColor) {
		const fontSize = 10;
		return {
			id: 'hlstatsMapNames',
			beforeDatasetsDraw: function(chart) {
				const ctx = chart.ctx;
				const area = chart.chartArea;
				const x = chart.scales.x;

				ctx.save();
				ctx.beginPath();
				ctx.rect(area.left, area.top, area.right - area.left, area.bottom - area.top);
				ctx.clip();
				ctx.strokeStyle = textColor;
				ctx.fillStyle = textColor;
				ctx.lineWidth = 1;
				ctx.font = fontSize + 'px sans-serif';
				ctx.textAlign = 'right';
				ctx.textBaseline = 'top';

				segments.forEach(function(segment, k) {
					if (segment.map === '') {
						return;
					}
					// Boundaries sit halfway between the last point of one
					// map and the first point of the next.
					const left = Math.max(area.left, x.getPixelForValue(segment.start - 0.5));
					const right = Math.min(area.right, x.getPixelForValue(segment.end + 0.5));
					if (right <= left) {
						return;
					}

					if (k % 2 === 0) {
						ctx.globalAlpha = 0.07;
						ctx.fillRect(left, area.top, right - left, area.bottom - area.top);
					}
					if (k > 0 && left > area.left) {
						ctx.globalAlpha = 0.3;
						ctx.beginPath();
						ctx.moveTo(Math.round(left) + 0.5, area.top);
						ctx.lineTo(Math.round(left) + 0.5, area.bottom);
						ctx.stroke();
					}
					if (right - left >= fontSize + 1) {
						// Rotated a quarter turn: reads bottom to top, ends at
						// the top of the plot, sits right of the separator.
						ctx.globalAlpha = 0.7;
						ctx.save();
						ctx.translate(left + 2, area.top + 3);
						ctx.rotate(-Math.PI / 2);
						ctx.fillText(segment.map, 0, 0);
						ctx.restore();
					}
				});
				ctx.restore();
			},
		};
	}

	function mapAtIndex(segments, index) {
		for (let i = 0; i < segments.length; i++) {
			if (index >= segments[i].start && index <= segments[i].end) {
				return segments[i].map;
			}
		}
		return '';
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
				renderChart(canvas, data, range, bgColor, textColor);
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
			font: { size: 13, weight: 'normal' },
			text: 'Average Players Last 24h: ' + lastDay + '  Last 1h: ' + lastHour,
		};
	}

	function renderChart(canvas, data, range, bgColor, textColor) {
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
			return formatLabel(row.timestamp, range, false);
		});

		// Only the 24h view has one row per sample; the coarser views
		// average many samples per row, so a per-row map is meaningless there
		// (the old PNG also drew maps on the 24h view only).
		const segments = range === 1 ? mapSegments(rows) : [];
		const plugins = [HLStatsChartZoom.plugin];
		if (segments.length > 0) {
			plugins.push(mapNamesPlugin(segments, textColor));
		}

		// css/mobile.css caps the canvas at its container's width on small
		// screens; draw at that width instead of scaling an 870px bitmap down.
		if (canvas.clientWidth > 0 && canvas.clientWidth < canvas.width) {
			canvas.width = canvas.clientWidth;
		}

		// Narrow screens: taller plot, fewer ticks, no axis titles (the legend
		// already names every series) and a larger font so the text stays legible.
		const compact = canvas.width < 600;
		if (compact) {
			canvas.height = Math.max(canvas.height, 280);
		}
		Chart.defaults.font.size = 13;

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
			plugins: plugins,
			options: {
				responsive: false,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 14 } },
					title: averagesTitle(data.averages, textColor),
					tooltip: {
						callbacks: {
							title: function(items) {
								return items.length ? formatFull(rows[items[0].dataIndex].timestamp) : '';
							},
							afterTitle: function(items) {
								const map = items.length ? mapAtIndex(segments, items[0].dataIndex) : '';
								return map ? 'Map: ' + map : '';
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
								return formatLabel(rows[value].timestamp, range, HLStatsChartZoom.isZoomed(this.chart));
							},
						},
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y: {
						type: 'linear',
						position: 'left',
						beginAtZero: true,
						title: { display: !compact, text: 'Players', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { color: 'rgba(255, 255, 255, 0.1)' },
					},
					y1: {
						type: 'linear',
						position: 'right',
						beginAtZero: true,
						title: { display: !compact, text: 'Uptime / FPS', color: textColor },
						ticks: { color: textColor, maxTicksLimit: compact ? 5 : undefined },
						grid: { display: false },
					},
				},
			},
		});
	}
})();
