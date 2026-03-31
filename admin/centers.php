<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user    = current_user();
$pdo     = db();
$centers = get_centers_with_occupancy();
$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

// Map barangay id to name for quick lookup
$barangayById = [];
foreach ($barangays as $b) {
    $barangayById[$b['id']] = $b['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evacuation Centers | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/admin_center.css">
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
                        <li><a href="centers.php" class="sidebar-link active"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span> <span class="sidebar-badge"><?php echo count($centers); ?></span></a></li>
                        <li><a href="users.php" class="sidebar-link"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
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
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span> <span class="sidebar-badge">8</span></a></li>
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
                    <h1>Evacuation Centers</h1>
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
                        <h2>Evacuation Centers Management</h2>
                        <p>
                            <i class="fas fa-map-marker-alt" style="color: var(--primary-red);"></i> 
                            San Ildefonso, Bulacan
                        </p>
                    </div>
                    <a href="center_edit.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New Center
                    </a>
                </div>

                <!-- Stats Cards -->
                <?php
                $totalCapacity = 0;
                $totalEvacuees = 0;
                $availableCount = 0;
                $nearCount = 0;
                $fullCount = 0;
                
                foreach ($centers as $c) {
                    $totalCapacity += (int)$c['max_capacity_people'];
                    $totalEvacuees += (int)$c['current_occupancy'];
                    
                    if ($c['status'] === 'available') $availableCount++;
                    else if ($c['status'] === 'near_capacity') $nearCount++;
                    else if ($c['status'] === 'full') $fullCount++;
                }
                ?>
                
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo count($centers); ?></div>
                            <div class="stat-label">Total Centers</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($totalEvacuees); ?></div>
                            <div class="stat-label">Current Evacuees</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle" style="color: #2E7D32;"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $availableCount; ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color: var(--accent-yellow);"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $nearCount; ?></div>
                            <div class="stat-label">Near Capacity</div>
                        </div>
                    </div>
                </div>

                <!-- Centers Table Card -->
                <div class="card">
                    <?php if (!$centers): ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>No evacuation centers defined yet.</p>
                            <a href="center_edit.php" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Your First Center
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Barangay</th>
                                        <th>Status</th>
                                        <th>Capacity</th>
                                        <th>Evacuees</th>
                                        <th>Utilization</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($centers as $c): ?>
                                        <?php
                                        $max = (int)$c['max_capacity_people'];
                                        $cur = (int)$c['current_occupancy'];
                                        $percent = $max > 0 ? round(($cur / $max) * 100) : 0;
                                        $fillClass = '';
                                        
                                        if ($c['status'] === 'available') $fillClass = 'available';
                                        else if ($c['status'] === 'near_capacity') $fillClass = 'near_capacity';
                                        else if ($c['status'] === 'full') $fillClass = 'full';
                                        else if ($c['status'] === 'temp_shelter') $fillClass = 'temp_shelter';
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['barangay_name']); ?></td>
                                            <td>
                                                <span class="status-pill status-<?php echo htmlspecialchars($c['status']); ?>">
                                                    <?php echo $c['status'] === 'near_capacity' ? 'Near Capacity' : htmlspecialchars($c['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($max); ?></td>
                                            <td><?php echo number_format($cur); ?></td>
                                            <td>
                                                <div class="utilization-container">
                                                    <div class="utilization-bar">
                                                        <div class="utilization-fill <?php echo $fillClass; ?>" style="width: <?php echo $percent; ?>%;"></div>
                                                    </div>
                                                    <span class="utilization-text"><?php echo $percent; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="center_edit.php?id=<?php echo (int)$c['id']; ?>" class="action-btn">
                                                    <i class="fas fa-edit"></i> Edit
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
    </script>
</body>
</html>