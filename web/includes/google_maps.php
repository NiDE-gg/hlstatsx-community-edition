<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Nicholas Hastings (nshastings@gmail.com)
http://www.hlxcommunity.com

HLstatsX Community Edition is a continuation of
ELstatsNEO - Real-time player and clan rankings and statistics
Copyleft (L) 2008-20XX Malte Bayer (steam@neo-soft.org)
http://ovrsized.neo-soft.org/

ELstatsNEO is an very improved & enhanced - so called Ultra-Humongus Edition of HLstatsX
HLstatsX - Real-time player and clan rankings and statistics for Half-Life 2
http://www.hlstatsx.com/
Copyright (C) 2005-2007 Tobias Oetzel (Tobi@hlstatsx.com)

HLstatsX is an enhanced version of HLstats made by Simon Garner
HLstats - Real-time player and clan rankings and statistics for Half-Life
http://sourceforge.net/projects/hlstats/
Copyright (C) 2001  Simon Garner

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

For support and installation notes visit http://www.hlxcommunity.com
*/

// Builds the marker data for the "Player Locations" Google Map and hands it
// off to includes/js/maps.js as a single JSON payload (read by that script
// via a `<script type="application/json" id="hlstats-map-data-<type>">`
// block, e.g. "hlstats-map-data-main" or "hlstats-map-data-clan").
// Previously this function echoed one `createMarker(...)`/`createMarkerS(...)`
// JS function-call string per marker, with city/country/player/server names
// interpolated into JS source (through jsEscape(), but still PHP-built JS).
// No PHP-generated JS logic remains here now; only the external script tag(s)
// and the JSON bootstrap data.
function printMap($type = 'main')
{
	global $db, $game, $g_options, $clandata, $clan;

	if ($type == 'main') {
		echo ('<script src="https://maps.googleapis.com/maps/api/js?callback=Function.prototype&amp;key=' . rawurlencode(GOOGLE_MAPS_API_KEY) . '" type="text/javascript"></script>' . "\n");
	}

	$mapCenter = getMapCenter(($type == 'clan' && $clandata['mapregion'] != '') ? $clandata['mapregion'] : $g_options['google_map_region']);
	$mapTypeId = getMapTypeId($g_options['google_map_type']);

	$preloadImages = array(IMAGE_PATH . '/mm_20_blue.png');
	if ($type == 'main') {
		$preloadImages[] = IMAGE_PATH . '/mm_20_red.png';
	}
	$preloadImages[] = IMAGE_PATH . '/mm_20_shadow.png';

	$bootstrap = array(
		'type' => $type,
		'game' => $game,
		'imagePath' => IMAGE_PATH,
		'mapCenter' => $mapCenter,
		'mapTypeId' => $mapTypeId,
		'servers' => array(),
		'players' => array(),
	);

	if ($type == 'main') {
		$db->query("SELECT serverId, IF(publicaddress != '', publicaddress, CONCAT(address, ':', port)) AS addr, name, kills, lat, lng, city, country FROM hlstats_Servers WHERE game='$game' AND lat IS NOT NULL AND lng IS NOT NULL");

		$servers = array();
		while ($row = $db->fetch_array())
		{
			//Skip this part, if we already have the location info (should be the same)
			if (!isset($servers[$row['lat'] . ',' . $row['lng']]))
			{
				$servers[$row['lat'] . ',' . $row['lng']] = array('lat' => $row['lat'], 'lng' => $row['lng'], 'addr' => $row['addr'], 'city' => $row['city'], 'country' => $row['country']);
			}

			$servers[$row['lat'] . ',' . $row['lng']]['servers'][] = array('serverId' => $row['serverId'], 'addr' => $row['addr'], 'name' => $row['name'], 'kills' => $row['kills']);
		}
		foreach ($servers as $map_location)
		{
			$kills = 0;
			$serverMarkers = array();
			foreach ($map_location['servers'] as $server)
			{
				$search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
				$replace_pattern = array("");
				$server['name'] = preg_replace($search_pattern, $replace_pattern, $server['name']);

				$serverMarkers[] = array(
					'id' => (int) $server['serverId'],
					'addr' => urldecode(preg_replace($search_pattern, $replace_pattern, $server['addr'])),
					'name' => urldecode(preg_replace($search_pattern, $replace_pattern, $server['name'])),
				);
				$kills += $server['kills'];
			}

			$bootstrap['servers'][] = array(
				'lat' => (float) $map_location['lat'],
				'lng' => (float) $map_location['lng'],
				'city' => urldecode($map_location['city']),
				'country' => urldecode($map_location['country']),
				'kills' => $kills,
				'items' => $serverMarkers,
			);
		}

		$db->query("SELECT
					hlstats_Livestats.*
				FROM
					hlstats_Livestats
				INNER JOIN
					hlstats_Servers
					ON (hlstats_Servers.serverId=hlstats_Livestats.server_id)
				WHERE
					hlstats_Livestats.cli_lat IS NOT NULL
					AND hlstats_Livestats.cli_lng IS NOT NULL
					AND hlstats_Servers.game='$game'
					");
		$players = array();
		while ($row = $db->fetch_array())
		{
			//Skip this part, if we already have the location info (should be the same)
			if (!isset($players[$row['cli_lat'] . ',' . $row['cli_lng']]))
			{
				$players[$row['cli_lat'] . ',' . $row['cli_lng']] = array('cli_lat' => $row['cli_lat'], 'cli_lng' => $row['cli_lng'], 'cli_city' => $row['cli_city'], 'cli_country' => $row['cli_country']);
			}
			$search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
			$replace_pattern = array("");
			$row['name'] = preg_replace($search_pattern, $replace_pattern, $row['name']);

			$players[$row['cli_lat'] . ',' . $row['cli_lng']]['players'][] = array('playerId' => $row['player_id'], 'name' => $row['name'], 'kills' => $row['kills'], 'deaths' => $row['deaths'], 'connected' => $row['connected']);
		}

		foreach ($players as $map_location)
		{
			$playerMarkers = array();
			foreach ($map_location['players'] as $player)
			{
				$stamp = time() - $player['connected'];
				$hours = sprintf("%02d", floor($stamp / 3600));
				$min = sprintf("%02d", floor(($stamp % 3600) / 60));
				$sec = sprintf("%02d", floor($stamp % 60));
				$time_str = $hours . ":" . $min . ":" . $sec;

				$playerMarkers[] = array(
					'id' => (int) $player['playerId'],
					'name' => urldecode(preg_replace($search_pattern, $replace_pattern, $player['name'])),
					'kills' => (int) $player['kills'],
					'deaths' => (int) $player['deaths'],
					'time' => $time_str,
				);
			}

			$bootstrap['players'][] = array(
				'lat' => (float) $map_location['cli_lat'],
				'lng' => (float) $map_location['cli_lng'],
				'city' => urldecode($map_location['cli_city']),
				'country' => urldecode($map_location['cli_country']),
				'items' => $playerMarkers,
			);
		}
	} else if ($type == 'clan') {
		$db->query("
			SELECT
				playerId,
				lastName,
				country,
				skill,
				kills,
				deaths,
				lat,
				lng,
				city,
				country
			FROM
				hlstats_Players
			WHERE
				clan=$clan
				AND hlstats_Players.hideranking = 0
			GROUP BY
				hlstats_Players.playerId
		");
		$players = array();
		while ( $row = $db->fetch_array() )
		{
			//Skip this part, if we already have the location info (should be the same)
			if ( !isset($players[ $row['lat'] . ',' . $row['lng'] ]) )
			{
				$players[ $row['lat'] . ',' . $row['lng'] ] = array(
					'lat' => $row['lat'],
					'lng' => $row['lng'],
					'city' => $row['city'],
					'country' => $row['country']
				);
			}
			$search_pattern = array("/[^A-Za-z0-9\[\]*.,=()!\"$%&^`ґ':;ЯІі#+~_\-|<>\/@{}дцьДЦЬ ]/");
			$replace_pattern = array("");
			$row['lastName'] = preg_replace($search_pattern, $replace_pattern, $row['lastName']);

			$players[ $row['lat'] . ',' . $row['lng'] ]['players'][] = array(
				'playerId' => $row['playerId'],
				'name' => $row['lastName'],
				'kills' => $row['kills'],
				'deaths' => $row['deaths'],
                // Where should this information come from??
				//'connected' => $row['connected']
			);
		}

		foreach ( $players as $location )
		{
			$playerMarkers = array();
			foreach ( $location['players'] as $player )
			{
				$playerMarkers[] = array(
					'id' => (int) $player['playerId'],
					'name' => urldecode(preg_replace($search_pattern, $replace_pattern, $player['name'])),
					'kills' => (int) $player['kills'],
					'deaths' => (int) $player['deaths'],
				);
			}

			$bootstrap['players'][] = array(
				'lat' => (float) $location['lat'],
				'lng' => (float) $location['lng'],
				'city' => urldecode($location['city']),
				'country' => urldecode($location['country']),
				'items' => $playerMarkers,
			);
		}
	}

	$bootstrap['preload'] = $preloadImages;

	echo '<script type="application/json" id="hlstats-map-data-' . htmlspecialchars($type, ENT_QUOTES) . '">' . jsEscape($bootstrap) . "</script>\n";
	echo '<script type="text/javascript" src="' . asset_url(INCLUDE_PATH . '/js/maps.js') . '"></script>' . "\n";
}

// Returns the {lat, lng, zoom} to center the map on for a given region name
// (used to come from printMapCenter(), which echoed the equivalent
// `var mapLatLng = ...; var mapZoom = ...;` JS statements).
function getMapCenter($country)
{
	switch (strtoupper($country))
	{
		case 'EUROPE':
			return array('lat' => 48.8, 'lng' => 8.5, 'zoom' => 3);
		case 'NORTH AMERICA':
			return array('lat' => 45.0, 'lng' => -97.0, 'zoom' => 3);
		case 'SOUTH AMERICA':
			return array('lat' => -14.8, 'lng' => -61.2, 'zoom' => 3);
		case 'NORTH AFRICA':
			return array('lat' => 25.4, 'lng' => 8.4, 'zoom' => 4);
		case 'SOUTH AFRICA':
			return array('lat' => -29.0, 'lng' => 23.7, 'zoom' => 5);
		case 'NORTH EUROPE':
			return array('lat' => 62.6, 'lng' => 15.4, 'zoom' => 4);
		case 'EAST EUROPE':
			return array('lat' => 51.9, 'lng' => 31.8, 'zoom' => 4);
		case 'GERMANY':
			return array('lat' => 51.1, 'lng' => 10.1, 'zoom' => 5);
		case 'FRANCE':
			return array('lat' => 47.2, 'lng' => 2.4, 'zoom' => 5);
		case 'SPAIN':
			return array('lat' => 40.3, 'lng' => -4.0, 'zoom' => 5);
		case 'UNITED KINGDOM':
			return array('lat' => 54.0, 'lng' => -4.3, 'zoom' => 5);
		case 'DENMARK':
			return array('lat' => 56.1, 'lng' => 9.2, 'zoom' => 6);
		case 'SWEDEN':
			return array('lat' => 63.2, 'lng' => 16.3, 'zoom' => 4);
		case 'NORWAY':
			return array('lat' => 65.6, 'lng' => 13.1, 'zoom' => 4);
		case 'FINLAND':
			return array('lat' => 65.1, 'lng' => 26.6, 'zoom' => 4);
		case 'NETHERLANDS':
			return array('lat' => 52.3, 'lng' => 5.4, 'zoom' => 7);
		case 'BELGIUM':
			return array('lat' => 50.7, 'lng' => 4.5, 'zoom' => 7);
		case 'SUISSE':
			return array('lat' => 46.8, 'lng' => 8.2, 'zoom' => 7);
		case 'AUSTRIA':
			return array('lat' => 47.7, 'lng' => 14.1, 'zoom' => 7);
		case 'POLAND':
			return array('lat' => 52.1, 'lng' => 19.3, 'zoom' => 6);
		case 'ITALY':
			return array('lat' => 42.6, 'lng' => 12.7, 'zoom' => 5);
		case 'TURKEY':
			return array('lat' => 39.0, 'lng' => 34.9, 'zoom' => 6);
		case 'ROMANIA':
			return array('lat' => 45.94, 'lng' => 24.96, 'zoom' => 6);
		case 'BRAZIL':
			return array('lat' => -12.0, 'lng' => -53.1, 'zoom' => 4);
		case 'ARGENTINA':
			return array('lat' => -34.3, 'lng' => -65.7, 'zoom' => 3);
		case 'RUSSIA':
			return array('lat' => 65.7, 'lng' => 98.8, 'zoom' => 3);
		case 'ASIA':
			return array('lat' => 20.4, 'lng' => 95.6, 'zoom' => 3);
		case 'CHINA':
			return array('lat' => 36.2, 'lng' => 104.0, 'zoom' => 4);
		case 'JAPAN':
			return array('lat' => 36.2, 'lng' => 136.8, 'zoom' => 5);
		case 'SOUTH KOREA':
			return array('lat' => 36.6, 'lng' => 127.8, 'zoom' => 6);
		case 'TAIWAN':
			return array('lat' => 23.6, 'lng' => 121, 'zoom' => 7);
		case 'AUSTRALIA':
			return array('lat' => -26.1, 'lng' => 134.8, 'zoom' => 4);
		case 'CANADA':
			return array('lat' => 60.0, 'lng' => -97.0, 'zoom' => 3);
		case 'WORLD':
			return array('lat' => 25.0, 'lng' => 8.5, 'zoom' => 2);
		default:
			return array('lat' => 48.8, 'lng' => 8.5, 'zoom' => 3);
	}
}

// Returns the google.maps.MapTypeId key for the configured map style, or
// null when none should be set explicitly (the map then falls back to the
// Maps API's own default), matching the previous printMapType() behavior.
function getMapTypeId($maptype)
{
	switch (strtoupper($maptype))
	{
		case 'SATELLITE':
			return 'SATELLITE';
		case 'MAP':
			return 'ROADMAP';
		case 'HYBRID':
			return 'HYBRID';
		case 'PHYSICAL':
			return 'TERRAIN';
		default:
			return null;
	}
}
?>
