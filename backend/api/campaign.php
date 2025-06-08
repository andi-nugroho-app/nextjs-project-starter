<?php
require_once '../db.php';

class CampaignController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'POST':
                return $this->createCampaign();
            case 'GET':
                return isset($_GET['id']) ? $this->getCampaign() : $this->listCampaigns();
            case 'PUT':
                return $this->incrementViews();
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function createCampaign() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['name'])) {
                http_response_code(400);
                return ['error' => 'Campaign name is required'];
            }

            $sql = "INSERT INTO campaigns (name) VALUES (:name)";
            $this->db->query($sql, [':name' => $data['name']]);
            
            $campaignId = $this->db->getConnection()->lastInsertId();

            // Get the created campaign
            $sql = "SELECT * FROM campaigns WHERE id = :id";
            $result = $this->db->query($sql, [':id' => $campaignId]);
            $campaign = $result->fetch();

            return [
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => $campaign
            ];

        } catch (Exception $e) {
            error_log("Campaign Creation Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to create campaign'];
        }
    }

    private function getCampaign() {
        try {
            $campaignId = $_GET['id'];

            $sql = "SELECT 
                        c.*,
                        COUNT(DISTINCT m.id) as media_count,
                        COUNT(DISTINCT g.id) as gps_count,
                        COUNT(DISTINCT s.id) as social_links_count
                    FROM campaigns c
                    LEFT JOIN media_logs m ON c.id = m.campaign_id
                    LEFT JOIN gps_logs g ON c.id = g.campaign_id
                    LEFT JOIN social_links s ON c.id = s.campaign_id
                    WHERE c.id = :id
                    GROUP BY c.id";

            $result = $this->db->query($sql, [':id' => $campaignId]);
            $campaign = $result->fetch();

            if (!$campaign) {
                http_response_code(404);
                return ['error' => 'Campaign not found'];
            }

            return [
                'success' => true,
                'data' => $campaign
            ];

        } catch (Exception $e) {
            error_log("Campaign Retrieval Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve campaign'];
        }
    }

    private function listCampaigns() {
        try {
            $sql = "SELECT 
                        c.*,
                        COUNT(DISTINCT m.id) as media_count,
                        COUNT(DISTINCT g.id) as gps_count,
                        COUNT(DISTINCT s.id) as social_links_count
                    FROM campaigns c
                    LEFT JOIN media_logs m ON c.id = m.campaign_id
                    LEFT JOIN gps_logs g ON c.id = g.campaign_id
                    LEFT JOIN social_links s ON c.id = s.campaign_id
                    GROUP BY c.id
                    ORDER BY c.created_at DESC";

            $result = $this->db->query($sql);

            return [
                'success' => true,
                'data' => $result->fetchAll()
            ];

        } catch (Exception $e) {
            error_log("Campaigns List Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve campaigns'];
        }
    }

    private function incrementViews() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['id'])) {
                http_response_code(400);
                return ['error' => 'Campaign ID is required'];
            }

            $sql = "UPDATE campaigns 
                   SET current_views = current_views + 1 
                   WHERE id = :id";

            $this->db->query($sql, [':id' => $data['id']]);

            // Get updated view count
            $sql = "SELECT current_views FROM campaigns WHERE id = :id";
            $result = $this->db->query($sql, [':id' => $data['id']]);
            $campaign = $result->fetch();

            return [
                'success' => true,
                'message' => 'View count incremented',
                'data' => [
                    'current_views' => $campaign['current_views']
                ]
            ];

        } catch (Exception $e) {
            error_log("View Increment Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to increment view count'];
        }
    }
}

// Handle the request
$controller = new CampaignController();
$response = $controller->handleRequest();
echo json_encode($response);
?>
