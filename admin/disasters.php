<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$stmt = $pdo->query("SELECT * FROM disasters ORDER BY status = 'ongoing' DESC, level DESC, started_at DESC, id DESC");
$disasters = $stmt->fetchAll();

// Get counts for stats
$ongoingCount = 0;
$totalDisasters = count($disasters);
$highestLevel = 0;

foreach ($disasters as $d) {
    if ($d['status'] === 'ongoing') $ongoingCount++;
    if ((int)$d['level'] > $highestLevel) $highestLevel = (int)$d['level'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disasters & Events | MDRRMO San Ildefonso</title>
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

        /* Sidebar Toggle Button - Outside Sidebar */
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

        /* Sidebar */
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

        /* Main Content */
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

        /* Page Header */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-header-left h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 4px;
        }

        .page-header-left p {
            color: #95A5A6;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary-red);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px var(--primary-red);
        }

        .btn-primary i {
            font-size: 14px;
        }

        .btn-secondary {
            background: #F8F9FA;
            color: #5D6D7E;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid #EDE7E7;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #EDE7E7;
            color: var(--primary-red);
            border-color: var(--primary-red);
        }

        /* Card */
        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--light-red);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
            font-size: 20px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #2C3E50;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 12px;
            color: #95A5A6;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th {
            text-align: left;
            padding: 16px 12px;
            background: #F8F9FA;
            color: #5D6D7E;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #EDE7E7;
        }

        .table td {
            padding: 16px 12px;
            border-bottom: 1px solid #F0F0F0;
            color: #2C3E50;
        }

        .table tr:hover td {
            background: #F8F9FA;
        }

        /* Type Badges */
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .type-typhoon {
            background: #E3F2FD;
            color: #1976D2;
        }

        .type-flood {
            background: #E0F2F1;
            color: #00796B;
        }

        .type-earthquake {
            background: #FBE9E7;
            color: #D84315;
        }

        .type-fire {
            background: #FFEBEE;
            color: #D32F2F;
        }

        .type-landslide {
            background: #EFEBE9;
            color: #5D4037;
        }

        .type-others {
            background: #F3E5F5;
            color: #7B1FA2;
        }

        /* Level Indicators */
        .level-indicator {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            min-width: 60px;
            text-align: center;
        }

        .level-1 { background: #E8F5E9; color: #2E7D32; }
        .level-2 { background: #FFF3E0; color: #E65100; }
        .level-3 { background: #FFEBEE; color: #D32F2F; }
        .level-4 { background: #F3E5F5; color: #7B1FA2; }
        .level-5 { background: #B71C1C; color: white; }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-ongoing {
            background: #FFEBEE;
            color: #D32F2F;
            position: relative;
            padding-left: 20px;
        }

        .status-ongoing::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: #D32F2F;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: translateY(-50%) scale(1); }
            50% { opacity: 0.5; transform: translateY(-50%) scale(1.2); }
            100% { opacity: 1; transform: translateY(-50%) scale(1); }
        }

        .status-ended {
            background: #F5F5F5;
            color: #757575;
        }

        .status-upcoming {
            background: #E3F2FD;
            color: #1976D2;
        }

        /* Action Buttons */
        .action-btn {
            color: #95A5A6;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .action-btn:hover {
            background: var(--light-red);
            color: var(--primary-red);
        }

        .action-btn i {
            font-size: 12px;
        }

        /* Small Empty State */
        .empty-state-small {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            background: #F8F9FA;
            border-radius: 16px;
            margin: 10px 0;
        }

        .empty-state-small i {
            font-size: 32px;
            color: #D32F2F;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state-small p {
            font-size: 14px;
            color: #5D6D7E;
            margin-bottom: 16px;
            font-weight: 500;
        }

        .empty-state-small .btn-primary {
            padding: 8px 16px;
            font-size: 13px;
        }

        .empty-state-small .btn-primary i {
            font-size: 12px;
            margin: 0;
            color: white;
            opacity: 1;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 16px;
            border: 1px solid #EDE7E7;
            border-radius: 10px;
            font-size: 13px;
            min-width: 250px;
            flex: 1;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-red);
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid #EDE7E7;
            border-radius: 10px;
            font-size: 13px;
            background: white;
            min-width: 150px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
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
        }

        @media (max-width: 768px) {
            .dashboard {
                padding: 16px;
            }
            .top-nav {
                padding: 0 16px;
            }
            .user-info {
                display: none;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .filter-bar {
                flex-direction: column;
            }
            .filter-input, .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar Toggle Button -->
        <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
            <i class="fas fa-chevron-left"></i>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-image">
                        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<span class=logo-icon-fallback>⚡</span>';">
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
                        <li><a href="index.php" class="sidebar-link"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link active"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span> <span class="sidebar-badge"><?php echo $ongoingCount; ?></span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li> -->
                        <!-- <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li> -->
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li> -->
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><span class="sidebar-badge">8</span></a></li>
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
                    <h1>Disasters & Events</h1>
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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-left">
                        <h2>Disaster Management</h2>
                        <p>
                            <i class="fas fa-exclamation-triangle" style="color: var(--primary-red);"></i> 
                            Track and manage disaster events
                        </p>
                    </div>
                    <a href="disaster_edit.php" class="btn-primary">
                        <i class="fas fa-plus"></i> New Disaster
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-history"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $totalDisasters; ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-circle" style="color: var(--primary-red);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $ongoingCount; ?></div>
                            <div class="stat-label">Ongoing</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $highestLevel; ?></div>
                            <div class="stat-label">Highest Level</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo date('Y'); ?></div>
                            <div class="stat-label">Current Year</div>
                        </div>
                    </div>
                </div>

                <!-- Disasters Table Card -->
                <div class="card">
                    <!-- Filter Bar (only show if there are disasters) -->
                    <?php if ($disasters): ?>
                    <div class="filter-bar">
                        <input type="text" class="filter-input" placeholder="Search disasters..." id="searchInput">
                        <select class="filter-select" id="typeFilter">
                            <option value="">All Types</option>
                            <option value="typhoon">Typhoon</option>
                            <option value="flood">Flood</option>
                            <option value="earthquake">Earthquake</option>
                            <option value="fire">Fire</option>
                            <option value="landslide">Landslide</option>
                            <option value="others">Others</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="ended">Ended</option>
                            <option value="upcoming">Upcoming</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!$disasters): ?>
                        <!-- Small Empty State -->
                        <div class="empty-state-small">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>No disasters have been recorded yet.</p>
                            <a href="disaster_edit.php" class="btn-primary">
                                <i class="fas fa-plus"></i> Record First Disaster
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table" id="disastersTable">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Level</th>
                                        <th>Status</th>
                                        <th>Title</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($disasters as $d): 
                                        $type = strtolower($d['type']);
                                        $typeClass = 'type-others';
                                        if ($type === 'typhoon') $typeClass = 'type-typhoon';
                                        else if ($type === 'flood') $typeClass = 'type-flood';
                                        else if ($type === 'earthquake') $typeClass = 'type-earthquake';
                                        else if ($type === 'fire') $typeClass = 'type-fire';
                                        else if ($type === 'landslide') $typeClass = 'type-landslide';
                                        
                                        $level = (int)$d['level'];
                                        $levelClass = 'level-1';
                                        if ($level === 2) $levelClass = 'level-2';
                                        else if ($level === 3) $levelClass = 'level-3';
                                        else if ($level === 4) $levelClass = 'level-4';
                                        else if ($level >= 5) $levelClass = 'level-5';
                                        
                                        $status = $d['status'];
                                        $statusClass = 'status-' . $status;
                                        
                                        $startDate = date('M d, Y H:i', strtotime($d['started_at']));
                                        $endDate = $d['ended_at'] ? date('M d, Y H:i', strtotime($d['ended_at'])) : '—';
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="type-badge <?php echo $typeClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($d['type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="level-indicator <?php echo $levelClass; ?>">
                                                    Sig <?php echo $level; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($status)); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($d['title']); ?></strong></td>
                                            <td><?php echo $startDate; ?></td>
                                            <td><?php echo $endDate; ?></td>
                                            <td>
                                                <a href="disaster_edit.php?id=<?php echo (int)$d['id']; ?>" class="action-btn">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($status === 'ongoing'): ?>
                                                    <a href="end_disaster.php?id=<?php echo (int)$d['id']; ?>" class="action-btn" style="color: var(--primary-red);" onclick="return confirm('End this disaster?')">
                                                        <i class="fas fa-stop"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                            <a href="index.php" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                            <a href="disaster_report.php" class="btn-secondary">
                                <i class="fas fa-file-pdf"></i> Report
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const mobileToggle = document.getElementById('mobileToggle');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('collapsed');
            
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

        <?php if ($disasters): ?>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('disastersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const title = row.cells[3].textContent.toLowerCase();
                if (title.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Type filter
        document.getElementById('typeFilter').addEventListener('change', function() {
            const type = this.value.toLowerCase();
            const table = document.getElementById('disastersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!type) {
                    row.style.display = '';
                    continue;
                }
                const rowType = row.cells[0].textContent.trim().toLowerCase();
                if (rowType.includes(type)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            const status = this.value.toLowerCase();
            const table = document.getElementById('disastersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!status) {
                    row.style.display = '';
                    continue;
                }
                const rowStatus = row.cells[2].textContent.trim().toLowerCase();
                if (rowStatus.includes(status)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>