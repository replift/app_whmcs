<?php

// Attempt to retrieve the environment variable using getenv()
$replift_app_env = getenv('REPLIFT_APP_ENV');

// Check if the environment variable is available in the $_SERVER or $_ENV superglobals if getenv() is not enabled
if (!$replift_app_env) {
    $replift_app_env = $_SERVER['REPLIFT_APP_ENV'] ?? $_ENV['REPLIFT_APP_ENV'] ?? 'prod';
}

// Initialize variables to default production values
$replift_dashboard_link = "https://dashboard.replift.com";
$replift_api_marketplace = "https://dashboard.replift.com/api/marketplace/";
$replift_api_whitelist = "https://api.replift.com/api/v2/ip_whitelist/";
$replift_marketplace_token = "2MulZ2cPhmYKHYXnkrbfemX9mbDaHtKruHE4ptrd";

// Set values based on the environment

switch ($replift_app_env) {
    case "dev":
        $replift_dashboard_link = "https://development-dashboard.replift.com";
        $replift_api_marketplace = "https://development-dashboard.replift.com/api/marketplace";
        $replift_api_whitelist = "https://api-dev.replift.com/api/v2/ip_whitelist/";
        $replift_marketplace_token = "IAZt7TSjgIlpBPLEeEVufl2ulidUKeUscAPN7PGf";
        break;
    case "stage":
        //$replift_dashboard_link = "https://staging-dashboard.replift.com";
        //$replift_api_marketplace = "https://staging-dashboard.replift.com/api/marketplace";
        //$replift_api_whitelist = "https://api-stage.replift.com/api/v2/ip_whitelist/";
        //$replift_marketplace_token = "KxP8wCuVFF7WmZVt0jlD8ZUHClqO0ciqmwPLJuBA";
        break;
}
