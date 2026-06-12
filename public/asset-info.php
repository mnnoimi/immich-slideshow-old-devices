<?php
// Suppress deprecation warnings so they don't corrupt the JSON response
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once './ImmichApi.php';
require_once './Configuration.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

$configuration  = new Configuration();
$immich_url     = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);
$locale         = $configuration->get(Configuration::LOCALE) ?? 'en';

$AR_DAYS   = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
$AR_MONTHS = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

$asset_id = isset($_GET['asset']) ? trim($_GET['asset']) : null;

if (!$asset_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing asset parameter']);
    exit;
}

try {
    $api = new ImmichApi($immich_url, $immich_api_key);
    $asset = $api->getAssetInfo($asset_id);

    $result = [];

    // Date/time from EXIF or file metadata
    $datetime = $asset['exifInfo']['dateTimeOriginal']
        ?? $asset['localDateTime']
        ?? null;

    if ($datetime) {
        $dt = new DateTime($datetime);
        if ($locale === 'ar') {
            $result['datetime'] = $AR_DAYS[(int)$dt->format('w')] . '، ' .
                                  $dt->format('j') . ' ' .
                                  $AR_MONTHS[(int)$dt->format('n') - 1] . ' ' .
                                  $dt->format('Y') . ' · ' .
                                  $dt->format('H:i');
        } else {
            $result['datetime'] = $dt->format('D, M j, Y · g:i A');
        }
    }

    // Recognized people
    $people = [];
    if (isset($asset['people']) && is_array($asset['people'])) {
        foreach ($asset['people'] as $person) {
            if (!empty($person['name'])) {
                $people[] = htmlspecialchars($person['name'], ENT_QUOTES, 'UTF-8');
            }
        }
    }
    if (!empty($people)) {
        $result['people'] = $people;
    }

    // Location from EXIF
    $locationParts = array_filter([
        $asset['exifInfo']['city'] ?? null,
        $asset['exifInfo']['state'] ?? null,
        $asset['exifInfo']['country'] ?? null,
    ]);
    if (!empty($locationParts)) {
        $result['location'] = implode(', ', $locationParts);
    }

    echo json_encode($result);
} catch (Exception $e) {
    error_log("asset-info.php error: " . $e->getMessage());
    echo json_encode([]);
}
