<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gps_tracking');

// File Upload Paths
define('UPLOAD_PHOTOS_DIR', '../uploads/photos/');
define('UPLOAD_VIDEOS_DIR', '../uploads/videos/');

// Allowed File Types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4']);

// Max File Sizes (in bytes)
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);  // 5MB
define('MAX_VIDEO_SIZE', 50 * 1024 * 1024); // 50MB

// API Response Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
?>
