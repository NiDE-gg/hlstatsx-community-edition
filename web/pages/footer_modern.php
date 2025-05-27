<?php
/*
HLstatsX Community Edition - Modern Footer Template
*/

if (!defined('IN_HLSTATS')) {
    die('Do not access this file directly.');
}

// Calculate script time
global $scripttime, $db;
$scripttime = round(microtime(true) - $scripttime, 4);

// Check if modern theme is selected
$selectedStyle = (isset($_COOKIE['style']) && $_COOKIE['style']) ? $_COOKIE['style'] : $g_options['style'];
$isModernTheme = ($selectedStyle === 'modern.css');
?>

<?php if ($isModernTheme): ?>
</main>

<!-- Modern Footer -->
<footer class="hlx-footer mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="https://github.com/NiDE-gg/hlstatsx-community-edition" target="_blank" class="d-block mb-2">
                    <img src="<?php echo IMAGE_PATH; ?>/footer-small.png" alt="HLstatsX Community Edition" class="img-fluid" style="max-height: 60px;" />
                </a>
                <p class="text-light mb-0">Real-time gaming statistics and rankings</p>
            </div>
            
            <div class="col-md-4 mb-3">
                <h6 class="text-light mb-2">Quick Links</h6>
                <ul class="list-unstyled">
                    <li><a href="<?php echo $g_options['scripturl']; ?>" class="text-light-emphasis text-decoration-none">Home</a></li>
                    <li><a href="<?php echo $g_options['scripturl']; ?>?mode=search" class="text-light-emphasis text-decoration-none">Search Players</a></li>
                    <li><a href="<?php echo $g_options['scripturl']; ?>?mode=help" class="text-light-emphasis text-decoration-none">Help & Support</a></li>
                    <li><a href="<?php echo $g_options['scripturl']; ?>?mode=admin" class="text-light-emphasis text-decoration-none">Admin Panel</a></li>
                </ul>
            </div>
            
            <div class="col-md-4 mb-3">
                <h6 class="text-light mb-2">Statistics</h6>
                <div class="small text-light-emphasis">
                    <?php if (isset($_SESSION['nojs']) && $_SESSION['nojs'] == 1): ?>
                    <div class="alert alert-warning alert-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        JavaScript is disabled. Enable it for full functionality.
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <span>Page generated in:</span>
                        <span class="fw-semibold"><?php echo $scripttime; ?>s</span>
                    </div>
                    
                    <?php if ($g_options['showqueries'] == 1): ?>
                    <div class="d-flex justify-content-between">
                        <span>Database queries:</span>
                        <span class="fw-semibold"><?php echo $db->querycount; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <hr class="border-light opacity-25">
        
        <div class="row align-items-center">
            <div class="col-md-8">
                <p class="mb-0 text-light-emphasis">
                    Generated in real-time by 
                    <a href="https://github.com/NiDE-gg/hlstatsx-community-edition" target="_blank" class="text-light text-decoration-none fw-semibold">
                        HLstatsX Community Edition <?php echo $g_options['version']; ?>
                    </a>
                </p>
                <p class="mb-0 small text-light-emphasis">
                    All images are copyrighted by their respective owners.
                </p>
            </div>
            
            <div class="col-md-4 text-md-end">
                <div class="btn-group btn-group-sm" role="group">
                    <?php if (isset($_SESSION['loggedin'])): ?>
                    <a href="hlstats.php?logout=1" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $g_options['scripturl']; ?>?mode=admin" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-gear me-1"></i>Admin
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($g_options['sitename']): ?>
        <div class="text-center mt-3 pt-3 border-top border-light border-opacity-25">
            <small class="text-light-emphasis">
                <i class="bi bi-globe me-1"></i>
                Powered by <strong><?php echo $g_options['sitename']; ?></strong>
            </small>
        </div>
        <?php endif; ?>
    </div>
</footer>

<!-- Bootstrap 5 JavaScript Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
        crossorigin="anonymous"></script>

<!-- Modern HLstatsX JavaScript -->
<script src="<?php echo INCLUDE_PATH; ?>/js/hlstats-modern.js"></script>

<!-- Chart.js for future statistics charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>

<?php else: ?>
<!-- Legacy Footer -->
<div style="clear:both;"></div>
<br />
<br />
<div id="footer">
    <a href="https://github.com/NiDE-gg/hlstatsx-community-edition" target="_blank">
        <img src="<?php echo IMAGE_PATH; ?>/footer-small.png" alt="HLstatsX Community Edition" border="0" />
    </a>
</div>
<br />
<div class="fSmall" style="text-align:center;">
<?php
    if (isset($_SESSION['nojs']) && $_SESSION['nojs'] == 1) {
        echo 'You are currently viewing the basic version of this page, please enable JavaScript and reload the page to access full functionality.<br />';
    }

    echo 'Generated in real-time by <a href="https://github.com/NiDE-gg/hlstatsx-community-edition" target="_blank">HLstatsX Community Edition '.$g_options['version'].'</a>';

    if ($g_options['showqueries'] == 1) {
        echo '
            <br />
            Executed '.$db->querycount." queries, generated this page in $scripttime Seconds\n";
    }
?>
<br />
All images are copyrighted by their respective owners.

<?php
    echo '<br /><br />[<a href="'.$g_options['scripturl']."?mode=admin\">Admin</a>]";

    if (isset($_SESSION['loggedin'])) {
        echo '&nbsp;[<a href="hlstats.php?logout=1">Logout</a>]';
    }
?>
</div>
</div>
<?php endif; ?>

<?php
global $mode, $redirect_to_game;
if (($g_options["show_google_map"] == 1) && ($mode == "contents") && ($redirect_to_game > 0)) {
    include(INCLUDE_PATH . '/google_maps.php');
    printMap();
}
?>

</body>
</html>