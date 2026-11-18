<?php
error_reporting(E_ALL);
ini_set("memory_limit", "32M");
ini_set("max_execution_time", "0");

// Database credentials come from the same untracked .env as the web pages (the
// repository root, one level above this folder; see .env.example), so this
// tracked file holds no secret. Environment variables of the same name are used
// when there is no .env (cron or container).
$heatmapEnv = array();
$heatmapEnvFile = dirname(__DIR__) . '/.env';
if (is_readable($heatmapEnvFile)) {
	foreach (file($heatmapEnvFile, FILE_IGNORE_NEW_LINES) as $heatmapEnvLine) {
		$heatmapEnvLine = trim($heatmapEnvLine);
		if ($heatmapEnvLine === '' || $heatmapEnvLine[0] === '#' || strpos($heatmapEnvLine, '=') === false) {
			continue;
		}
		list($heatmapKey, $heatmapValue) = array_map('trim', explode('=', $heatmapEnvLine, 2));
		if (strlen($heatmapValue) >= 2 && ($heatmapValue[0] === '"' || $heatmapValue[0] === "'")
			&& substr($heatmapValue, -1) === $heatmapValue[0]) {
			$heatmapValue = substr($heatmapValue, 1, -1);
		}
		$heatmapEnv[$heatmapKey] = $heatmapValue;
	}
}
// An empty value counts as not set
$heatmapSetting = function ($name, $default) use ($heatmapEnv) {
	if (($heatmapEnv[$name] ?? '') !== '') {
		return $heatmapEnv[$name];
	}
	$value = getenv($name);
	return ($value === false || $value === '') ? $default : $value;
};

define('DB_HOST',	$heatmapSetting('DB_HOST', 'localhost'));
define('DB_USER',	$heatmapSetting('DB_USERNAME', ''));
define('DB_PASS',	$heatmapSetting('DB_PASSWORD', ''));
define('DB_NAME',	$heatmapSetting('DB_NAME', ''));
// The web folder of this checkout, unless HEATMAP_WEB_PATH says otherwise
define('HLXCE_WEB',	$heatmapSetting('HEATMAP_WEB_PATH', dirname(__DIR__) . '/web'));
define('HUD_URL',	'http://www.hlxcommunity.com');
define('OUTPUT_SIZE',	'medium');

unset($heatmapEnv, $heatmapEnvFile, $heatmapEnvLine, $heatmapKey, $heatmapValue, $heatmapSetting);

if (DB_NAME === '' || DB_USER === '') {
	fwrite(STDERR, "Set DB_HOST, DB_USERNAME, DB_PASSWORD and DB_NAME in " . dirname(__DIR__) . "/.env (see .env.example) or in the environment.\n");
	exit(1);
}

define('DB_PREFIX',	'hlstats');
define('KILL_LIMIT',	10000);
define('DEBUG', 1);

// No need to change this unless you are on really low disk.
define('CACHE_DIR',	dirname(__FILE__) . '/cache');

