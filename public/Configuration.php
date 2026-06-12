<?php

class Configuration {
    private const CONFIG_FILE = 'config.json';
    private array $fileConfig;

    const IMMICH_URL = 'IMMICH_URL';
    const IMMICH_API_KEY = 'IMMICH_API_KEY';
    const ALBUM_ID = 'ALBUM_ID';
    const CAROUSEL_DURATION = 'CAROUSEL_DURATION';
    const RANDOM_ORDER = 'RANDOM_ORDER';
    const CROP = 'CROP_TO_SCREEN';
    const ORIENTATION = 'IMAGES_ORIENTATION';
    const BACKGROUND_COLOR = 'BACKGROUND_COLOR';
    const STATUS_BAR_STYLE = 'STATUS_BAR_STYLE';
    const KEN_BURNS        = 'KEN_BURNS';
    const LOCALE           = 'LOCALE';

    public function __construct() {
        if (file_exists(self::CONFIG_FILE)) {
            $this->fileConfig = json_decode(file_get_contents(self::CONFIG_FILE), true);
        }
    }

    public function get(string $key) {
        if (isset($this->fileConfig[$key])) {
            return $this->fileConfig[$key];
        }
        $value = getenv($key);
        if ($value === false) {
            return false;
        }
        // Strip inline comments (e.g. "all # landscape, portrait, all" → "all")
        if (($pos = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $pos));
        }
        return $value;
    }

    public static function save(array $config) {
        file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    }
}