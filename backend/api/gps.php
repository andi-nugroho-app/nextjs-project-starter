<?php
require_once '../db.php';

class GPSController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                return $this->handlePost();
            case 'GET':
                return $this->handleGet();
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function handlePost() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($data['latitude']) || !isset($data['longitude']) || !isset($data['campaign_id'])) {
                http_response_code(400);
                return ['error' => 'Missing required fields'];
            }

            // Validate coordinates
            if (!$this->isValidCoordinate($data['latitude'], $data['longitude'])) {
                http_response_code(400);
                return ['error' => 'Invalid coordinates'];
            }

            $sql = "INSERT INTO gps_logs (campaign_id, latitude, longitude) VALUES (:campaign_id, :latitude, :longitude)";
            
            $params = [
                ':campaign_id' => $data['campaign_id'],
                ':latitude' => $data['latitude'],
                ':longitude' => $data['longitude']
            ];

            $this->db->query($sql, $params);

            return [
                'success' => true,
                'message' => 'GPS coordinates logged successfully',
                'data' => [
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (Exception $e) {
            error_log("GPS Logging Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to log GPS coordinates'];
        }
    }

    private function handleGet() {
        try {
            $campaign_id = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;

            if (!$campaign_id) {
                http_response_code(400);
                return ['error' => 'Campaign ID is required'];
            }

            $sql = "SELECT * FROM gps_logs WHERE campaign_id = :campaign_id ORDER BY captured_at DESC";
            $result = $this->db->query($sql, [':campaign_id' => $campaign_id]);

            return [
                'success' => true,
                'data' => $result->fetchAll()
            ];

        } catch (Exception $e) {
            error_log("GPS Retrieval Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve GPS logs'];
        }
    }

    private function isValidCoordinate($lat, $lng) {
        return is_numeric($lat) && 
               is_numeric($lng) && 
               $lat >= -90 && 
               $lat <= 90 && 
               $lng >= -180 && 
               $lng <= 180;
    }
}

// Handle the request
$controller = new GPSController();
$response = $controller->handleRequest();
echo json_encode($response);
?>
