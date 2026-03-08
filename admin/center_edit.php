<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();
$coordinators = $pdo->query("SELECT id, full_name FROM users WHERE role = 'coordinator' AND is_active = 1 ORDER BY full_name")->fetchAll();

$center = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM evacuation_centers WHERE id = ?");
    $stmt->execute([$id]);
    $center = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $lat     = trim($_POST['lat'] ?? '');
    $lng     = trim($_POST['lng'] ?? '');
    $maxCap  = (int)($_POST['max_capacity_people'] ?? 0);
    $maxFam  = (int)($_POST['max_capacity_families'] ?? 0);
    $status  = $_POST['status'] ?? 'available';
    $coordId = isset($_POST['coordinator_user_id']) && $_POST['coordinator_user_id'] !== ''
        ? (int)$_POST['coordinator_user_id'] : null;
    $notes   = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!$barangayId) {
        $errors[] = 'Barangay is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if (!is_numeric($lat) || !is_numeric($lng)) {
        $errors[] = 'Valid latitude and longitude are required.';
    }
    if ($maxCap <= 0) {
        $errors[] = 'Max capacity (people) must be greater than zero.';
    }
    if (!in_array($status, ['available','near_capacity','full','temp_shelter','closed'], true)) {
        $errors[] = 'Invalid status.';
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE evacuation_centers
                                   SET name = ?, barangay_id = ?, address = ?, lat = ?, lng = ?,
                                       max_capacity_people = ?, max_capacity_families = ?, status = ?,
                                       coordinator_user_id = ?, notes = ?
                                   WHERE id = ?");
            $stmt->execute([
                $name, $barangayId, $address, $lat, $lng,
                $maxCap, $maxFam, $status,
                $coordId, $notes, $id
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO evacuation_centers
                                   (name, barangay_id, address, lat, lng,
                                    max_capacity_people, max_capacity_families, status,
                                    coordinator_user_id, notes)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $name, $barangayId, $address, $lat, $lng,
                $maxCap, $maxFam, $status,
                $coordId, $notes
            ]);
            $id = (int)$pdo->lastInsertId();
        }

        header('Location: centers.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'Edit' : 'Add' ?> Evacuation Center | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent main content scrolling */
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
            flex-shrink: 0;
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

        /* Split Screen Layout */
        .split-screen {
            display: flex;
            flex: 1;
            overflow: hidden; /* Prevent split-screen scrolling */
        }

        /* Form Panel - No Scrollbar */
        .form-panel {
            width: 40%;
            min-width: 500px;
            max-width: 600px;
            background: white;
            overflow: hidden; /* Hide overflow */
            display: flex;
            flex-direction: column;
            border-right: 1px solid #EDE7E7;
        }

        .form-panel-content {
            padding: 24px;
            overflow-y: auto; /* Allow content to scroll */
            flex: 1;
            scrollbar-width: none; /* Hide scrollbar for Firefox */
            -ms-overflow-style: none; /* Hide scrollbar for IE/Edge */
        }

        /* Hide scrollbar for Chrome/Safari */
        .form-panel-content::-webkit-scrollbar {
            display: none;
        }

        .form-header {
            margin-bottom: 24px;
            flex-shrink: 0;
        }

        .form-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 4px;
        }

        .form-header p {
            color: #95A5A6;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Map Panel */
        .map-panel {
            flex: 1;
            position: relative;
            background: #1a1a1a;
            overflow: hidden;
        }

        #map {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        /* Map Controls Hint */
        .map-hint {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            color: #2C3E50;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            border: 1px solid #EDE7E7;
        }

        .map-hint i {
            color: var(--primary-red);
        }

        /* Form */
        .form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #2C3E50;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            color: var(--primary-red);
            font-size: 13px;
            width: 16px;
        }

        .form-required {
            color: var(--primary-red);
            font-size: 11px;
            margin-left: 4px;
        }

        .form-control {
            padding: 10px 14px;
            border: 1px solid #EDE7E7;
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.2s;
            background: white;
            width: 100%;
        }

        .form-control:hover {
            border-color: #D32F2F;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%235D6D7E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-hint {
            font-size: 11px;
            color: #95A5A6;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Status Badge Preview */
        .status-preview {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .status-available { background: #E8F5E9; color: #2E7D32; }
        .status-near_capacity { background: #FFF8E1; color: #FFA000; }
        .status-full { background: #FFEBEE; color: #D32F2F; }
        .status-temp_shelter { background: #E3F2FD; color: #1976D2; }
        .status-closed { background: #F5F5F5; color: #757575; }

        /* Error Messages */
        .error-messages {
            background: #FFEBEE;
            border-left: 4px solid var(--primary-red);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            flex-shrink: 0;
        }

        .error-messages ul {
            list-style: none;
            padding: 0;
        }

        .error-messages li {
            color: #D32F2F;
            font-size: 13px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-messages li i {
            font-size: 13px;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #EDE7E7;
            flex-shrink: 0;
        }

        .btn-primary {
            background: var(--primary-red);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px var(--primary-red);
        }

        .btn-secondary {
            background: #F8F9FA;
            color: #5D6D7E;
            padding: 12px 24px;
            border: 1px solid #EDE7E7;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #EDE7E7;
            color: var(--primary-red);
            border-color: var(--primary-red);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .form-panel {
                min-width: 450px;
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
            
            .split-screen {
                flex-direction: column;
            }
            
            .form-panel {
                width: 100%;
                max-width: 100%;
                min-width: auto;
                height: 50%;
            }
            
            .map-panel {
                height: 50%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-nav {
                padding: 0 16px;
            }
            .user-info {
                display: none;
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
                        <li><a href="centers.php" class="sidebar-link active"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li>
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
                    <h1><?= $id ? 'Edit Center' : 'New Center' ?></h1>
                </div>

                <div class="user-menu">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['full_name'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'Admin') ?></span>
                            <span class="user-role">MDRRMO Administrator</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Split Screen Content -->
            <div class="split-screen">
                <!-- Left: Form Panel - No Scrollbar -->
                <div class="form-panel">
                    <div class="form-panel-content">
                        <div class="form-header">
                            <h2><?= $id ? 'Edit Evacuation Center' : 'Add New Evacuation Center' ?></h2>
                            <p>
                                <i class="fas fa-map-marker-alt" style="color: var(--primary-red);"></i>
                                <?= $id ? 'Update center information' : 'Register a new evacuation center' ?>
                            </p>
                        </div>

                        <?php if ($errors): ?>
                            <div class="error-messages">
                                <ul>
                                    <?php foreach ($errors as $err): ?>
                                        <li>
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?= htmlspecialchars($err) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="form">
                            <!-- Basic Information -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-building"></i>
                                    Center Name <span class="form-required">*</span>
                                </label>
                                <input type="text" name="name" required
                                       class="form-control"
                                       placeholder="e.g., San Juan Elementary School"
                                       value="<?= htmlspecialchars($_POST['name'] ?? ($center['name'] ?? '')) ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-map-pin"></i>
                                        Barangay <span class="form-required">*</span>
                                    </label>
                                    <?php
                                    $selectedBarangay = $_POST['barangay_id'] ?? ($center['barangay_id'] ?? '');
                                    ?>
                                    <select name="barangay_id" required class="form-control">
                                        <option value="">-- Select Barangay --</option>
                                        <?php foreach ($barangays as $b): ?>
                                            <option value="<?= (int)$b['id'] ?>"
                                                <?= (string)$selectedBarangay === (string)$b['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-flag"></i>
                                        Status <span class="form-required">*</span>
                                    </label>
                                    <?php $selectedStatus = $_POST['status'] ?? ($center['status'] ?? 'available'); ?>
                                    <select name="status" class="form-control" id="statusSelect">
                                        <?php
                                        $statuses = ['available','near_capacity','full','temp_shelter','closed'];
                                        foreach ($statuses as $s): ?>
                                            <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                                                <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Address <span class="form-required">*</span>
                                </label>
                                <input type="text" name="address" required
                                       class="form-control"
                                       placeholder="Street address, landmarks"
                                       value="<?= htmlspecialchars($_POST['address'] ?? ($center['address'] ?? '')) ?>">
                            </div>

                            <!-- Coordinates -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-latitude"></i>
                                        Latitude <span class="form-required">*</span>
                                    </label>
                                    <input type="text" name="lat" required
                                           class="form-control"
                                           placeholder="e.g., 15.0828"
                                           value="<?= htmlspecialchars($_POST['lat'] ?? ($center['lat'] ?? '')) ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-longitude"></i>
                                        Longitude <span class="form-required">*</span>
                                    </label>
                                    <input type="text" name="lng" required
                                           class="form-control"
                                           placeholder="e.g., 120.9417"
                                           value="<?= htmlspecialchars($_POST['lng'] ?? ($center['lng'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="form-hint" style="margin-top: -8px;">
                                <i class="fas fa-info-circle"></i>
                                Click on the map to set coordinates, or drag the marker
                            </div>

                            <!-- Capacity -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-users"></i>
                                        Max People <span class="form-required">*</span>
                                    </label>
                                    <input type="number" name="max_capacity_people" min="1" required
                                           class="form-control"
                                           value="<?= htmlspecialchars($_POST['max_capacity_people'] ?? ($center['max_capacity_people'] ?? '0')) ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-home"></i>
                                        Max Families
                                    </label>
                                    <input type="number" name="max_capacity_families" min="0"
                                           class="form-control"
                                           value="<?= htmlspecialchars($_POST['max_capacity_families'] ?? ($center['max_capacity_families'] ?? '0')) ?>">
                                </div>
                            </div>

                            <!-- Coordinator -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tie"></i>
                                    Coordinator
                                </label>
                                <?php $selectedCoord = $_POST['coordinator_user_id'] ?? ($center['coordinator_user_id'] ?? ''); ?>
                                <select name="coordinator_user_id" class="form-control">
                                    <option value="">-- None Assigned --</option>
                                    <?php foreach ($coordinators as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"
                                            <?= (string)$selectedCoord === (string)$c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Assign a barangay coordinator to this center
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-sticky-note"></i>
                                    Notes
                                </label>
                                <textarea name="notes" rows="4" class="form-control"
                                          placeholder="Additional information..."><?= htmlspecialchars($_POST['notes'] ?? ($center['notes'] ?? '')) ?></textarea>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= $id ? 'Save Changes' : 'Create Center' ?>
                                </button>
                                <a href="centers.php" class="btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right: Map Panel -->
                <div class="map-panel">
                    <div id="map"></div>
                    <div class="map-hint">
                        <i class="fas fa-mouse-pointer"></i>
                        Click to place marker • Drag to adjust
                    </div>
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

        // Map initialization
        const defaultLat = parseFloat('<?= $center['lat'] ?? '15.0828' ?>');
        const defaultLng = parseFloat('<?= $center['lng'] ?? '120.9417' ?>');

        const map = L.map('map').setView([defaultLat, defaultLng], 16);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '© OpenStreetMap, © CartoDB',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(map);

        // Custom marker icon using red color
        const redIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background: #D32F2F; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true,
            icon: redIcon
        }).addTo(map);

        // Update form inputs when marker is dragged
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            document.querySelector('input[name="lat"]').value = pos.lat.toFixed(6);
            document.querySelector('input[name="lng"]').value = pos.lng.toFixed(6);
        });

        // Move marker when clicking on map
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.querySelector('input[name="lat"]').value = e.latlng.lat.toFixed(6);
            document.querySelector('input[name="lng"]').value = e.latlng.lng.toFixed(6);
        });

        // Update marker when lat/lng inputs change manually
        document.querySelector('input[name="lat"]').addEventListener('change', updateMarkerFromInputs);
        document.querySelector('input[name="lng"]').addEventListener('change', updateMarkerFromInputs);

        function updateMarkerFromInputs() {
            const lat = parseFloat(document.querySelector('input[name="lat"]').value);
            const lng = parseFloat(document.querySelector('input[name="lng"]').value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);
            }
        }

        // Add a circle to show approximate area (optional)
        L.circle([defaultLat, defaultLng], {
            color: '#D32F2F',
            fillColor: '#FFEBEE',
            fillOpacity: 0.2,
            radius: 100
        }).addTo(map);
    </script>
</body>
</html>