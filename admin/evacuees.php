<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$user = current_user();
$pdo  = db();

// --- Summary Metrics ---
$totalEvacuees = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
$totalFamilies = (int)$pdo->query("SELECT COUNT(*) FROM evac_registrations")->fetchColumn();
$totalCenters  = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();

$demo = $pdo->query("
    SELECT
        COALESCE(SUM(adults),0)   AS grand_adults,
        COALESCE(SUM(children),0) AS grand_children,
        COALESCE(SUM(seniors),0)  AS grand_seniors,
        COALESCE(SUM(pwds),0)     AS grand_pwds
    FROM evac_registrations
")->fetch();

$evacSummary = $pdo->query("
    SELECT
        ec.id,
        ec.name          AS center_name,
        b.name           AS barangay_name,
        ec.status,
        ec.max_capacity_people,
        ec.max_capacity_families,
        u.full_name        AS coordinator_name,
        u.contact_number   AS coordinator_contact,
        COALESCE(SUM(er.adults),0)        AS total_adults,
        COALESCE(SUM(er.children),0)      AS total_children,
        COALESCE(SUM(er.seniors),0)       AS total_seniors,
        COALESCE(SUM(er.pwds),0)          AS total_pwds,
        COALESCE(SUM(er.total_members),0) AS total_evacuees,
        COUNT(DISTINCT er.id)             AS total_families
    FROM evacuation_centers ec
    LEFT JOIN barangays b            ON b.id  = ec.barangay_id
    LEFT JOIN users u                ON u.id  = ec.coordinator_user_id
    LEFT JOIN evac_registrations er  ON er.center_id = ec.id
    GROUP BY ec.id
    ORDER BY total_evacuees DESC
")->fetchAll();

$barangaySummary = $pdo->query("
    SELECT
        b.name AS barangay_name,
        COALESCE(SUM(er.adults),0)        AS total_adults,
        COALESCE(SUM(er.children),0)      AS total_children,
        COALESCE(SUM(er.seniors),0)       AS total_seniors,
        COALESCE(SUM(er.pwds),0)          AS total_pwds,
        COALESCE(SUM(er.total_members),0) AS total_evacuees,
        COUNT(DISTINCT er.id)             AS total_families
    FROM evac_registrations er
    JOIN barangays b ON b.id = er.barangay_id
    GROUP BY b.id
    ORDER BY total_evacuees DESC
")->fetchAll();

$recentRegs = $pdo->query("
    SELECT
        er.id,
        er.family_head_name,
        er.adults, er.children, er.seniors, er.pwds, er.total_members,
        er.created_at,
        ec.name   AS center_name,
        b.name    AS barangay_name,
        u.full_name AS registered_by
    FROM evac_registrations er
    JOIN evacuation_centers ec ON ec.id = er.center_id
    JOIN barangays b           ON b.id  = er.barangay_id
    JOIN users u               ON u.id  = er.created_by
    ORDER BY er.created_at DESC
    LIMIT 20
")->fetchAll();

$grandChildren = array_sum(array_column($evacSummary, 'total_children'));
$grandAdults   = array_sum(array_column($evacSummary, 'total_adults'));
$grandSeniors  = array_sum(array_column($evacSummary, 'total_seniors'));
$grandPwds     = array_sum(array_column($evacSummary, 'total_pwds'));
$grandFamilies = array_sum(array_column($evacSummary, 'total_families'));
$grandTotal    = array_sum(array_column($evacSummary, 'total_evacuees'));
$grandCap      = array_sum(array_column($evacSummary, 'max_capacity_people'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evacuees | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar Toggle ── */
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
            transition: left 0.3s cubic-bezier(0.4,0,0.2,1), color 0.2s, background 0.2s;
        }
        .sidebar-toggle-btn:hover { color: var(--primary-red); background: var(--light-red); }
        .sidebar-toggle-btn.collapsed { left: var(--sidebar-collapsed-width); }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
            position: fixed;
            height: 100vh;
            z-index: 1000;
            border-right: 1px solid #EDE7E7;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar.collapsed .sidebar-link span,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .logo-text { display: none; }
        .sidebar.collapsed .sidebar-link { justify-content: center; padding: 15px 0; }
        .sidebar.collapsed .sidebar-link i { margin: 0; font-size: 20px; }
        .sidebar.collapsed .sidebar-header { padding: 20px 0; justify-content: center; }

        .sidebar-header {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #EDE7E7;
            flex-shrink: 0;
        }
        .sidebar-logo { display: flex; align-items: center; gap: 12px; }
        .logo-image {
            width: 40px; height: 40px;
            background: var(--primary-red);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
        }
        .logo-image img { width: 100%; height: 100%; object-fit: cover; }
        .logo-text h3 { font-size: 16px; font-weight: 700; color: #2C3E50; line-height: 1.3; }
        .logo-text p  { font-size: 11px; color: #95A5A6; }

        .sidebar-content {
            padding: 20px 0; flex: 1;
            overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none;
        }
        .sidebar-content::-webkit-scrollbar { display: none; }

        .sidebar-section { margin-bottom: 20px; }
        .sidebar-section-title {
            padding: 10px 20px;
            font-size: 12px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: #95A5A6;
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
            font-size: 11px; padding: 2px 8px;
            border-radius: 30px; margin-left: auto;
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .main-content.expanded { margin-left: var(--sidebar-collapsed-width); }

        /* ── Top Nav ── */
        .top-nav {
            background: white;
            padding: 0 32px;
            height: 80px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #EDE7E7;
            position: sticky; top: 0; z-index: 99;
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
            width: 40px; height: 40px;
            background: var(--accent-yellow);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-red); font-weight: 700; font-size: 18px;
        }
        .user-name { font-weight: 600; font-size: 14px; color: #2C3E50; }
        .user-role { font-size: 12px; color: #95A5A6; }

        /* ── Page Body ── */
        .dashboard { padding: 24px 32px; }

        /* ── Welcome Bar ── */
        .welcome-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px;
        }
        .welcome-text h2 { font-size: 20px; font-weight: 600; color: #2C3E50; margin-bottom: 4px; }
        .welcome-text p  { color: #95A5A6; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .date-badge {
            background: var(--light-yellow); color: #B26A00;
            padding: 4px 12px; border-radius: 30px; font-size: 13px;
            display: flex; align-items: center; gap: 6px;
        }

        /* ── Stat Cards ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px; margin-bottom: 24px;
        }
        .stat-card {
            background: white; border-radius: 12px; padding: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
            display: flex; align-items: center; gap: 10px;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px rgba(211,47,47,0.2);
            border-color: var(--primary-red);
        }
        .stat-icon-small {
            width: 36px; height: 36px;
            background: var(--light-red); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary-red); font-size: 16px; flex-shrink: 0;
        }
        .stat-icon-small.blue   { background: #E3F2FD; color: var(--map-blue); }
        .stat-icon-small.green  { background: #E8F5E9; color: var(--map-green); }
        .stat-icon-small.yellow { background: var(--light-yellow); color: #B26A00; }
        .stat-icon-small.purple { background: #F3E5F5; color: #8E44AD; }
        .stat-icon-small.teal   { background: #E0F2F1; color: #16A085; }
        .stat-value-small { font-size: 18px; font-weight: 700; color: #2C3E50; line-height: 1.2; }
        .stat-label-small { font-size: 11px; color: #95A5A6; text-transform: uppercase; letter-spacing: 0.3px; }

        /* ── Cards ── */
        .card {
            background: white; border-radius: 20px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7; margin-bottom: 24px;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 16px;
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
        .badge.blue   { background: #E3F2FD; color: var(--map-blue); }
        .badge.green  { background: #E8F5E9; color: var(--map-green); }
        .badge.yellow { background: var(--light-yellow); color: #B26A00; }

        /* ── Overview Grid (matches index quick stats style) ── */
        .overview-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
        }
        .overview-box {
            background: #FAFAFA; border-radius: 12px; padding: 16px; text-align: center;
            border: 1px solid #F0F0F0;
        }
        .overview-box-val { font-size: 24px; font-weight: 700; line-height: 1; }
        .overview-box-lbl { font-size: 11px; color: #95A5A6; margin-top: 5px; }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; margin: -20px; margin-top: 0; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table thead tr { background: #FAFAFA; }
        .data-table th {
            padding: 11px 16px; text-align: left;
            font-size: 11px; font-weight: 600; color: #95A5A6;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid #EDE7E7; white-space: nowrap;
        }
        .data-table td {
            padding: 12px 16px; border-bottom: 1px solid #F5F5F5; vertical-align: middle;
        }
        .data-table tbody tr:hover { background: #FAFAFA; }
        .data-table tfoot td {
            padding: 12px 16px; background: #FAFAFA;
            font-weight: 700; border-top: 1px solid #EDE7E7;
        }

        .center-name { font-weight: 600; color: #2C3E50; font-size: 13.5px; }
        .center-brgy { font-size: 11.5px; color: #95A5A6; margin-top: 2px; }
        .center-brgy i { color: var(--primary-red); font-size: 10px; }

        .coord-name    { font-weight: 600; font-size: 13px; }
        .coord-contact { font-size: 11.5px; color: #95A5A6; margin-top: 2px; }
        .coord-contact i { color: var(--map-green); }

        /* Demographic chips — same pill style as index */
        .chip {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 3px 10px; border-radius: 30px;
            font-size: 12px; font-weight: 600;
        }
        .chip-child  { background: #E3F2FD; color: #1976D2; }
        .chip-adult  { background: #E8F5E9; color: var(--map-green); }
        .chip-senior { background: #F3E5F5; color: #8E44AD; }
        .chip-pwd    { background: #FFF3E0; color: #E65100; }
        .chip-total  { background: var(--light-red); color: var(--primary-red); font-size: 14px; }

        /* Capacity bar */
        .cap-wrap { min-width: 130px; }
        .cap-bar  { height: 4px; background: #F0F0F0; border-radius: 10px; overflow: hidden; margin-bottom: 4px; }
        .cap-fill { height: 100%; border-radius: 10px; }
        .cap-text { font-size: 11px; color: #95A5A6; }

        /* Status badge — same pattern as index dots */
        .status-badge {
            padding: 3px 10px; border-radius: 30px;
            font-size: 10px; font-weight: 700; white-space: nowrap;
        }
        .st-available { background: #E8F5E9; color: var(--map-green); }
        .st-near      { background: var(--light-yellow); color: #B26A00; }
        .st-full      { background: var(--light-red); color: var(--primary-red); }
        .st-temp      { background: #E3F2FD; color: #1976D2; }
        .st-closed    { background: #F5F5F5; color: #95A5A6; }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px; flex-wrap: wrap;
        }
        .filter-bar input[type="text"] {
            padding: 8px 12px 8px 34px;
            border: 1px solid #EDE7E7; border-radius: 10px;
            font-size: 13px; outline: none; min-width: 220px;
            background: #F8F9FA url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2395A5A6' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
            transition: border-color .2s, background .2s;
        }
        .filter-bar input:focus { border-color: var(--primary-red); background-color: white; }
        .filter-select {
            padding: 8px 12px; border: 1px solid #EDE7E7; border-radius: 10px;
            font-size: 13px; outline: none; background: #F8F9FA; color: #2C3E50; cursor: pointer;
        }
        .filter-select:focus { border-color: var(--primary-red); }

        /* ── Barangay Grid ── */
        .brgy-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr));
            gap: 12px;
        }
        .brgy-card {
            background: #FAFAFA; border: 1px solid #EDE7E7;
            border-radius: 16px; padding: 16px;
            transition: all 0.2s; border-left: 3px solid transparent;
        }
        .brgy-card:hover {
            border-left-color: var(--primary-red);
            box-shadow: 0 4px 12px rgba(211,47,47,0.1);
            transform: translateY(-1px);
        }
        .brgy-name {
            font-weight: 600; font-size: 13px; color: #2C3E50;
            display: flex; align-items: center; gap: 6px; margin-bottom: 10px;
        }
        .brgy-name i { color: var(--primary-red); font-size: 11px; }
        .brgy-demos { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
        .brgy-total { font-size: 24px; font-weight: 700; color: var(--primary-red); line-height: 1; }
        .brgy-sublbl { font-size: 11px; color: #95A5A6; margin-top: 3px; }

        /* ── Recent Registrations ── */
        .family-cell { display: flex; align-items: center; gap: 10px; }
        .family-avatar {
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--light-red); color: var(--primary-red);
            font-weight: 700; font-size: 13px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .family-name  { font-weight: 600; font-size: 13px; color: #2C3E50; }
        .family-sub   { font-size: 11px; color: #95A5A6; }

        .empty-state { text-align: center; padding: 48px 20px; color: #95A5A6; }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; opacity: .4; }

        /* ── Responsive ── */
        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 992px) {
            .sidebar-toggle-btn { display: none; }
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; transition: transform 0.3s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
            .mobile-toggle { display: block; }
            .overview-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .dashboard { padding: 16px; }
            .top-nav { padding: 0 16px; }
            .user-role, .user-name + .user-role { display: none; }
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
                    <li><a href="maps.php"     class="sidebar-link"><i class="fas fa-map"></i><span>Maps</span></a></li>
                    <li><a href="evacuees.php" class="sidebar-link active">
                        <i class="fas fa-people-arrows"></i><span>Evacuees</span>
                        <span class="sidebar-badge"><?php echo number_format($totalEvacuees); ?></span>
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
                <h1>Evacuees</h1>
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
                    <h2>Evacuee Management</h2>
                    <p>
                        <i class="fas fa-map-marker-alt" style="color:var(--primary-red)"></i>
                        San Ildefonso, Bulacan — All registered evacuees
                        <span class="date-badge"><i class="far fa-calendar"></i><?php echo date('F j, Y'); ?></span>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="maps.php" style="text-decoration:none">
                        <span class="badge blue" style="padding:6px 14px;cursor:pointer"><i class="fas fa-map"></i> View Map</span>
                    </a>
                    <a href="centers.php" style="text-decoration:none">
                        <span class="badge" style="padding:6px 14px;cursor:pointer"><i class="fas fa-list"></i> Centers</span>
                    </a>
                </div>
            </div>

            <!-- Stat Cards — identical sizing to index.php -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-people-arrows"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($totalEvacuees); ?></div>
                        <div class="stat-label-small">Total Evacuees</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-home"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($totalFamilies); ?></div>
                        <div class="stat-label-small">Families</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small green"><i class="fas fa-user"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_adults']); ?></div>
                        <div class="stat-label-small">Adults</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small teal"><i class="fas fa-child"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_children']); ?></div>
                        <div class="stat-label-small">Children</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small purple"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_seniors']); ?></div>
                        <div class="stat-label-small">Seniors</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small yellow"><i class="fas fa-wheelchair"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_pwds']); ?></div>
                        <div class="stat-label-small">PWDs</div>
                    </div>
                </div>
            </div>

            <!-- Capacity Overview — using index.php quick stats style -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Overall Capacity Overview</h3>
                    <span class="badge green"><?php echo $grandCap > 0 ? round(($grandTotal/$grandCap)*100) : 0; ?>% Occupied</span>
                </div>
                <div class="overview-grid">
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--primary-red)"><?php echo number_format($grandTotal); ?></div>
                        <div class="overview-box-lbl">Total Evacuees</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--map-blue)"><?php echo number_format($grandCap); ?></div>
                        <div class="overview-box-lbl">Total Capacity</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--map-green)"><?php echo number_format(max(0,$grandCap-$grandTotal)); ?></div>
                        <div class="overview-box-lbl">Available Slots</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:#8E44AD"><?php echo number_format($grandFamilies); ?></div>
                        <div class="overview-box-lbl">Total Families</div>
                    </div>
                </div>
            </div>

            <!-- Centers Summary Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Evacuation Centers Summary</h3>
                    <span class="badge"><?php echo count($evacSummary); ?> Centers</span>
                </div>

                <div class="filter-bar">
                    <input type="text" id="centerSearch" placeholder="Search center or barangay…">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="near_capacity">Near Capacity</option>
                        <option value="full">Full</option>
                        <option value="temp_shelter">Temp Shelter</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <?php if (empty($evacSummary)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No evacuation registrations recorded yet.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table" id="centersTable">
                        <thead>
                            <tr>
                                <th>Center</th>
                                <th>Coordinator</th>
                                <th>Children</th>
                                <th>Adults</th>
                                <th>Seniors</th>
                                <th>PWD</th>
                                <th>Families</th>
                                <th>Total</th>
                                <th>Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($evacSummary as $row):
                            $pct = $row['max_capacity_people'] > 0
                                ? min(round(($row['total_evacuees'] / $row['max_capacity_people']) * 100), 100) : 0;
                            $barColor = '#2E7D32'; $statusLabel = 'Available'; $statusClass = 'st-available';
                            if ($row['status'] === 'near_capacity') { $barColor='#FFC107'; $statusLabel='Near Cap'; $statusClass='st-near'; }
                            elseif ($row['status'] === 'full')       { $barColor='#D32F2F'; $statusLabel='Full';    $statusClass='st-full'; }
                            elseif ($row['status'] === 'temp_shelter'){ $barColor='#3498DB'; $statusLabel='Temp';  $statusClass='st-temp'; }
                            elseif ($row['status'] === 'closed')     { $barColor='#95A5A6'; $statusLabel='Closed'; $statusClass='st-closed'; }
                        ?>
                        <tr data-status="<?php echo $row['status']; ?>">
                            <td>
                                <div class="center-name"><?php echo htmlspecialchars($row['center_name']); ?></div>
                                <div class="center-brgy"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['barangay_name']); ?></div>
                            </td>
                            <td>
                                <?php if ($row['coordinator_name']): ?>
                                    <div class="coord-name"><?php echo htmlspecialchars($row['coordinator_name']); ?></div>
                                    <div class="coord-contact"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($row['coordinator_contact'] ?? '—'); ?></div>
                                <?php else: ?>
                                    <span style="color:#95A5A6;font-style:italic;font-size:12px">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="chip chip-child"><?php echo number_format($row['total_children']); ?></span></td>
                            <td><span class="chip chip-adult"><?php echo number_format($row['total_adults']); ?></span></td>
                            <td><span class="chip chip-senior"><?php echo number_format($row['total_seniors']); ?></span></td>
                            <td><span class="chip chip-pwd"><?php echo number_format($row['total_pwds']); ?></span></td>
                            <td><strong><?php echo number_format($row['total_families']); ?></strong></td>
                            <td><span class="chip chip-total"><?php echo number_format($row['total_evacuees']); ?></span></td>
                            <td>
                                <div class="cap-wrap">
                                    <div class="cap-bar">
                                        <div class="cap-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>"></div>
                                    </div>
                                    <div class="cap-text"><?php echo number_format($row['total_evacuees']); ?> / <?php echo number_format($row['max_capacity_people']); ?> (<?php echo $pct; ?>%)</div>
                                </div>
                            </td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="color:#95A5A6;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Totals</td>
                                <td><span class="chip chip-child"><?php echo number_format($grandChildren); ?></span></td>
                                <td><span class="chip chip-adult"><?php echo number_format($grandAdults); ?></span></td>
                                <td><span class="chip chip-senior"><?php echo number_format($grandSeniors); ?></span></td>
                                <td><span class="chip chip-pwd"><?php echo number_format($grandPwds); ?></span></td>
                                <td><strong><?php echo number_format($grandFamilies); ?></strong></td>
                                <td><span class="chip chip-total"><?php echo number_format($grandTotal); ?></span></td>
                                <td colspan="2">
                                    <strong><?php echo number_format($grandTotal); ?></strong> / <?php echo number_format($grandCap); ?>
                                    <span style="color:#95A5A6;font-size:11px">(<?php echo $grandCap>0?round(($grandTotal/$grandCap)*100):0; ?>%)</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Barangay Breakdown -->
            <?php if (!empty($barangaySummary)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marked-alt"></i> By Barangay of Origin</h3>
                    <span class="badge blue"><?php echo count($barangaySummary); ?> Barangays</span>
                </div>
                <div class="brgy-grid">
                    <?php foreach ($barangaySummary as $brgy): ?>
                    <div class="brgy-card">
                        <div class="brgy-name"><i class="fas fa-location-dot"></i><?php echo htmlspecialchars($brgy['barangay_name']); ?></div>
                        <div class="brgy-demos">
                            <span class="chip chip-child"><?php echo $brgy['total_children']; ?> C</span>
                            <span class="chip chip-adult"><?php echo $brgy['total_adults']; ?> A</span>
                            <span class="chip chip-senior"><?php echo $brgy['total_seniors']; ?> S</span>
                            <span class="chip chip-pwd"><?php echo $brgy['total_pwds']; ?> P</span>
                        </div>
                        <div class="brgy-total"><?php echo number_format($brgy['total_evacuees']); ?></div>
                        <div class="brgy-sublbl"><?php echo number_format($brgy['total_families']); ?> families</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Registrations -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Recent Registrations</h3>
                    <span class="badge"><?php echo count($recentRegs); ?> Records</span>
                </div>
                <?php if (empty($recentRegs)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No registrations yet.</p></div>
                <?php else: ?>
                <div class="filter-bar" style="margin-bottom:0;padding-bottom:16px;border-bottom:1px solid #F0F0F0">
                    <input type="text" id="recentSearch" placeholder="Search by name, center or barangay…" style="min-width:280px">
                </div>
                <div class="table-wrap" style="margin-top:16px">
                    <table class="data-table" id="recentTable">
                        <thead>
                            <tr>
                                <th>Family Head</th>
                                <th>Evacuation Center</th>
                                <th>Barangay</th>
                                <th>C</th>
                                <th>A</th>
                                <th>S</th>
                                <th>P</th>
                                <th>Total</th>
                                <th>Registered By</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentRegs as $reg): ?>
                        <tr>
                            <td>
                                <div class="family-cell">
                                    <div class="family-avatar"><?php echo strtoupper(substr($reg['family_head_name'],0,1)); ?></div>
                                    <div>
                                        <div class="family-name"><?php echo htmlspecialchars($reg['family_head_name']); ?></div>
                                        <div class="family-sub">ID #<?php echo $reg['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12.5px"><?php echo htmlspecialchars($reg['center_name']); ?></td>
                            <td style="font-size:12.5px"><?php echo htmlspecialchars($reg['barangay_name']); ?></td>
                            <td><span class="chip chip-child"><?php echo $reg['children']; ?></span></td>
                            <td><span class="chip chip-adult"><?php echo $reg['adults']; ?></span></td>
                            <td><span class="chip chip-senior"><?php echo $reg['seniors']; ?></span></td>
                            <td><span class="chip chip-pwd"><?php echo $reg['pwds']; ?></span></td>
                            <td><span class="chip chip-total"><?php echo $reg['total_members']; ?></span></td>
                            <td style="font-size:12px;color:#95A5A6"><?php echo htmlspecialchars($reg['registered_by']); ?></td>
                            <td style="font-size:11.5px;color:#95A5A6;white-space:nowrap">
                                <?php echo date('M j, Y', strtotime($reg['created_at'])); ?><br>
                                <span style="font-size:10.5px"><?php echo date('g:i A', strtotime($reg['created_at'])); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /dashboard -->
    </main>
</div>

<script>
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

    // Centers table filter
    const searchInput  = document.getElementById('centerSearch');
    const statusFilter = document.getElementById('statusFilter');
    const tableBody    = document.querySelector('#centersTable tbody');

    function filterTable() {
        const q  = searchInput.value.toLowerCase();
        const st = statusFilter.value;
        tableBody.querySelectorAll('tr').forEach(row => {
            const matchQ  = q  === '' || row.textContent.toLowerCase().includes(q);
            const matchSt = st === '' || row.dataset.status === st;
            row.style.display = (matchQ && matchSt) ? '' : 'none';
        });
    }
    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Recent search
    const recentSearch = document.getElementById('recentSearch');
    if (recentSearch) {
        const recentBody = document.querySelector('#recentTable tbody');
        recentSearch.addEventListener('input', () => {
            const q = recentSearch.value.toLowerCase();
            recentBody.querySelectorAll('tr').forEach(row => {
                row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
</script>
</body>
</html>