<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once './ImmichApi.php';
require_once './Configuration.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$configuration  = new Configuration();
$immich_url     = $configuration->get(Configuration::IMMICH_URL);
$immich_api_key = $configuration->get(Configuration::IMMICH_API_KEY);

$album_id   = isset($_GET['album_id'])   ? trim($_GET['album_id'])   : $configuration->get(Configuration::ALBUM_ID);
$orientation = isset($_GET['orientation']) ? trim($_GET['orientation']) : ($configuration->get(Configuration::ORIENTATION) ?? 'all');
$random      = filter_var($_GET['random'] ?? $configuration->get(Configuration::RANDOM_ORDER) ?? 'false', FILTER_VALIDATE_BOOLEAN);

try {
    $api      = new ImmichApi($immich_url, $immich_api_key);
    $photos   = [];

    foreach (explode(',', $album_id) as $id) {
        $id = trim($id);
        if (empty($id)) continue;
        try {
            $photos = array_merge($photos, $api->getAlbumAssets($id));
        } catch (Exception $e) {
            error_log("album-refresh.php: failed to fetch album $id: " . $e->getMessage());
        }
    }

    if ($orientation !== 'all') {
        $photos = array_values(array_filter($photos, function ($p) use ($orientation) {
            return $p['orientation'] === $orientation;
        }));
    }

    if ($random) {
        shuffle($photos);
    }

    echo json_encode($photos);
} catch (Exception $e) {
    error_log("album-refresh.php error: " . $e->getMessage());
    echo json_encode([]);
}
