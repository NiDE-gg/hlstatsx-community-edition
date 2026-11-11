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


 * Table
 * Generates an HTML table.
 * 
 * @package HLstatsX Community Edition
 * @author Nicholas Hastings (nshastings@gmail.com)
 * @copyright HLstatsX Community Edition
 * @version 2008
 * @access public
 */

class Table
{
	var $columns;
	var $keycol;
	var $sort;
	var $sortorder;
	var $sort2;
	var $page;
	var $showranking;
	var $numperpage;
	var $var_page;
	var $var_sort;
	var $var_sortorder;
	var $sorthash;
	var $ajax;

	var $columnlist;
	var $startitem;

	const MAX_PAGE = 1000000;

	var $maxpagenumbers = 20;
	public $sort_default_order;

	function __construct($columns, $keycol, $sort_default, $sort_default2,
	                $showranking=false, $numperpage=50, $var_page='page',
	                $var_sort='sort', $var_sortorder='sortorder', $sorthash='',
	                $sort_default_order='desc', $ajax = false)
	{

		global $g_options;
		
		$this->columns = $columns;
		$this->keycol  = $keycol;
		$this->showranking = $showranking;
		$this->numperpage  = $numperpage;
		$this->var_page = $var_page;
		$this->var_sort = $var_sort;
		$this->var_sortorder = $var_sortorder;
		$this->sorthash = $sorthash;
		$this->sort_default_order = $sort_default_order;
		$this->ajax = ( $g_options['playerinfo_tabs'] ) ? $ajax : false;

		$this->page = valid_request(intval($_GET[$var_page] ?? ''), true);
		$this->sort = valid_request($_GET[$var_sort] ?? '', false);
		$this->sortorder = valid_request($_GET[$var_sortorder] ?? '', false);

		// Capped: a huge page number turned the LIMIT offset into a float and the
		// query into a syntax error
		$this->page = max(1, min($this->page, self::MAX_PAGE));
		$this->startitem = ($this->page - 1) * $this->numperpage;

		foreach ($columns as $col)
		{
			if ($col->sort != 'no')
			{
				$this->columnlist[] = $col->name;
			}
		}

		if (!is_array($this->columnlist) || !in_array($this->sort, $this->columnlist, true))
		{
			$this->sort = $sort_default;
		}

		if ($this->sortorder != 'asc' && $this->sortorder != 'desc')
		{
			$this->sortorder = $this->sort_default_order;
		}

		if ($this->sort == $sort_default2)
		{
			$this->sort2 = $sort_default;
		}
		else
		{
			$this->sort2 = $sort_default2;
		}
	}
	
	function start($numitems, $width=100, $align='center')
	{
		global $g_options, $game, $realgame, $db;
		$numpages = ceil($numitems / $this->numperpage);
?>

<div class="subblock">

<table class="data-table">
	<tr class="data-table-head">
<?php
		$totalwidth = 0;

		if ($this->showranking)
		{
			$totalwidth += 5;
			echo "<td style=\"width:5%;text-align:right;\" class=\"fSmall\">Rank</td>\n";
		}

		foreach ($this->columns as $col)
		{
			$totalwidth += $col->width;
			echo "<td style=\"width:$col->width%;text-align:$col->align;\" class=\"fSmall" . $col->mobileClass() . "\">";
			if ($col->sort != 'no')
			{
				echo getSortArrow($this->sort, $this->sortorder, $col->name,
					$col->title, $this->var_sort, $this->var_sortorder,
					$this->sorthash);
			}
			else
			{
				echo $col->title;
			}
			echo "</td>\n";
		}
?>
	</tr>

<?php
		if ($totalwidth != 100)
		{
			error("Warning: Column widths do not add to 100%! (=$totalwidth%)", false);
		}

		$rank = ($this->page - 1) * $this->numperpage + 1;

	}
	
