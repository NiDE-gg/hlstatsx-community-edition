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


/* Profile support:

To see SQL run counts and run times, set the $profile variable below to something
that evaluates as true.

Add the following table to your database:
CREATE TABLE IF NOT EXISTS `hlstats_sql_web_profile` (
`queryid` int(11) NOT NULL AUTO_INCREMENT,
`source` tinytext NOT NULL,
`run_count` int(11) NOT NULL,
`run_time` float NOT NULL,
PRIMARY KEY (`queryid`),
UNIQUE KEY `source` (`source`(64))
) ENGINE=MyISAM;
*/

if (!defined('IN_HLSTATS')) {
	die('Do not access this file directly.');
}

/**
 * A result set of DB_mysql: every row read at once, like mysqli's default
 * (buffered) results, so num_rows() and data_seek() work as they did.
 */
class DB_mysql_result
{
	public $rows;
	public $names;
	public $pos = 0;

	function __construct(array $rows, array $names)
	{
		$this->rows = $rows;
		$this->names = $names;
	}
}

/**
 * The legacy query layer of the pages ($db), on the PDO connection the
 * repositories use (the container's "pdo"): one connection per request instead of
 * a mysqli one next to it. It behaves like the mysqli version: values come back as
 * strings (or null), fetch_array() gives numeric and named keys, queries go out as
 * plain text (emulated prepares), and errors are reported by error().
 */
class DB_mysql
{
	var $db_addr;
	var $db_user;
	var $db_pass;
	var $db_name;

	/** @var PDO|null */
	var $pdo = null;
	var $last_result;
	var $last_query;
	var $last_insert_id;
	var $last_error = '';
	var $profile = 0;
	var $querycount = 0;

	function __construct($db_addr, $db_user, $db_pass, $db_name, $use_pconnect = false)
	{
		// The connection settings are the container's (bootstrap.php, from the same
		// config.php constants); these are only kept for error messages.
		$this->db_addr = $db_addr;
		$this->db_user = $db_user;
		$this->db_pass = $db_pass;
		$this->db_name = $db_name;
		$this->querycount = 0;
	}

	// The shared PDO connection, opened on first use. Entry points build the
	// container before $db; a script that didn't gets one here, shared through
	// $GLOBALS['container'] so the pages reuse it.
	function pdo()
	{
		if ($this->pdo === null) {
			if (!isset($GLOBALS['container'])) {
				$GLOBALS['container'] = require ROOT_PATH . '/bootstrap.php';
			}
			$this->pdo = $GLOBALS['container']->get('pdo');
		}

		return $this->pdo;
	}

