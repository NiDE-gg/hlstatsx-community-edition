<?php
/*
HLstatsX Community Edition - Modern Theme Helper Functions
Functions to enhance the modern theme experience
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

/**
 * Check if modern theme is active
 */
function isModernTheme() {
    global $g_options;
    $selectedStyle = (isset($_COOKIE['style']) && $_COOKIE['style']) ? $_COOKIE['style'] : $g_options['style'];
    return ($selectedStyle === 'modern.css');
}

/**
 * Modern section title with Bootstrap styling
 */
function printModernSectionTitle($title, $icon = '') {
    if (isModernTheme()) {
        echo '<div class="hlx-block">';
        echo '<h2 class="h4 mb-0 d-flex align-items-center">';
        if ($icon) {
            echo '<i class="bi bi-' . $icon . ' me-2 text-primary"></i>';
        }
        echo htmlspecialchars($title);
        echo '</h2>';
        echo '</div>';
    } else {
        // Fallback to original function
        printSectionTitle($title);
    }
}

/**
 * Modern data table wrapper
 */
function startModernTable($headers = array(), $classes = 'hlx-table table table-striped table-hover', $sortable = false) {
    if (isModernTheme()) {
        echo '<div class="table-responsive">';
        echo '<table class="' . $classes . '"' . ($sortable ? ' data-sortable="true"' : '') . '>';
        
        if (!empty($headers)) {
            echo '<thead><tr>';
            foreach ($headers as $header => $sortKey) {
                $sortAttr = $sortKey ? ' data-sort="' . $sortKey . '"' : '';
                echo '<th' . $sortAttr . '>' . htmlspecialchars($header) . '</th>';
            }
            echo '</tr></thead>';
        }
        echo '<tbody>';
    } else {
        // Fallback to legacy table
        echo '<table class="data-table">';
        if (!empty($headers)) {
            echo '<tr class="data-table-head">';
            foreach ($headers as $header => $sortKey) {
                echo '<td class="fSmall">' . htmlspecialchars($header) . '</td>';
            }
            echo '</tr>';
        }
    }
}

/**
 * End modern table wrapper
 */
function endModernTable() {
    if (isModernTheme()) {
        echo '</tbody></table></div>';
    } else {
        echo '</table>';
    }
}

/**
 * Modern player rank badge
 */
function getModernRankBadge($rank) {
    if (!isModernTheme()) {
        return $rank;
    }
    
    $badgeClass = 'badge bg-secondary';
    if ($rank == 1) {
        $badgeClass = 'hlx-rank-badge hlx-rank-1';
    } elseif ($rank == 2) {
        $badgeClass = 'hlx-rank-badge hlx-rank-2';
    } elseif ($rank == 3) {
        $badgeClass = 'hlx-rank-badge hlx-rank-3';
    } elseif ($rank <= 10) {
        $badgeClass = 'badge bg-warning text-dark';
    } elseif ($rank <= 50) {
        $badgeClass = 'badge bg-info text-dark';
    }
    
    return '<span class="' . $badgeClass . '">#' . number_format($rank) . '</span>';
}

/**
 * Modern stat card
 */
function printModernStatCard($title, $value, $icon = '', $color = 'primary') {
    if (isModernTheme()) {
        echo '<div class="col-md-3 col-sm-6 mb-3">';
        echo '<div class="hlx-stat-card bg-' . $color . '">';
        if ($icon) {
            echo '<i class="bi bi-' . $icon . ' mb-2" style="font-size: 1.5rem;"></i>';
        }
        echo '<div class="hlx-stat-number" data-count="' . $value . '">' . number_format($value) . '</div>';
        echo '<div class="hlx-stat-label">' . htmlspecialchars($title) . '</div>';
        echo '</div>';
        echo '</div>';
    } else {
        // Legacy output
        echo '<div class="stat-item">';
        echo '<strong>' . number_format($value) . '</strong><br>';
        echo $title;
        echo '</div>';
    }
}

/**
 * Modern player card
 */
function printModernPlayerCard($playerData) {
    if (!isModernTheme()) {
        return false;
    }
    
    echo '<div class="hlx-player-card">';
    echo '<div class="row align-items-center">';
    
    // Avatar
    echo '<div class="col-auto">';
    echo '<img src="' . (isset($playerData['avatar']) ? $playerData['avatar'] : 'hlstatsimg/noimage.gif') . '" ';
    echo 'alt="Avatar" class="rounded" width="48" height="48">';
    echo '</div>';
    
    // Player info
    echo '<div class="col">';
    echo '<h6 class="mb-1">';
    echo '<a href="?mode=playerinfo&player=' . $playerData['playerId'] . '" class="text-decoration-none">';
    echo htmlspecialchars($playerData['lastName']);
    echo '</a>';
    echo '</h6>';
    
    if (isset($playerData['clan'])) {
        echo '<small class="text-muted">Clan: ' . htmlspecialchars($playerData['clan']) . '</small><br>';
    }
    
    echo '<small class="text-muted">';
    echo 'Skill: ' . number_format($playerData['skill']) . ' | ';
    echo 'Activity: ' . number_format($playerData['activity'], 1) . '%';
    echo '</small>';
    echo '</div>';
    
    // Rank
    echo '<div class="col-auto">';
    echo getModernRankBadge($playerData['rank']);
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    
    return true;
}