	function draw ($result, $numitems, $width=100, $align='center')
	{
		global $g_options, $game, $realgame, $db;
		$numpages = ceil($numitems / $this->numperpage);
?>

<div class="subblock" style="text-align:<?php echo $align; ?>;">

<table class="data-table">

		<tr class="data-table-head">
<?php
		$totalwidth = 0;

		if ($this->showranking)
		{
			$totalwidth += 5;
			echo "<td style=\"width:5%;text-align:right;\" class=\"fSmall\">Rank</td>\n";
		}

		foreach ($this->columns as $col)
		{
			$totalwidth += $col->width;
			echo "<td style=\"width:$col->width%;text-align:$col->align;\" class=\"fSmall" . $col->mobileClass() . "\">";
			if ($col->sort != 'no')
			{
				echo getSortArrow($this->sort, $this->sortorder, $col->name,
					$col->title, $this->var_sort, $this->var_sortorder,
					$this->sorthash, $this->ajax);
			}
			else
			{
				echo $col->title;
			}
			echo "</td>\n";
		}
?>
		</tr>

<?php
		if ($totalwidth != 100)
		{
			error("Warning: Column widths do not add to 100%! (=$totalwidth%)", false);
		}

		$rank = ($this->page - 1) * $this->numperpage + 1;

		while ($rowdata = $db->fetch_array($result))
		{
			echo "<tr>\n";
			$i = 0;

			if ($this->showranking)
			{
				$c = ($i % 2) + 1;
				$i++;
				echo "<td style=\"text-align:right;\" class=\"bg$c\">$rank</td>\n";
			}

			foreach ($this->columns as $col)
			{
				$c = ($i % 2) + 1;
				$class = "";

				$cellbody = '';
				$colval = $rowdata[$col->name] ?? '';
                $colval_lower = (!empty($rowdata[$col->name])) ? strtolower($rowdata[$col->name]) : null;

				if ($col->align != 'left')
				{
					$colalign = " style=\"text-align:$col->align;\"";
				}
				else
				{
					$colalign = "";
				}

				$class = "bg$c" . $col->mobileClass();

				if (($col->icon) || ($col->flag))
				{
					$cellbody = '&nbsp;';
				}

				if ($col->link)
				{
					// (The javascript: link variant was unused and put the raw key into the href.)
					$link = str_ireplace('%k', urlencode((string)$rowdata[$this->keycol]), $col->link);
					$cellbody .= "<a href=\"" . $g_options['scripturl'] . "?$link\">";
				}

				if ($col->icon)
				{
					$image = getImage("/$col->icon");
					if ($image)
					{
						$cellbody .= '<img src="'.$image['url']. "\" class=\"tableicon\" alt=\"$col->icon\" />";
					}
				}
				elseif ($col->flag)
				{
					#$link = ereg_replace("%f", $col->link);
					if ($g_options['countrydata'] == 1) { 
						if ($rowdata['flag'] == '') {
							$rowdata['flag'] = '0';
							$alt_text        = 'No Country';
						} else {
							$alt_text        = eHtml(ucfirst(strtolower((string) ($rowdata['country'] ?? ''))));
						}

						$cellbody .= '<img src="' . getFlag($rowdata['flag'])."\" class=\"tableicon\" alt=\"$alt_text\" title=\"$alt_text\" />";
					}
					else
					{
						$col->flag = 'player';
						$cellbody .= '<img src="' . IMAGE_PATH 	. "/$col->flag.gif\" class=\"tableicon\" alt=\"$col->icon.gif\" />";
					}                
				}  
				
				switch ($col->type) {
					case 'elorank':
                        if (empty($colval)) {
                              $colval = '0';
                        }

						$cellbody = '<img src="' . IMAGE_PATH  . "/mmranks/" . (int) $colval . ".png\" class=\"tableicon\" alt=\"elorank\" style=\"height:20px;width:50px;\" />";
						break;
					case 'timestamp':
						$cellbody  = timestamp_to_str($colval);
						break;           

					case 'roleimg':
						$image = getImage("/games/$game/roles/" . $colval_lower);
						// check if image exists for game -- otherwise check realgame
						if ($image)
						{
							$cellbody .= '<img src="' . eHtml($image['url']) . '" alt="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" title="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" />&nbsp;';
						}
						elseif ($image = getImage("/games/$realgame/roles/" . $colval_lower))
						{
							$cellbody .= '<img src="' . eHtml($image['url']) . '" alt="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" title="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" />&nbsp;';
						}
						
						if (!empty($col->fname[$colval_lower]))
						{
							$cellbody .= '<b>' . eHtmlOnce($col->fname[$colval_lower]) . '</b>';
						}
						else
						{
							$cellbody .= '<b>' . eHtml(ucwords(preg_replace('/_/', ' ', (string) $colval))) . '</b>';
						}

						break;
					  
					case 'weaponimg':
						// Check if game has the image -- if not, failback to real game.  If not, no image.
						$image = getImage("/games/$game/weapons/" . $colval_lower);
						if ($image)
						{
							$cellbody .= '<img src="' . eHtml($image['url']) . '" ' . $image['size'] . ' alt="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" title="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" />';
						}
						elseif ($image = getImage("/games/$realgame/weapons/" . $colval_lower))
						{
							$cellbody .= '<img src="' . eHtml($image['url']) . '" ' . $image['size'] . ' alt="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" title="' . eHtmlOnce($col->fname[$colval_lower] ?? '') . '" />';
						}
						else
						{
							$cellbody .= '<b>' . (!empty($col->fname[$colval_lower]) ? eHtmlOnce($col->fname[$colval_lower]) : eHtml(ucwords(preg_replace('/_/', ' ', (string) $colval)))) . '</b>';
						}
						break;

					case 'bargraph':
						$cellbody .= '<meter min="0" max="100" low="25" high="50" optimum="75" value="'.$colval.'"></meter>';
						break;
					case 'heatmap':
						$heatmap = getImage("/games/$game/heatmaps/$colval-kill");
						$heatmapthumb = getImage("/games/$game/heatmaps/$colval-kill-thumb");

						if ($heatmap) {
							$cellbody .= "<span style=\"text-align: center;\"><a href=\"" . eHtml($heatmap['url']) . "\" rel=\"boxed\"><img width=\"20\" height=\"16\" src=\"" . eHtml($heatmapthumb['url'] ?? '') . "\" /></a></span>";
						} else {
							$cellbody .= "&nbsp;";
						}
						break;
					default:
						if ($this->showranking && $rank == 1 && $i == 1)
							$cellbody .= '<b>';
						if ((is_numeric($colval)) && ($colval >= 1000))
							$colval = number_format($colval);
						$colval = nl2br(htmlspecialchars((string) $colval, ENT_COMPAT));

						if ($col->embedlink == 'yes')
							{
								// %A%url%text%/A% markers come from queries that build links to
								// this site (playerhistory, admin events), but the same columns hold
								// raw player text such as chat messages: only link to scripturl.
								$linkPrefix = htmlspecialchars($g_options['scripturl'] . '?', ENT_COMPAT);
								$colval = preg_replace_callback('/%A%([^ %]+)%(.*?)%\/A%/s', function ($m) use ($linkPrefix) {
									if (strpos($m[1], $linkPrefix) === 0) {
										return '<a href="' . $m[1] . '">' . $m[2] . '</a>';
									}
									return $m[2];
								}, $colval);
							}

						$cellbody .= $colval;
							if ($this->showranking && $rank == 1 && $i == 1)
								$cellbody .= '</b>';
							break;
				}

				if ($col->link)
				{
					$cellbody .= '</a>';
				}

				if ($col->append)
				{
					// Plain text in every caller; one of them appends an award verb set
					// from the admin panel
					$cellbody .= eHtmlOnce($col->append);
				}
				
				if ($col->skill_change) {
					$rowdata['last_skill_change'] = (int) ($rowdata['last_skill_change'] ?? 0);
					if ($rowdata['last_skill_change'] == 0)
						$cellbody .= "&nbsp;<img src=\"" . IMAGE_PATH
							. "/t1.gif\" alt=\"".$rowdata['last_skill_change']." Points\" />";
					elseif ($rowdata['last_skill_change'] > 0)
						$cellbody .= "&nbsp;<img src=\"" . IMAGE_PATH
							. "/t0.gif\" alt=\"".$rowdata['last_skill_change']." Points\" />";
					elseif ($rowdata['last_skill_change'] < 0)
						$cellbody .= "&nbsp;<img src=\"" . IMAGE_PATH
							. "/t2.gif\" alt=\"".$rowdata['last_skill_change']." Points\" />";
				}
				
				echo "<td$colalign class=\"$class\">"
						. $cellbody
						. "</td>\n";
				$i++;
			}

			echo "</tr>\n\n";

			$rank++;
		}
?>
		</table>
</div><br /><br />
<?php
		if ($numpages > 1)
		{
?>
<div class="subblock" style="text-align:right;">
	<span style="text-align:right;">
<?php
			echo 'Page: ';

			$start = $this->page - intval($this->maxpagenumbers / 2);
			if ($start < 1) $start=1;

			$end = $numpages;
			if ($end > $this->maxpagenumbers + $start-1)
				$end = $this->maxpagenumbers + $start-1;

			if ($end - $start + 1 < $this->maxpagenumbers)
				$start = $end - $this->maxpagenumbers + 1;

			if ($start < 1) $start=1;

			if ($start > 1)
			{
				if ($start > 2)
					$this->_echoPageNumber(1, "First page", "", " ...");
				else
					$this->_echoPageNumber(1, 1);
			}

			for ($i=$start; $i <= $end; $i++)
			{
				if ($i == $this->page)
				{
					echo "<b>$i</b> ";
				}
				else
				{
					$this->_echoPageNumber($i, $i);
				}

				if ($i == $end && $i < $numpages)
				{
					if ($i < $numpages - 1)
						$this->_echoPageNumber($numpages, "Last page", "... ");
					else
						$this->_echoPageNumber($numpages, $numpages);
				}
			}
		?>
	</span>
</div><br /><br />
<?php
		}
	}

