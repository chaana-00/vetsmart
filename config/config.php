<?php
/* ---------- VetSmart configuration ---------- */
define('APP_NAME', 'VetSmart');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'vetsmart');
define('DB_USER', 'root');
define('DB_PASS', '1234');

// Google Maps JavaScript API key (enable "Maps JavaScript API", "Places API", "Geocoding API").
// Leave empty to use the free OpenStreetMap picker instead (no key needed).
define('GOOGLE_MAPS_API_KEY', '');

// Map starting point (Negombo, Sri Lanka)
define('MAP_DEFAULT_LAT', 7.2083);
define('MAP_DEFAULT_LNG', 79.8358);

// Post-mortem image uploads
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/postmortem/');
define('MAX_UPLOAD_MB', 5);

// How often browsers check for new notifications (ms)
define('NOTIF_POLL_MS', 5000);

date_default_timezone_set('Asia/Colombo');