/**
 * Modern alert/message box
 */
function printModernAlert($message, $type = 'info', $dismissible = false) {
    if (isModernTheme()) {
        $alertClass = 'alert alert-' . $type;
        if ($dismissible) {
            $alertClass .= ' alert-dismissible fade show';
        }
        
        echo '<div class="' . $alertClass . '" role="alert">';
        
        // Add icon based on type
        $iconMap = [
            'success' => 'check-circle',
            'warning' => 'exclamation-triangle',
            'danger' => 'exclamation-octagon',
            'info' => 'info-circle'
        ];
        
        if (isset($iconMap[$type])) {
            echo '<i class="bi bi-' . $iconMap[$type] . ' me-2"></i>';
        }
        
        echo htmlspecialchars($message);
        
        if ($dismissible) {
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        }
        
        echo '</div>';
    } else {
        // Legacy styling
        echo '<div class="' . $type . '">' . htmlspecialchars($message) . '</div>';
    }
}

/**
 * Modern progress bar
 */
function printModernProgress($current, $max, $label = '', $color = 'primary') {
    if (!isModernTheme()) {
        return false;
    }
    
    $percentage = $max > 0 ? ($current / $max) * 100 : 0;
    
    echo '<div class="mb-2">';
    if ($label) {
        echo '<div class="d-flex justify-content-between mb-1">';
        echo '<span class="fw-semibold">' . htmlspecialchars($label) . '</span>';
        echo '<span class="text-muted">' . number_format($current) . ' / ' . number_format($max) . '</span>';
        echo '</div>';
    }
    
    echo '<div class="progress" style="height: 8px;">';
    echo '<div class="progress-bar bg-' . $color . '" style="width: ' . $percentage . '%" ';
    echo 'role="progressbar" aria-valuenow="' . $current . '" aria-valuemin="0" aria-valuemax="' . $max . '"></div>';
    echo '</div>';
    echo '</div>';
    
    return true;
}

/**
 * Modern button helper
 */
function getModernButton($text, $url, $style = 'primary', $size = '', $icon = '') {
    if (!isModernTheme()) {
        return '<a href="' . $url . '">' . htmlspecialchars($text) . '</a>';
    }
    
    $sizeClass = $size ? ' btn-' . $size : '';
    $iconHtml = $icon ? '<i class="bi bi-' . $icon . ' me-1"></i>' : '';
    
    return '<a href="' . $url . '" class="btn btn-' . $style . $sizeClass . ' hlx-btn">' . 
           $iconHtml . htmlspecialchars($text) . '</a>';
}

/**
 * Modern pagination
 */
function printModernPagination($currentPage, $totalPages, $baseUrl) {
    if (!isModernTheme() || $totalPages <= 1) {
        return false;
    }
    
    echo '<nav aria-label="Page navigation">';
    echo '<ul class="pagination justify-content-center">';
    
    // Previous button
    $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
    $prevUrl = ($currentPage > 1) ? $baseUrl . '&page=' . ($currentPage - 1) : '#';
    echo '<li class="page-item' . $prevDisabled . '">';
    echo '<a class="page-link" href="' . $prevUrl . '">Previous</a>';
    echo '</li>';
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">1</a></li>';
        if ($start > 2) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i == $currentPage) ? ' active' : '';
        echo '<li class="page-item' . $activeClass . '">';
        echo '<a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>';
        echo '</li>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
    $nextUrl = ($currentPage < $totalPages) ? $baseUrl . '&page=' . ($currentPage + 1) : '#';
    echo '<li class="page-item' . $nextDisabled . '">';
    echo '<a class="page-link" href="' . $nextUrl . '">Next</a>';
    echo '</li>';
    
    echo '</ul>';
    echo '</nav>';
    
    return true;
}

/**
 * Enhanced section title that adapts to theme
 */
function printSectionTitle($title) {
    global $g_options;
    
    if (isModernTheme()) {
        printModernSectionTitle($title);
    } else {
        // Original function behavior
        echo '<div class="subblock">';
        echo '<div class="block_icon">game</div>';
        echo '<div class="block_title">' . htmlspecialchars($title) . '</div>';
        echo '</div>';
    }
}
?>