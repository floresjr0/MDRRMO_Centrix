<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$ann = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $ann = $stmt->fetch();
}

$disasters = $pdo->query("SELECT id, title FROM disasters ORDER BY status = 'ongoing' DESC, started_at DESC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $type    = $_POST['type'] ?? 'general';
    $disasterId = isset($_POST['disaster_id']) && $_POST['disaster_id'] !== ''
        ? (int)$_POST['disaster_id'] : null;
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($body === '') {
        $errors[] = 'Body is required.';
    }
    if (!in_array($type, ['general','disaster'], true)) {
        $errors[] = 'Invalid type.';
    }

    if (!$errors) {
        if ($id && $ann) {
            $stmt = $pdo->prepare("UPDATE announcements
                                   SET title = ?, body = ?, type = ?, disaster_id = ?, is_pinned = ?
                                   WHERE id = ?");
            $stmt->execute([$title, $body, $type, $disasterId, $isPinned, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements
                                   (title, body, type, disaster_id, is_pinned, published_at, created_by)
                                   VALUES (?,?,?,?,?,NOW(),?)");
            $stmt->execute([$title, $body, $type, $disasterId, $isPinned, $user['id']]);
            $id = (int)$pdo->lastInsertId();
        }

        header('Location: announcements.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit Announcement' : 'New Announcement'; ?> | MDRRMO San Ildefonso</title>
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

        .btn-secondary {
            background: #F8F9FA;
            color: #5D6D7E;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            border: 1px solid #EDE7E7;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Error Messages */
        .error-messages {
            background: #FFEBEE;
            border-left: 4px solid var(--primary-red);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .error-messages ul {
            list-style: none;
            padding: 0;
        }

        .error-messages li {
            color: #D32F2F;
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-messages li i {
            font-size: 14px;
        }

        .error-messages li:last-child {
            margin-bottom: 0;
        }

        /* Form */
        .form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #2C3E50;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            color: var(--primary-red);
            font-size: 14px;
            width: 18px;
        }

        .form-required {
            color: var(--primary-red);
            font-size: 12px;
            margin-left: 4px;
        }

        .form-control {
            padding: 12px 16px;
            border: 1px solid #EDE7E7;
            border-radius: 12px;
            font-size: 14px;
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

        .form-control.error {
            border-color: var(--primary-red);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 150px;
            line-height: 1.6;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235D6D7E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .form-hint {
            font-size: 12px;
            color: #95A5A6;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-hint i {
            font-size: 12px;
        }

        /* Checkbox */
        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }

        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-red);
            cursor: pointer;
        }

        .form-checkbox label {
            font-size: 14px;
            color: #2C3E50;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .form-checkbox i {
            color: #FFA000;
            font-size: 14px;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #EDE7E7;
        }

        .btn-submit {
            background: var(--primary-red);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .btn-submit:hover {
            background: var(--dark-red);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px -8px var(--primary-red);
        }

        .btn-submit i {
            font-size: 16px;
        }

        .btn-cancel {
            background: #F8F9FA;
            color: #5D6D7E;
            padding: 14px 28px;
            border: 1px solid #EDE7E7;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            justify-content: center;
        }

        .btn-cancel:hover {
            background: #EDE7E7;
            color: var(--primary-red);
            border-color: var(--primary-red);
        }

        /* Type Badge Preview */
        .type-preview {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .type-preview.general {
            background: #E3F2FD;
            color: #1976D2;
        }

        .type-preview.disaster {
            background: #FFEBEE;
            color: #D32F2F;
        }

        /* Responsive */
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
            
            .form-row {
                grid-template-columns: 1fr;
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
            .card {
                padding: 20px;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn-submit, .btn-cancel {
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
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li>
                        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li>
                        <li><a href="announcements.php" class="sidebar-link active"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
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
                    <h1><?php echo $id ? 'Edit Announcement' : 'New Announcement'; ?></h1>
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
                        <h2><?php echo $id ? 'Edit Announcement' : 'Create New Announcement'; ?></h2>
                        <p>
                            <i class="fas fa-bullhorn" style="color: var(--primary-red);"></i> 
                            <?php echo $id ? 'Update announcement details' : 'Create a new public announcement'; ?>
                        </p>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="card">
                    <?php if ($errors): ?>
                        <div class="error-messages">
                            <ul>
                                <?php foreach ($errors as $err): ?>
                                    <li>
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($err); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="form">
                        <!-- Title -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-heading"></i>
                                Title <span class="form-required">*</span>
                            </label>
                            <input type="text" name="title" required
                                   class="form-control"
                                   placeholder="e.g., Typhoon Enteng Preparedness Measures"
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ($ann['title'] ?? '')); ?>">
                        </div>

                        <!-- Type and Disaster Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Type <span class="form-required">*</span>
                                </label>
                                <?php
                                $selectedType = $_POST['type'] ?? ($ann['type'] ?? 'general');
                                ?>
                                <select name="type" class="form-control" id="announcementType">
                                    <option value="general" <?php echo $selectedType === 'general' ? 'selected' : ''; ?>>
                                        General
                                    </option>
                                    <option value="disaster" <?php echo $selectedType === 'disaster' ? 'selected' : ''; ?>>
                                        Disaster-related
                                    </option>
                                </select>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    <span id="typeHint">General announcements for all users</span>
                                </div>
                            </div>

                            <div class="form-group" id="disasterField">
                                <label class="form-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Linked Disaster
                                </label>
                                <?php
                                $selectedDisaster = $_POST['disaster_id'] ?? ($ann['disaster_id'] ?? '');
                                ?>
                                <select name="disaster_id" class="form-control">
                                    <option value="">-- None (General) --</option>
                                    <?php foreach ($disasters as $d): ?>
                                        <option value="<?php echo (int)$d['id']; ?>"
                                            <?php echo (string)$selectedDisaster === (string)$d['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Link to an ongoing or recent disaster
                                </div>
                            </div>
                        </div>

                        <!-- Pinned Checkbox -->
                        <div class="form-checkbox">
                            <input type="checkbox" name="is_pinned" value="1" id="is_pinned"
                                <?php 
                                    $checkedPinned = isset($_POST['is_pinned'])
                                        ? (bool)$_POST['is_pinned']
                                        : (isset($ann['is_pinned']) && $ann['is_pinned']);
                                    echo $checkedPinned ? 'checked' : '';
                                ?>>
                            <label for="is_pinned">
                                <i class="fas fa-thumbtack"></i>
                                Pin this announcement to the top
                            </label>
                        </div>
                        <div class="form-hint" style="margin-top: -8px; margin-bottom: 8px;">
                            <i class="fas fa-info-circle"></i>
                            Pinned announcements appear first in the list
                        </div>

                        <!-- Body -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                Body <span class="form-required">*</span>
                            </label>
                            <textarea name="body" rows="8" class="form-control" 
                                      placeholder="Write the announcement content here..."><?php
                                echo htmlspecialchars($_POST['body'] ?? ($ann['body'] ?? ''));
                            ?></textarea>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                You can include important details, instructions, and contact information
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i>
                                <?php echo $id ? 'Save Changes' : 'Create Announcement'; ?>
                            </button>
                            <a href="announcements.php" class="btn-cancel">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
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

        // Dynamic type hint and disaster field visibility
        const typeSelect = document.getElementById('announcementType');
        const typeHint = document.getElementById('typeHint');
        const disasterField = document.getElementById('disasterField');

        function updateTypeUI() {
            const type = typeSelect.value;
            
            if (type === 'general') {
                typeHint.textContent = 'General announcements for all users';
                disasterField.style.opacity = '0.5';
            } else {
                typeHint.textContent = 'Announcements related to specific disasters';
                disasterField.style.opacity = '1';
            }
        }

        typeSelect.addEventListener('change', updateTypeUI);
        updateTypeUI(); // Initial call
    </script>
</body>
</html>