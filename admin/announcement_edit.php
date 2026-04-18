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
// Sidebar badges
$_badgeCenters       = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();
$_badgeOngoing       = (int)$pdo->query("SELECT COUNT(*) FROM disasters WHERE status = 'ongoing'")->fetchColumn();
$_badgeAnnouncements = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$_badgeEvacuees      = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
// $_badgeUsers        = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit Announcement' : 'New Announcement'; ?> | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/announcement_edit.css" />
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
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span><?php if($_badgeCenters > 0): ?><span class="sidebar-badge"><?php echo $_badgeCenters; ?></span><?php endif; ?></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span><?php if($_badgeOngoing > 0): ?><span class="sidebar-badge"><?php echo $_badgeOngoing; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li>
                        <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li> -->
                        <li><a href="announcements.php" class="sidebar-link active"><i class="fas fa-bullhorn"></i> <span>Announcements</span><?php if($_badgeAnnouncements > 0): ?><span class="sidebar-badge"><?php echo $_badgeAnnouncements; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li> -->
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><?php if($_badgeEvacuees > 0): ?><span class="sidebar-badge"><?php echo $_badgeEvacuees; ?></span><?php endif; ?></a></li>
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