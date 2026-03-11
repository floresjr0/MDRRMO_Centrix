<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user = current_user();
$pdo  = db();

// Summary metrics
$summary = [
    'total_centers'     => 0,
    'total_evacuees'    => 0,
    'status_available'  => 0,
    'status_near'       => 0,
    'status_full'       => 0,
    'status_temp'       => 0,
    'status_closed'     => 0,
];

$row = $pdo->query("SELECT COUNT(*) AS c FROM evacuation_centers")->fetch();
if ($row) {
    $summary['total_centers'] = (int)$row['c'];
}

$row = $pdo->query("SELECT COALESCE(SUM(total_members),0) AS total FROM evac_registrations")->fetch();
if ($row) {
    $summary['total_evacuees'] = (int)$row['total'];
}

$st = $pdo->query("SELECT status, COUNT(*) AS c FROM evacuation_centers GROUP BY status");
foreach ($st as $s) {
    switch ($s['status']) {
        case 'available':
            $summary['status_available'] = (int)$s['c'];
            break;
        case 'near_capacity':
            $summary['status_near'] = (int)$s['c'];
            break;
        case 'full':
            $summary['status_full'] = (int)$s['c'];
            break;
        case 'temp_shelter':
            $summary['status_temp'] = (int)$s['c'];
            break;
        case 'closed':
            $summary['status_closed'] = (int)$s['c'];
            break;
    }
}

$centers = get_centers_with_occupancy();

// Latest weather + active disaster for quick admin view
// Live weather for San Ildefonso (no cron)
$lat = 15.0828;
$lon = 120.9417;

$weather = null;

