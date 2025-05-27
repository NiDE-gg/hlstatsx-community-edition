<?php
/*
HLstatsX Community Edition - Modern Autocomplete API
Enhanced autocomplete endpoint for modern theme
*/

define('IN_HLSTATS', true);
require('config.php');
require(INCLUDE_PATH . '/class_db.php');
require(INCLUDE_PATH . '/functions.php');

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers for security
header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_HOST']);
header('Access-Control-Allow-Methods: GET');

try {
    // Initialize database
    $db_classname = 'DB_' . DB_TYPE;
    if (!class_exists($db_classname)) {
        throw new Exception('Database class not found');
    }
    
    $db = new $db_classname(DB_ADDR, DB_USER, DB_PASS, DB_NAME, DB_PCONNECT);
    $g_options = getOptions();
    
    // Get search query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $game = isset($_GET['game']) ? valid_request($_GET['game'], false) : '';
    
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }
    
    // Escape query for SQL
    $query = $db->escape($query);
    
    // Build WHERE clause for game filtering
    $gameWhere = '';
    if ($game) {
        $gameWhere = " AND game = '" . $db->escape($game) . "'";
    }
    
    // Search players
    $sql = "
        SELECT 
            playerId,
            lastName as name,
            skill,
            activity,
            game,
            (SELECT COUNT(*) + 1 FROM hlstats_Players p2 
             WHERE p2.skill > hlstats_Players.skill AND p2.game = hlstats_Players.game AND p2.hideranking = 0) as rank
        FROM hlstats_Players 
        WHERE 
            lastName LIKE '%{$query}%' 
            AND hideranking = 0
            {$gameWhere}
        ORDER BY 
            skill DESC, 
            lastName ASC 
        LIMIT 10
    ";
    
    $result = $db->query($sql);
    $players = [];
    
    while ($row = $db->fetch_assoc($result)) {
        // Try to get avatar (this would need to be implemented based on your avatar system)
        $avatar = null;
        
        // Format activity as percentage
        $activity = round($row['activity'], 1);
        
        $players[] = [
            'id' => $row['playerId'],
            'name' => $row['name'],
            'skill' => (int)$row['skill'],
            'activity' => $activity,
            'rank' => (int)$row['rank'],
            'game' => $row['game'],
            'avatar' => $avatar
        ];
    }
    
    // Return JSON response
    echo json_encode($players);
    
} catch (Exception $e) {
    // Log error and return empty result
    error_log('Autocomplete error: ' . $e->getMessage());
    echo json_encode([]);
}
?>