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

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}

// DB_ADDR - The address of the database server, in host:port format.
//           (You might also try setting this to e.g. ":/tmp/mysql.sock" to
//           use a Unix domain socket, if your mysqld is on the same box as
//           your web server.)
define("DB_ADDR", 'localhost');

// DB_USER - The username to connect to the database as
define("DB_USER", '');

// DB_PASS - The password for DB_USER
define("DB_PASS", '');

// DB_NAME - The name of the database
define("DB_NAME", '');

// DB_TYPE - The database server type. Only "mysql" is supported currently
define("DB_TYPE", 'mysql');

// default 'utf8mb4'
define("DB_CHARSET", 'utf8mb4');

// default 'utf8mb4_unicode_ci'
define("DB_COLLATE", 'utf8mb4_unicode_ci');

// DB_PCONNECT - Set to 1 to use persistent database connections. Persistent
//               connections can give better performance, but may overload
//               the database server. Set to 0 to use non-persistent
//               connections.
define("DB_PCONNECT", 0);

// INCLUDE_PATH - Filesystem path to the includes directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
define("INCLUDE_PATH", './includes');


// PAGE_PATH - Filesystem path to the pages directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
define("PAGE_PATH", './pages');


// PAGE_PATH - Filesystem path to the hlstatsimg directory, relative to hlstats.php. This must be specified
//		as a relative path.
//
//                Under Windows, make sure you use forward slash (/) instead
//                of back slash (\) and use absolute paths if you are having any issue.
//
// 		Note: the progress directory under hlstatsimg must be writable!!
define("IMAGE_PATH", './hlstatsimg');

// How often dynamicly generated images are updated (in seconds)
define("IMAGE_UPDATE_INTERVAL", 300);

define('GOOGLE_MAPS_API_KEY', "");

// Add a chat delay to prevent real-time tracking and provide information that can help in-game.
// Only allowed from 1 minute to CHAT_DELAY_MAX_TIME, other values ​​disable this.
// Disabled by default.
define('CHAT_DELAY_TIME', 0);
define('CHAT_DELAY_MAX_TIME', 20);

define('ROOT_PATH', __DIR__);

// Maximum storage time for player trend image cache (in seconds).
// Default value: 604800 seconds = 7 days.
define('TREND_CACHE_STORAGE_TIME', 604800);

// The probability of triggering the code to clear the cache of player trend images
// so as not to overload the disk every time you visit the site
// (specified as a percentage from 1 to 100).
define('TREND_CACHE_PROBABILITY', 10); // 10 %

// The largest number of images in the trend cache for 1 player,
// any images above this number will be deleted.
define('TREND_CACHE_MAX_FILES_PER_PLAYER', 5);

//define("DB_DEBUG", true);

?>