	function data_seek($row_number, $query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}
		if ( $query_id instanceof DB_mysql_result && $row_number >= 0 && $row_number < count($query_id->rows) )
		{
			$query_id->pos = (int) $row_number;
			return true;
		}
		return false;
	}

	function fetch_array($query_id = 0)
	{
		$row = $this->fetch_row($query_id);
		if ( !is_array($row) )
		{
			// null past the last row, false without a result, as mysqli
			return $row;
		}

		$result = $query_id ?: $this->last_result;
		// Numeric and named keys, in the order mysqli_fetch_array() gave them (a name
		// used twice keeps the last value, as there)
		$both = array();
		foreach ( $row as $i => $value )
		{
			$both[$i] = $value;
			$both[$result->names[$i]] = $value;
		}
		return $both;
	}

	function fetch_assoc($query_id = 0)
	{
		$row = $this->fetch_row($query_id);
		if ( !is_array($row) )
		{
			// null past the last row, false without a result, as mysqli
			return $row;
		}

		$result = $query_id ?: $this->last_result;
		$assoc = array();
		foreach ( $row as $i => $value )
		{
			$assoc[$result->names[$i]] = $value;
		}
		return $assoc;
	}

	function fetch_row($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id instanceof DB_mysql_result && $query_id->pos < count($query_id->rows) )
		{
			return $query_id->rows[$query_id->pos++];
		}
		// mysqli returned null at the end of the rows; callers test the value as a boolean
		return $query_id instanceof DB_mysql_result ? null : false;
	}

	function fetch_row_set($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id )
		{
			$rowset = array();
			while ( $row = $this->fetch_array($query_id) )
				$rowset[] = $row;

			return $rowset;
		}
		return false;
	}

	function free_result($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id instanceof DB_mysql_result )
		{
			$query_id->rows = array();
			$query_id->pos = 0;
			return true;
		}
		return false;
	}

	function insert_id()
	{
		return $this->last_insert_id;
	}

	function num_rows($query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $query_id instanceof DB_mysql_result )
		{
			return count($query_id->rows);
		}
		return false;
	}

	function query($query, $showerror=true)
	{
		$this->last_query = $query;
		$this->last_error = '';
		$starttime = microtime(true);
		try {
			// Emulated: sent as plain text like mysqli_query(), so every statement
			// works (some can't be prepared natively), and no placeholder is parsed
			$pdo = $this->pdo();
			$stmt = $pdo->prepare($query, array(PDO::ATTR_EMULATE_PREPARES => true));
			$stmt->execute();
			if ( $stmt->columnCount() > 0 )
			{
				$names = array();
				$floatDecimals = array();
				for ( $i = 0; $i < $stmt->columnCount(); $i++ )
				{
					$meta = $stmt->getColumnMeta($i);
					$names[$i] = $meta['name'];
					if ( in_array($meta['native_type'] ?? '', array('FLOAT', 'DOUBLE'), true) )
					{
						$floatDecimals[$i] = (int) ($meta['precision'] ?? 31);
					}
				}
				$rows = $stmt->fetchAll(PDO::FETCH_NUM);
				// mysqli gave every value as the server's text. PDO gives integers and
				// floats as PHP numbers (DECIMAL stays text): integers go back to text,
				// floats are written with their column's decimals ("1.00", not "1"),
				// and without an exponent when the decimals aren't fixed (31).
				foreach ( $rows as &$row )
				{
					foreach ( $row as $i => &$value )
					{
						if ( $value === null || is_string($value) )
						{
							continue;
						}
						if ( isset($floatDecimals[$i]) && is_float($value) )
						{
							if ( $floatDecimals[$i] < 31 )
							{
								$value = sprintf('%.' . $floatDecimals[$i] . 'F', $value);
							}
							else
							{
								// The server writes 1e20 and 1.5e-20, but 0.00000015
								$text = (string) $value;
								$magnitude = abs($value);
								if ( $magnitude >= 1e15 || ($magnitude > 0 && $magnitude < 1e-15) )
								{
									$value = str_replace(array('.0E+', '.0E', 'E+', 'E'), array('e', 'e', 'e', 'e'), $text);
								}
								else
								{
									$value = (stripos($text, 'e') === false) ? $text : rtrim(rtrim(sprintf('%.20F', $value), '0'), '.');
								}
							}
						}
						else
						{
							$value = (string) $value;
						}
					}
					unset($value);
				}
				unset($row);
				$this->last_result = new DB_mysql_result($rows, $names);
			}
			else
			{
				$this->last_result = true;
			}
			$stmt->closeCursor();
			$this->last_insert_id = (int) $pdo->lastInsertId();
		} catch (PDOException $e) {
			$this->last_result = false;
			$this->last_error = '(' . ($e->errorInfo[1] ?? $e->getCode()) . ') ' . ($e->errorInfo[2] ?? $e->getMessage());
		}
		$endtime = microtime(true);

		$this->querycount++;

		if ( defined('DB_DEBUG') && DB_DEBUG == true )
		{
			echo "<p><pre>$query</pre><hr /></p>";
		}

		if ( $this->last_result )
		{
			if($this->profile)
			{
				$backtrace = debug_backtrace();
				$profilequery = "insert into hlstats_sql_web_profile (source, run_count, run_time) values ".
					"(" . $this->pdo()->quote(basename($backtrace[0]['file']).':'.$backtrace[0]['line']) . ",1,'".($endtime-$starttime)."')"
					."ON DUPLICATE KEY UPDATE run_count = run_count+1, run_time=run_time+".($endtime-$starttime);
				try {
					$this->pdo()->exec($profilequery);
				} catch (PDOException $e) {
				}
			}
			return $this->last_result;
		}
		else
		{
			if ($showerror)
			{
				$this->error('Bad query.');
			}
			else
			{
				return false;
			}
		}
	}

	function result($row, $field, $query_id = 0)
	{
		if ( !$query_id )
		{
			$query_id = $this->last_result;
		}

		if ( $this->data_seek($row, $query_id) )
		{
			$data = $this->fetch_array($query_id);
			return ($data && isset($data[$field])) ? $data[$field] : false;
		}
		return false;
	}

	// Like mysqli_real_escape_string(): PDO::quote() with the quotes taken off (it
	// follows the connection's charset and NO_BACKSLASH_ESCAPES the same way)
	function escape($string)
	{
		$quoted = $this->pdo()->quote((string) $string);

		return ($quoted === false) ? addslashes((string) $string) : substr($quoted, 1, -1);
	}

	function error($message, $exit=true)
	{
		$details = "Server Address: $this->db_addr\n" .
			"Server Username: $this->db_user\n" .
			"Error Diagnostic: $message\n" .
			"Server Error: $this->last_error\n" .
			"Last SQL Query:\n$this->last_query";

		// Connection details and SQL are only shown to visitors in debug mode
		error_log("HLstatsX database error:\n$details");

		if ( defined('DB_DEBUG') && DB_DEBUG == true )
		{
			echo '<pre>' . eHtml($details) . '</pre>';
		}

		error('Database error. The details have been written to the web server error log.', $exit);
	}
}
?>
