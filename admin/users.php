<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role,
           u.is_active, u.is_email_verified,
           b.name AS barangay_name
    FROM users u
    JOIN barangays b ON b.id = u.barangay_id
    ORDER BY u.id DESC
")->fetchAll();

// Get current user for display
$user = current_user();
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
                        <li><a href="users.php" class="sidebar-link active"><i class="fas fa-users"></i> <span>User Management</span> <span class="sidebar-badge"><?php echo count($users); ?></span></a></li>
                        <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i> <span>Disasters</span></a></li>
                    </ul>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Operations</div>
                    <ul class="sidebar-menu">
                        <!-- <li><a href="assistance.php" class="sidebar-link"><i class="fas fa-hand-holding-heart"></i> <span>Assistance</span> </a></li> -->
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
                <?php
                $totalUsers = count($users);
                $adminCount = 0;
                $coordinatorCount = 0;
                $mswdoCount = 0;
                $barangayCount = 0;
                $verifiedCount = 0;
                $activeCount = 0;
                
                foreach ($users as $u) {
                    if ($u['role'] === 'admin') $adminCount++;
                    else if ($u['role'] === 'coordinator') $coordinatorCount++;
                    else if ($u['role'] === 'mswdo') $mswdoCount++;
                    else if ($u['role'] === 'barangay') $barangayCount++;
                    
                    if ($u['is_email_verified']) $verifiedCount++;
                    if ($u['is_active']) $activeCount++;
                }
                ?>
                
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
                    <div class="filter-bar">
                        <input type="text" class="filter-input" placeholder="Search users..." id="searchInput">
                        <select class="filter-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="mswdo">MSWDO</option>
                            <option value="barangay">Barangay</option>
                        </select>
                        <select class="filter-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <?php if (!$users): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users found.</p>
                            <a href="create_coordinator.php" class="btn-primary">
                                <i class="fas fa-user-plus"></i> Add Your First User
                            </a>
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
                                    <?php foreach ($users as $u): 
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

        // Simple search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                if (name.includes(searchText) || email.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });

        // Role filter
        document.getElementById('roleFilter').addEventListener('change', function() {
            const role = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                if (!role) {
                    row.style.display = '';
                    continue;
                }
                const rowRole = row.cells[3].textContent.trim().toLowerCase();
                if (rowRole === role) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
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
                    setTimeout(() => { closeModal(); location.reload(); }, 1200);
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

    // Close modal on backdrop click
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    </script>
</body>
</html>