	function _echoPageNumber($number, $label, $prefix = '', $postfix = '')
	{
		global $g_options;

		$queryStr = makeQueryString($this->var_page, $number);
		$url = $g_options['scripturl'] . '?' . $queryStr;

		if ($this->sorthash) {
			$url .= "#{$this->sorthash}";
		}

		$urlSafe = eHtml($url);

		$refresh = '';
		if ($this->ajax) {
			// tabs.js reloads the AJAX tab on that page instead of following the link
			$refresh = ' data-tab-refresh="' . eHtml(json_encode([$this->var_page => $number])) . '"';
		}

		echo "{$prefix}<a href=\"{$urlSafe}\"{$refresh}>{$label}</a>{$postfix} ";
	}
}


//
// TableColumn
//
// Data structure for the properties of a column in a Table
//

class TableColumn
{
	var $name;
	var $title;

	var $align = 'left';
	var $width = 20;
	var $icon;
	var $mmrank;
	var $link;
	var $sort = 'yes';
	var $append;
	var $type = 'text';
	var $embedlink = 'no';
	var $flag;
	var $skill_change;
	var $heatmap;
	var $mobile = 'show';
	public $fname;

	function __construct($name, $title, $attrs="", $fname=null)
	{
		$this->name = $name;
		$this->title= $title;

		$allowed_attrs = array(
			'align',
			'width',
			'icon',
			'mmrank',
			'link',
			'sort',
			'append',
			'type',
			'embedlink',
			'flag',
			'skill_change',
			'heatmap',
			'mobile'
		);

		parse_str($attrs ?? 0, $attr_vars);

		foreach ($allowed_attrs as $a)
		{
			if (isset($attr_vars[$a]))
			{
				$this->$a = $attr_vars[$a];
			}
		}

		$this->fname = $fname;
	}

	// mobile=hide drops the column on small screens (css/mobile.css)
	function mobileClass()
	{
		return $this->mobile == 'hide' ? ' hide-mobile' : '';
	}
}