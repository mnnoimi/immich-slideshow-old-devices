<?php
/**
 * Immich Slideshow - Main entry point
 * 
 * This script creates a slideshow interface for Immich photo albums.
 * It supports various customization options through GET parameters or environment variables.
 */

require_once './ImmichApi.php';
require_once './Configuration.php';

$configuration = new Configuration();

// Configuration parameters with validation
$immich_url = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

// Get and validate input parameters with defaults
$album_id = $_GET['album_id'] ?? $configuration->get(Configuration::ALBUM_ID);
$carousel_duration = (int)($_GET['duration'] ?? $configuration->get(Configuration::CAROUSEL_DURATION) ?? 5);
$background = preg_match('/^[a-zA-Z0-9#]+$/', $_GET['background'] ?? '') 
    ? $_GET['background'] 
    : ($configuration->get(Configuration::BACKGROUND_COLOR) ?? '#000000');
$random_order = filter_var($_GET['random'] ?? $configuration->get(Configuration::RANDOM_ORDER) ?? 'false', FILTER_VALIDATE_BOOLEAN);
$status_bar_style = in_array($_GET['status_bar'] ?? '', ['default', 'black-translucent', 'black']) 
    ? $_GET['status_bar'] 
    : ($configuration->get(Configuration::STATUS_BAR_STYLE) ?? 'black-translucent');
$orientation = in_array($_GET['orientation'] ?? '', ['landscape', 'portrait', 'all'])
    ? $_GET['orientation']
    : ($configuration->get(Configuration::ORIENTATION) ?? 'all');
$ken_burns      = filter_var($_GET['ken_burns'] ?? $configuration->get(Configuration::KEN_BURNS) ?? 'true', FILTER_VALIDATE_BOOLEAN);
$crop_to_screen = filter_var($_GET['crop']     ?? $configuration->get(Configuration::CROP)       ?? 'true', FILTER_VALIDATE_BOOLEAN);
$raw_locale     = $_GET['locale'] ?? $configuration->get(Configuration::LOCALE) ?? 'ar';
$locale       = in_array($raw_locale, ['en', 'ar']) ? $raw_locale : 'ar';

// Validate required parameters
if (!$album_id) {
    http_response_code(400);
    echo "Error: Missing required parameter 'album_id'";
    exit;
}

// Validate carousel duration
if ($carousel_duration < 1) {
    $carousel_duration = 5;
}

try {
    // Initialize API and fetch photos
    $api = new ImmichApi($immich_url, $immich_api_key);
    
    // Support multiple album IDs separated by comma
    $album_ids = explode(',', $album_id);
    $photos = [];

    foreach ($album_ids as $id) {
        $id = trim($id);
        if (empty($id)) continue;
        
        try {
            $album_photos = $api->getAlbumAssets($id);
            $photos = array_merge($photos, $album_photos);
        } catch (Exception $e) {
            // Log error but continue with other albums
            error_log("Warning: Failed to fetch photos from album $id: " . $e->getMessage());
        }
    }
    
    if (empty($photos)) {
        throw new Exception("No photos found in the specified album(s)");
    }

    // Filter photos by orientation if needed
    if ($orientation !== 'all') {
        $photos = array_values(array_filter($photos, function($photo) use ($orientation) {
            return $photo['orientation'] === $orientation;
        }));

        if (empty($photos)) {
            throw new Exception("No photos found with the specified orientation");
        }
    }
    
    if ($random_order) {
        shuffle($photos);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo "Error: Unable to fetch photos - " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $locale; ?>" <?php if ($locale === 'ar') { echo 'dir="rtl"'; } ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, minimal-ui"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo htmlspecialchars($status_bar_style); ?>"/>
    <meta name="apple-mobile-web-app-status-bar" content="<?php echo htmlspecialchars($status_bar_style); ?>"/>
    <meta name="theme-color" content="<?php echo htmlspecialchars($background); ?>"/>
    <title>Immich Slideshow</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico?v=<?php echo filemtime('assets/favicon.ico'); ?>"/>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-icon-180.png?v=<?php echo filemtime('assets/apple-icon-180.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=<?php echo filemtime('assets/favicon-32.png'); ?>"/>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=<?php echo filemtime('assets/favicon-16.png'); ?>"/>
    <link rel="stylesheet" href="assets/main.css?v=<?php echo filemtime('assets/main.css'); ?>"/>
    <script src="assets/main.js?v=<?php echo filemtime('assets/main.js'); ?>"></script>
    <style>
        #overlay-clock {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.45);
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            z-index: 500;
            text-align: right;
            pointer-events: none;
            text-shadow: 0 1px 3px rgba(0,0,0,0.9);
            font-family: sans-serif;
        }
        #clock-time {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 2px;
            font-family: monospace;
            line-height: 1;
        }
        #clock-date {
            font-size: 20px;
            margin-top: 5px;
            opacity: 0.9;
        }
        #overlay-info {
            position: fixed;
            bottom: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.45);
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            z-index: 500;
            text-align: right;
            pointer-events: none;
            text-shadow: 0 1px 3px rgba(0,0,0,0.9);
            font-family: sans-serif;
            max-width: 300px;
        }
        #info-datetime { font-size: 20px; opacity: 0.85; }
        #info-people   { font-size: 14px; font-weight: bold; margin-top: 3px; }
        #info-location { font-size: 13px; opacity: 0.85; margin-top: 2px; }
        #overlay-counter {
            position: fixed;
            bottom: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.45);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            z-index: 500;
            pointer-events: none;
            text-shadow: 0 1px 3px rgba(0,0,0,0.9);
            font-family: monospace;
            font-size: 13px;
            letter-spacing: 1px;
        }
    </style>
    <style>
        html, body {
            background-color: <?php echo htmlspecialchars($background); ?>;
        }
    </style>
