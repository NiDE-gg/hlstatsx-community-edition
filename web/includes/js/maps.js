/**
 * "Player Locations" Google Map.
 *
 * includes/google_maps.php used to echo a page-specific <script> block that
 * declared createMarker()/createMarkerS() and then, for every marker, echoed
 * a `createMarker(...)`/`createMarkerS(...)` call with city/country/player/
 * server names interpolated straight into the JS source (through jsEscape(),
 * but still JS source built by PHP string concatenation).
 *
 * This file replaces that: google_maps.php now publishes everything the map
 * needs (center, zoom, map type, preload images and the marker list) as one
 * JSON payload in a `<script type="application/json"
 * id="hlstats-map-data-<type>">` block, and this script reads it, builds the
 * map and creates the markers.
 */
(function () {
	'use strict';

	function findBootstrapElement() {
		return document.querySelector('script[id^="hlstats-map-data-"]');
	}

	function preloadImages(urls) {
		(urls || []).forEach(function (src) {
			var img = new Image();
			img.src = src;
		});
	}

	// Mirrors the old (window.)escape()-free HTML building in
	// createMarker()/createMarkerS(): everything coming from the server is
	// untrusted, so it's always run through this before going into
	// innerHTML/InfoWindow content.
	function escapeHtml(value) {
		var div = document.createElement('div');
		div.textContent = String(value);
		return div.innerHTML;
	}

	// Same popup markup as the old createMarker(point, city, country, player_info)
	// function: a title row, then one Kills/Deaths row per player (plus a Time
	// row when includeTime is set, i.e. when type == 'main').
	function buildPlayerContent(location, includeTime) {
		var html = '<table class="gmapstab"><tr><td colspan="2" class="gmapstabtitle" style="border-bottom:1px solid black;">' + escapeHtml(location.city) + ', ' + escapeHtml(location.country) + '</td></tr>';

		(location.items || []).forEach(function (player) {
			html += '<tr><td><a href="hlstats.php?mode=playerinfo&amp;player=' + escapeHtml(player.id) + '">' + escapeHtml(player.name) + '</a></td></tr>';
			html += '<tr><td>Kills/Deaths</td><td>' + escapeHtml(player.kills) + ':' + escapeHtml(player.deaths) + '</td></tr>';
			if (includeTime && player.time) {
				html += '<tr><td>Time</td><td>' + escapeHtml(player.time) + '</td></tr>';
			}
		});

		html += '</table>';
		return html;
	}

	// Same popup markup as the old createMarkerS(point, servers, city, country, kills).
	function buildServerContent(location, game) {
		var html = '<table class="gmapstab"><tr><td colspan="2" class="gmapstabtitle" style="border-bottom:1px solid black;">' + escapeHtml(location.city) + ', ' + escapeHtml(location.country) + '</td></tr>';

		(location.items || []).forEach(function (server) {
			// server.addr is "ip:port" and must stay literal (not URL-encoded)
			// for the steam://connect/ handler to parse it; it's still run
			// through escapeHtml() since it ends up inside innerHTML/href.
			html += '<tr><td><a href="hlstats.php?mode=servers&server_id=' + escapeHtml(server.id) + '&amp;game=' + escapeHtml(game) + '">' + escapeHtml(server.name) + '</a></td></tr>';
			html += '<tr><td>' + escapeHtml(server.addr) + ' (<a href="steam://connect/' + escapeHtml(server.addr) + '">connect</a>)</td></tr>';
		});

		html += '<tr><td>' + escapeHtml(location.kills) + ' kills</td></tr></table>';
		return html;
	}

	function addMarker(map, position, icon, content) {
		var marker = new google.maps.Marker({
			position: position,
			map: map,
			icon: icon
		});
		var infowindow = new google.maps.InfoWindow({ content: content });
		google.maps.event.addListener(marker, 'click', function () {
			infowindow.open(map, marker);
		});
		return marker;
	}

	function init() {
		var dataEl = findBootstrapElement();
		if (!dataEl || typeof google === 'undefined' || !google.maps) {
			return;
		}

		var data;
		try {
			data = JSON.parse(dataEl.textContent);
		} catch (e) {
			console.error('Google map: invalid bootstrap payload', e);
			return;
		}

		var mapEl = document.getElementById('map');
		if (!mapEl) {
			return;
		}

		preloadImages(data.preload);

		var mapOptions = {
			center: new google.maps.LatLng(data.mapCenter.lat, data.mapCenter.lng),
			zoom: data.mapCenter.zoom,
			scrollwheel: false,
			mapTypeControl: true,
			mapTypeControlOptions: { style: google.maps.MapTypeControlStyle.DROPDOWN_MENU },
			navigationControl: true,
			navigationControlOptions: { style: google.maps.NavigationControlStyle.ZOOM_PAN }
		};
		if (data.mapTypeId && google.maps.MapTypeId[data.mapTypeId]) {
			mapOptions.mapTypeId = google.maps.MapTypeId[data.mapTypeId];
		}

		var map = new google.maps.Map(mapEl, mapOptions);

		var pointIcon = data.imagePath + '/mm_20_blue.png';
		var pointIconRed = data.imagePath + '/mm_20_red.png';
		var includeTime = data.type === 'main';

		(data.players || []).forEach(function (location) {
			addMarker(map, new google.maps.LatLng(location.lat, location.lng), pointIcon, buildPlayerContent(location, includeTime));
		});

		(data.servers || []).forEach(function (location) {
			addMarker(map, new google.maps.LatLng(location.lat, location.lng), pointIconRed, buildServerContent(location, data.game));
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
