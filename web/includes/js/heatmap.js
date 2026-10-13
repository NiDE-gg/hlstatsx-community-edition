/**
 * Lightweight lightbox for heatmap image popups.
 *
 * Replaces the MooTools SqueezeBox binding that used to run on
 * a[rel=boxed] links (see mapinfo.php). Vanilla ES6+, no dependencies.
 */

document.addEventListener('DOMContentLoaded', () => {
	const style = document.createElement('style');
	style.textContent = `
		.hlx-lightbox-overlay {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.75);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 65555;
		}
		.hlx-lightbox-box {
			position: relative;
			max-width: 90vw;
			max-height: 90vh;
			background: #fff;
			padding: 10px;
			border-radius: 4px;
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.7);
			text-align: center;
		}
		.hlx-lightbox-box img {
			display: block;
			max-width: 100%;
			max-height: 80vh;
			margin: 0 auto;
		}
		.hlx-lightbox-caption {
			margin-top: 8px;
			font-size: 13px;
			color: #333;
		}
		.hlx-lightbox-close {
			position: absolute;
			top: -12px;
			right: -12px;
			width: 26px;
			height: 26px;
			line-height: 24px;
			text-align: center;
			background: #fff;
			border: 1px solid #999;
			border-radius: 50%;
			cursor: pointer;
			font-size: 16px;
			color: #333;
			text-decoration: none;
		}
	`;
	document.head.appendChild(style);

	let activeOverlay = null;
	let keydownHandler = null;

	const closeLightbox = () => {
		if (!activeOverlay) {
			return;
		}

		activeOverlay.remove();
		activeOverlay = null;

		if (keydownHandler) {
			document.removeEventListener('keydown', keydownHandler);
			keydownHandler = null;
		}
	};

	const openLightbox = (href, caption) => {
		closeLightbox();

		const overlay = document.createElement('div');
		overlay.className = 'hlx-lightbox-overlay';

		const box = document.createElement('div');
		box.className = 'hlx-lightbox-box';

		const closeBtn = document.createElement('a');
		closeBtn.href = '#';
		closeBtn.className = 'hlx-lightbox-close';
		closeBtn.textContent = '×';
		closeBtn.addEventListener('click', (e) => {
			e.preventDefault();
			closeLightbox();
		});

		const img = document.createElement('img');
		img.src = href;
		if (caption) {
			img.alt = caption;
		}

		box.appendChild(closeBtn);
		box.appendChild(img);

		if (caption) {
			const captionEl = document.createElement('div');
			captionEl.className = 'hlx-lightbox-caption';
			captionEl.textContent = caption;
			box.appendChild(captionEl);
		}

		overlay.appendChild(box);

		overlay.addEventListener('click', (e) => {
			if (e.target === overlay) {
				closeLightbox();
			}
		});

		document.body.appendChild(overlay);
		activeOverlay = overlay;

		keydownHandler = (e) => {
			if (e.key === 'Escape') {
				closeLightbox();
			}
		};
		document.addEventListener('keydown', keydownHandler);
	};

	document.querySelectorAll('a[rel="boxed"]').forEach((link) => {
		link.addEventListener('click', (e) => {
			e.preventDefault();
			openLightbox(link.getAttribute('href'), link.getAttribute('title'));
		});
	});
});