</head>
<body>
    <div class="carousel">
        <a href="#" id="current-link">
            <img src="assets/apple-icon-180.png" id="current-img" alt="Current slideshow image"/>
            <img src="assets/apple-icon-180.png" id="next-img" alt="Next slideshow image"/>
        </a>
    </div>
    <div class="pause-icon" id="pause-icon">
        <img src="assets/pause.png" alt="Pause"/>
    </div>

    <div id="overlay-clock">
        <div id="clock-time">00:00:00</div>
        <div id="clock-date"></div>
    </div>

    <div id="overlay-counter">
        <div id="photo-counter"></div>
    </div>

    <div id="overlay-info">
        <div id="info-datetime"></div>
        <div id="info-people"></div>
        <div id="info-location"></div>
    </div>

    <div id="progress-bar"><div id="progress-fill"></div></div>

    <div id="nav-prev-zone" class="nav-zone nav-zone-left">
        <div id="nav-prev-arrow" class="nav-arrow">&#10094;</div>
    </div>
    <div id="nav-next-zone" class="nav-zone nav-zone-right">
        <div id="nav-next-arrow" class="nav-arrow">&#10095;</div>
    </div>



    <script>
        initSlideshow({
            photos:       <?php echo json_encode($photos); ?>,
            duration:     <?php echo $carousel_duration; ?>,
            albumId:      "<?php echo htmlspecialchars($album_id, ENT_QUOTES); ?>",
            orientation:  "<?php echo htmlspecialchars($orientation, ENT_QUOTES); ?>",
            random:       <?php echo $random_order    ? 'true' : 'false'; ?>,
            kenBurns:     <?php echo $ken_burns       ? 'true' : 'false'; ?>,
            cropToScreen: <?php echo $crop_to_screen  ? 'true' : 'false'; ?>
        });

        document.onkeydown = function(e) {
        e = e || window.event;
        var keyCode = e.keyCode || e.which;

        switch(keyCode) {
            // --- REFRESH (Up Arrow) ---
            case 38: 
                // Remote: Manual Refresh
                window.location.reload();
                break;

            // --- FORWARD (Right Arrow & Down Arrow) ---
            case 39: // Right Arrow
            case 40: // Down Arrow
                if (typeof nextImage === 'function') {
                    nextImage();
                }
                break;

            // --- BACKWARD (Left Arrow) ---
            case 37: // Left Arrow
                if (typeof previousImage === 'function') {
                    previousImage();
                } else {
                    window.location.reload();
                }
                break;

            // --- CENTER (Enter / OK) ---
            case 13: // Enter
                if (typeof togglePause === 'function') {
                    togglePause();
                }
                break;
        }
    };
    </script>
    <script>
        // Clock
        var LOCALE       = <?php echo json_encode($locale); ?>;
        var MONTHS_SHORT = <?php echo json_encode($locale === 'ar'
            ? ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']
            : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
        ); ?>;
        var DAYS_LONG    = <?php echo json_encode($locale === 'ar'
            ? ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت']
            : ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']
        ); ?>;

        function updateClock() {
            var now = new Date();
            var h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            var timeEl = document.getElementById('clock-time');
            var dateEl = document.getElementById('clock-date');
            if (timeEl) {
                timeEl.innerHTML = (h < 10 ? '0' : '') + h + ':' +
                                   (m < 10 ? '0' : '') + m + ':' +
                                   (s < 10 ? '0' : '') + s;
            }
            if (dateEl) {
                if (LOCALE === 'ar') {
                    dateEl.innerHTML = DAYS_LONG[now.getDay()] + '، ' +
                                       now.getDate() + ' ' +
                                       MONTHS_SHORT[now.getMonth()] + ' ' +
                                       now.getFullYear();
                } else {
                    dateEl.innerHTML = DAYS_LONG[now.getDay()] + ', ' +
                                       MONTHS_SHORT[now.getMonth()] + ' ' +
                                       now.getDate() + ', ' + now.getFullYear();
                }
            }
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Image info overlay
        function fetchAssetInfo(assetId) {
            if (!assetId) return;
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '/asset-info.php?asset=' + encodeURIComponent(assetId), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        updateInfoOverlay(JSON.parse(xhr.responseText));
                    } catch (e) {
                        updateInfoOverlay({});
                    }
                }
            };
            xhr.send();
        }

        function updateInfoOverlay(info) {
            var dtEl  = document.getElementById('info-datetime');
            var pplEl = document.getElementById('info-people');
            var locEl = document.getElementById('info-location');
            if (dtEl)  dtEl.innerHTML  = info.datetime || '';
            if (pplEl) pplEl.innerHTML = (info.people && info.people.length) ? info.people.join(', ') : '';
            if (locEl) locEl.innerHTML = info.location || '';
            var infoEl = document.getElementById('overlay-info');
            if (infoEl) {
                infoEl.style.display = (info.datetime || (info.people && info.people.length) || info.location) ? '' : 'none';
            }
        }

        // Called from main.js on every image change
        window.onImageChange = function(assetId) {
            fetchAssetInfo(assetId);
        };
    </script>
</body>
</html>