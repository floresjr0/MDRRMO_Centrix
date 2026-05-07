<?php
require_once __DIR__ . '/../pages/session.php';
require_login('coordinator');

$pdo  = db();
$user = current_user();

// ── Assigned centers with expected-evacuee counts ─────────────────────────
$stmt = $pdo->prepare("
    SELECT
        c.*,
        b.name AS barangay_name,
        COALESCE(t.expected_count, 0) AS expected_count
    FROM evacuation_centers c
    JOIN barangays b ON b.id = c.barangay_id
    LEFT JOIN (
        SELECT
            nt.center_id,
            SUM(COALESCE(ch.total_members, 1)) AS expected_count
        FROM   evac_navigation_tracking nt
        LEFT JOIN citizen_household ch ON ch.user_id = nt.user_id
        WHERE  nt.status = 'navigating'
        GROUP  BY nt.center_id
    ) t ON t.center_id = c.id
    WHERE c.coordinator_user_id = ?
");
$stmt->execute([$user['id']]);
$centers = $stmt->fetchAll();

// Get first center ID for bottom nav links (if any)
$firstCenterId = !empty($centers) ? (int)$centers[0]['id'] : 0;

// ── Per-center breakdown: barangay origin of navigating citizens ───────────
$breakdownStmt = $pdo->prepare("
    SELECT
        nt.center_id,
        b.name  AS barangay_name,
        SUM(COALESCE(ch.total_members, 1)) AS citizen_count
    FROM   evac_navigation_tracking nt
    JOIN   users u  ON u.id  = nt.user_id
    JOIN   barangays b ON b.id = u.barangay_id
    LEFT JOIN citizen_household ch ON ch.user_id = nt.user_id
    WHERE  nt.status = 'navigating'
      AND  nt.center_id IN (
               SELECT id FROM evacuation_centers WHERE coordinator_user_id = ?
           )
    GROUP  BY nt.center_id, u.barangay_id
    ORDER  BY citizen_count DESC
");
$breakdownStmt->execute([$user['id']]);
$breakdownRows = $breakdownStmt->fetchAll();

$breakdown = [];
foreach ($breakdownRows as $row) {
    $breakdown[(int)$row['center_id']][] = $row;
}

$totalExpected  = array_sum(array_column($centers, 'expected_count'));
$totalCenters   = count($centers);
$activeCenters  = count(array_filter($centers, fn($c) => $c['status'] !== 'closed'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coordinator Dashboard - MDRRMO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- External CSS (separated) -->
    <link rel="stylesheet" href="../asset/css/coordinator_index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<!-- Overlay for drawer -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeMenu()"></div>

<div class="layout">

    <!-- SIDEBAR DRAWER -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand-row">
                <div class="brand-logo-sm">
                    <img src="../img/mdrrmo.png" alt="MDRRMO Logo"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                </div>
                <div>
                    <div class="brand-name-sm">MDRRMO</div>
                    <div class="brand-tagline-sm">#BidaAngLagingHanda</div>
                </div>
            </div>
            <button class="sidebar-close" onclick="closeMenu()" aria-label="Close menu">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'], 0, 1))); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="user-role">Coordinator</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>
            <a href="#" class="nav-item active">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
            </a>
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span> Walk-in</a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span> Arrivals</a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span> Registrations</a>
        </nav>

        <div class="sidebar-status">
            <span class="status-dot-green"></span>
            SYSTEM ONLINE
        </div>

        <!-- SIDEBAR FOOTER WITH LOGOUT + LOGOS + COPYRIGHT -->
        <div class="sidebar-footer">
            <a href="../pages/logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </a>

            <!-- Collaboration logos (replace with actual partner images) -->
            <div class="sidebar-collab">
                <img src="../img/mdrrmo.png" alt="Partner 2">
                <img src="../img/basc.png" alt="Partner 1">
                <img src="../img/ics.jpg" alt="Partner 3">
            </div>

            <!-- Copyright text -->
            <div class="sidebar-copy">
                © 2026 MDRRMOxBASC_ICS. All rights reserved.
            </div>
        </div>
    </aside>

    <!-- BOTTOM NAVIGATION – 4 items (Logout removed) -->
    <nav class="bottom-nav">
        <div class="bottom-nav-inner">
            <a href="index.php" class="bottom-nav-item active">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>
                Dashboard
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="<?php echo $firstCenterId ? 'center_app_arrivals.php?id=' . $firstCenterId : '#'; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span>
                App Arrivals
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="<?php echo $firstCenterId ? 'center_walkin.php?id=' . $firstCenterId : '#'; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                Walk-in
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="<?php echo $firstCenterId ? 'center_registrations.php?id=' . $firstCenterId : '#'; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                Registrations
                <span class="bottom-nav-dot"></span>
            </a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- Top bar -->
        <header class="topbar">
            <div class="topbar-brand">
                <div class="topbar-logo" aria-hidden="true">
                    <img src="../img/mdrrmo.png" alt="MDRRMO Logo"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                </div>
                <div class="topbar-brand-text">
                    <div class="topbar-title">Coordinator Dashboard</div>
                    <div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div>
                </div>
            </div>
            <div class="topbar-right">
                <span class="topbar-date" id="topbar-clock"></span>
                <button class="refresh-btn" id="refreshBtn" onclick="refreshCounts()">
                    <span class="spin-icon" id="spinIcon">⟳</span>
                    <span>Refresh</span>
                </button>
                <button class="hamburger-btn" onclick="openMenu()" aria-label="Open menu">
                    <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </header>

        <!-- Page content -->
        <main class="page">
            <h1 class="page-heading">Your <span>Assigned Centers</span></h1>

            <!-- SUMMARY STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg></div>
                    <div><div class="stat-val"><?php echo $totalCenters; ?></div><div class="stat-label">Assigned Centers</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="4" r="2"/><path d="M10 9h4l1 5-3 1v5"/><path d="M10 9l-1 5 3 1"/></svg></div>
                    <div><div class="stat-val" id="total-expected"><?php echo $totalExpected; ?></div><div class="stat-label">Expected Evacuees (en route, incl. households)</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                    <div><div class="stat-val"><?php echo $activeCenters; ?></div><div class="stat-label">Active / Open Centers</div></div>
                </div>
            </div>

            <!-- CENTER LIST -->
            <div class="section-header">
                <div class="section-title">Center Overview</div>
                <span class="last-updated" id="last-updated">Auto‑refreshes every 30s</span>
            </div>

            <?php if (!$centers): ?>
                <div class="empty-state">
                    <div class="empty-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg></div>
                    <div class="empty-title">No Centers Assigned</div>
                    <div class="empty-desc">No evacuation centers are assigned to your account yet.<br>Please contact an administrator.</div>
                </div>
            <?php else: ?>
                <ul class="centers-list" id="centerList">
                    <?php foreach ($centers as $c):
                        $centerId    = (int)$c['id'];
                        $expected    = (int)$c['expected_count'];
                        $statusSlug  = strtolower(preg_replace('/\s+/', '_', $c['status']));
                        $hasEvacuees = $expected > 0;
                        $pillClass   = $hasEvacuees ? 'has-evacuees' : 'no-evacuees';
                        $bdown       = $breakdown[$centerId] ?? [];
                        $maxCount    = !empty($bdown) ? max(array_column($bdown, 'citizen_count')) : 1;

                        $maxCap   = (int)$c['max_capacity_people'];
                        $capPct   = $maxCap > 0 ? min(100, round($expected / $maxCap * 100)) : 0;
                        $capClass = $capPct >= 85 ? 'danger' : ($capPct >= 60 ? 'warning' : 'safe');
                    ?>
                    <li class="center-card" data-center-id="<?php echo $centerId; ?>">
                        <div class="center-card-header">
                            <div class="center-name-wrap">
                                <div class="center-name"><?php echo htmlspecialchars($c['name']); ?></div>
                                <div class="center-barangay">📍 <?php echo htmlspecialchars($c['barangay_name']); ?></div>
                            </div>
                            <span class="status-badge status-<?php echo htmlspecialchars($statusSlug); ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                            <span class="expected-pill <?php echo $pillClass; ?>" id="pill-<?php echo $centerId; ?>">
                                <span class="pill-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="4" r="2"/><path d="M10 9h4l1 5-3 1v5"/><path d="M10 9l-1 5 3 1"/></svg></span>
                                <span class="pill-count pill-val"><?php echo $expected; ?></span> expected
                            </span>
                            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="btn-manage">Manage <svg viewBox="0 0 16 16"><polyline points="6 3 11 8 6 13"/></svg></a>
                        </div>

                        <?php if ($maxCap > 0): ?>
                        <div class="capacity-row">
                            <span class="capacity-label">Capacity</span>
                            <div class="cap-bar-track"><div class="cap-bar <?php echo $capClass; ?>" id="capbar-<?php echo $centerId; ?>" style="width:<?php echo $capPct; ?>%"></div></div>
                            <span class="capacity-pct" id="cappct-<?php echo $centerId; ?>"><?php echo $expected; ?> / <?php echo $maxCap; ?> (<?php echo $capPct; ?>%)</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasEvacuees): ?>
                        <div class="breakdown-section">
                            <div class="breakdown-label">Breakdown by Barangay of Origin</div>
                            <table class="breakdown-table">
                                <thead><tr><th>Barangay</th><th style="text-align:center;">People</th><th style="min-width:90px;"></th></tr></thead>
                                <tbody>
                                    <?php foreach ($bdown as $brow):
                                        $pct = $maxCount > 0 ? round((int)$brow['citizen_count'] / $maxCount * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($brow['barangay_name']); ?></td>
                                        <td class="count-cell"><?php echo (int)$brow['citizen_count']; ?></td>
                                        <td><div class="bar-wrap"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
// Live clock
function updateClock() {
    const el = document.getElementById('topbar-clock');
    if (!el) return;
    const now = new Date();
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    el.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear() + '  ·  ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

// Hamburger menu
function openMenu() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('drawerOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeMenu() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('drawerOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

// Auto-refresh expected counts via AJAX
const AUTO_REFRESH_INTERVAL = 3000;
let refreshTimer = null;

function refreshCounts() {
    const btn = document.getElementById('refreshBtn');
    const spinIcon = document.getElementById('spinIcon');
    btn.disabled = true;
    btn.classList.add('spinning');
    fetch('expected_counts.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            let total = 0;
            data.centers.forEach(c => {
                const pill = document.getElementById('pill-' + c.id);
                const capBar = document.getElementById('capbar-' + c.id);
                const capPct = document.getElementById('cappct-' + c.id);
                if (pill) {
                    const val = pill.querySelector('.pill-val');
                    if (val) val.textContent = c.expected_count;
                    pill.className = 'expected-pill ' + (c.expected_count > 0 ? 'has-evacuees' : 'no-evacuees');
                }
                if (capBar && c.max_capacity_people > 0) {
                    const pct = Math.min(100, Math.round(c.expected_count / c.max_capacity_people * 100));
                    capBar.style.width = pct + '%';
                    capBar.className = 'cap-bar ' + (pct >= 85 ? 'danger' : (pct >= 60 ? 'warning' : 'safe'));
                    if (capPct) capPct.textContent = c.expected_count + ' / ' + c.max_capacity_people + ' (' + pct + '%)';
                }
                total += c.expected_count;
            });
            const totalEl = document.getElementById('total-expected');
            if (totalEl) totalEl.textContent = total;
            const ts = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('last-updated').textContent = 'Last updated: ' + ts;
        })
        .catch(() => {
            document.getElementById('last-updated').textContent = 'Refresh failed — retrying…';
        })
        .finally(() => {
            btn.disabled = false;
            btn.classList.remove('spinning');
        });
}

function startAutoRefresh() {
    clearInterval(refreshTimer);
    refreshTimer = setInterval(refreshCounts, AUTO_REFRESH_INTERVAL);
}
startAutoRefresh();
</script>
</body>
</html>