<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$user = current_user();
$pdo  = db();

// --- Summary Metrics ---
$totalEvacuees = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
$totalFamilies = (int)$pdo->query("SELECT COUNT(*) FROM evac_registrations")->fetchColumn();
$totalCenters  = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();

$demo = $pdo->query("
    SELECT
        COALESCE(SUM(adults),0)   AS grand_adults,
        COALESCE(SUM(children),0) AS grand_children,
        COALESCE(SUM(seniors),0)  AS grand_seniors,
        COALESCE(SUM(pwds),0)     AS grand_pwds
    FROM evac_registrations
")->fetch();

$evacSummary = $pdo->query("
    SELECT
        ec.id,
        ec.name          AS center_name,
        b.name           AS barangay_name,
        ec.status,
        ec.max_capacity_people,
        ec.max_capacity_families,
        u.full_name        AS coordinator_name,
        u.contact_number   AS coordinator_contact,
        COALESCE(SUM(er.adults),0)        AS total_adults,
        COALESCE(SUM(er.children),0)      AS total_children,
        COALESCE(SUM(er.seniors),0)       AS total_seniors,
        COALESCE(SUM(er.pwds),0)          AS total_pwds,
        COALESCE(SUM(er.total_members),0) AS total_evacuees,
        COUNT(DISTINCT er.id)             AS total_families
    FROM evacuation_centers ec
    LEFT JOIN barangays b            ON b.id  = ec.barangay_id
    LEFT JOIN users u                ON u.id  = ec.coordinator_user_id
    LEFT JOIN evac_registrations er  ON er.center_id = ec.id
    GROUP BY ec.id
    ORDER BY
        CASE ec.status WHEN 'closed' THEN 1 ELSE 0 END ASC,
        total_evacuees DESC
")->fetchAll();

$barangaySummary = $pdo->query("
    SELECT
        b.name AS barangay_name,
        COALESCE(SUM(er.adults),0)        AS total_adults,
        COALESCE(SUM(er.children),0)      AS total_children,
        COALESCE(SUM(er.seniors),0)       AS total_seniors,
        COALESCE(SUM(er.pwds),0)          AS total_pwds,
        COALESCE(SUM(er.total_members),0) AS total_evacuees,
        COUNT(DISTINCT er.id)             AS total_families
    FROM evac_registrations er
    JOIN barangays b ON b.id = er.barangay_id
    GROUP BY b.id
    ORDER BY total_evacuees DESC
")->fetchAll();

$recentRegs = $pdo->query("
    SELECT
        er.id,
        er.family_head_name,
        er.adults, er.children, er.seniors, er.pwds, er.total_members,
        er.created_at,
        ec.name   AS center_name,
        b.name    AS barangay_name,
        u.full_name AS registered_by
    FROM evac_registrations er
    JOIN evacuation_centers ec ON ec.id = er.center_id
    JOIN barangays b           ON b.id  = er.barangay_id
    JOIN users u               ON u.id  = er.created_by
    ORDER BY er.created_at DESC
    LIMIT 20
")->fetchAll();

$archiveBatches = $pdo->query("
    SELECT
        era.archive_label,
        era.disaster_id,
        era.archived_at,
        era.archived_by,
        COUNT(*)              AS total_families,
        SUM(era.total_members)    AS total_evacuees,
        SUM(era.adults)           AS total_adults,
        SUM(era.children)         AS total_children,
        SUM(era.seniors)          AS total_seniors,
        SUM(era.pwds)             AS total_pwds,
        u.full_name           AS archived_by_name
    FROM evac_registrations_archive era
    LEFT JOIN users u ON u.id = era.archived_by
    GROUP BY era.archive_label, era.disaster_id, DATE(era.archived_at), era.archived_by
    ORDER BY era.archived_at DESC
")->fetchAll();

$disasters = $pdo->query("
    SELECT id, title, type, level FROM disasters
    ORDER BY status = 'ongoing' DESC, started_at DESC
")->fetchAll();

$grandChildren = array_sum(array_column($evacSummary, 'total_children'));
$grandAdults   = array_sum(array_column($evacSummary, 'total_adults'));
$grandSeniors  = array_sum(array_column($evacSummary, 'total_seniors'));
$grandPwds     = array_sum(array_column($evacSummary, 'total_pwds'));
$grandFamilies = array_sum(array_column($evacSummary, 'total_families'));
$grandTotal    = array_sum(array_column($evacSummary, 'total_evacuees'));
$grandCap      = array_sum(array_column($evacSummary, 'max_capacity_people'));

$_badgeCenters       = (int)$pdo->query("SELECT COUNT(*) FROM evacuation_centers")->fetchColumn();
$_badgeOngoing       = (int)$pdo->query("SELECT COUNT(*) FROM disasters WHERE status = 'ongoing'")->fetchColumn();
$_badgeAnnouncements = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$_badgeEvacuees      = (int)$pdo->query("SELECT COALESCE(SUM(total_members),0) FROM evac_registrations")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evacuees | MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../asset/css/admin_evacuees.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body>
<div class="app-wrapper">

    <div class="sidebar-toggle-btn" id="sidebarToggleBtn">
        <i class="fas fa-chevron-left"></i>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-image">
                    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRqukasrXgrajWG753eZaSE0F17M3XFWroASQ&s" alt="MDRRMO"
                         onerror="this.style.display='none';this.parentElement.innerHTML='<span style=color:white;font-weight:700;font-size:18px>M</span>'">
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
                    <li><a href="index.php"     class="sidebar-link"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
                    <li><a href="centers.php"   class="sidebar-link"><i class="fas fa-map-marker-alt"></i><span>Evacuation Centers</span><?php if($_badgeCenters > 0): ?><span class="sidebar-badge"><?php echo $_badgeCenters; ?></span><?php endif; ?></a></li>
                    <li><a href="users.php"     class="sidebar-link"><i class="fas fa-users"></i><span>User Management</span></a></li>
                    <li><a href="disasters.php" class="sidebar-link"><i class="fas fa-exclamation-triangle"></i><span>Disasters</span><?php if($_badgeOngoing > 0): ?><span class="sidebar-badge"><?php echo $_badgeOngoing; ?></span><?php endif; ?></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Operations</div>
                <ul class="sidebar-menu">
                    <li><a href="announcements.php" class="sidebar-link"><i class="fas fa-bullhorn"></i><span>Announcements</span><?php if($_badgeAnnouncements > 0): ?><span class="sidebar-badge"><?php echo $_badgeAnnouncements; ?></span><?php endif; ?></a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Monitoring</div>
                <ul class="sidebar-menu">
                    <li><a href="maps.php"     class="sidebar-link"><i class="fas fa-map"></i><span>Maps</span></a></li>
                    <li><a href="evacuees.php" class="sidebar-link active">
                        <i class="fas fa-people-arrows"></i><span>Evacuees</span>
                        <?php if($_badgeEvacuees > 0): ?><span class="sidebar-badge"><?php echo $_badgeEvacuees; ?></span><?php endif; ?>
                    </a></li>
                </ul>
            </div>
            <div class="sidebar-section">
                <div class="sidebar-section-title">Settings</div>
                <ul class="sidebar-menu">
                    <li><a href="../pages/logout.php" class="sidebar-link"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="top-nav">
            <div class="page-title">
                <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
                <h1>Evacuees</h1>
            </div>
            <div class="user-menu">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'] ?? 'A', 0, 1)); ?></div>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?></div>
                        <div class="user-role">MDRRMO Administrator</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard">

            <!-- Flash Messages -->
            <?php if (isset($_GET['archived'])): ?>
            <div class="flash flash-success">
                <i class="fas fa-check-circle"></i>
                Successfully archived <strong><?php echo (int)$_GET['archived']; ?></strong> registration(s)
                under <strong><?php echo htmlspecialchars($_GET['label'] ?? ''); ?></strong>.
                All centers have been reset to Available.
            </div>
            <?php elseif (isset($_GET['error'])): ?>
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php
                    $errors = [
                        'label_required'      => 'Archive label is required.',
                        'archive_failed'      => 'Archive failed. Please try again or check the server logs.',
                        'nothing_to_archive'  => 'There are no active registrations to archive.',
                    ];
                    echo $errors[$_GET['error']] ?? 'An unknown error occurred.';
                ?>
            </div>
            <?php endif; ?>

            <!-- Welcome Bar -->
            <div class="welcome-bar">
                <div class="welcome-text">
                    <h2>Evacuee Management</h2>
                    <p>
                        <i class="fas fa-map-marker-alt" style="color:var(--primary-red)"></i>
                        San Ildefonso, Bulacan — All registered evacuees
                        <span class="date-badge"><i class="far fa-calendar"></i><?php echo date('F j, Y'); ?></span>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <a href="maps.php" style="text-decoration:none">
                        <span class="badge blue" style="padding:6px 14px;cursor:pointer"><i class="fas fa-map"></i> View Map</span>
                    </a>
                    <a href="centers.php" style="text-decoration:none">
                        <span class="badge" style="padding:6px 14px;cursor:pointer"><i class="fas fa-list"></i> Centers</span>
                    </a>
                    <?php if ($totalEvacuees > 0): ?>
                    <button type="button" onclick="openArchiveModal()"
                            style="padding:8px 16px;background:#C0392B;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-archive"></i> Archive &amp; Reset
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon-small"><i class="fas fa-people-arrows"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($totalEvacuees); ?></div>
                        <div class="stat-label-small">Total Evacuees</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small blue"><i class="fas fa-home"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($totalFamilies); ?></div>
                        <div class="stat-label-small">Families</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small green"><i class="fas fa-user"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_adults']); ?></div>
                        <div class="stat-label-small">Adults</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small teal"><i class="fas fa-child"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_children']); ?></div>
                        <div class="stat-label-small">Children</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small purple"><i class="fas fa-user-clock"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_seniors']); ?></div>
                        <div class="stat-label-small">Seniors</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-small yellow"><i class="fas fa-wheelchair"></i></div>
                    <div class="stat-content">
                        <div class="stat-value-small"><?php echo number_format($demo['grand_pwds']); ?></div>
                        <div class="stat-label-small">PWDs</div>
                    </div>
                </div>
            </div>

            <!-- Capacity Overview -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Overall Capacity Overview</h3>
                    <span class="badge green"><?php echo $grandCap > 0 ? round(($grandTotal/$grandCap)*100) : 0; ?>% Occupied</span>
                </div>
                <div class="overview-grid">
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--primary-red)"><?php echo number_format($grandTotal); ?></div>
                        <div class="overview-box-lbl">Total Evacuees</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--map-blue)"><?php echo number_format($grandCap); ?></div>
                        <div class="overview-box-lbl">Total Capacity</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:var(--map-green)"><?php echo number_format(max(0,$grandCap-$grandTotal)); ?></div>
                        <div class="overview-box-lbl">Available Slots</div>
                    </div>
                    <div class="overview-box">
                        <div class="overview-box-val" style="color:#8E44AD"><?php echo number_format($grandFamilies); ?></div>
                        <div class="overview-box-lbl">Total Families</div>
                    </div>
                </div>
            </div>

            <!-- Centers Summary Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Evacuation Centers Summary</h3>
                    <span class="badge"><?php echo count($evacSummary); ?> Centers</span>
                </div>

                <div class="filter-bar">
                    <input type="text" id="centerSearch" placeholder="Search center or barangay…">
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="near_capacity">Near Capacity</option>
                        <option value="full">Full</option>
                        <option value="temp_shelter">Temp Shelter</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <?php if (empty($evacSummary)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No evacuation registrations recorded yet.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table" id="centersTable">
                        <thead>
                            <tr>
                                <th>Center</th>
                                <th>Coordinator</th>
                                <th>Children</th>
                                <th>Adults</th>
                                <th>Seniors</th>
                                <th>PWD</th>
                                <th>Families</th>
                                <th>Total</th>
                                <th>Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($evacSummary as $row):
                            $isClosed = ($row['status'] === 'closed');
                            $pct = $row['max_capacity_people'] > 0
                                ? min(round(($row['total_evacuees'] / $row['max_capacity_people']) * 100), 100) : 0;
                            $barColor = '#2E7D32'; $statusLabel = 'Available'; $statusClass = 'st-available';
                            if ($isClosed)                           { $barColor='#bbb';    $statusLabel='Closed';   $statusClass='st-closed'; }
                            elseif ($row['status']==='near_capacity'){ $barColor='#FFC107'; $statusLabel='Near Cap'; $statusClass='st-near'; }
                            elseif ($row['status']==='full')         { $barColor='#D32F2F'; $statusLabel='Full';     $statusClass='st-full'; }
                            elseif ($row['status']==='temp_shelter') { $barColor='#3498DB'; $statusLabel='Temp';     $statusClass='st-temp'; }
                        ?>
                        <tr data-status="<?php echo $row['status']; ?>" class="<?php echo $isClosed ? 'row-closed' : ''; ?>">
                            <td>
                                <div class="center-name"><?php echo htmlspecialchars($row['center_name']); ?></div>
                                <div class="center-brgy"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['barangay_name']); ?></div>
                                <?php if ($isClosed): ?>
                                    <span class="chip-closed">Closed — not accepting evacuees</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['coordinator_name']): ?>
                                    <div class="coord-name"><?php echo htmlspecialchars($row['coordinator_name']); ?></div>
                                    <div class="coord-contact"><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($row['coordinator_contact'] ?? '—'); ?></div>
                                <?php else: ?>
                                    <span style="color:#95A5A6;font-style:italic;font-size:12px">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="chip <?php echo $isClosed ? '' : 'chip-child'; ?>"><?php echo number_format($row['total_children']); ?></span></td>
                            <td><span class="chip <?php echo $isClosed ? '' : 'chip-adult'; ?>"><?php echo number_format($row['total_adults']); ?></span></td>
                            <td><span class="chip <?php echo $isClosed ? '' : 'chip-senior'; ?>"><?php echo number_format($row['total_seniors']); ?></span></td>
                            <td><span class="chip <?php echo $isClosed ? '' : 'chip-pwd'; ?>"><?php echo number_format($row['total_pwds']); ?></span></td>
                            <td><strong><?php echo number_format($row['total_families']); ?></strong></td>
                            <td><span class="chip <?php echo $isClosed ? '' : 'chip-total'; ?>"><?php echo number_format($row['total_evacuees']); ?></span></td>
                            <td>
                                <div class="cap-wrap">
                                    <div class="cap-bar">
                                        <div class="cap-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>"></div>
                                    </div>
                                    <div class="cap-text"><?php echo number_format($row['total_evacuees']); ?> / <?php echo number_format($row['max_capacity_people']); ?> (<?php echo $pct; ?>%)</div>
                                </div>
                            </td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="color:#95A5A6;font-size:11px;text-transform:uppercase;letter-spacing:.5px">Totals</td>
                                <td><span class="chip chip-child"><?php echo number_format($grandChildren); ?></span></td>
                                <td><span class="chip chip-adult"><?php echo number_format($grandAdults); ?></span></td>
                                <td><span class="chip chip-senior"><?php echo number_format($grandSeniors); ?></span></td>
                                <td><span class="chip chip-pwd"><?php echo number_format($grandPwds); ?></span></td>
                                <td><strong><?php echo number_format($grandFamilies); ?></strong></td>
                                <td><span class="chip chip-total"><?php echo number_format($grandTotal); ?></span></td>
                                <td colspan="2">
                                    <strong><?php echo number_format($grandTotal); ?></strong> / <?php echo number_format($grandCap); ?>
                                    <span style="color:#95A5A6;font-size:11px">(<?php echo $grandCap>0?round(($grandTotal/$grandCap)*100):0; ?>%)</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Barangay Breakdown -->
            <?php if (!empty($barangaySummary)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marked-alt"></i> By Barangay of Origin</h3>
                    <span class="badge blue"><?php echo count($barangaySummary); ?> Barangays</span>
                </div>
                <div class="brgy-grid">
                    <?php foreach ($barangaySummary as $brgy): ?>
                    <div class="brgy-card">
                        <div class="brgy-name"><i class="fas fa-location-dot"></i><?php echo htmlspecialchars($brgy['barangay_name']); ?></div>
                        <div class="brgy-demos">
                            <span class="chip chip-child"><?php echo $brgy['total_children']; ?> C</span>
                            <span class="chip chip-adult"><?php echo $brgy['total_adults']; ?> A</span>
                            <span class="chip chip-senior"><?php echo $brgy['total_seniors']; ?> S</span>
                            <span class="chip chip-pwd"><?php echo $brgy['total_pwds']; ?> P</span>
                        </div>
                        <div class="brgy-total"><?php echo number_format($brgy['total_evacuees']); ?></div>
                        <div class="brgy-sublbl"><?php echo number_format($brgy['total_families']); ?> families</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Registrations -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Recent Registrations</h3>
                    <span class="badge"><?php echo count($recentRegs); ?> Records</span>
                </div>
                <?php if (empty($recentRegs)): ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>No registrations yet.</p></div>
                <?php else: ?>
                <div class="filter-bar" style="margin-bottom:0;padding-bottom:16px;border-bottom:1px solid #F0F0F0">
                    <input type="text" id="recentSearch" placeholder="Search by name, center or barangay…" style="min-width:280px">
                </div>
                <div class="table-wrap" style="margin-top:16px">
                    <table class="data-table" id="recentTable">
                        <thead>
                            <tr>
                                <th>Family Head</th>
                                <th>Evacuation Center</th>
                                <th>Barangay</th>
                                <th>C</th><th>A</th><th>S</th><th>P</th>
                                <th>Total</th>
                                <th>Registered By</th>
                                <th>Date / Time</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentRegs as $reg): ?>
                        <tr>
                            <td>
                                <div class="family-cell">
                                    <div class="family-avatar"><?php echo strtoupper(substr($reg['family_head_name'],0,1)); ?></div>
                                    <div>
                                        <div class="family-name"><?php echo htmlspecialchars($reg['family_head_name']); ?></div>
                                        <div class="family-sub">ID #<?php echo $reg['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12.5px"><?php echo htmlspecialchars($reg['center_name']); ?></td>
                            <td style="font-size:12.5px"><?php echo htmlspecialchars($reg['barangay_name']); ?></td>
                            <td><span class="chip chip-child"><?php echo $reg['children']; ?></span></td>
                            <td><span class="chip chip-adult"><?php echo $reg['adults']; ?></span></td>
                            <td><span class="chip chip-senior"><?php echo $reg['seniors']; ?></span></td>
                            <td><span class="chip chip-pwd"><?php echo $reg['pwds']; ?></span></td>
                            <td><span class="chip chip-total"><?php echo $reg['total_members']; ?></span></td>
                            <td style="font-size:12px;color:#95A5A6"><?php echo htmlspecialchars($reg['registered_by']); ?></td>
                            <td style="font-size:11.5px;color:#95A5A6;white-space:nowrap">
                                <?php echo date('M j, Y', strtotime($reg['created_at'])); ?><br>
                                <span style="font-size:10.5px"><?php echo date('g:i A', strtotime($reg['created_at'])); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Archive History -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-archive"></i> Archive History</h3>
                    <div style="display:flex;gap:8px;align-items:center">
                        <span class="badge"><?php echo count($archiveBatches); ?> Batches</span>
                        <?php if (!empty($archiveBatches)): ?>
                        <?php /* FIX: Use a plain button + JS to open print tab, NOT an <a> tag
                                  that could bleed into surrounding elements / the modal below */ ?>
                        <button type="button" onclick="window.open('print_archive.php','_blank')"
                                style="padding:6px 13px;background:#1A5276;color:#fff;border:none;border-radius:7px;
                                       font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;
                                       align-items:center;gap:5px">
                            <i class="fas fa-print"></i> Print All
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($archiveBatches)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No archives yet. Use "Archive &amp; Reset" after a disaster to save records here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($archiveBatches as $batch): ?>
                    <div class="archive-batch">
                        <div>
                            <div class="archive-batch-label">
                                <i class="fas fa-folder" style="color:#C0392B;margin-right:6px"></i>
                                <?php echo htmlspecialchars($batch['archive_label']); ?>
                            </div>
                            <div class="archive-batch-meta">
                                Archived <?php echo date('F j, Y g:i A', strtotime($batch['archived_at'])); ?>
                                by <?php echo htmlspecialchars($batch['archived_by_name'] ?? 'Admin'); ?>
                            </div>
                            <div class="archive-batch-demos" style="margin-top:8px">
                                <span class="chip chip-child"><?php echo number_format($batch['total_children']); ?> C</span>
                                <span class="chip chip-adult"><?php echo number_format($batch['total_adults']); ?> A</span>
                                <span class="chip chip-senior"><?php echo number_format($batch['total_seniors']); ?> S</span>
                                <span class="chip chip-pwd"><?php echo number_format($batch['total_pwds']); ?> P</span>
                                <span style="font-size:12px;color:#888"><?php echo number_format($batch['total_families']); ?> families</span>
                            </div>
                        </div>
                        <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:8px">
                            <div>
                                <div class="archive-total"><?php echo number_format($batch['total_evacuees']); ?></div>
                                <div class="archive-total-lbl">Evacuees</div>
                            </div>
                            <?php /* FIX: plain button + JS instead of <a> tag */ ?>
                            <button type="button"
                                    onclick="window.open('print_archive.php?label=<?php echo urlencode($batch['archive_label']); ?>','_blank')"
                                    style="padding:5px 12px;background:#EBF5FB;color:#1A5276;border:1px solid #AED6F1;
                                           border-radius:6px;font-size:11.5px;font-weight:600;cursor:pointer;
                                           display:inline-flex;align-items:center;gap:5px">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /dashboard -->
    </main>
</div><!-- /app-wrapper -->

<!-- ══════════════════════════════════════════
     ARCHIVE MODAL
     IMPORTANT: This is OUTSIDE .app-wrapper intentionally
     so it can never be a child of any <a> tag above.
     The form uses id="archiveForm" and submits via JS
     to guarantee the POST goes to archive_evacuees.php.
══════════════════════════════════════════ -->
<div class="modal-overlay" id="archiveModal" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.5);align-items:center;justify-content:center">
    <div class="modal-box">
        <h3><i class="fas fa-archive" style="color:#C0392B"></i> Archive &amp; Reset Evacuees</h3>
        <p>This will move all current registrations to the archive and reset all evacuation centers to <strong>Available</strong>.</p>

        <div class="modal-warning">
            <i class="fas fa-exclamation-triangle" style="margin-top:2px"></i>
            <span>This action cannot be undone. Make sure the disaster event has ended before archiving.</span>
        </div>

        <!-- FIX: id on form, explicit method/action, no wrapping anchors anywhere near here -->
        <form id="archiveForm" method="POST" action="archive_evacuees.php">
            <label for="archive_label">Archive Label <span style="color:#C0392B">*</span></label>
            <input type="text" id="archive_label" name="archive_label"
                   placeholder="e.g. Typhoon Bagyong Nonoy – March 2026" required>

            <label for="disaster_id">Link to Disaster (optional)</label>
            <select name="disaster_id" id="disaster_id">
                <option value="">— None —</option>
                <?php foreach ($disasters as $d): ?>
                <option value="<?php echo (int)$d['id']; ?>">
                    <?php echo htmlspecialchars(ucfirst($d['type']) . ' – ' . $d['title'] . ' (Signal ' . $d['level'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeArchiveModal()">
                    Cancel
                </button>
                <button type="button" class="btn-archive" onclick="submitArchiveForm()">
                    <i class="fas fa-archive"></i> Archive Now
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Sidebar toggle ────────────────────────────────────────────────
const sidebar      = document.getElementById('sidebar');
const mainContent  = document.getElementById('mainContent');
const toggleBtn    = document.getElementById('sidebarToggleBtn');
const mobileToggle = document.getElementById('mobileToggle');

toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');
    toggleBtn.classList.toggle('collapsed');
    const icon = toggleBtn.querySelector('i');
    icon.className = sidebar.classList.contains('collapsed')
        ? 'fas fa-chevron-right'
        : 'fas fa-chevron-left';
});
mobileToggle.addEventListener('click', () => sidebar.classList.toggle('show'));

// ── Archive modal ─────────────────────────────────────────────────
const archiveModal = document.getElementById('archiveModal');

function openArchiveModal() {
    archiveModal.style.display = 'flex';
}

function closeArchiveModal() {
    archiveModal.style.display = 'none';
}

function submitArchiveForm() {
    const form  = document.getElementById('archiveForm');
    const label = document.getElementById('archive_label').value.trim();

    if (!label) {
        document.getElementById('archive_label').focus();
        return;
    }

    // Explicitly POST to archive_evacuees.php — bypasses any browser quirks
    form.method = 'POST';
    form.action = 'archive_evacuees.php';
    form.submit();
}

// Close modal when clicking the dark overlay background
archiveModal.addEventListener('click', function(e) {
    if (e.target === this) closeArchiveModal();
});

// ── Centers table filter ──────────────────────────────────────────
const searchInput  = document.getElementById('centerSearch');
const statusFilter = document.getElementById('statusFilter');
const tableBody    = document.querySelector('#centersTable tbody');

function filterTable() {
    const q  = searchInput.value.toLowerCase();
    const st = statusFilter.value;
    tableBody.querySelectorAll('tr').forEach(row => {
        const matchQ  = q  === '' || row.textContent.toLowerCase().includes(q);
        const matchSt = st === '' || row.dataset.status === st;
        row.style.display = (matchQ && matchSt) ? '' : 'none';
    });
}
searchInput.addEventListener('input', filterTable);
statusFilter.addEventListener('change', filterTable);

// ── Recent registrations search ───────────────────────────────────
const recentSearch = document.getElementById('recentSearch');
if (recentSearch) {
    const recentBody = document.querySelector('#recentTable tbody');
    recentSearch.addEventListener('input', () => {
        const q = recentSearch.value.toLowerCase();
        recentBody.querySelectorAll('tr').forEach(row => {
            row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
</script>
</body>
</html>