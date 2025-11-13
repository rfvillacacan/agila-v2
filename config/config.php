<?php
/**
 * Application Configuration
 */

// Base paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/pcap');
define('PROCESSED_PATH', BASE_PATH . '/uploads/processed');
define('PROGRESS_PATH', BASE_PATH . '/uploads/progress');
define('HQ_LOCATION_FILE', BASE_PATH . '/uploads/hq_location.json');
define('PLAYBACK_STATE_FILE', BASE_PATH . '/uploads/playback_state.json');

// Ensure directories exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(PROCESSED_PATH)) {
    mkdir(PROCESSED_PATH, 0755, true);
}
if (!is_dir(PROGRESS_PATH)) {
    mkdir(PROGRESS_PATH, 0755, true);
}
if (!is_dir(dirname(HQ_LOCATION_FILE))) {
    mkdir(dirname(HQ_LOCATION_FILE), 0755, true);
}

// Default HQ location (Riyadh, Saudi Arabia)
define('DEFAULT_HQ_LAT', 24.7136);
define('DEFAULT_HQ_LNG', 46.6753);
define('DEFAULT_HQ_NAME', 'Riyadh, KSA');

// Geolocation API
define('GEO_API_URL', 'http://ip-api.com/json/');
define('GEO_API_TIMEOUT', 5);

// Processing settings
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB
define('PROCESSING_TIMEOUT', 600); // 10 minutes

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('UTC');

