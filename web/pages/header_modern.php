<?php
/*
HLstatsX Community Edition - Real-time player and clan rankings and statistics
Modern Header Template with Bootstrap 5 and responsive design
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

// hit counter
$db->query("UPDATE hlstats_Options SET value=value+1 WHERE keyname='counter_hits';"); 

// visit counter
if (isset($_COOKIE['ELstatsNEO_Visit']) && $_COOKIE['ELstatsNEO_Visit'] == 0) {
    $db->query("UPDATE hlstats_Options SET value=value+1 WHERE keyname='counter_visits';");
    @setcookie('ELstatsNEO_Visit', '1', time() + ($g_options['counter_visit_timeout'] * 60), '/');   
}

global $game, $mode;

// Style selection logic (preserved from original)
$selectedStyle = (isset($_COOKIE['style']) && $_COOKIE['style']) ? $_COOKIE['style'] : "";
$selectedStyle = isset($_POST['stylesheet']) ? $_POST['stylesheet'] : $selectedStyle; 

if(!empty($selectedStyle)) {
    $testfile = sprintf("%s/%s/%s", PAGE_PATH, '../styles', $selectedStyle);
    if(!file_exists($testfile)) {
        $selectedStyle = "";
    }
}

if(empty($selectedStyle)) {
    $selectedStyle = $g_options['style'];
}	

if (isset($_POST['stylesheet']) || isset($_COOKIE['style'])) {
    setcookie('style', $selectedStyle, time()+60*60*24*30);
}

// Determine icon path
if ($selectedStyle) {
    $style = preg_replace('/\.css$/','',$selectedStyle);
} else {
    $style = preg_replace('/\.css$/','',$g_options['style']);
}
$iconpath = IMAGE_PATH . "/icons";
if (file_exists($iconpath . "/" . $style)) {
    $iconpath = $iconpath . "/" . $style;
}

// Check if modern theme is selected
$isModernTheme = ($selectedStyle === 'modern.css');

// Determine theme preference for dark/light mode
$themePreference = isset($_COOKIE['theme_mode']) ? $_COOKIE['theme_mode'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $themePreference; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="HLstatsX Community Edition - Real-time gaming statistics and rankings">
    <meta name="author" content="HLstatsX Community Edition">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <?php if ($isModernTheme): ?>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Inter font for modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Legacy CSS for compatibility -->
    <link rel="stylesheet" type="text/css" href="hlstats.css" />
    <link rel="stylesheet" type="text/css" href="styles/<?php echo $selectedStyle; ?>" />
    <link rel="stylesheet" type="text/css" href="css/SqueezeBox.css" />
    
    <?php if ($mode == 'players'): ?>
    <link rel="stylesheet" type="text/css" href="css/Autocompleter.css" />
    <?php endif; ?>
    
    <link rel="SHORTCUT ICON" href="favicon.ico" />
    
    <!-- JavaScript libraries -->
    <?php if ($isModernTheme): ?>
    <!-- Modern vanilla JS will be loaded at end of body -->
    <?php else: ?>
    <!-- Legacy MooTools for backward compatibility -->
    <script type="text/javascript" src="<?php echo INCLUDE_PATH; ?>/js/mootools.js"></script>
    <?php endif; ?>
    
    <script type="text/javascript" src="<?php echo INCLUDE_PATH; ?>/js/SqueezeBox.js"></script>
    <script type="text/javascript" src="<?php echo INCLUDE_PATH; ?>/js/heatmap.js"></script>
    
    <?php if ($g_options['playerinfo_tabs'] == '1'): ?>
    <script type="text/javascript" src="<?php echo INCLUDE_PATH; ?>/js/tabs.js"></script>
    <?php endif; ?>
    
    <title>
    <?php
        echo $g_options['sitename']; 
        foreach ($title as $t) {
            echo " - $t";
        }
    ?>
    </title>
</head>
<body<?php echo $isModernTheme ? ' class="hlx-modern"' : ''; ?>>

<?php
// JavaScript check (preserved from original)
if (isset($_POST['js']) && $_POST['js']) {
    $_SESSION['nojs'] = 0;
} else {
    if ((!isset($_SESSION['nojs'])) or ($_SESSION['nojs'] == 1)) {
        echo '
        <form name="jsform" id="jsform" action="" method="post" style="display:none">
        <div>
        <input name="js" type="text" value="true" />
        <script type="text/javascript">
        document.jsform.submit();
        </script>
        </div>
        </form>';
        $_SESSION['nojs'] = 1;
        $g_options['playerinfo_tabs'] = 0;
        $g_options['show_google_map'] = 0;
    }
}

// Determine extra tabs
$extratabs = NULL;
if ($g_options['sourcebans_address'] && file_exists($iconpath . "/title-sourcebans.png")) {
    $extratabs .= $g_options['sourcebans_address'];
}
if ($g_options['forum_address'] && file_exists($iconpath . "/title-forum.png")) {
    $forumtabs = $g_options['forum_address'];
}

// Get active games count
$resultGames = $db->query("
    SELECT COUNT(code)
    FROM hlstats_Games
    WHERE hidden='0'
");
list($num_games) = $db->fetch_row($resultGames);
?>

<?php if ($isModernTheme): ?>
<!-- Modern Theme Layout -->

<!-- Theme Toggle Button -->
<div class="hlx-theme-toggle" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
    <i class="bi bi-moon-fill" id="theme-icon"></i>
</div>

<!-- Main Header -->
<header class="hlx-header">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand" href="<?php echo $g_options['scripturl']; ?>">
                <img src="<?php echo $iconpath; ?>/title.png" alt="HLstatsX Community Edition" />
                <span class="ms-2 d-none d-md-inline"><?php echo $g_options['sitename']; ?></span>
            </a>
            
            <!-- Mobile menu toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Main navigation -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $g_options['scripturl']; ?>">
                            <i class="bi bi-house me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $g_options['scripturl']; ?>?mode=search">
                            <i class="bi bi-search me-1"></i>Search
                        </a>
                    </li>
                    <?php if ($extratabs): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $extratabs; ?>" target="_blank">
                            <i class="bi bi-ban me-1"></i>SourceBans
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($forumtabs)): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $forumtabs; ?>" target="_blank">
                            <i class="bi bi-chat-square-text me-1"></i>Forums
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $g_options['scripturl']; ?>?mode=help">
                            <i class="bi bi-question-circle me-1"></i>Help
                        </a>
                    </li>
                </ul>
                
                <!-- Style selector -->
                <?php if ($g_options['display_style_selector'] == 1): ?>
                <div class="navbar-nav">
                    <form class="d-flex align-items-center me-3" method="post">
                        <label class="form-label me-2 mb-0 text-light">Theme:</label>
                        <select name="stylesheet" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php 
                            $styles = array();
                            $d = dir('styles'); 
                            while (false !== ($e = $d->read())) { 
                                if (is_file("styles/$e") && ($e != '.') && ($e != '..') && $e != $g_options['style']) { 
                                    $ename = ucwords(strtolower(str_replace(array('_','.css'), array(' ',''), $e))); 
                                    $styles[$e] = $ename; 
                                } 
                            }
                            $d->close(); 
                            asort($styles); 
                            $styles = array_merge(array($g_options['style'] => 'Default'),$styles);
                            foreach ($styles as $e => $ename) { 
                                $sel = ($e == $selectedStyle) ? ' selected="selected"' : ''; 
                                echo "<option value=\"$e\"$sel>$ename</option>\n"; 
                            } 
                            ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Games List (if multiple games) -->
<?php if ($num_games > 1 && $g_options['display_gamelist'] == 1): ?>
<div class="container-fluid mt-2">
    <div class="hlx-game-selector d-flex justify-content-center flex-wrap gap-2">
        <?php include(PAGE_PATH .'/gameslist.php'); ?>
    </div>
</div>
<?php endif; ?>

<!-- Breadcrumb Navigation -->
<div class="container-fluid">
    <nav aria-label="breadcrumb" class="hlx-breadcrumb">
        <ol class="breadcrumb">
            <?php
            if ($g_options['sitename'] && $g_options['siteurl']) {
                echo '<li class="breadcrumb-item"><a href="http://' . preg_replace('/http:\/\//', '', $g_options['siteurl']) . '">'. $g_options['sitename'] . '</a></li>';
            }
            echo '<li class="breadcrumb-item"><a href="' . $_SERVER['PHP_SELF'] . '">HLstatsX</a></li>';
            
            foreach ($location as $l => $url) {
                $url = preg_replace('/%s/', $g_options['scripturl'], $url);
                $url = preg_replace('/&/', '&amp;', $url);
                if ($url) {
                    echo '<li class="breadcrumb-item"><a href="' . $url . '">' . $l . '</a></li>';
                } else {
                    echo '<li class="breadcrumb-item active" aria-current="page">' . $l . '</li>';
                }
            }
            ?>
        </ol>
    </nav>
</div>

<main class="container-fluid">

    <!-- Game-specific Navigation -->
    <?php if ($game != ''): ?>
    <nav class="hlx-nav">
        <ul class="nav nav-pills nav-fill flex-column flex-md-row">
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?game=$game"; ?>" class="nav-link">
                    <i class="bi bi-server me-1"></i>Servers
                </a>
            </li>
            
            <?php if ($g_options['nav_globalchat'] == 1): ?>
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=chat&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-chat-dots me-1"></i>Chat
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-people me-1"></i>Players
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=clans&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-shield me-1"></i>Clans
                </a>
            </li>
            
            <?php if ($g_options["countrydata"] == 1): ?>
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=countryclans&amp;game=$game&amp;sort=nummembers"; ?>" class="nav-link">
                    <i class="bi bi-globe me-1"></i>Countries
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=awards&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-trophy me-1"></i>Awards
                </a>
            </li>
            
            <?php
            // Check for actions
            $db->query("SELECT game FROM hlstats_Actions WHERE game='".$game."' LIMIT 1");
            if ($db->num_rows() > 0):
            ?>
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=actions&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-lightning me-1"></i>Actions
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=weapons&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-crosshair me-1"></i>Weapons
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=maps&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-map me-1"></i>Maps
                </a>
            </li>
            
            <?php
            $result = $db->query("SELECT game from hlstats_Roles WHERE game='$game' AND hidden = '0'");
            if ($db->num_rows($result) > 0):
            ?>
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=roles&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-person-badge me-1"></i>Roles
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($g_options['nav_cheaters'] == 1): ?>
            <li class="nav-item">
                <a href="<?php echo $g_options['scripturl'] . "?mode=bans&amp;game=$game"; ?>" class="nav-link">
                    <i class="bi bi-ban me-1"></i>Bans
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Banner -->
    <?php if ($g_options['bannerdisplay'] != 0 && ($mode == 'contents' || $g_options['bannerdisplay'] == 1)): ?>
    <div class="hlx-block text-center mb-4">
        <img src="<?php echo ((strncmp($g_options['bannerfile'], 'http:/', 6) == 0) ? $g_options['bannerfile'] : IMAGE_PATH.'/'.$g_options['bannerfile']); ?>" 
             alt="Banner" class="img-fluid rounded" />
    </div>
    <?php endif; ?>

<?php else: ?>
<!-- Legacy Theme Layout (preserved original structure) -->
<div class="block">
    <div class="headerblock">
        <div class="title">
            <a href="<?php echo $g_options['scripturl']; ?>">
                <img src="<?php echo $iconpath; ?>/title.png" alt="HLstatsX Community Edition" title="HLstatsX Community Edition" />
            </a>
        </div>

        <?php if ($num_games > 1 && $g_options['display_gamelist'] == 1): ?>
        <div class="header_gameslist"><?php include(PAGE_PATH .'/gameslist.php'); ?></div>
        <?php endif; ?>
        
        <div class="headertabs">
            <ul>
                <li class="header_tabs"><a href="<?php echo $g_options['scripturl'] ?>">Contents</a></li>
                <li class="header_tabs"><a href="<?php echo $g_options['scripturl'] ?>?mode=search">Search</a></li>
                <?php if ($extratabs) { print '<li class="header_tabs"><a href="' . $extratabs . '" target="_blank">Sourcebans</a></li>'; } ?>
                <?php if (isset($forumtabs)) { print '<li class="header_tabs"><a href="' . $forumtabs . '" target="_blank">Forums</a></li>'; } ?>
                <li class="header_tabs"><a href="<?php echo $g_options['scripturl'] ?>?mode=help">Help</a></li>
            </ul>
        </div>
    </div>
    
    <div class="location" style="clear:both;width:100%;">
        <ul class="fNormal" style="float:left">
        <?php
        if ($g_options['sitename'] && $g_options['siteurl']) {
            echo '<li><a href="http://' . preg_replace('/http:\/\//', '', $g_options['siteurl']) . '">'. $g_options['sitename'] . '</a> <span class="arrow">&raquo;</span></li>';
        }
        echo '<li><a href="http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '">HLstatsX</a>';

        foreach ($location as $l => $url) {
            $url = preg_replace('/%s/', $g_options['scripturl'], $url);
            $url = preg_replace('/&/', '&amp;', $url);
            echo ' <span class="arrow">&raquo;</span></li><li>';
            if ($url) {
                echo "<a href=\"$url\">$l</a>";
            } else {
                echo "<strong>$l</strong>";
            }
        }
        ?>
        </li>
        </ul>

        <?php if ($g_options['display_style_selector'] == 1): ?>
        <div class="fNormal" style="float:right;"> 
            <form name="style_selection" id="style_selection" action="" method="post"> Style: 
                <select name="stylesheet" onchange="document.style_selection.submit()"> 
                <?php 
                $styles = array();
                $d = dir('styles'); 
                while (false !== ($e = $d->read())) { 
                    if (is_file("styles/$e") && ($e != '.') && ($e != '..') && $e != $g_options['style']) { 
                        $ename = ucwords(strtolower(str_replace(array('_','.css'), array(' ',''), $e))); 
                        $styles[$e] = $ename; 
                    } 
                }
                $d->close(); 
                asort($styles); 
                $styles = array_merge(array($g_options['style'] => 'Default'),$styles);
                foreach ($styles as $e => $ename) { 
                    $sel = ($e == $selectedStyle) ? ' selected="selected"' : ''; 
                    echo "<option value=\"$e\"$sel>$ename</option>\n"; 
                } 
                ?> 
                </select> 
            </form> 
        </div> 
        <?php endif; ?>
    </div>
    <div class="location_under" style="clear:both;width:100%;"></div>
</div>

<br />
      
<div class="content" style="clear:both;">
    <?php if ($g_options['bannerdisplay'] != 0 && ($mode == 'contents' || $g_options['bannerdisplay']==1)): ?>    
    <div class="block" style="text-align:center;">
        <img src="<?php echo ((strncmp($g_options['bannerfile'], 'http:/', 6) == 0)?$g_options['bannerfile']:IMAGE_PATH.'/'.$g_options['bannerfile']); ?>" alt="Banner" />
    </div>
    <?php endif; ?>        

    <?php if ($game != ''): ?>    
    <nav>
    <ul class="fancyNav">
        <li><a href="<?php echo $g_options['scripturl']  . "?game=$game";  ?>" class="fHeading">Servers</a></li>
        <?php if ($g_options['nav_globalchat']==1): ?>
        <li><a href="<?php echo $g_options['scripturl']  . "?mode=chat&amp;game=$game";  ?>" class="fHeading">Chat</a></li>
        <?php endif; ?>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=players&amp;game=$game"; ?>" class="fHeading">Players</a></li>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=clans&amp;game=$game"; ?>" class="fHeading">Clans</a></li>
        <?php if ($g_options["countrydata"]==1): ?>
        <li><a href="<?php echo $g_options['scripturl']  . "?mode=countryclans&amp;game=$game&amp;sort=nummembers";  ?>" class="fHeading">Countries</a></li>
        <?php endif; ?>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=awards&amp;game=$game"; ?>" class="fHeading">Awards</a></li>
        <?php
        $db->query("SELECT game FROM hlstats_Actions WHERE game='".$game."' LIMIT 1");
        if ($db->num_rows()>0): 
        ?> 
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=actions&amp;game=$game"; ?>" class="fHeading">Actions</a></li>
        <?php endif; ?>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=weapons&amp;game=$game"; ?>" class="fHeading">Weapons</a></li>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=maps&amp;game=$game"; ?>" class="fHeading">Maps</a></li>
        <?php
        $result = $db->query("SELECT game from hlstats_Roles WHERE game='$game' AND hidden = '0'");
        if ($db->num_rows($result) > 0):
        ?>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=roles&amp;game=$game"; ?>" class="fHeading">Roles</a></li>
        <?php endif; ?>
        <?php if ($g_options['nav_cheaters'] == 1): ?>
        <li><a href="<?php echo $g_options['scripturl'] . "?mode=bans&amp;game=$game"; ?>" class="fHeading">Bans</a></li>
        <?php endif; ?>
    </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>