<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$disaster = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM disasters WHERE id = ?");
    $stmt->execute([$id]);
    $disaster = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'] ?? 'typhoon';
    $level  = (int)($_POST['level'] ?? 1);
    $status = $_POST['status'] ?? 'planned';
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $start  = trim($_POST['started_at'] ?? '');
    $end    = trim($_POST['ended_at'] ?? '');

    $validTypes = ['typhoon','flood','earthquake','heat','landslide','other'];
    $validStatus = ['planned','ongoing','resolved'];

    if (!in_array($type, $validTypes, true)) {
        $errors[] = 'Invalid disaster type.';
    }
    if ($level < 1 || $level > 5) {
        $errors[] = 'Level must be between 1 and 5.';
    }
    if (!in_array($status, $validStatus, true)) {
        $errors[] = 'Invalid status.';
    }
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!$errors) {
    if ($id && $disaster) {
        $stmt = $pdo->prepare("UPDATE disasters
                               SET type = ?, level = ?, status = ?, title = ?,
                                   description = ?, started_at = ?, ended_at = ?
                               WHERE id = ?");
        $stmt->execute([$type, $level, $status, $title, $desc ?: null, $start ?: null, $end ?: null, $id]);
        $savedId = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO disasters
                               (type, level, status, title, description, started_at, ended_at)
                               VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$type, $level, $status, $title, $desc ?: null, $start ?: null, $end ?: null]);
        $savedId = (int)$pdo->lastInsertId();
    }

    // ── Push notification — only if status is ongoing ──
    if ($status === 'ongoing') {
        require_once __DIR__ . '/../pages/notify.php';

        $types  = ['typhoon'=>'Bagyo','flood'=>'Baha','earthquake'=>'Lindol',
                   'heat'=>'Init','landslide'=>'Landslide','fire'=>'Sunog','other'=>'Iba pa'];
        $levels = [1=>'Mababa',2=>'Katamtaman',3=>'Mataas',4=>'Sukdulan',5=>'Kritikal'];

        $tl = $types[$type] ?? ucfirst($type);
        $ll = $levels[$level] ?? 'Signal #'.$level;

        $notifTitle = "⚠️ MDRRMO Alert: {$tl} Signal #{$level}";
        $notifBody  = "{$ll} na antas ng panganib. " . mb_substr($desc ?: 'Manatiling alerto at sundin ang mga tagubilin.', 0, 100);

        // Clear the lock for this disaster so it always fires when admin explicitly saves
        $lockFile = sys_get_temp_dir() . '/mdrrmo_notif_lock.json';
        $lock = file_exists($lockFile)
            ? (json_decode(file_get_contents($lockFile), true) ?? [])
            : [];
        unset($lock['disaster_' . $savedId]); // force resend
        file_put_contents($lockFile, json_encode($lock));

        sendOneSignalNotification($notifTitle, $notifBody, [
            'type'          => 'disaster',
            'level'         => $level,
            'disaster_type' => $type,
            'disaster_id'   => $savedId,
        ]);
    }

    // ── "All clear" notification when resolved ──
    if ($status === 'resolved') {
        require_once __DIR__ . '/../pages/notify.php';
        sendOneSignalNotification(
            "✅ MDRRMO: Tapos na ang alerto",
            "Ang {$title} ay natapos na. Manatiling maingat.",
            ['type' => 'resolved', 'disaster_id' => $savedId]
        );
    }

    header('Location: disasters.php');
    exit;
}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit Disaster' : 'New Disaster'; ?> | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="../asset/css/admin_disaster_edit.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
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
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link active"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
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
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><span></span> <span class="sidebar-badge">8</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Settings</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="profile.php" class="sidebar-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a></li> -->
                        <!-- <li><a href="settings.php" class="sidebar-link"><i class="fas fa-cog"></i> <span>Settings</span></a></li> -->
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
                    <h1><?php echo $id ? 'Edit Disaster' : 'New Disaster'; ?></h1>
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
                        <h2><?php echo $id ? 'Edit Disaster Event' : 'Create New Disaster'; ?></h2>
                        <p>
                            <i class="fas fa-exclamation-triangle" style="color: var(--primary-red);"></i> 
                            <?php echo $id ? 'Update disaster information' : 'Record a new disaster event'; ?>
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

                    <!-- Info Box -->
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>Disaster levels range from 1 (minor) to 5 (catastrophic). Status indicates the current phase of the event.</p>
                    </div>

                    <form method="post" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Type <span class="form-required">*</span>
                                </label>
                                <?php
                                $selectedType = $_POST['type'] ?? ($disaster['type'] ?? 'typhoon');
                                ?>
                                <select name="type" class="form-control">
                                    <?php foreach (['typhoon','flood','earthquake','heat','landslide','other'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $selectedType === $opt ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($opt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-chart-line"></i>
                                    Level <span class="form-required">*</span>
                                </label>
                                <input type="number" name="level" min="1" max="5" required
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['level'] ?? ($disaster['level'] ?? 1)); ?>">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    1 = Minor, 5 = Catastrophic
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-clock"></i>
                                    Status <span class="form-required">*</span>
                                </label>
                                <?php
                                $selectedStatus = $_POST['status'] ?? ($disaster['status'] ?? 'planned');
                                ?>
                                <select name="status" class="form-control">
                                    <?php foreach (['planned','ongoing','resolved'] as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo $selectedStatus === $st ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($st); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Title <span class="form-required">*</span>
                                </label>
                                <input type="text" name="title" required
                                       class="form-control"
                                       placeholder="e.g., Typhoon Enteng"
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ($disaster['title'] ?? '')); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                Description
                            </label>
                            <textarea name="description" rows="4" class="form-control"
                                      placeholder="Provide details about the disaster..."><?php
                                echo htmlspecialchars($_POST['description'] ?? ($disaster['description'] ?? ''));
                            ?></textarea>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Optional: Add additional information
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Start Time
                                </label>
                                <input type="datetime-local" name="started_at"
                                       class="form-control"
                                       value="<?php 
                                            $startValue = $_POST['started_at'] ?? ($disaster['started_at'] ?? '');
                                            if ($startValue && !$_POST) {
                                                echo date('Y-m-d\TH:i', strtotime($startValue));
                                            } else {
                                                echo htmlspecialchars($startValue);
                                            }
                                       ?>">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    When the disaster started
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-check"></i>
                                    End Time
                                </label>
                                <input type="datetime-local" name="ended_at"
                                       class="form-control"
                                       value="<?php 
                                            $endValue = $_POST['ended_at'] ?? ($disaster['ended_at'] ?? '');
                                            if ($endValue && !$_POST) {
                                                echo date('Y-m-d\TH:i', strtotime($endValue));
                                            } else {
                                                echo htmlspecialchars($endValue);
                                            }
                                       ?>">
                                <div class="form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Leave empty if ongoing
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i>
                                <?php echo $id ? 'Save Changes' : 'Create Disaster'; ?>
                            </button>
                            <a href="disasters.php" class="btn-cancel">
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
    </script>
</body>
</html>