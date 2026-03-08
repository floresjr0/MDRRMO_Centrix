<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role,
           u.is_active, u.is_email_verified,
           b.name AS barangay_name
    FROM users u
    JOIN barangays b ON b.id = u.barangay_id
    ORDER BY u.id DESC
")->fetchAll();

// Get current user for display
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | MDRRMO San Ildefonso</title>
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

        /* Role Badges */
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .role-admin {
            background: var(--light-red);
            color: var(--primary-red);
        }

        .role-coordinator {
            background: #E3F2FD;
            color: #1976D2;
        }

        .role-mswdo {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .role-barangay {
            background: #FFF3E0;
            color: #E65100;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .status-inactive {
            background: #FFEBEE;
            color: #D32F2F;
        }

        .status-verified {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .status-unverified {
            background: #FFF3E0;
            color: #E65100;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95A5A6;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #EDE7E7;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
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
                        <li><a href="users.php" class="sidebar-link active"><i class="fas fa-users"></i> <span>User Management</span> <span class="sidebar-badge"><?php echo count($users); ?></span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span> </a></li>
                        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li>
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li>
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><span class="sidebar-badge">8</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Settings</div>
                    <ul class="sidebar-menu">
                        <li><a href="profile.php" class="sidebar-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a></li>
                        <li><a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
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
                    <h1>User Management</h1>
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
                        <h2>User Accounts</h2>
                        <p>
                            <i class="fas fa-users" style="color: var(--primary-red);"></i> 
                            Manage system users and their roles
                        </p>
                    </div>
                    <a href="create_coordinator.php" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Add Coordinator
                    </a>
                </div>

                <!-- Stats Cards -->
                <?php
                $totalUsers = count($users);
                $adminCount = 0;
                $coordinatorCount = 0;
                $mswdoCount = 0;
                $barangayCount = 0;
                $verifiedCount = 0;
                $activeCount = 0;
                
                foreach ($users as $u) {
                    if ($u['role'] === 'admin') $adminCount++;
                    else if ($u['role'] === 'coordinator') $coordinatorCount++;
                    else if ($u['role'] === 'mswdo') $mswdoCount++;
                    else if ($u['role'] === 'barangay') $barangayCount++;
                    
                    if ($u['is_email_verified']) $verifiedCount++;
                    if ($u['is_active']) $activeCount++;
                }
                ?>
                
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $totalUsers; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $adminCount; ?></div>
                            <div class="stat-label">Admins</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $coordinatorCount; ?></div>
                            <div class="stat-label">Coordinators</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2E7D32;"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $verifiedCount; ?></div>
                            <div class="stat-label">Verified</div>
                        </div>
                    </div>
                </div>

                <!-- Users Table Card -->
                <div class="card">
                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <input type="text" class="filter-input" placeholder="Search users..." id="searchInput">
                        <select class="filter-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="mswdo">MSWDO</option>
                            <option value="barangay">Barangay</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <?php if (!$users): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users found.</p>
                            <a href="create_coordinator.php" class="btn-primary">
                                <i class="fas fa-user-plus"></i> Add Your First User
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Barangay</th>
                                        <th>Verification</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): 
                                        $roleClass = '';
                                        if ($u['role'] === 'admin') $roleClass = 'role-admin';
                                        else if ($u['role'] === 'coordinator') $roleClass = 'role-coordinator';
                                        else if ($u['role'] === 'mswdo') $roleClass = 'role-mswdo';
                                        else if ($u['role'] === 'barangay') $roleClass = 'role-barangay';
                                    ?>
                                        <tr>
                                            <td><strong>#<?php echo (int)$u['id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td>
                                                <span class="role-badge <?php echo $roleClass; ?>">
                                                    <?php echo ucfirst(htmlspecialchars($u['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($u['barangay_name']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $u['is_email_verified'] ? 'status-verified' : 'status-unverified'; ?>">
                                                    <?php echo $u['is_email_verified'] ? 'Verified' : 'Unverified'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $u['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="edit_user.php?id=<?php echo (int)$u['id']; ?>" class="action-btn">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="reset_password.php?id=<?php echo (int)$u['id']; ?>" class="action-btn">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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

        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                if (name.includes(searchText) || email.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Role filter
        document.getElementById('roleFilter').addEventListener('change', function() {
            const role = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!role) {
                    row.style.display = '';
                    continue;
                }
                const rowRole = row.cells[3].textContent.trim().toLowerCase();
                if (rowRole === role) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>