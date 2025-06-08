<?php
require_once '../db.php';

class MediaController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                return $this->handleUpload();
            case 'GET':
                return $this->handleGet();
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function handleUpload() {
        try {
            if (!isset($_FILES['file']) || !isset($_POST['media_type']) || !isset($_POST['campaign_id'])) {
                http_response_code(400);
                return ['error' => 'Missing required fields'];
            }

            $file = $_FILES['file'];
            $mediaType = $_POST['media_type'];
            $campaignId = $_POST['campaign_id'];

            // Validate media type
            if (!in_array($mediaType, ['photo', 'video_front', 'video_rear'])) {
                http_response_code(400);
                return ['error' => 'Invalid media type'];
            }

            // Validate file
            $validationResult = $this->validateFile($file, $mediaType);
            if ($validationResult !== true) {
                http_response_code(400);
                return ['error' => $validationResult];
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Determine upload directory
            $uploadDir = ($mediaType === 'photo') ? UPLOAD_PHOTOS_DIR : UPLOAD_VIDEOS_DIR;
            $filepath = $uploadDir . $filename;

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Log to database
            $sql = "INSERT INTO media_logs (campaign_id, media_type, file_path) 
                   VALUES (:campaign_id, :media_type, :file_path)";
            
            $this->db->query($sql, [
                ':campaign_id' => $campaignId,
                ':media_type' => $mediaType,
                ':file_path' => $filepath
            ]);

            return [
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => [
                    'file_path' => $filepath,
                    'media_type' => $mediaType,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (Exception $e) {
            error_log("Media Upload Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to upload media'];
        }
    }

    private function handleGet() {
        try {
            $campaignId = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;
            $mediaType = isset($_GET['media_type']) ? $_GET['media_type'] : null;

            if (!$campaignId) {
                http_response_code(400);
                return ['error' => 'Campaign ID is required'];
            }

            $sql = "SELECT * FROM media_logs WHERE campaign_id = :campaign_id";
            $params = [':campaign_id' => $campaignId];

            if ($mediaType) {
                $sql .= " AND media_type = :media_type";
                $params[':media_type'] = $mediaType;
            }

            $sql .= " ORDER BY captured_at DESC";
            $result = $this->db->query($sql, $params);

            return [
                'success' => true,
                'data' => $result->fetchAll()
            ];

        } catch (Exception $e) {
            error_log("Media Retrieval Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve media'];
        }
    }

    private function validateFile($file, $mediaType) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'File upload failed';
        }

        // Validate file size
        $maxSize = ($mediaType === 'photo') ? MAX_IMAGE_SIZE : MAX_VIDEO_SIZE;
        if ($file['size'] > $maxSize) {
            return 'File size exceeds limit';
        }

        // Validate file type
        $mimeType = mime_content_type($file['tmp_name']);
        $allowedTypes = ($mediaType === 'photo') ? ALLOWED_IMAGE_TYPES : ALLOWED_VIDEO_TYPES;
        
        if (!in_array($mimeType, $allowedTypes)) {
            return 'Invalid file type';
        }

        return true;
    }
}

// Handle the request
$controller = new MediaController();
$response = $controller->handleRequest();
echo json_encode($response);
?>
