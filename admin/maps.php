<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user    = current_user();
$pdo     = db();
$centers = get_centers_with_occupancy();

$summary = [
    'total_centers'    => 0,
    'total_evacuees'   => 0,
    'status_available' => 0,
    'status_near'      => 0,
    'status_full'      => 0,
    'status_temp'      => 0,
    'status_closed'    => 0,
];

$row = $pdo->query("SELECT COUNT(*) AS c FROM evacuation_centers")->fetch();
if ($row) $summary['total_centers'] = (int)$row['c'];

$row = $pdo->query("SELECT COALESCE(SUM(total_members),0) AS total FROM evac_registrations")->fetch();
if ($row) $summary['total_evacuees'] = (int)$row['total'];

$st = $pdo->query("SELECT status, COUNT(*) AS c FROM evacuation_centers GROUP BY status");
foreach ($st as $s) {
    switch ($s['status']) {
        case 'available':     $summary['status_available'] = (int)$s['c']; break;
        case 'near_capacity': $summary['status_near']      = (int)$s['c']; break;
        case 'full':          $summary['status_full']      = (int)$s['c']; break;
        case 'temp_shelter':  $summary['status_temp']      = (int)$s['c']; break;
        case 'closed':        $summary['status_closed']    = (int)$s['c']; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maps | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body { background-color: #F5F5F5; min-height: 100vh; overflow-x: hidden; }

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
        }

        .app-wrapper { display: flex; min-height: 100vh; }

        /* ── Sidebar Toggle ── */
        .sidebar-toggle-btn {
            position: fixed; left: var(--sidebar-width); top: 20px;
            z-index: 1001; background: white;
            width: 28px; height: 28px; border-radius: 0 8px 8px 0;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 2px 0 8px rgba(0,0,0,0.05);
            border: 1px solid #EDE7E7; border-left: none; color: #95A5A6;
            transition: left 0.3s cubic-bezier(0.4,0,0.2,1), color 0.2s, background 0.2s;
        }
        .sidebar-toggle-btn:hover { color: var(--primary-red); background: var(--light-red); }
        .sidebar-toggle-btn.collapsed { left: var(--sidebar-collapsed-width); }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width); background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
            position: fixed; height: 100vh; z-index: 1000;
            border-right: 1px solid #EDE7E7;
            display: flex; flex-direction: column; overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .logo-text { display: none; }
        .sidebar.collapsed .sidebar-link { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .sidebar-link i { margin: 0; font-size: 20px; }
        .sidebar.collapsed .sidebar-header { padding: 20px 0; justify-content: center; }

        .sidebar-header {
            padding: 24px 20px; display: flex; align-items: center;
            border-bottom: 1px solid #EDE7E7; flex-shrink: 0;
        }
        .sidebar-logo { display: flex; align-items: center; gap: 12px; }
        .logo-image {
            width: 40px; height: 40px; background: var(--primary-red);
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
        }
        .logo-image img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text h3 { font-size: 16px; font-weight: 700; color: #2C3E50; line-height: 1.3; }
        .logo-text p  { font-size: 11px; color: #95A5A6; }

        .sidebar-content {
            padding: 20px 0; flex: 1; overflow-y: auto;
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .sidebar-content::-webkit-scrollbar { display: none; }
        .sidebar-section { margin-bottom: 20px; }
        .sidebar-section-title {
            padding: 10px 20px; font-size: 12px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px; color: #95A5A6;
        }
        .sidebar-menu { list-style: none; }
        .sidebar-link {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: #5D6D7E;
            text-decoration: none; font-size: 14px; font-weight: 500;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .sidebar-link:hover { background: var(--light-red); color: var(--primary-red); border-left-color: var(--primary-red); }
        .sidebar-link.active { background: var(--light-red); color: var(--primary-red); border-left-color: var(--primary-red); }
        .sidebar-link i { width: 20px; font-size: 16px; color: inherit; }
        .sidebar-badge {
            background: var(--primary-red); color: white;
            font-size: 11px; padding: 2px 8px; border-radius: 30px; margin-left: auto;
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1; margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .main-content.expanded { margin-left: var(--sidebar-collapsed-width); }

        /* ── Top Nav ── */
        .top-nav {
            background: white; padding: 0 32px; height: 80px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #EDE7E7; position: sticky; top: 0; z-index: 99;
        }
        .page-title { display: flex; align-items: center; gap: 16px; }
        .page-title h1 { font-size: 24px; font-weight: 700; color: #2C3E50; }
        .mobile-toggle { display: none; background: none; border: none; font-size: 20px; color: #5D6D7E; cursor: pointer; }

        .user-menu { display: flex; align-items: center; gap: 20px; }
        .user-profile {
            display: flex; align-items: center; gap: 12px;
            background: #F8F9FA; padding: 8px 16px 8px 12px; border-radius: 40px;
        }
        .user-avatar {
            width: 40px; height: 40px; background: var(--accent-yellow);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--primary-red); font-weight: 700; font-size: 18px;
        }
        .user-name { font-weight: 600; font-size: 14px; color: #2C3E50; }
        .user-role { font-size: 12px; color: #95A5A6; }

        /* ── Dashboard Body ── */
        .dashboard { padding: 24px 32px; }

        /* ── Welcome Bar ── */
        .welcome-bar {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;
        }
        .welcome-text h2 { font-size: 20px; font-weight: 600; color: #2C3E50; margin-bottom: 4px; }
        .welcome-text p  { color: #95A5A6; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .date-badge {
            background: var(--light-yellow); color: #B26A00;
            padding: 4px 12px; border-radius: 30px; font-size: 13px;
            display: flex; align-items: center; gap: 6px;
        }

        /* ── Stat Cards — identical to index.php ── */
        .stats-row {
            display: grid; grid-template-columns: repeat(6, 1fr);
            gap: 12px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02); border: 1px solid #EDE7E7;
            display: flex; align-items: center; gap: 10px; transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px rgba(211,47,47,0.2);
            border-color: var(--primary-red);
        }
        .stat-icon-small {
            width: 36px; height: 36px; background: var(--light-red); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-red); font-size: 16px; flex-shrink: 0;
        }
        .stat-icon-small.blue   { background: #E3F2FD; color: var(--map-blue); }
        .stat-icon-small.green  { background: #E8F5E9; color: var(--map-green); }
        .stat-icon-small.yellow { background: var(--light-yellow); color: #B26A00; }
        .stat-value-small { font-size: 18px; font-weight: 700; color: #2C3E50; line-height: 1.2; }
        .stat-label-small { font-size: 11px; color: #95A5A6; text-transform: uppercase; letter-spacing: 0.3px; }

        /* ── Two-column grid — same 1.5fr/1fr as index.php ── */
        .grid-2 { display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; }

        /* ── Cards ── */
        .card {
            background: white; border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02); border: 1px solid #EDE7E7;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 20px 16px; border-bottom: 1px solid #F0F0F0;
        }
        .card-header h3 {
            font-size: 16px; font-weight: 600; color: #2C3E50;
            display: flex; align-items: center; gap: 8px;
        }
        .card-header h3 i { color: var(--primary-red); }
        .badge {
            background: var(--light-red); color: var(--primary-red);
            padding: 4px 10px; border-radius: 30px; font-size: 11px; font-weight: 600;
        }
        .badge.yellow { background: var(--light-yellow); color: #B26A00; }
        .badge.blue   { background: #E3F2FD; color: var(--map-blue); }

        /* ── Map Card ── */
        .map-card { padding: 0; overflow: hidden; }
        .map-header {
            padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #F0F0F0;
        }
        .map-header h3 {
            font-size: 16px; font-weight: 600; color: #2C3E50;
            display: flex; align-items: center; gap: 8px;
        }
        .map-header h3 i { color: var(--primary-red); }

        .map-wrapper { position: relative; height: 500px; }
        #mainMap { width: 100%; height: 100%; }

        /* Legend pill — matches index.php style exactly */
        .map-legend {
            position: absolute; top: 14px; right: 14px; z-index: 10;
            background: rgba(255,255,255,0.95);
            padding: 6px 12px; border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex; gap: 12px; font-size: 10px; font-weight: 500;
            border: 1px solid #EDE7E7; backdrop-filter: blur(5px);
        }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot {
            width: 8px; height: 8px; border-radius: 50%;
        }

        /* Map layer controls */
        .map-controls {
            position: absolute; bottom: 14px; left: 14px; z-index: 10;
            display: flex; gap: 6px;
        }
        .map-ctrl-btn {
            background: white; border: 1px solid #EDE7E7; border-radius: 10px;
            padding: 5px 11px; font-size: 11px; font-weight: 600;
            color: #95A5A6; cursor: pointer;
            display: flex; align-items: center; gap: 5px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06); transition: all 0.2s;
        }
        .map-ctrl-btn:hover, .map-ctrl-btn.active {
            background: var(--light-red); color: var(--primary-red); border-color: var(--primary-red);
        }

        /* ── Right Panel ── */
        .panel-col { display: flex; flex-direction: column; gap: 24px; }

        /* Filter tabs — matching index-style pill tabs */
        .filter-tabs {
            display: flex; gap: 6px; padding: 14px 20px; border-bottom: 1px solid #F0F0F0;
        }
        .ftab {
            flex: 1; text-align: center; padding: 6px 4px; border-radius: 10px;
            font-size: 11px; font-weight: 600; cursor: pointer;
            border: 1px solid #EDE7E7; color: #95A5A6; background: transparent;
            transition: all 0.18s;
        }
        .ftab:hover, .ftab.active {
            background: var(--light-red); color: var(--primary-red); border-color: var(--primary-red);
        }

        /* Centers list */
        .center-list {
            display: flex; flex-direction: column; gap: 8px;
            padding: 14px 16px; overflow-y: auto; max-height: 320px;
        }
        .center-list::-webkit-scrollbar { width: 3px; }
        .center-list::-webkit-scrollbar-thumb { background: #EDE7E7; border-radius: 3px; }

        /* Center card — same FAFAFA style as index */
        .center-card {
            background: #FAFAFA; border: 1px solid #EDE7E7;
            border-radius: 14px; padding: 12px; cursor: pointer;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .center-card:hover, .center-card.highlighted {
            border-left-color: var(--primary-red);
            background: var(--light-red);
            box-shadow: 0 2px 10px rgba(211,47,47,0.1);
        }
        .cc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; margin-bottom: 6px; }
        .cc-name { font-size: 13px; font-weight: 600; color: #2C3E50; line-height: 1.4; }
        .cc-status {
            padding: 2px 8px; border-radius: 30px;
            font-size: 9px; font-weight: 700; flex-shrink: 0; white-space: nowrap;
        }
        .cc-status.available     { background: #E8F5E9; color: var(--map-green); }
        .cc-status.near_capacity { background: var(--light-yellow); color: #B26A00; }
        .cc-status.full          { background: var(--light-red); color: var(--primary-red); }
        .cc-status.temp_shelter  { background: #E3F2FD; color: #1976D2; }
        .cc-status.closed        { background: #F5F5F5; color: #95A5A6; }

        .cc-meta { font-size: 11.5px; color: #95A5A6; margin-bottom: 8px; }
        .cc-meta span { display: flex; align-items: center; gap: 5px; margin-bottom: 2px; }
        .cc-meta i { font-size: 10px; color: var(--primary-red); width: 10px; }

        .cc-bar-wrap { margin-top: 6px; }
        .cc-bar-hdr  { display: flex; justify-content: space-between; font-size: 9px; color: #95A5A6; margin-bottom: 3px; }
        .cc-bar      { height: 4px; background: #EDE7E7; border-radius: 10px; overflow: hidden; }
        .cc-bar-fill { height: 100%; border-radius: 10px; }

        /* Quick stats grid — matches index.php Quick Overview card exactly */
        .quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 16px 20px; }
        .quick-box {
            background: #FAFAFA; border-radius: 12px; padding: 12px; text-align: center;
            border: 1px solid #F0F0F0;
        }
        .quick-box.span2 { grid-column: span 2; }
        .qb-val { font-size: 20px; font-weight: 700; line-height: 1; }
        .qb-lbl { font-size: 11px; color: #95A5A6; margin-top: 5px; }

        /* View all link */
        .view-all-link {
            padding: 14px 20px; border-top: 1px solid #F0F0F0; text-align: center;
        }
        .view-all-link a { color: var(--primary-red); text-decoration: none; font-size: 13px; font-weight: 500; }

        /* ── Popup — identical to index.php custom-popup ── */
        .custom-popup .leaflet-popup-content-wrapper {
            background: white; border-radius: 12px; padding: 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); overflow: hidden;
        }
        .custom-popup .leaflet-popup-content { margin: 0; width: 200px !important; padding: 0; }
        .custom-popup .leaflet-popup-tip { background: white; }

        .mini-modal { padding: 12px; }
        .mini-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .mini-title {
            font-size: 13px; font-weight: 700; color: #2C3E50;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px;
        }
        .mini-status { padding: 2px 6px; border-radius: 20px; font-size: 8px; font-weight: 700; }
        .mini-status.available     { background: #E8F5E9; color: var(--map-green); }
        .mini-status.near-capacity { background: var(--light-yellow); color: #B26A00; }
        .mini-status.full          { background: var(--light-red); color: var(--primary-red); }
        .mini-status.temp-shelter  { background: #E3F2FD; color: #1976D2; }
        .mini-status.closed        { background: #F5F5F5; color: #95A5A6; }

        .mini-location { display: flex; align-items: center; gap: 4px; color: #95A5A6; font-size: 9px; margin-bottom: 10px; }
        .mini-location i { color: var(--primary-red); font-size: 8px; }

        .mini-stats { display: flex; justify-content: space-between; margin-bottom: 10px; background: #F8F9FA; padding: 8px; border-radius: 8px; }
        .mini-stat  { text-align: center; flex: 1; }
        .mini-stat-value { font-size: 12px; font-weight: 700; color: #2C3E50; }
        .mini-stat-label { font-size: 7px; color: #95A5A6; text-transform: uppercase; letter-spacing: .2px; margin-top: 2px; }

        .mini-capacity { margin-bottom: 10px; }
        .mini-capacity-header { display: flex; justify-content: space-between; font-size: 8px; margin-bottom: 3px; color: #5D6D7E; }
        .mini-capacity-bar    { width: 100%; height: 4px; background: #EDE7E7; border-radius: 10px; overflow: hidden; }
        .mini-capacity-fill   { height: 100%; border-radius: 10px; }

        .mini-footer { display: flex; gap: 6px; }
        .mini-btn {
            flex: 1; padding: 6px 0; border-radius: 6px; border: none;
            font-size: 9px; font-weight: 600; cursor: pointer; text-align: center;
            text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .mini-btn-primary   { background: var(--primary-red); color: white; }
        .mini-btn-primary:hover { background: var(--dark-red); }
        .mini-btn-secondary { background: #F8F9FA; color: #5D6D7E; border: 1px solid #EDE7E7; }

        /* ── Responsive ── */
        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 992px) {
            .sidebar-toggle-btn { display: none; }
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-toggle { display: block; }
            .grid-2 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .dashboard { padding: 16px; }
            .top-nav { padding: 0 16px; }
        }
    </style>
</head>
<body>
<div class="app-wrapper">

    <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-image">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO"
                         onerror="this.style.display='none';this.parentElement.innerHTML='<span style=color:white;font-weight:700;font-size:18px>M</span>'">
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
                    <li><a href="index.php"     class="sidebar-link"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                    <li><a href="centers.php"   class="sidebar-link"><i class="fas fa-map-marker-alt"></i><span>Evacuation Centers</span></a></li>
                    <li><a href="users.php"     class="sidebar-link"><i class="fas fa-users"></i><span>User Management</span></a></li>
                    <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i><span>Disasters</span></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Operations</div>
                <ul class="sidebar-menu">
                    <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i><span>Announcements</span></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Monitoring</div>
                <ul class="sidebar-menu">
                    <li><a href="maps.php"     class="sidebar-link active"><i class="fas fa-map"></i><span>Maps</span></a></li>
                    <li><a href="evacuees.php" class="sidebar-link">
                        <i class="fas fa-people-arrows"></i><span>Evacuees</span>
                        <span class="sidebar-badge"><?php echo number_format($summary['total_evacuees']); ?></span>
                    </a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Settings</div>
                <ul class="sidebar-menu">
                    <li><a href="../pages/logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">

        <div class="top-nav">
            <div class="page-title">
                <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
                <h1>Maps</h1>
            </div>
            <div class="user-menu">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'] ?? 'A', 0, 1)); ?></div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></div>
                        <div class="user-role">MDRRMO Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard">

            <!-- Welcome Bar -->
            <div class="welcome-bar">
                <div class="welcome-text">
                    <h2>Evacuation Map</h2>
                    <p>
                        <i class="fas fa-map-marker-alt" style="color:var(--primary-red)"></i>
                        San Ildefonso, Bulacan — Live center locations &amp; status
                        <span class="date-badge"><i class="far fa-calendar"></i><?php echo date('F j, Y'); ?></span>
                    </p>
                </div>
                <div style="display:flex;gap:8px">
                    <a href="evacuees.php" style="text-decoration:none">
                        <span class="badge" style="padding:6px 14px;cursor:pointer"><i class="fas fa-people-arrows"></i> View Evacuees</span>
                    </a>
                    <a href="centers.php" style="text-decoration:none">
                        <span class="badge yellow" style="padding:6px 14px;cursor:pointer"><i class="fas fa-list"></i> Centers</span>
                    </a>
                </div>
            </div>

            <!-- Stat Cards — identical to index.php -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-building"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['total_centers']; ?></div>
                        <div class="stat-label-small">Centers</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($summary['total_evacuees']); ?></div>
                        <div class="stat-label-small">Evacuees</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_available']; ?></div>
                        <div class="stat-label-small">Available</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small yellow"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_near']; ?></div>
                        <div class="stat-label-small">Near Cap</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_full']; ?></div>
                        <div class="stat-label-small">Full</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-house-damage"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo $summary['status_temp']; ?></div>
                        <div class="stat-label-small">Temp Shelter</div>
                    </div>
                </div>
            </div>

            <!-- Map + Panel — same 1.5fr/1fr as index.php -->
            <div class="grid-2">

                <!-- Map Card -->
                <div class="card map-card">
                    <div class="map-header">
                        <h3><i class="fas fa-map"></i> San Ildefonso, Bulacan</h3>
                        <div class="map-legend" style="position:static;box-shadow:none;border:1px solid #EDE7E7">
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-green)"></span>Available</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-yellow)"></span>Near Cap</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-red)"></span>Full</div>
                            <div class="legend-item"><span class="legend-dot" style="background:var(--map-blue)"></span>Temp</div>
                            <div class="legend-item"><span class="legend-dot" style="background:#95A5A6"></span>Closed</div>
                        </div>
                    </div>
                    <div class="map-wrapper">
                        <div id="mainMap"></div>
                        <div class="map-controls">
                            <button class="map-ctrl-btn active" id="layerStreet" onclick="switchLayer('street')"><i class="fas fa-road"></i> Street</button>
                            <button class="map-ctrl-btn"        id="layerLight"  onclick="switchLayer('light')"><i class="fas fa-sun"></i> Light</button>
                        </div>
                    </div>
                </div>

                <!-- Right Panel -->
                <div class="panel-col">

                    <!-- Centers list — same card style as index.php Evacuation Centers -->
                    <div class="card" style="overflow:hidden">
                        <div class="card-header">
                            <h3><i class="fas fa-map-pin"></i> Evacuation Centers</h3>
                            <span class="badge" id="centerCountBadge"><?php echo count($centers); ?> Active</span>
                        </div>

                        <div class="filter-tabs">
                            <button class="ftab active" onclick="filterCards('all',this)">All</button>
                            <button class="ftab" onclick="filterCards('available',this)">Open</button>
                            <button class="ftab" onclick="filterCards('near_capacity',this)">Near</button>
                            <button class="ftab" onclick="filterCards('full',this)">Full</button>
                        </div>

                        <div class="center-list" id="centerList"></div>

                        <div class="view-all-link">
                            <a href="centers.php">View All Centers <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <!-- Quick Overview — exact copy of index.php Quick Overview -->
                    <div class="card" style="overflow:hidden">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Quick Overview</h3>
                        </div>
                        <div class="quick-grid">
                            <div class="quick-box">
                                <div class="qb-val" style="color:var(--map-green)"><?php echo $summary['status_available']; ?></div>
                                <div class="qb-lbl">Available</div>
                            </div>
                            <div class="quick-box">
                                <div class="qb-val" style="color:var(--map-yellow)"><?php echo $summary['status_near']; ?></div>
                                <div class="qb-lbl">Near Capacity</div>
                            </div>
                            <div class="quick-box">
                                <div class="qb-val" style="color:var(--map-red)"><?php echo $summary['status_full']; ?></div>
                                <div class="qb-lbl">Full</div>
                            </div>
                            <div class="quick-box">
                                <div class="qb-val" style="color:var(--map-blue)"><?php echo $summary['status_temp']; ?></div>
                                <div class="qb-lbl">Temp Shelters</div>
                            </div>
                            <div class="quick-box span2">
                                <div class="qb-val" style="color:#95A5A6"><?php echo $summary['status_closed']; ?></div>
                                <div class="qb-lbl">Closed Centers</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div><!-- /grid-2 -->

        </div><!-- /dashboard -->
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    /* ── Sidebar toggle — identical to index.php ── */
    const sidebar      = document.getElementById('sidebar');
    const mainContent  = document.getElementById('mainContent');
    const toggleBtn    = document.getElementById('sidebarToggleBtn');
    const mobileToggle = document.getElementById('mobileToggle');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        toggleBtn.classList.toggle('collapsed');
        const icon = toggleBtn.querySelector('i');
        icon.className = sidebar.classList.contains('collapsed') ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    });
    mobileToggle.addEventListener('click', () => sidebar.classList.toggle('show'));

    /* ── Centers data ── */
    const centers = <?php echo json_encode(array_map(function($c) {
        return [
            'id'                  => (int)$c['id'],
            'name'                => $c['name'],
            'lat'                 => (float)$c['lat'],
            'lng'                 => (float)$c['lng'],
            'barangay'            => $c['barangay_name'],
            'status'              => $c['status'],
            'max_capacity_people' => (int)$c['max_capacity_people'],
            'current_occupancy'   => (int)$c['current_occupancy'],
        ];
    }, $centers)); ?>;

    function statusColor(s) {
        if (s === 'near_capacity') return '#FFC107';
        if (s === 'full')          return '#D32F2F';
        if (s === 'temp_shelter')  return '#3498DB';
        if (s === 'closed')        return '#95A5A6';
        return '#2E7D32';
    }
    function statusLabel(s) {
        const map = { available:'Available', near_capacity:'Near Cap', full:'Full', temp_shelter:'Temp', closed:'Closed' };
        return map[s] || s;
    }
    function statusShort(s) {
        const map = { available:'A', near_capacity:'N', full:'F', temp_shelter:'T', closed:'C' };
        return map[s] || '?';
    }

    /* ── Map — tile layers ── */
    const map = L.map('mainMap', { zoomControl: true });
    const tileLayers = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    { attribution:'© OpenStreetMap', maxZoom:19 }),
        light:  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                    { attribution:'© OpenStreetMap, © CartoDB', subdomains:'abcd', maxZoom:20 })
    };
    tileLayers.street.addTo(map);
    map.setView([15.0828, 120.9417], 13);

    /* Municipal boundary */
    L.polygon([
        [15.1050,120.9100],[15.1100,120.9300],[15.1080,120.9600],
        [15.0950,120.9800],[15.0800,120.9900],[15.0600,120.9850],
        [15.0400,120.9700],[15.0350,120.9400],[15.0450,120.9100],
        [15.0650,120.9000],[15.0850,120.9000],[15.1050,120.9100]
    ], {
        color:'#D32F2F', weight:2, opacity:.4,
        fillColor:'#D32F2F', fillOpacity:.03, dashArray:'6 4'
    }).addTo(map).bindTooltip('San Ildefonso, Bulacan', { sticky: true });

    /* ── Markers — same circleMarker style as index.php ── */
    const markerMap = {};

    if (centers.length > 0) {
        centers.forEach(c => {
            const color = statusColor(c.status);
            const pct   = c.max_capacity_people > 0
                ? Math.min((c.current_occupancy / c.max_capacity_people) * 100, 100) : 0;
            const sc    = c.status.replace(/_/g, '-');

            const marker = L.circleMarker([c.lat, c.lng], {
                radius: 8, weight: 2, color: 'white', fillColor: color, fillOpacity: .9
            }).addTo(map);

            const html = `
                <div class="mini-modal">
                    <div class="mini-header">
                        <h3 class="mini-title">${c.name}</h3>
                        <span class="mini-status ${sc}">${statusShort(c.status)}</span>
                    </div>
                    <div class="mini-location">
                        <i class="fas fa-map-marker-alt"></i><span>${c.barangay}</span>
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
                        <div class="mini-capacity-header"><span>Fill</span><span>${Math.round(pct)}%</span></div>
                        <div class="mini-capacity-bar">
                            <div class="mini-capacity-fill" style="width:${pct}%;background:${color}"></div>
                        </div>
                    </div>
                    <div class="mini-footer">
                        <a href="centers.php?id=${c.id}" class="mini-btn mini-btn-primary">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <button class="mini-btn mini-btn-secondary" onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=${c.lat},${c.lng}','_blank')">
                            <i class="fas fa-directions"></i>
                        </button>
                    </div>
                </div>`;

            marker.bindPopup(html, { className:'custom-popup', minWidth:200, maxWidth:200 });
            marker.on('click', () => highlightCard(c.id));
            markerMap[c.id] = marker;
        });

        const group = L.featureGroup(Object.values(markerMap));
        map.fitBounds(group.getBounds().pad(.2));
    } else {
        document.getElementById('mainMap').innerHTML =
            '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#95A5A6">No evacuation centers defined yet.</div>';
    }

    /* ── Panel cards ── */
    function renderCards(filter) {
        const list   = document.getElementById('centerList');
        const badge  = document.getElementById('centerCountBadge');
        list.innerHTML = '';
        const filtered = filter === 'all' ? centers : centers.filter(c => c.status === filter);
        badge.textContent = filtered.length + (filter === 'all' ? ' Active' : '');

        if (!filtered.length) {
            list.innerHTML = '<div style="text-align:center;color:#95A5A6;padding:20px;font-size:12px">No centers found.</div>';
            return;
        }
        filtered.forEach(c => {
            const color = statusColor(c.status);
            const pct   = c.max_capacity_people > 0
                ? Math.min(Math.round((c.current_occupancy / c.max_capacity_people) * 100), 100) : 0;

            const div = document.createElement('div');
            div.className = 'center-card';
            div.id = `card-${c.id}`;
            div.innerHTML = `
                <div class="cc-head">
                    <div class="cc-name">${c.name}</div>
                    <span class="cc-status ${c.status}">${statusLabel(c.status)}</span>
                </div>
                <div class="cc-meta">
                    <span><i class="fas fa-map-marker-alt"></i>${c.barangay}</span>
                    <span><i class="fas fa-users"></i>${c.current_occupancy} / ${c.max_capacity_people} persons</span>
                </div>
                <div class="cc-bar-wrap">
                    <div class="cc-bar-hdr"><span>Occupancy</span><span>${pct}%</span></div>
                    <div class="cc-bar"><div class="cc-bar-fill" style="width:${pct}%;background:${color}"></div></div>
                </div>`;

            div.addEventListener('click', () => {
                map.flyTo([c.lat, c.lng], 16, { animate: true, duration: .8 });
                markerMap[c.id] && markerMap[c.id].openPopup();
                highlightCard(c.id);
            });
            list.appendChild(div);
        });
    }

    function highlightCard(id) {
        document.querySelectorAll('.center-card').forEach(el => el.classList.remove('highlighted'));
        const card = document.getElementById(`card-${id}`);
        if (card) { card.classList.add('highlighted'); card.scrollIntoView({ behavior:'smooth', block:'nearest' }); }
    }

    function filterCards(f, btn) {
        document.querySelectorAll('.ftab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderCards(f);
    }

    renderCards('all');

    /* ── Layer switcher ── */
    let currentLayer = tileLayers.street;
    function switchLayer(type) {
        map.removeLayer(currentLayer);
        currentLayer = tileLayers[type];
        currentLayer.addTo(map);
        document.getElementById('layerStreet').classList.toggle('active', type === 'street');
        document.getElementById('layerLight').classList.toggle('active', type === 'light');
    }
</script>
</body>
</html>