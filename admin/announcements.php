<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$stmt = $pdo->query("SELECT a.*, d.title AS disaster_title
                     FROM announcements a
                     LEFT JOIN disasters d ON d.id = a.disaster_id
                     ORDER BY a.is_pinned DESC, a.published_at DESC, a.id DESC");
$announcements = $stmt->fetchAll();

// Get counts for stats
$totalAnnouncements = count($announcements);
$pinnedCount = 0;
$alertCount = 0;
$infoCount = 0;
$warningCount = 0;

foreach ($announcements as $a) {
    if ($a['is_pinned']) $pinnedCount++;
    
    $type = strtolower($a['type'] ?? '');
    if ($type === 'alert') $alertCount++;
    else if ($type === 'warning') $warningCount++;
    else if ($type === 'info') $infoCount++;
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
    <title>Announcements | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/admin_announcement.css" />
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
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span> <?php if($_badgeCenters > 0): ?><span class="sidebar-badge"><?php echo $_badgeCenters; ?></span><?php endif; ?></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span><?php if($_badgeOngoing > 0): ?><span class="sidebar-badge"><?php echo $_badgeOngoing; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span></a></li> -->
                        <!-- <li><a href="reports.php" class="sidebar-link"><i class="fas fa-file-alt"></i> <span>Reports</span></a></li> -->
                        <li><a href="announcements.php" class="sidebar-link active"><i class="fas fa-bullhorn"></i> <span>Announcements</span> <?php if($_badgeAnnouncements > 0): ?><span class="sidebar-badge"><?php echo $_badgeAnnouncements; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="weather.php" class="sidebar-link"><i class="fas fa-cloud-sun"></i> <span>Weather</span></a></li> -->
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span><?php if($_badgeEvacuees > 0): ?><span class="sidebar-badge"><?php echo number_format($_badgeEvacuees); ?></span><?php endif; ?></a></li>
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
                    <h1>Announcements</h1>
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
                        <h2>Announcements Management</h2>
                        <p>
                            <i class="fas fa-bullhorn" style="color: var(--primary-red);"></i> 
                            Create and manage public announcements and alerts
                        </p>
                    </div>
                    <a href="announcement_edit.php" class="btn-primary">
                        <i class="fas fa-plus"></i> New Announcement
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-bullhorn"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $totalAnnouncements; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-thumbtack" style="color: #FFA000;"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $pinnedCount; ?></div>
                            <div class="stat-label">Pinned</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-circle" style="color: var(--primary-red);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $alertCount; ?></div>
                            <div class="stat-label">Alerts</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-info-circle" style="color: #1976D2;"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $infoCount; ?></div>
                            <div class="stat-label">Info</div>
                        </div>
                    </div>
                </div>

                <!-- Announcements Card -->
                <div class="card">
                    <!-- Filter Bar (only show if there are announcements) -->
                    <?php if ($announcements): ?>
                    <div class="filter-bar">
                        <input type="text" class="filter-input" placeholder="Search announcements..." id="searchInput">
                        <select class="filter-select" id="typeFilter">
                            <option value="">All Types</option>
                            <option value="alert">Alert</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                            <option value="update">Update</option>
                            <option value="other">Other</option>
                        </select>
                        <select class="filter-select" id="pinnedFilter">
                            <option value="">All</option>
                            <option value="pinned">Pinned</option>
                            <option value="unpinned">Unpinned</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if (!$announcements): ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements have been created yet.</p>
                            <a href="announcement_edit.php" class="btn-primary">
                                <i class="fas fa-plus"></i> Create First Announcement
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table" id="announcementsTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Related Disaster</th>
                                        <th>Pinned</th>
                                        <th>Published</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($announcements as $a): 
                                        $type = strtolower($a['type'] ?? 'other');
                                        $typeClass = 'type-other';
                                        if ($type === 'alert') $typeClass = 'type-alert';
                                        else if ($type === 'warning') $typeClass = 'type-warning';
                                        else if ($type === 'info') $typeClass = 'type-info';
                                        else if ($type === 'update') $typeClass = 'type-update';
                                        
                                        $publishedDate = date('M d, Y H:i', strtotime($a['published_at']));
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="type-badge <?php echo $typeClass; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($a['type'] ?? 'Other')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($a['disaster_title']): ?>
                                                    <span style="display: flex; align-items: center; gap: 4px;">
                                                        <i class="fas fa-exclamation-triangle" style="color: var(--primary-red); font-size: 11px;"></i>
                                                        <?php echo htmlspecialchars($a['disaster_title']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #95A5A6;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($a['is_pinned']): ?>
                                                    <span class="pinned-badge">
                                                        <i class="fas fa-thumbtack"></i> Pinned
                                                    </span>
                                                <?php else: ?>
                                                    <span class="unpinned">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $publishedDate; ?></td>
                                            <td>
                                                <a href="announcement_edit.php?id=<?php echo (int)$a['id']; ?>" class="action-btn">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="announcement_delete.php?id=<?php echo (int)$a['id']; ?>" 
                                                   class="action-btn" 
                                                   onclick="return confirm('Delete this announcement?')"
                                                   style="color: #D32F2F;">
                                                    <i class="fas fa-trash"></i>
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

        <?php if ($announcements): ?>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('announcementsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const title = row.cells[0].textContent.toLowerCase();
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
            const table = document.getElementById('announcementsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!type) {
                    row.style.display = '';
                    continue;
                }
                const rowType = row.cells[1].textContent.trim().toLowerCase();
                if (rowType.includes(type)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Pinned filter
        document.getElementById('pinnedFilter').addEventListener('change', function() {
            const filter = this.value;
            const table = document.getElementById('announcementsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!filter) {
                    row.style.display = '';
                    continue;
                }
                
                const pinnedCell = row.cells[3].textContent.trim().toLowerCase();
                
                if (filter === 'pinned' && pinnedCell.includes('pinned')) {
                    row.style.display = '';
                } else if (filter === 'unpinned' && pinnedCell === '—') {
                    row.style.display = '';
                } else if (filter === 'pinned' && pinnedCell === '—') {
                    row.style.display = 'none';
                } else if (filter === 'unpinned' && pinnedCell.includes('pinned')) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>