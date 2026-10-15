/*
 * Zoom and pan for the Chart.js line charts (server load, game trend,
 * player trend).
 *
 * Exposes a global `HLStatsChartZoom` with a Chart.js inline plugin. A chart
 * opts in with `plugins: [HLStatsChartZoom.plugin]` and passes its theme
 * colors as `options.plugins.hlstatsZoom = { textColor, bgColor }`. All
 * charts use a category x axis, so the zoom window is a pair of data
 * indices written to `options.scales.x.min/max`; the y axes rescale to the
 * visible points on their own.
 *
 * Controls:
 *   - drag across the plot with the mouse to zoom into that span
 *   - Ctrl (or Cmd) + mouse wheel to zoom in/out around the cursor
 *   - double-click to reset
 *   - a toolbar under the chart (zoom out / in, pan left / right, reset),
 *     which is also the only control on touch screens, where a drag must
 *     keep scrolling the page
 *
 * No dependency besides the vendored Chart.js UMD build (web/includes/js/chart.js),
 * which must be loaded first.
 */
(function() {
	// Smallest number of points a zoomed window may show.
	const MIN_POINTS = 4;
	// A drag shorter than this (CSS px) is a click, not a selection.
	const MIN_DRAG_PX = 6;
	const ZOOM_STEP = 0.5;

	function pointCount(chart) {
		return chart.data.labels ? chart.data.labels.length : 0;
	}

	function isZoomed(chart) {
		const x = chart.options.scales.x;
		return x.min !== undefined || x.max !== undefined;
	}

	function currentWindow(chart) {
		const last = pointCount(chart) - 1;
		const x = chart.options.scales.x;
		return {
			min: x.min === undefined ? 0 : x.min,
			max: x.max === undefined ? last : x.max,
		};
	}

	function setWindow(chart, min, max) {
		const last = pointCount(chart) - 1;
		const minSpan = Math.min(MIN_POINTS - 1, last);

		min = Math.round(min);
		max = Math.round(max);
		if (max - min < minSpan) {
			const center = (min + max) / 2;
			min = Math.round(center - minSpan / 2);
			max = min + minSpan;
		}
		if (min < 0) {
			max -= min;
			min = 0;
		}
		if (max > last) {
			min -= max - last;
			max = last;
		}
		min = Math.max(0, min);

		const x = chart.options.scales.x;
		if (min <= 0 && max >= last) {
			x.min = undefined;
			x.max = undefined;
		} else {
			x.min = min;
			x.max = max;
		}
		chart.update('none');
		updateToolbar(chart);
	}

	function resetZoom(chart) {
		setWindow(chart, 0, pointCount(chart) - 1);
	}

	// factor < 1 zooms in, > 1 zooms out; `center` is the data index that
	// stays at the same place on screen.
	function zoomBy(chart, factor, center) {
		const win = currentWindow(chart);
		const span = win.max - win.min;
		if (center === undefined) {
			center = (win.min + win.max) / 2;
		}
		const newSpan = Math.max(1, span * factor);
		const ratio = span > 0 ? (center - win.min) / span : 0.5;
		const min = center - newSpan * ratio;
		setWindow(chart, min, min + newSpan);
	}

	// Shift the window by a fraction of its width (negative = left).
	function panBy(chart, fraction) {
		const win = currentWindow(chart);
		const span = win.max - win.min;
		const shift = (fraction < 0 ? -1 : 1) * Math.max(1, Math.round(span * Math.abs(fraction)));
		setWindow(chart, win.min + shift, win.max + shift);
	}

	function indexAtPixel(chart, px) {
		const last = pointCount(chart) - 1;
		const index = chart.scales.x.getValueForPixel(px);
		return Math.min(last, Math.max(0, Math.round(index)));
	}

	function makeButton(label, title, onClick, textColor) {
		const button = document.createElement('button');
		button.type = 'button';
		button.textContent = label;
		button.title = title;
		button.setAttribute('aria-label', title);
		button.style.cssText = 'margin:0 0 0 4px; padding:1px 8px; min-width:28px; cursor:pointer; '
			+ 'font:inherit; line-height:1.4; background:transparent; border-radius:3px; '
			+ 'color:' + textColor + '; border:1px solid ' + textColor + ';';
		button.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			onClick();
		});
		return button;
	}

	function buildToolbar(chart, opts) {
		const canvas = chart.canvas;
		const textColor = opts.textColor || '#ffffff';

		const toolbar = document.createElement('div');
		toolbar.className = 'chart-zoom-toolbar';
		toolbar.style.cssText = 'display:flex; align-items:center; justify-content:flex-end; '
			+ 'flex-wrap:wrap; gap:4px 0; padding:4px 6px; font-size:12px; color:' + textColor + ';'
			+ (opts.bgColor ? ' background-color:' + opts.bgColor + ';' : '');

		const hint = document.createElement('span');
		hint.className = 'chart-zoom-hint';
		hint.textContent = 'Drag to zoom, Ctrl+wheel to zoom, double-click to reset';
		hint.style.cssText = 'margin-right:auto; opacity:0.7;';
		toolbar.appendChild(hint);

		const buttons = {
			out: makeButton(String.fromCharCode(0x2212), 'Zoom out', function() { zoomBy(chart, 1 / ZOOM_STEP); }, textColor),
			in: makeButton('+', 'Zoom in', function() { zoomBy(chart, ZOOM_STEP); }, textColor),
			left: makeButton(String.fromCharCode(0x25C0), 'Pan left', function() { panBy(chart, -0.5); }, textColor),
			right: makeButton(String.fromCharCode(0x25B6), 'Pan right', function() { panBy(chart, 0.5); }, textColor),
			reset: makeButton('Reset', 'Reset zoom', function() { resetZoom(chart); }, textColor),
		};
		Object.keys(buttons).forEach(function(key) {
			toolbar.appendChild(buttons[key]);
		});

		// pages/game.php wraps one chart in a link to the server page; keep
		// the toolbar outside it so its buttons don't navigate away.
		const anchor = canvas.parentNode && canvas.parentNode.tagName === 'A' ? canvas.parentNode : canvas;
		anchor.insertAdjacentElement('afterend', toolbar);

		chart.$hlstatsZoom.toolbar = toolbar;
		chart.$hlstatsZoom.buttons = buttons;
		updateToolbar(chart);
	}

	function setDisabled(button, disabled) {
		button.disabled = disabled;
		button.style.opacity = disabled ? '0.35' : '1';
		button.style.cursor = disabled ? 'default' : 'pointer';
	}

	function updateToolbar(chart) {
		const state = chart.$hlstatsZoom;
		if (!state || !state.buttons) {
			return;
		}
		const last = pointCount(chart) - 1;
		const win = currentWindow(chart);
		const zoomed = isZoomed(chart);
		setDisabled(state.buttons.out, !zoomed);
		setDisabled(state.buttons.reset, !zoomed);
		setDisabled(state.buttons.in, win.max - win.min <= Math.min(MIN_POINTS - 1, last));
		setDisabled(state.buttons.left, win.min <= 0);
		setDisabled(state.buttons.right, win.max >= last);
	}

	function insideChartArea(chart, pos) {
		const area = chart.chartArea;
		return pos.x >= area.left && pos.x <= area.right && pos.y >= area.top && pos.y <= area.bottom;
	}

	function bindEvents(chart) {
		const canvas = chart.canvas;
		const state = chart.$hlstatsZoom;
		const position = function(e) {
			return Chart.helpers.getRelativePosition(e, chart);
		};

		canvas.addEventListener('pointerdown', function(e) {
			// Touch drags keep scrolling the page; touch users get the toolbar.
			if (e.pointerType === 'touch' || e.button !== 0) {
				return;
			}
			const pos = position(e);
			if (!insideChartArea(chart, pos)) {
				return;
			}
			state.drag = { startX: pos.x, endX: pos.x, pointerId: e.pointerId };
			canvas.setPointerCapture(e.pointerId);
		});

		canvas.addEventListener('pointermove', function(e) {
			if (!state.drag || state.drag.pointerId !== e.pointerId) {
				return;
			}
			const area = chart.chartArea;
			state.drag.endX = Math.min(area.right, Math.max(area.left, position(e).x));
			chart.draw();
		});

		const endDrag = function(e) {
			const drag = state.drag;
			if (!drag || drag.pointerId !== e.pointerId) {
				return;
			}
			state.drag = null;
			if (canvas.hasPointerCapture(e.pointerId)) {
				canvas.releasePointerCapture(e.pointerId);
			}
			if (e.type === 'pointerup' && Math.abs(drag.endX - drag.startX) >= MIN_DRAG_PX) {
				// The click that follows a selection must not follow the
				// surrounding link (pages/game.php).
				state.suppressClick = true;
				const a = indexAtPixel(chart, drag.startX);
				const b = indexAtPixel(chart, drag.endX);
				setWindow(chart, Math.min(a, b), Math.max(a, b));
			} else {
				chart.draw();
			}
		};
		canvas.addEventListener('pointerup', endDrag);
		canvas.addEventListener('pointercancel', endDrag);

		canvas.addEventListener('click', function(e) {
			if (state.suppressClick) {
				state.suppressClick = false;
				e.preventDefault();
				e.stopPropagation();
			}
		}, true);

		// Inside a link (pages/game.php), a mouse drag would otherwise start
		// dragging the link and cancel the selection.
		canvas.addEventListener('dragstart', function(e) {
			e.preventDefault();
		});

		canvas.addEventListener('dblclick', function(e) {
			if (isZoomed(chart)) {
				e.preventDefault();
				resetZoom(chart);
			}
		});

		canvas.addEventListener('wheel', function(e) {
			if (!(e.ctrlKey || e.metaKey) || e.deltaY === 0) {
				return;
			}
			const pos = position(e);
			if (!insideChartArea(chart, pos)) {
				return;
			}
			// Also stops the browser's own Ctrl+wheel page zoom.
			e.preventDefault();
			zoomBy(chart, e.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP, chart.scales.x.getValueForPixel(pos.x));
		}, { passive: false });
	}

	const plugin = {
		id: 'hlstatsZoom',

		afterInit: function(chart, args, opts) {
			chart.$hlstatsZoom = { drag: null, suppressClick: false };
			if (pointCount(chart) <= MIN_POINTS) {
				return;
			}
			bindEvents(chart);
			buildToolbar(chart, opts || {});
		},

		// Selection rectangle while dragging.
		afterDraw: function(chart) {
			const drag = chart.$hlstatsZoom && chart.$hlstatsZoom.drag;
			if (!drag || drag.startX === drag.endX) {
				return;
			}
			const ctx = chart.ctx;
			const area = chart.chartArea;
			const left = Math.min(drag.startX, drag.endX);
			const width = Math.abs(drag.endX - drag.startX);
			ctx.save();
			ctx.fillStyle = 'rgba(59, 107, 255, 0.2)';
			ctx.strokeStyle = 'rgba(59, 107, 255, 0.8)';
			ctx.lineWidth = 1;
			ctx.fillRect(left, area.top, width, area.bottom - area.top);
			ctx.strokeRect(left + 0.5, area.top + 0.5, width - 1, area.bottom - area.top - 1);
			ctx.restore();
		},
	};

	window.HLStatsChartZoom = {
		plugin: plugin,
		isZoomed: isZoomed,
		reset: resetZoom,
	};
})();
