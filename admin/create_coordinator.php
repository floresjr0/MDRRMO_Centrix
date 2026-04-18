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
    $contact = trim($_POST['contact_number'] ?? '');
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
    if ($contact === '') {
    $errors[] = 'Contact number is required.';
}

if (!preg_match('/^09[0-9]{9}$/', $contact)) {
    $errors[] = 'Enter a valid Philippine mobile number (e.g., 09123456789).';
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
    contact_number,
    password_hash,
    role,
    barangay_id,
    house_number,
    is_email_verified,
    otp_code_hash,
    otp_expires_at,
    is_active
)       VALUES (
               ?, ?, ?, ?, 'coordinator', ?, ?, 1, NULL, NULL, ?
            )
        ");

        try {
            $stmt->execute([$fullName, $email, $contact, $passwordHash, $barangayId, $houseNo, $isActive]);
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
    <link rel="stylesheet" href="../asset/css/admin_create_coordinator.css" />
    <style>

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
                        <!-- <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li>
                        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li> -->
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li> -->
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><span></span> <span class="sidebar-badge">8</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Settings</div>
                    <ul class="sidebar-menu">
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

                        <div class="form-group">
    <label class="form-label">
        <i class="fas fa-phone"></i>
        Contact Number <span class="form-required">*</span>
    </label>
    <input type="text" name="contact_number" required
           class="form-control"
           placeholder="e.g., 09123456789"
           value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
    <div class="form-hint">
        <i class="fas fa-info-circle"></i>
        Philippine mobile number
    </div>
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