if (defined('WEATHER_API_KEY') && WEATHER_API_KEY !== '') {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=" . WEATHER_API_KEY . "&units=metric";
    $json = @file_get_contents($url);

    if ($json !== false) {
        $data = json_decode($json, true);
        if (isset($data['main'])) {
            $tempC = (float)$data['main']['temp'];
            $humidity = (float)$data['main']['humidity'];

            // Heat index calculation
            $t = $tempC;
            $rh = $humidity;
            $heatIndex = $t;
            if ($t >= 27 && $rh >= 40) {
                $heatIndex = -8.784695 + 1.61139411*$t + 2.338549*$rh
                    - 0.14611605*$t*$rh - 0.012308094*($t*$t)
                    - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
                    + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
            }

            // Comfort level
            $level = 'low';
            if ($heatIndex >= 41) {
                $level = 'extreme';
            } elseif ($heatIndex >= 38) {
                $level = 'high';
            } elseif ($heatIndex >= 32) {
                $level = 'medium';
            }

            $condition = $data['weather'][0]['description'] ?? 'N/A';

            $weather = [
                'temp_c' => $tempC,
                'humidity' => $humidity,
                'heat_index' => round($heatIndex),
                'level' => $level,
                'condition_text' => $condition
            ];
        }
    }
}
$disasterStmt = $pdo->query("SELECT * FROM disasters WHERE status = 'ongoing' ORDER BY level DESC, started_at DESC LIMIT 1");
$activeDisaster = $disasterStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MDRRMO Dashboard | San Ildefonso, Bulacan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #F5F5F5;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Color Variables from Logo */
        :root {
            --primary-red: #D32F2F;
            --accent-yellow: #FFC107;
            --dark-red: #B71C1C;
            --light-red: #FFEBEE;
            --light-yellow: #FFF8E1;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --map-green: #2E7D32;
            --map-yellow: #FFC107;
            --map-red: #D32F2F;
            --map-blue: #3498DB;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar Toggle Button - Outside Sidebar with Smooth Animation */
        .sidebar-toggle-btn {
            position: fixed;
            left: var(--sidebar-width);
            top: 20px;
            z-index: 1001;
            background: white;
            width: 28px;
            height: 28px;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
            border: 1px solid #EDE7E7;
            border-left: none;
            color: #95A5A6;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s, background 0.2s;
        }

        .sidebar-toggle-btn:hover {
            color: var(--primary-red);
            background: var(--light-red);
        }

        .sidebar-toggle-btn.collapsed {
            left: var(--sidebar-collapsed-width);
        }

        /* Sidebar - No Scrollbar with Smooth Animation */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            border-right: 1px solid #EDE7E7;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .logo-text {
            display: none;
        }

        .sidebar.collapsed .sidebar-link {
            justify-content: center;
            padding: 15px 0;
        }

        .sidebar.collapsed .sidebar-link i {
            margin: 0;
            font-size: 20px;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 20px 0;
            justify-content: center;
        }

        .sidebar-header {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #EDE7E7;
            flex-shrink: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Logo ready for image */
        .logo-image {
            width: 40px;
            height: 40px;
            background: var(--primary-red);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Fallback if no image */
        .logo-icon-fallback {
            color: white;
            font-weight: bold;
            font-size: 20px;
        }

        .logo-text h3 {
            font-size: 16px;
            font-weight: 700;
            color: #2C3E50;
            line-height: 1.3;
        }

        .logo-text p {
            font-size: 11px;
            color: #95A5A6;
        }

        .sidebar-content {
            padding: 20px 0;
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar-content::-webkit-scrollbar {
            display: none;
        }

        .sidebar-section {
            margin-bottom: 20px;
        }

        .sidebar-section-title {
            padding: 10px 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #95A5A6;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #5D6D7E;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-link:hover {
            background: var(--light-red);
            color: var(--primary-red);
            border-left-color: var(--primary-red);
        }

        .sidebar-link.active {
            background: var(--light-red);
            color: var(--primary-red);
            border-left-color: var(--primary-red);
        }

        .sidebar-link i {
            width: 20px;
            font-size: 16px;
            color: inherit;
        }

        .sidebar-badge {
            background: var(--primary-red);
            color: white;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 30px;
            margin-left: auto;
        }

        /* Main Content with Smooth Animation */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 0 32px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #EDE7E7;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2C3E50;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: #5D6D7E;
            cursor: pointer;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #F8F9FA;
            padding: 8px 16px 8px 12px;
            border-radius: 40px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
            font-weight: 700;
            font-size: 18px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: #2C3E50;
        }

        .user-role {
            font-size: 12px;
            color: #95A5A6;
        }

        /* Dashboard Content */
        .dashboard {
            padding: 24px 32px;
        }

        /* Welcome Bar */
        .welcome-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .welcome-text h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 4px;
        }

        .welcome-text p {
            color: #95A5A6;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-badge {
            background: var(--light-yellow);
            color: #B26A00;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Minimized Stat Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px rgba(211, 47, 47, 0.2);
            border-color: var(--primary-red);
        }

        .stat-icon-small {
            width: 36px;
            height: 36px;
            background: var(--light-red);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
            font-size: 16px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value-small {
            font-size: 18px;
            font-weight: 700;
            color: #2C3E50;
            line-height: 1.2;
        }

        .stat-label-small {
            font-size: 11px;
            color: #95A5A6;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Two Column Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #2C3E50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: var(--primary-red);
        }

        .badge {
            background: var(--light-red);
            color: var(--primary-red);
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.yellow {
            background: var(--light-yellow);
            color: #B26A00;
        }

        /* Current Situation */
        .situation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #FAFAFA;
            border-radius: 16px;
            margin-bottom: 15px;
            border-left: 3px solid var(--primary-red);
        }

        .situation-icon {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary-red);
        }

        .situation-content {
            flex: 1;
        }

        .situation-title {
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .situation-desc {
            font-size: 13px;
            color: #95A5A6;
        }

        .level-indicator {
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 600;
        }

        .level-high { background: var(--light-red); color: var(--primary-red); }
        .level-medium { background: var(--light-yellow); color: #B26A00; }
        .level-low { background: #E8F5E9; color: #2E7D32; }

        /* Weather Widget */
        .weather-widget {
            background: linear-gradient(135deg, var(--primary-red) 0%, #B71C1C 100%);
            border-radius: 16px;
            padding: 15px;
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .weather-temp {
            font-size: 32px;
            font-weight: 700;
        }

        .weather-details {
            flex: 1;
        }

        .weather-condition {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .weather-stats {
            display: flex;
            gap: 12px;
            font-size: 11px;
            opacity: 0.9;
        }

        /* Centers List */
        .centers-list {
            margin-top: 10px;
        }

        .center-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #F0F0F0;
        }

        .center-item:last-child {
            border-bottom: none;
        }

        .center-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 2px;
        }

        .center-info p {
            font-size: 12px;
            color: #95A5A6;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .capacity-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .capacity-bar {
            width: 60px;
            height: 4px;
            background: #F0F0F0;
            border-radius: 10px;
            overflow: hidden;
        }

        .capacity-fill {
            height: 100%;
            background: var(--primary-red);
            border-radius: 10px;
        }

        .capacity-fill.yellow {
            background: var(--accent-yellow);
        }

        .capacity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .dot-green { background: var(--map-green); }
        .dot-yellow { background: var(--map-yellow); }
        .dot-red { background: var(--map-red); }
        .dot-blue { background: var(--map-blue); }
        .dot-gray { background: #95A5A6; }

        /* Pending Items */
        .pending-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #F0F0F0;
        }

        .pending-avatar {
            width: 36px;
            height: 36px;
            background: var(--light-red);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
            font-weight: 600;
            font-size: 14px;
        }

        .pending-info {
            flex: 1;
        }

        .pending-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 2px;
        }

        .pending-info p {
            font-size: 11px;
            color: #95A5A6;
        }

        .pending-action {
            background: var(--light-yellow);
            color: #B26A00;
            padding: 4px 10px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
        }

        /* Map Container */
        .map-container {
            position: relative;
            width: 100%;
            height: 300px;
            border-radius: 16px;
            overflow: hidden;
        }

        #adminMap {
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* Map Legend - Top Right */
        .map-legend {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            background: white;
            padding: 8px 12px;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            gap: 12px;
            font-size: 10px;
            font-weight: 500;
            border: 1px solid #EDE7E7;
            backdrop-filter: blur(5px);
            background: rgba(255,255,255,0.95);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .legend-color {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .legend-color.green { background: var(--map-green); }
        .legend-color.yellow { background: var(--map-yellow); }
        .legend-color.red { background: var(--map-red); }
        .legend-color.blue { background: var(--map-blue); }

        /* Ultra Minimal Modal for Map Markers - Fits Map Container */
        .custom-popup .leaflet-popup-content-wrapper {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .custom-popup .leaflet-popup-content {
            margin: 0;
            width: 200px !important;
            padding: 0;
        }

        .custom-popup .leaflet-popup-tip {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mini-modal {
            padding: 12px;
        }

        .mini-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .mini-title {
            font-size: 13px;
            font-weight: 700;
            color: #2C3E50;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 110px;
        }

        .mini-status {
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .mini-status.available {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .mini-status.near-capacity {
            background: var(--light-yellow);
            color: #B26A00;
        }

        .mini-status.full {
            background: var(--light-red);
            color: var(--primary-red);
        }

        .mini-status.temp-shelter {
            background: #E3F2FD;
            color: #1976D2;
        }

        .mini-location {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #95A5A6;
            font-size: 9px;
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-location i {
            color: var(--primary-red);
            font-size: 8px;
        }

        .mini-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            background: #F8F9FA;
            padding: 8px;
            border-radius: 8px;
        }

        .mini-stat {
            text-align: center;
            flex: 1;
        }

        .mini-stat-value {
            font-size: 12px;
            font-weight: 700;
            color: #2C3E50;
        }

        .mini-stat-label {
            font-size: 7px;
            color: #95A5A6;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            margin-top: 2px;
        }

        .mini-capacity {
            margin-bottom: 10px;
        }

        .mini-capacity-header {
            display: flex;
            justify-content: space-between;
            font-size: 8px;
            margin-bottom: 3px;
            color: #5D6D7E;
        }

        .mini-capacity-bar {
            width: 100%;
            height: 4px;
            background: #F0F0F0;
            border-radius: 10px;
            overflow: hidden;
        }

        .mini-capacity-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }

        .mini-footer {
            display: flex;
            gap: 6px;
        }

        .mini-btn {
            flex: 1;
            padding: 6px 0;
            border-radius: 6px;
            border: none;
            font-size: 9px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
        }

        .mini-btn-primary {
            background: var(--primary-red);
            color: white;
        }

        .mini-btn-primary:hover {
            background: var(--dark-red);
        }

        .mini-btn-secondary {
            background: #F8F9FA;
            color: #5D6D7E;
            border: 1px solid #EDE7E7;
        }

        .mini-btn-secondary:hover {
            background: #EDE7E7;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar-toggle-btn {
                display: none;
            }
            
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
            }
            .mobile-toggle {
                display: block;
            }
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard {
                padding: 16px;
            }
            .top-nav {
                padding: 0 16px;
            }
            .user-info {
                display: none;
            }
            .map-legend {
                flex-wrap: wrap;
                width: calc(100% - 30px);
                top: 10px;
                right: 15px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Toggle Button - Outside Sidebar -->
        <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
            <i class="fas fa-chevron-left"></i>
        </div>

        <!-- Sidebar - No Scrollbar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-image">
                        <!-- MDRRMO Logo - Ready for image -->
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=logo-icon-fallback></span>';">
                        <!-- Fallback icon if image doesn't exist -->
                    </div>
                    <div class="logo-text">
                        <h3>MDRRMO</h3>
                        <p>San Ildefonso</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-content">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Main</div>
                    <ul class="sidebar-menu">
                        <li><a href="#" class="sidebar-link active"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <!-- <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li>
                        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li>
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
                    </ul>
                </div> -->

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li>
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span> <span class="sidebar-badge"><?php echo number_format($summary['total_evacuees']); ?></span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Settings</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="profile.php" class="sidebar-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a></li>
                        <li><a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> <span>Settings</span></a></li> -->
                        <li><a href="../pages/logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                    </ul>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <button class="mobile-toggle" id="mobileToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>Dashboard</h1>
                </div>

                <div class="user-menu">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['full_name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></span>
                            <span class="user-role">MDRRMO Administrator</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard">
                <!-- Welcome Bar -->
                <div class="welcome-bar">
                    <div class="welcome-text">
                        <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'] ?? 'Admin')[0]); ?>!</h2>
                        <p>
                            <i class="fas fa-map-marker-alt" style="color: var(--primary-red);"></i> San Ildefonso, Bulacan
                            <span class="date-badge">
                                <i class="far fa-calendar"></i> <?php echo date('F j, Y'); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <span class="badge yellow">
                            <i class="fas fa-exclamation-triangle"></i> 3 Pending Approvals
                        </span>
                    </div>
                </div>

                <!-- Minimized Stat Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['total_centers']; ?></div>
                            <div class="stat-label-small">Centers</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo number_format($summary['total_evacuees']); ?></div>
                            <div class="stat-label-small">Evacuees</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-check-circle" style="color: var(--map-green);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_available']; ?></div>
                            <div class="stat-label-small">Available</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-exclamation-triangle" style="color: var(--map-yellow);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_near']; ?></div>
                            <div class="stat-label-small">Near Cap</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-times-circle" style="color: var(--map-red);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $summary['status_full']; ?></div>
                            <div class="stat-label-small">Full</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-small"><i class="fas fa-thermometer-half"></i></div>
                        <div class="stat-content">
                            <div class="stat-value-small"><?php echo $weather['heat_index'] ?? '32'; ?>°C</div>
                            <div class="stat-label-small">Heat Index</div>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid-2">
                    <!-- Left Column -->
                    <div>
                        <!-- Current Situation Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-exclamation-triangle"></i> Current Situation</h3>
                                <span class="badge">Live</span>
                            </div>
                            
                            <?php if ($activeDisaster): ?>
                            <div class="situation-item">
                                <div class="situation-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="situation-content">
                                    <div class="situation-title">
                                        <?php echo htmlspecialchars(strtoupper($activeDisaster['type'])); ?>
                                        <span class="level-indicator level-high">
                                            Signal <?php echo (int)$activeDisaster['level']; ?>
                                        </span>
                                    </div>
                                    <div class="situation-desc">
                                        <?php echo htmlspecialchars($activeDisaster['title']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="situation-item" style="border-left-color: var(--map-green);">
                                <div class="situation-icon" style="color: var(--map-green);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="situation-content">
                                    <div class="situation-title">
                                        All Clear
                                        <span class="level-indicator level-low">Normal</span>
                                    </div>
                                    <div class="situation-desc">
                                        No active disasters in San Ildefonso
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($weather): ?>
                            <div class="weather-widget">
                                <div class="weather-temp"><?php echo round($weather['temp_c']); ?>°</div>
                                <div class="weather-details">
                                    <div class="weather-condition">
                                        <i class="fas fa-cloud-sun"></i> <?php echo htmlspecialchars($weather['condition_text']); ?>
                                    </div>
                                    <div class="weather-stats">
                                        <span><i class="fas fa-thermometer-half"></i> HI: <?php echo $weather['heat_index']; ?>°C</span>
                                        <span><i class="fas fa-tint"></i> <?php echo $weather['humidity']; ?>%</span>
                                        <span class="level-indicator level-<?php echo $weather['level']; ?>" style="background: rgba(255,255,255,0.2); color: white;">
                                            <?php echo ucfirst($weather['level']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pending Approvals Card -->
                        <!-- <div class="card" style="margin-top: 24px;">
                            <div class="card-header">
                                <h3><i class="fas fa-clock"></i> Pending Approvals</h3>
                                <span class="badge yellow">3 New</span>
                            </div>
                            
                            <div class="centers-list">
                                <div class="pending-item">
                                    <div class="pending-avatar">JD</div>
                                    <div class="pending-info">
                                        <h4>Juan Dela Cruz</h4>
                                        <p>Barangay Coordinator · Brgy. San Juan</p>
                                    </div>
                                    <a href="#" class="pending-action">Review</a>
                                </div>
                                <div class="pending-item">
                                    <div class="pending-avatar">MS</div>
                                    <div class="pending-info">
                                        <h4>Maria Santos</h4>
                                        <p>Evacuation Staff · Poblacion</p>
                                    </div>
                                    <a href="#" class="pending-action">Review</a>
                                </div>
                                <div class="pending-item">
                                    <div class="pending-avatar">PR</div>
                                    <div class="pending-info">
                                        <h4>Pedro Reyes</h4>
                                        <p>MSWD Officer Verification</p>
                                    </div>
                                    <a href="#" class="pending-action">Review</a>
                                </div>
                            </div>
                        </div> -->
                    </div>

                    <!-- Right Column -->
                    <div>
                        <!-- Evacuation Centers Card -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-map-pin"></i> Evacuation Centers</h3>
                                <span class="badge"><?php echo count($centers); ?> Active</span>
                            </div>
                            
                            <div class="centers-list">
                                <?php 
                                $displayCenters = array_slice($centers, 0, 4);
                                foreach ($displayCenters as $center): 
                                    $dotClass = 'dot-gray';
                                    $fillClass = '';
                                    $capacityPercent = ($center['max_capacity_people'] > 0) 
                                        ? ($center['current_occupancy'] / $center['max_capacity_people']) * 100 
                                        : 0;
                                    
                                    if ($center['status'] === 'available') {
                                        $dotClass = 'dot-green';
                                        $fillClass = 'green';
                                    } else if ($center['status'] === 'near_capacity') {
                                        $dotClass = 'dot-yellow';
                                        $fillClass = 'yellow';
                                    } else if ($center['status'] === 'full') {
                                        $dotClass = 'dot-red';
                                    } else if ($center['status'] === 'temp_shelter') {
                                        $dotClass = 'dot-blue';
                                    }
                                ?>
                                <div class="center-item">
                                    <div class="center-info">
                                        <h4><?php echo htmlspecialchars($center['name']); ?></h4>
                                        <p><i class="fas fa-map-marker-alt" style="color: var(--primary-red);"></i> <?php echo htmlspecialchars($center['barangay_name']); ?></p>
                                    </div>
                                    <div class="capacity-indicator">
                                        <div class="capacity-bar">
                                            <div class="capacity-fill <?php echo $fillClass; ?>" style="width: <?php echo min($capacityPercent, 100); ?>%;"></div>
                                        </div>
                                        <span class="capacity-dot <?php echo $dotClass; ?>"></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top: 15px; text-align: center;">
                                <a href="centers.php" style="color: var(--primary-red); text-decoration: none; font-size: 13px; font-weight: 500;">
                                    View All Centers <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        <<!-- Quick Stats Card -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> Quick Overview</h3>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">

        <div style="background:#FAFAFA;border-radius:12px;padding:12px;text-align:center;">
            <div style="font-size:20px;font-weight:700;color:var(--map-green);">
                <?php echo $summary['status_available']; ?>
            </div>
            <div style="font-size:11px;color:#95A5A6;">Available</div>
        </div>

        <div style="background:#FAFAFA;border-radius:12px;padding:12px;text-align:center;">
            <div style="font-size:20px;font-weight:700;color:var(--map-yellow);">
                <?php echo $summary['status_near']; ?>
            </div>
            <div style="font-size:11px;color:#95A5A6;">Near Capacity</div>
        </div>

        <div style="background:#FAFAFA;border-radius:12px;padding:12px;text-align:center;">
            <div style="font-size:20px;font-weight:700;color:var(--map-red);">
                <?php echo $summary['status_full']; ?>
            </div>
            <div style="font-size:11px;color:#95A5A6;">Full</div>
        </div>

        <div style="background:#FAFAFA;border-radius:12px;padding:12px;text-align:center;">
            <div style="font-size:20px;font-weight:700;color:var(--map-blue);">
                <?php echo $summary['status_temp']; ?>
            </div>
            <div style="font-size:11px;color:#95A5A6;">Temp Shelters</div>
        </div>

        <div style="background:#FAFAFA;border-radius:12px;padding:12px;text-align:center; grid-column: span 2;">
            <div style="font-size:20px;font-weight:700;color:#95A5A6;">
                <?php echo $summary['status_closed']; ?>
            </div>
            <div style="font-size:11px;color:#95A5A6;">Closed Centers</div>
        </div>

    </div>
</div>
                    </div>
                </div>

                <!-- Map Card with Legend at Top Right -->
                <div class="card" style="margin-top: 24px; padding: 0; overflow: hidden;">
                    <div class="map-container">
                        <div id="adminMap"></div>
                        
                        <!-- Map Legend - Top Right -->
                        <div class="map-legend">
                            <div class="legend-item">
                                <span class="legend-color green"></span>
                                <span>A</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color yellow"></span>
                                <span>N</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color red"></span>
                                <span>F</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color blue"></span>
                                <span>T</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Sidebar Toggle with external button - Smooth Animation
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const mobileToggle = document.getElementById('mobileToggle');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('collapsed');
            
            // Change icon with smooth transition
            const icon = toggleBtn.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });

        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Map with Ultra Minimal Modal
        const centers = <?php echo json_encode(array_map(function ($c) {
            return [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'lat' => (float)$c['lat'],
                'lng' => (float)$c['lng'],
                'barangay' => $c['barangay_name'],
                'status' => $c['status'],
                'max_capacity_people' => (int)$c['max_capacity_people'],
                'current_occupancy' => (int)$c['current_occupancy'],
            ];
        }, $centers)); ?>;

        if (centers.length > 0) {
            const map = L.map('adminMap', {zoomControl: true});
            const first = centers[0];
            map.setView([first.lat, first.lng], 12);
            
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap, © CartoDB',
                subdomains: 'abcd',
                maxZoom: 20
            }).addTo(map);

            centers.forEach((c) => {
                let color = '#2E7D32'; // green - available
                if (c.status === 'near_capacity') color = '#FFC107'; // yellow
                else if (c.status === 'full') color = '#D32F2F'; // red
                else if (c.status === 'temp_shelter') color = '#3498DB'; // blue

                const marker = L.circleMarker([c.lat, c.lng], {
                    radius: 8,
                    weight: 2,
                    color: 'white',
                    fillColor: color,
                    fillOpacity: 0.9
                }).addTo(map);
                
                // Calculate capacity percentage
                const capacityPercent = Math.min((c.current_occupancy / c.max_capacity_people) * 100, 100);
                
                // Format status for CSS class
                const statusClass = c.status.replace('_', '-');

                // Create ultra minimal popup content (200px wide)
                const popupContent = `
                    <div class="mini-modal">
                        <div class="mini-header">
                            <h3 class="mini-title">${c.name}</h3>
                            <span class="mini-status ${statusClass}">${c.status === 'available' ? 'A' : c.status === 'near_capacity' ? 'N' : c.status === 'full' ? 'F' : 'T'}</span>
                        </div>
                        
                        <div class="mini-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${c.barangay}</span>
                        </div>
                        
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.max_capacity_people}</div>
                                <div class="mini-stat-label">CAP</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.current_occupancy}</div>
                                <div class="mini-stat-label">EVA</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value">${c.max_capacity_people - c.current_occupancy}</div>
                                <div class="mini-stat-label">AVL</div>
                            </div>
                        </div>
                        
                        <div class="mini-capacity">
                            <div class="mini-capacity-header">
                                <span>Fill</span>
                                <span>${Math.round(capacityPercent)}%</span>
                            </div>
                            <div class="mini-capacity-bar">
                                <div class="mini-capacity-fill" style="width: ${capacityPercent}%; background: ${color};"></div>
                            </div>
                        </div>
                        
                        <div class="mini-footer">
                            <a href="centers.php?id=${c.id}" class="mini-btn mini-btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="mini-btn mini-btn-secondary" onclick="alert('Directions coming soon!')">
                                <i class="fas fa-directions"></i>
                            </button>
                        </div>
                    </div>
                `;

                // Bind popup with custom class
                marker.bindPopup(popupContent, {
                    className: 'custom-popup',
                    minWidth: 200,
                    maxWidth: 200
                });
            });
        } else {
            document.getElementById('adminMap').innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #95A5A6;">No evacuation centers defined yet.</div>';
        }
    </script>
</body>
</html>