<?php require_once __DIR__ . '/../pages/session.php'; require_login('admin');  
$pdo = db(); 

// Pagination & filter settings
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : '';
$statusFilter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';

// Build WHERE conditions for filtering
$where = [];
$params = [];

if (!empty($roleFilter)) {
    $where[] = "u.role = :role";
    $params[':role'] = $roleFilter;
}
if ($statusFilter === 'active') {
    $where[] = "u.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "u.is_active = 0";
}
if (!empty($search)) {
    $where[] = "(u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}
$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total filtered records
$countSql = "SELECT COUNT(*) FROM users u JOIN barangays b ON b.id = u.barangay_id $whereClause";
$stmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$totalRows = (int)$stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Fetch users for current page (filtered + paginated)
$sql = "SELECT u.id, u.full_name, u.email, u.role, u.is_active, u.is_email_verified, b.name AS barangay_name 
        FROM users u 
        JOIN barangays b ON b.id = u.barangay_id 
        $whereClause 
        ORDER BY u.id DESC 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$filteredUsers = $stmt->fetchAll();

// Stats: Overall counts (independent of filters)
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$coordinatorCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coordinator'")->fetchColumn();
$mswdoCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mswdo'")->fetchColumn();
$barangayCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'barangay'")->fetchColumn();
$verifiedCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_email_verified = 1")->fetchColumn();
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Get current user for display
$user = current_user();

// Sidebar badges
$_badgeCenters       = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();
$_badgeOngoing       = (int)$pdo->query("SELECT COUNT(*) FROM disasters WHERE status = 'ongoing'")->fetchColumn();
$_badgeAnnouncements = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$_badgeEvacuees      = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/admin_user.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* Pagination Styles - Clean & Modern */
        .pagination-container {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #EDE7E7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .pagination-info {
            font-size: 13px;
            color: #5D6D7E;
            background: #F8F9FA;
            padding: 6px 14px;
            border-radius: 30px;
        }
        .pagination {
            display: flex;
            gap: 6px;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .pagination li a, .pagination li span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border-radius: 10px;
            background: white;
            border: 1px solid #E2E8F0;
            color: #4A5568;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .pagination li a:hover {
            background: var(--light-red);
            border-color: var(--primary-red);
            color: var(--primary-red);
            transform: translateY(-1px);
        }
        .pagination li.active span {
            background: var(--primary-red);
            border-color: var(--primary-red);
            color: white;
            box-shadow: 0 2px 6px rgba(211, 47, 47, 0.2);
        }
        .pagination li.disabled span {
            background: #F8F9FA;
            color: #CBD5E0;
            border-color: #EDE7E7;
            cursor: not-allowed;
            transform: none;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-outline {
            background: white;
            border: 1px solid #EDE7E7;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            color: #5D6D7E;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-outline:hover {
            border-color: var(--primary-red);
            color: var(--primary-red);
            background: var(--light-red);
        }
        .btn-search {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
        }
        .btn-search:hover {
            background: var(--dark-red);
        }
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 180px;
        }
        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #95A5A6;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
            .pagination-container {
                flex-direction: column;
                align-items: flex-start;
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
                        <li><a href="centers.php" class="sidebar-link"><i class="fas fa-map-marker-alt"></i> <span>Evacuation Centers</span><?php if($_badgeCenters > 0): ?><span class="sidebar-badge"><?php echo $_badgeCenters; ?></span><?php endif; ?></a></li>
                        <li><a href="users.php" class="sidebar-link active"><i class="fas fa-users"></i> <span>User Management</span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span><?php if($_badgeOngoing > 0): ?><span class="sidebar-badge"><?php echo $_badgeOngoing; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i> <span>Announcements</span><?php if($_badgeAnnouncements > 0): ?><span class="sidebar-badge"><?php echo $_badgeAnnouncements; ?></span><?php endif; ?></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Monitoring</div>
                    <ul class="sidebar-menu">
                        <li><a href="maps.php" class="sidebar-link"><i class="fas fa-map"></i> <span>Maps</span></a></li>
                        <li><a href="evacuees.php" class="sidebar-link"><i class="fas fa-people-arrows"></i> <span>Evacuees</span> <?php if($_badgeEvacuees > 0): ?><span class="sidebar-badge"><?php echo number_format($_badgeEvacuees); ?></span><?php endif; ?></a></li>
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
                    <form method="GET" action="" id="filterForm" class="filter-bar">
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Search</label>
                            <input type="text" name="search" class="filter-input" placeholder="Name or email..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%;">
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-tag"></i> Role</label>
                            <select name="role_filter" class="filter-select" style="width:100%;">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="coordinator" <?php echo $roleFilter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                                <option value="mswdo" <?php echo $roleFilter === 'mswdo' ? 'selected' : ''; ?>>MSWDO</option>
                                <option value="barangay" <?php echo $roleFilter === 'barangay' ? 'selected' : ''; ?>>Barangay</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label><i class="fas fa-circle"></i> Status</label>
                            <select name="status_filter" class="filter-select" style="width:100%;">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-search"><i class="fas fa-filter"></i> Apply</button>
                            <a href="users.php" class="btn-outline"><i class="fas fa-undo-alt"></i> Reset</a>
                        </div>
                    </form>

                    <?php if (empty($filteredUsers) && $totalRows == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users match your filters.</p>
                            <a href="users.php" class="btn-primary">Clear Filters</a>
                        </div>
                    <?php elseif (empty($filteredUsers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users found.</p>
                            <a href="create_coordinator.php" class="btn-primary">Add Your First User</a>
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
                                    <?php foreach ($filteredUsers as $u): 
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
                                                <button onclick="openModal(<?php echo (int)$u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>', '<?php echo $u['is_active']; ?>')" class="action-btn">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINATION (at the bottom of the user list) -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                <i class="fas fa-table-list"></i> Showing <?php echo $offset + 1; ?> – <?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> users
                                <?php if (!empty($search) || !empty($roleFilter) || !empty($statusFilter)): ?>
                                    <span style="color:#D32F2F;">(filtered)</span>
                                <?php endif; ?>
                            </div>
                            <ul class="pagination">
                                <!-- Prev -->
                                <li class="<?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                                    <?php else: ?>
                                        <span><i class="fas fa-chevron-left"></i> Prev</span>
                                    <?php endif; ?>
                                </li>
                                <!-- Page numbers -->
                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                if ($start > 1) echo '<li><a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                if ($start > 2) echo '<li class="disabled"><span>...</span></li>';
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor;
                                if ($end < $totalPages - 1) echo '<li class="disabled"><span>...</span></li>';
                                if ($end < $totalPages) echo '<li><a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '">' . $totalPages . '</a></li>';
                                ?>
                                <!-- Next -->
                                <li class="<?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next <i class="fas fa-chevron-right"></i></a>
                                    <?php else: ?>
                                        <span>Next <i class="fas fa-chevron-right"></i></span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
        <div class="card" style="width:100%; max-width:480px; margin:20px; position:relative;">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
                <h3 style="font-size:16px; font-weight:700; color:#2C3E50;">Edit User</h3>
                <button onclick="closeModal()" style="background:none; border:none; font-size:20px; color:#95A5A6; cursor:pointer;">&times;</button>
            </div>

            <form id="editForm">
                <input type="hidden" id="editId">

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:600; color:#5D6D7E; display:block; margin-bottom:6px;">Full Name</label>
                    <input type="text" id="editName" class="filter-input" style="width:100%;" required>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:600; color:#5D6D7E; display:block; margin-bottom:6px;">Email</label>
                    <input type="email" id="editEmail" class="filter-input" style="width:100%;" required>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:600; color:#5D6D7E; display:block; margin-bottom:6px;">Role</label>
                    <select id="editRole" class="filter-select" style="width:100%;">
                        <option value="citizen">Citizen</option>
                        <option value="coordinator">Coordinator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-size:12px; font-weight:600; color:#5D6D7E; display:block; margin-bottom:6px;">Status</label>
                    <select id="editActive" class="filter-select" style="width:100%;">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="font-size:12px; font-weight:600; color:#5D6D7E; display:block; margin-bottom:6px;">New Password <span style="color:#95A5A6; font-weight:400;">(leave blank to keep current)</span></label>
                    <input type="password" id="editPassword" class="filter-input" style="width:100%;" placeholder="••••••••">
                </div>

                <div id="editMsg" style="display:none; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px;"></div>

                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
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

        // Modal edit
        function openModal(id, name, email, role, isActive) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editEmail').value = email;
            document.getElementById('editRole').value = role;
            document.getElementById('editActive').value = isActive;
            document.getElementById('editPassword').value = '';
            document.getElementById('editMsg').style.display = 'none';
            const modal = document.getElementById('editModal');
            modal.style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const msg = document.getElementById('editMsg');
            msg.style.display = 'none';

            const body = new FormData();
            body.append('id',       document.getElementById('editId').value);
            body.append('full_name',document.getElementById('editName').value);
            body.append('email',    document.getElementById('editEmail').value);
            body.append('role',     document.getElementById('editRole').value);
            body.append('is_active',document.getElementById('editActive').value);
            const pw = document.getElementById('editPassword').value;
            if (pw) body.append('password', pw);

            fetch('edit_user.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    msg.style.display = 'block';
                    if (data.ok) {
                        msg.style.background = '#E8F5E9';
                        msg.style.color = '#2E7D32';
                        msg.textContent = '✓ User updated successfully.';
                        setTimeout(() => { 
                            closeModal(); 
                            window.location.reload(); 
                        }, 1200);
                    } else {
                        msg.style.background = '#FFEBEE';
                        msg.style.color = '#D32F2F';
                        msg.textContent = '✗ ' + (data.error || 'Something went wrong.');
                    }
                })
                .catch(() => {
                    msg.style.display = 'block';
                    msg.style.background = '#FFEBEE';
                    msg.style.color = '#D32F2F';
                    msg.textContent = '✗ Network error.';
                });
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>