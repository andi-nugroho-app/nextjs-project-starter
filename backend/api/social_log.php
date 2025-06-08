<?php
require_once '../db.php';

class SocialLogController {
    private $db;
    private $allowedPlatforms = ['youtube', 'instagram', 'tiktok'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                return $this->logSocialLink();
            case 'GET':
                return $this->getSocialLinks();
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function logSocialLink() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (!isset($data['campaign_id']) || !isset($data['platform']) || !isset($data['video_url'])) {
                http_response_code(400);
                return ['error' => 'Missing required fields'];
            }

            // Validate platform
            if (!in_array(strtolower($data['platform']), $this->allowedPlatforms)) {
                http_response_code(400);
                return ['error' => 'Invalid platform'];
            }

            // Validate URL
            if (!filter_var($data['video_url'], FILTER_VALIDATE_URL)) {
                http_response_code(400);
                return ['error' => 'Invalid URL format'];
            }

            // Validate URL matches platform
            if (!$this->validatePlatformUrl($data['platform'], $data['video_url'])) {
                http_response_code(400);
                return ['error' => 'URL does not match platform'];
            }

            $sql = "INSERT INTO social_links (campaign_id, platform, video_url) 
                   VALUES (:campaign_id, :platform, :video_url)";
            
            $this->db->query($sql, [
                ':campaign_id' => $data['campaign_id'],
                ':platform' => strtolower($data['platform']),
                ':video_url' => $data['video_url']
            ]);

            return [
                'success' => true,
                'message' => 'Social link logged successfully',
                'data' => [
                    'platform' => $data['platform'],
                    'video_url' => $data['video_url'],
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (Exception $e) {
            error_log("Social Link Logging Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to log social link'];
        }
    }

    private function getSocialLinks() {
        try {
            $campaignId = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;
            $platform = isset($_GET['platform']) ? strtolower($_GET['platform']) : null;

            if (!$campaignId) {
                http_response_code(400);
                return ['error' => 'Campaign ID is required'];
            }

            $sql = "SELECT * FROM social_links WHERE campaign_id = :campaign_id";
            $params = [':campaign_id' => $campaignId];

            if ($platform && in_array($platform, $this->allowedPlatforms)) {
                $sql .= " AND platform = :platform";
                $params[':platform'] = $platform;
            }

            $sql .= " ORDER BY captured_at DESC";
            $result = $this->db->query($sql, $params);

            // Get platform distribution
            $distributionSql = "SELECT 
                                platform, 
                                COUNT(*) as count,
                                (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM social_links WHERE campaign_id = :campaign_id)) as percentage
                              FROM social_links 
                              WHERE campaign_id = :campaign_id 
                              GROUP BY platform";

            $distributionResult = $this->db->query($distributionSql, [':campaign_id' => $campaignId]);

            return [
                'success' => true,
                'data' => [
                    'links' => $result->fetchAll(),
                    'distribution' => $distributionResult->fetchAll()
                ]
            ];

        } catch (Exception $e) {
            error_log("Social Links Retrieval Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve social links'];
        }
    }

    private function validatePlatformUrl($platform, $url) {
        $patterns = [
            'youtube' => '/^https?:\/\/((?:www\.)?youtube\.com\/shorts\/|(?:www\.)?youtu\.be\/).+/',
            'instagram' => '/^https?:\/\/(?:www\.)?instagram\.com\/reel\/.+/',
            'tiktok' => '/^https?:\/\/(?:www\.)?tiktok\.com\/@[^\/]+\/video\/.+/'
        ];

        return preg_match($patterns[strtolower($platform)], $url) === 1;
    }
}

// Handle the request
$controller = new SocialLogController();
$response = $controller->handleRequest();
echo json_encode($response);
?>
