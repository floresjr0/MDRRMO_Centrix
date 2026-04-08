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
    <link rel="stylesheet" href="../asset/css/admin_disasters.css">
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
                    <h1>Disasters</h1>
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
                                                <a href="disaster_delete.php?id=<?php echo (int)$d['id']; ?>"
                                                class="action-btn"
                                                style="color: #95A5A6;"
                                                onclick="return confirm('Delete this disaster?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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