<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();
$user = current_user();

$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $houseNo    = trim($_POST['house_number'] ?? '');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$barangayId) {
        $errors[] = 'Please select a barangay.';
    }
    if ($houseNo === '') {
        $errors[] = 'House number is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected barangay is not valid.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (
                full_name,
                email,
                password_hash,
                role,
                barangay_id,
                house_number,
                is_email_verified,
                otp_code_hash,
                otp_expires_at,
                is_active
            ) VALUES (
                ?, ?, ?, 'coordinator', ?, ?, 1, NULL, NULL, ?
            )
        ");

        try {
            $stmt->execute([$fullName, $email, $passwordHash, $barangayId, $houseNo, $isActive]);
            header('Location: users.php?created=coordinator');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Failed to create coordinator account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Coordinator | MDRRMO San Ildefonso</title>
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
            max-width: 700px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
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
            padding: 12px 0;
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
            gap: 4px;
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

        /* Password Strength (optional enhancement) */
        .password-strength {
            margin-top: 8px;
            display: flex;
            gap: 4px;
        }

        .password-strength span {
            flex: 1;
            height: 4px;
            background: #EDE7E7;
            border-radius: 4px;
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
                        <li><a href="users.php" class="sidebar-link active"><i class="fas fa-users"></i> <span>User Management</span></a></li>
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
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><span></span> <span class="sidebar-badge">8</span></a></li>
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
                    <h1>Create Coordinator</h1>
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
                        <h2>New Coordinator Account</h2>
                        <p>
                            <i class="fas fa-user-plus" style="color: var(--primary-red);"></i> 
                            Create a new barangay coordinator account
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
                        <!-- Full Name -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i>
                                Full Name <span class="form-required">*</span>
                            </label>
                            <input type="text" name="full_name" required
                                   class="form-control"
                                   placeholder="Enter full name"
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Email <span class="form-required">*</span>
                            </label>
                            <input type="email" name="email" required
                                   class="form-control"
                                   placeholder="coordinator@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <!-- Password Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Password <span class="form-required">*</span>
                                </label>
                                <input type="password" name="password" required minlength="8"
                                       class="form-control"
                                       placeholder="Min. 8 characters">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    At least 8 characters
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i>
                                    Confirm Password <span class="form-required">*</span>
                                </label>
                                <input type="password" name="confirm_password" required minlength="8"
                                       class="form-control"
                                       placeholder="Re-enter password">
                            </div>
                        </div>

                        <!-- Barangay and House Number Row -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Barangay <span class="form-required">*</span>
                                </label>
                                <select name="barangay_id" required class="form-control">
                                    <option value="">-- Select Barangay --</option>
                                    <?php foreach ($barangays as $b): ?>
                                        <option value="<?php echo (int)$b['id']; ?>"
                                            <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-home"></i>
                                    House Number <span class="form-required">*</span>
                                </label>
                                <input type="text" name="house_number" required
                                       class="form-control"
                                       placeholder="e.g., 123"
                                       value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Active Account Checkbox -->
                        <div class="form-checkbox">
                            <input type="checkbox" name="is_active" value="1" id="is_active"
                                <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active">
                                <i class="fas fa-check-circle" style="color: var(--map-green);"></i>
                                Active account
                            </label>
                        </div>
                        <div class="form-hint" style="margin-top: -8px; margin-bottom: 8px;">
                            <i class="fas fa-info-circle"></i>
                            Inactive accounts cannot log in to the system
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i>
                                Create Coordinator
                            </button>
                            <a href="users.php" class="btn-cancel">
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

        // Optional: Simple password match validation
        const password = document.querySelector('input[name="password"]');
        const confirm = document.querySelector('input[name="confirm_password"]');
        
        function validatePassword() {
            if (password.value !== confirm.value) {
                confirm.setCustomValidity("Passwords don't match");
            } else {
                confirm.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', validatePassword);
        confirm.addEventListener('keyup', validatePassword);
    </script>
</body>
</html>