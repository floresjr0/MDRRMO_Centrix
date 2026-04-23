<?php
require_once __DIR__ . '/../pages/session.php';
require_login('coordinator');
require_once __DIR__ . '/../pages/center_helpers.php';

$pdo  = db();
$user = current_user();

$centerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ensure this center belongs to this coordinator
$stmt = $pdo->prepare("SELECT c.*, b.name AS barangay_name
                       FROM evacuation_centers c
                       JOIN barangays b ON b.id = c.barangay_id
                       WHERE c.id = ? AND c.coordinator_user_id = ?");
$stmt->execute([$centerId, $user['id']]);
$center = $stmt->fetch();

if (!$center) {
    http_response_code(404);
    echo 'Center not found or not assigned to you.';
    exit;
}

$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Record app arrival ────────────────────────────────────────────────
    if ($action === 'record_app_arrival') {
        $trackingId = (int)($_POST['tracking_id'] ?? 0);
        $navUserId  = (int)($_POST['nav_user_id']  ?? 0);
        $adults     = max(0, (int)($_POST['adults']   ?? 0));
        $children   = max(0, (int)($_POST['children'] ?? 0));
        $seniors    = max(0, (int)($_POST['seniors']  ?? 0));
        $pwds       = max(0, (int)($_POST['pwds']     ?? 0));
        $total      = $adults + $children + $seniors + $pwds;

        $chk = $pdo->prepare("SELECT nt.id, u.full_name, u.barangay_id,
                              u.contact_number, u.birthday, u.sex
                       FROM evac_navigation_tracking nt
                       JOIN users u ON u.id = nt.user_id
                       WHERE nt.id = ? AND nt.center_id = ? AND nt.status = 'navigating'");
$chk->execute([$trackingId, $centerId]);
$trackRow = $chk->fetch();

        if ($trackRow && $total > 0) {
            $ins = $pdo->prepare("INSERT INTO evac_registrations
    (center_id, family_head_name, contact_number, birthday, barangay_id,
     adults, children, seniors, pwds, total_members, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$ins->execute([
    $centerId,
    $trackRow['full_name'],
    $trackRow['contact_number'] ?? null,
    $trackRow['birthday'] ?? null,
    $trackRow['barangay_id'],
    $adults, $children, $seniors, $pwds, $total,
    $user['id']
            ]);

            $upd = $pdo->prepare("UPDATE evac_navigation_tracking
                                  SET status = 'arrived', updated_at = NOW()
                                  WHERE id = ?");
            $upd->execute([$trackingId]);

            refresh_center_status($centerId);
            header('Location: manage_center.php?id=' . $centerId . '&checkin=1');
            exit;
        } else {
            $errors[] = 'Could not record arrival — record may no longer be active.';
        }

    } elseif ($action === 'add_family') {
        $headName      = trim($_POST['family_head_name'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $birthday      = $_POST['birthday'] ?? '';
        $barangayId    = (int)($_POST['barangay_id'] ?? 0);
        $adults        = max(0, (int)($_POST['adults']   ?? 0));
        $children      = max(0, (int)($_POST['children'] ?? 0));
        $seniors       = max(0, (int)($_POST['seniors']  ?? 0));
        $pwds          = max(0, (int)($_POST['pwds']     ?? 0));
        $total         = $adults + $children + $seniors + $pwds;

        if ($headName === '')      $errors[] = 'Head of family name is required.';
        if ($contactNumber === '') $errors[] = 'Contact number is required.';
        if (empty($birthday))      $errors[] = 'Birthday is required.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) $errors[] = 'Invalid birthday format (YYYY-MM-DD).';
        if (!$barangayId)          $errors[] = 'Barangay is required.';
        if ($total <= 0)           $errors[] = 'Please specify at least one member.';

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO evac_registrations
                (center_id, family_head_name, contact_number, birthday, barangay_id, adults, children, seniors, pwds, total_members, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $centerId, $headName, $contactNumber, $birthday, $barangayId,
                $adults, $children, $seniors, $pwds, $total, $user['id']
            ]);

            refresh_center_status($centerId);
            header('Location: manage_center.php?id=' . $centerId);
            exit;
        }

    } elseif ($action === 'adjust') {
        $regId = (int)($_POST['reg_id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $delta = (int)($_POST['delta'] ?? 0);

        if (!in_array($field, ['adults','children','seniors','pwds'], true) || !in_array($delta, [-1, 1], true)) {
            $errors[] = 'Invalid adjustment.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM evac_registrations WHERE id = ? AND center_id = ?");
            $stmt->execute([$regId, $centerId]);
            $reg = $stmt->fetch();
            if ($reg) {
                $newVal   = max(0, (int)$reg[$field] + $delta);
                $adults   = $field === 'adults'   ? $newVal : (int)$reg['adults'];
                $children = $field === 'children' ? $newVal : (int)$reg['children'];
                $seniors  = $field === 'seniors'  ? $newVal : (int)$reg['seniors'];
                $pwds     = $field === 'pwds'     ? $newVal : (int)$reg['pwds'];
                $total    = $adults + $children + $seniors + $pwds;

                $upd = $pdo->prepare("UPDATE evac_registrations
                                      SET adults=?, children=?, seniors=?, pwds=?, total_members=?
                                      WHERE id=?");
                $upd->execute([$adults, $children, $seniors, $pwds, $total, $regId]);

                refresh_center_status($centerId);
                header('Location: manage_center.php?id=' . $centerId);
                exit;
            }
        }
    }
}

// ── App arrivals query (unchanged) ─────────────────────────────────────────
$appArrivalsStmt = $pdo->prepare("
    SELECT
        nt.id          AS tracking_id,
        nt.user_id,
        u.full_name,
        b.name         AS barangay_name,
        u.barangay_id,
        u.house_number,
        COALESCE(ch.adults,        1) AS adults,
        COALESCE(ch.children,      0) AS children,
        COALESCE(ch.seniors,       0) AS seniors,
        COALESCE(ch.pwds,          0) AS pwds,
        COALESCE(ch.total_members, 1) AS total_members,
        nt.updated_at
    FROM evac_navigation_tracking nt
    JOIN users u        ON u.id  = nt.user_id
    JOIN barangays b    ON b.id  = u.barangay_id
    LEFT JOIN citizen_household ch ON ch.user_id = nt.user_id
    WHERE nt.center_id = ?
      AND nt.status    = 'navigating'
    ORDER BY nt.updated_at ASC
");
$appArrivalsStmt->execute([$centerId]);
$appArrivals = $appArrivalsStmt->fetchAll();

// Registrations with new columns (contact_number, birthday)
$regsStmt = $pdo->prepare("SELECT r.*, b.name AS barangay_name
                           FROM evac_registrations r
                           JOIN barangays b ON b.id = r.barangay_id
                           WHERE r.center_id = ?
                           ORDER BY r.created_at DESC");
$regsStmt->execute([$centerId]);
$registrations = $regsStmt->fetchAll();

$occ      = get_center_occupancy($centerId);
$pct      = round($occ['percent']);
$barColor = $pct >= 100 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#16a34a');

$justCheckedIn = isset($_GET['checkin']) && $_GET['checkin'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Center – <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="stylesheet" href="../asset/css/manage_center.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ── App Arrivals Section (unchanged) ─────────────────────────────────────── */
.arrival-queue-empty {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 20px;
    background: #f9fafb;
    border-radius: 10px;
    color: #9ca3af;
    font-size: 14px;
}
.arrival-queue-empty svg { flex-shrink: 0; opacity: .5; }

.checkin-toast {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    background: #dcfce7;
    border: 1px solid #86efac;
    border-radius: 10px;
    color: #166534;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 16px;
    animation: fadeOut 4s forwards;
}
@keyframes fadeOut {
    0%,70%  { opacity: 1; }
    100%    { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
}

.app-arrivals-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.app-arrival-card {
    border: 2px solid #fed7aa;
    border-radius: 14px;
    background: #fff7ed;
    overflow: hidden;
    transition: border-color .2s;
}
.app-arrival-card:hover { border-color: #fb923c; }

.app-arrival-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 10px;
    gap: 10px;
    flex-wrap: wrap;
}

.app-arrival-person {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.app-arrival-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: #fff;
    font-size: 17px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.app-arrival-name {
    font-weight: 700;
    font-size: 15px;
    color: #1a1a2e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.app-arrival-meta {
    font-size: 12px;
    color: #78716c;
    margin-top: 1px;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}
.app-arrival-meta .dot { opacity: .4; }

.app-badge-nav {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 99px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.app-badge-nav svg { width: 11px; height: 11px; }

.app-arrival-members {
    padding: 0 16px 14px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
@media (max-width: 480px) {
    .app-arrival-members { grid-template-columns: 1fr; }
}

.app-member-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    padding: 8px 12px;
    gap: 8px;
}

.app-member-label {
    font-size: 13px;
    color: #57534e;
    font-weight: 500;
    flex: 1;
}

.app-member-controls {
    display: flex;
    align-items: center;
    gap: 6px;
}
.app-member-controls button {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #fed7aa;
    background: #fff7ed;
    color: #ea580c;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, border-color .15s;
    line-height: 1;
    padding: 0;
}
.app-member-controls button:hover {
    background: #f97316;
    border-color: #f97316;
    color: #fff;
}
.app-member-val {
    font-size: 15px;
    font-weight: 700;
    color: #1a1a2e;
    min-width: 22px;
    text-align: center;
}

.app-arrival-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 14px;
    border-top: 1px solid #fed7aa;
    gap: 10px;
    flex-wrap: wrap;
}
.app-total-wrap {
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.app-total-num {
    font-size: 24px;
    font-weight: 800;
    color: #ea580c;
    line-height: 1;
}
.app-total-label {
    font-size: 12px;
    color: #78716c;
    font-weight: 500;
}

.btn-record-arrival {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: #fff;
    border: none;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .2s, transform .1s;
    letter-spacing: .2px;
}
.btn-record-arrival:hover  { opacity: .9; }
.btn-record-arrival:active { transform: scale(.97); }
.btn-record-arrival svg { width: 15px; height: 15px; flex-shrink: 0; }

.profile-match {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    padding: 3px 8px 3px 6px;
    border-radius: 99px;
    font-weight: 600;
}
.profile-match.match-ok {
    background: #dcfce7;
    color: #166534;
}
.profile-match.match-diff {
    background: #fef9c3;
    color: #854d0e;
}

.en-route-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fff7ed;
    border: 1.5px solid #fed7aa;
    color: #c2410c;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 99px;
}
</style>
</head>
<body>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeMenu()"></div>

<div class="layout">

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
            <a href="index.php" class="nav-item">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
            </a>
            <a href="index.php" class="nav-item active">
                <span class="nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                </span>
                Centers
            </a>
        </nav>

        <div class="sidebar-status">
            <span class="status-dot-green"></span>
            SYSTEM ONLINE
        </div>
        <div class="sidebar-footer">
            <a href="../pages/logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </a>
        </div>
    </aside>

    <nav class="bottom-nav" aria-label="Mobile navigation">
        <div class="bottom-nav-inner">
            <a href="index.php" class="bottom-nav-item">
                <span class="bottom-nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="index.php" class="bottom-nav-item active">
                <span class="bottom-nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                </span>
                Centers
                <span class="bottom-nav-dot"></span>
            </a>
            <button class="bottom-nav-refresh" id="bnRefreshBtn" onclick="window.location.reload()" aria-label="Refresh">
                <span class="bn-spin" id="bnSpinIcon">⟳</span>
                Refresh
            </button>
            <a href="../pages/logout.php" class="bottom-nav-item">
                <span class="bottom-nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </span>
                Logout
                <span class="bottom-nav-dot"></span>
            </a>
        </div>
    </nav>

    <div class="main">
        <header class="topbar">
            <div class="topbar-brand">
                <div class="topbar-logo" aria-hidden="true">
                    <img src="../img/mdrrmo.png" alt="MDRRMO Logo"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                </div>
                <div class="topbar-brand-text">
                    <div class="topbar-title"><?php echo htmlspecialchars($center['name']); ?></div>
                    <div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div>
                </div>
            </div>
            <div class="topbar-right">
                <button class="hamburger-btn" onclick="openMenu()" aria-label="Open menu">
                    <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>
        </header>

        <main class="dashboard">
            <h1 class="page-heading">Manage <span><?php echo htmlspecialchars($center['name']); ?></span></h1>

            <!-- Center Status Card (unchanged) -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <h2>Center Status</h2>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <strong>Barangay</strong>
                        <?php echo htmlspecialchars($center['barangay_name']); ?>
                    </div>
                    <div class="info-row">
                        <strong>Status</strong>
                        <?php $sc = 'status-' . strtolower(preg_replace('/\s+/', '-', $center['status'])); ?>
                        <span class="status-pill <?php echo htmlspecialchars($sc); ?>">
                            <?php echo htmlspecialchars($center['status']); ?>
                        </span>
                    </div>
                    <div class="occ-bar-wrap">
                        <div class="occ-bar-label">
                            <span>Occupancy</span>
                            <span><?php echo $occ['current']; ?> / <?php echo $occ['max']; ?> people (<?php echo $pct; ?>%)</span>
                        </div>
                        <div class="occ-bar-track">
                            <div class="occ-bar-fill" style="width:<?php echo min(100,$pct); ?>%; background:<?php echo $barColor; ?>;"></div>
                        </div>
                    </div>
                    <p class="occ-note">
                        When capacity reaches 100%, status is set to <strong>full</strong> and new arrivals should be redirected to another center.
                    </p>
                </div>
            </section>

            <!-- App Arrivals Section (unchanged) -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:linear-gradient(135deg,#f97316,#ea580c);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                        </svg>
                    </div>
                    <h2>App Arrivals <small style="font-size:13px;font-weight:400;color:#78716c;">— citizens navigating here via the app</small></h2>
                    <?php if ($appArrivals): ?>
                        <span class="en-route-badge" style="margin-left:auto;">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                            <?php echo count($appArrivals); ?> en route
                        </span>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <?php if ($justCheckedIn): ?>
                    <div class="checkin-toast">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Evacuee recorded successfully!
                    </div>
                    <?php endif; ?>

                    <?php if (!$appArrivals): ?>
                        <div class="arrival-queue-empty">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                            No citizens are currently navigating to this center via the app.
                        </div>
                    <?php else: ?>
                        <div class="app-arrivals-grid">
                        <?php foreach ($appArrivals as $a):
                            $initial    = mb_strtoupper(mb_substr($a['full_name'], 0, 1));
                            $profileTotal = (int)$a['total_members'];
                        ?>
                        <div class="app-arrival-card" id="arrival-card-<?php echo (int)$a['tracking_id']; ?>">
                            <div class="app-arrival-card-header">
                                <div class="app-arrival-person">
                                    <div class="app-arrival-avatar"><?php echo htmlspecialchars($initial); ?></div>
                                    <div>
                                        <div class="app-arrival-name"><?php echo htmlspecialchars($a['full_name']); ?></div>
                                        <div class="app-arrival-meta">
                                            <svg viewBox="0 0 14 14" width="10" height="10" fill="#d45f10"><path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4Z"/></svg>
                                            <?php echo htmlspecialchars($a['barangay_name']); ?>
                                            <span class="dot">·</span>
                                            House #<?php echo htmlspecialchars($a['house_number']); ?>
                                            <span class="dot">·</span>
                                            Profile: <?php echo $profileTotal; ?> person<?php echo $profileTotal != 1 ? 's' : ''; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="app-badge-nav">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                                    En Route
                                </span>
                            </div>

                            <form method="post"
                                  id="form-arrival-<?php echo (int)$a['tracking_id']; ?>"
                                  onsubmit="return confirmArrival(this)">
                                <input type="hidden" name="action"      value="record_app_arrival">
                                <input type="hidden" name="tracking_id" value="<?php echo (int)$a['tracking_id']; ?>">
                                <input type="hidden" name="nav_user_id" value="<?php echo (int)$a['user_id']; ?>">

                                <div class="app-arrival-members">
                                    <?php
                                    $fields = [
                                        'adults'   => 'Adults',
                                        'children' => 'Children',
                                        'seniors'  => 'Seniors',
                                        'pwds'     => 'PWDs',
                                    ];
                                    foreach ($fields as $field => $label):
                                        $val = (int)$a[$field];
                                    ?>
                                    <div class="app-member-row">
                                        <span class="app-member-label"><?php echo $label; ?></span>
                                        <div class="app-member-controls">
                                            <button type="button"
                                                    onclick="adjustVal(<?php echo (int)$a['tracking_id']; ?>, '<?php echo $field; ?>', -1)">−</button>
                                            <span class="app-member-val"
                                                  id="val-<?php echo (int)$a['tracking_id']; ?>-<?php echo $field; ?>"><?php echo $val; ?></span>
                                            <button type="button"
                                                    onclick="adjustVal(<?php echo (int)$a['tracking_id']; ?>, '<?php echo $field; ?>', 1)">+</button>
                                        </div>
                                        <input type="hidden"
                                               name="<?php echo $field; ?>"
                                               id="hid-<?php echo (int)$a['tracking_id']; ?>-<?php echo $field; ?>"
                                               value="<?php echo $val; ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="app-arrival-footer">
                                    <div class="app-total-wrap">
                                        <div class="app-total-num"
                                             id="total-<?php echo (int)$a['tracking_id']; ?>"><?php echo $profileTotal; ?></div>
                                        <div class="app-total-label">&nbsp;total physically present</div>
                                    </div>

                                    <span class="profile-match match-ok"
                                          id="match-<?php echo (int)$a['tracking_id']; ?>">
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                        Matches profile
                                    </span>

                                    <button type="submit" class="btn-record-arrival">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <polyline points="16 11 18 13 22 9"/>
                                        </svg>
                                        Record as Arrived
                                    </button>
                                </div>

                                <input type="hidden"
                                       id="profile-total-<?php echo (int)$a['tracking_id']; ?>"
                                       value="<?php echo $profileTotal; ?>"
                                       data-adults="<?php echo (int)$a['adults']; ?>"
                                       data-children="<?php echo (int)$a['children']; ?>"
                                       data-seniors="<?php echo (int)$a['seniors']; ?>"
                                       data-pwds="<?php echo (int)$a['pwds']; ?>">
                            </form>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ── ADD WALK‑IN FAMILY (with Contact Number & Birthday) ── -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    </div>
                    <h2>Add Walk-in Family / Group</h2>
                </div>

                <?php if ($errors): ?>
                    <ul class="error-box">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" class="form-body">
                    <input type="hidden" name="action" value="add_family">

                    <label class="form-label">
                        Pangalan ng Head ng Pamilya
                        <input type="text" name="family_head_name" required
                               value="<?php echo htmlspecialchars($_POST['family_head_name'] ?? ''); ?>">
                    </label>

                    <!-- New fields: Contact Number and Birthday -->
                    <div class="grid-2">
                        <label class="form-label">
                            Contact Number
                            <input type="tel" name="contact_number" required
                                   value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>"
                                   placeholder="e.g., 09171234567">
                        </label>
                        <label class="form-label">
                            Birthday (of family head)
                            <input type="date" name="birthday" required
                                   value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>">
                        </label>
                    </div>

                    <label class="form-label">
                        Barangay
                        <select name="barangay_id" required>
                            <option value="">-- Pumili ng barangay --</option>
                            <?php foreach ($barangays as $b): ?>
                                <option value="<?php echo (int)$b['id']; ?>"
                                    <?php echo (isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="grid-2">
                        <label class="form-label">
                            Mga Matatanda (Adults)
                            <input type="number" name="adults" min="0" value="<?php echo (int)($_POST['adults'] ?? 0); ?>">
                        </label>
                        <label class="form-label">
                            Mga Bata (Children)
                            <input type="number" name="children" min="0" value="<?php echo (int)($_POST['children'] ?? 0); ?>">
                        </label>
                        <label class="form-label">
                            Seniors
                            <input type="number" name="seniors" min="0" value="<?php echo (int)($_POST['seniors'] ?? 0); ?>">
                        </label>
                        <label class="form-label">
                            PWDs
                            <input type="number" name="pwds" min="0" value="<?php echo (int)($_POST['pwds'] ?? 0); ?>">
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Record Arrival
                    </button>
                </form>
            </section>

            <!-- Registrations Table + Mobile Cards (unchanged display) -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </div>
                    <h2>Registered Families / Groups</h2>
                </div>

                <?php if (!$registrations): ?>
                    <div class="no-data">
                        <div class="no-data-icon">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        No families have been registered yet.
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Head</th>
                                    <th>Contact</th>
                                    <th>Birthday</th>
                                    <th>Barangay</th>
                                    <th>Adults</th>
                                    <th>Children</th>
                                    <th>Seniors</th>
                                    <th>PWDs</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($registrations as $r): ?>
                                <tr>
                                    <td class="cell-head"><?php echo htmlspecialchars($r['family_head_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['contact_number'] ?? ''); ?></td>
                                    <td><?php echo !empty($r['birthday']) ? date('M d, Y', strtotime($r['birthday'])) : ''; ?></td>
                                    <td><?php echo htmlspecialchars($r['barangay_name']); ?></td>

                                    <?php foreach (['adults','children','seniors','pwds'] as $field): ?>
                                    <td>
                                        <div class="adjust-cell">
                                            <form method="post" class="inline-adjust">
                                                <input type="hidden" name="action"  value="adjust">
                                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="field"  value="<?php echo $field; ?>">
                                                <input type="hidden" name="delta"  value="-1">
                                                <button type="submit">−</button>
                                            </form>
                                            <span class="adjust-val"><?php echo (int)$r[$field]; ?></span>
                                            <form method="post" class="inline-adjust">
                                                <input type="hidden" name="action"  value="adjust">
                                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="field"  value="<?php echo $field; ?>">
                                                <input type="hidden" name="delta"  value="1">
                                                <button type="submit">+</button>
                                            </form>
                                        </div>
                                    </td>
                                    <?php endforeach; ?>

                                    <td class="cell-total"><?php echo (int)$r['total_members']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="reg-cards">
                        <?php foreach ($registrations as $r): ?>
                        <div class="reg-card">
                            <div class="reg-card-head">
                                <div>
                                    <div class="reg-card-name"><?php echo htmlspecialchars($r['family_head_name']); ?></div>
                                    <div class="reg-card-barangay"><?php echo htmlspecialchars($r['barangay_name']); ?></div>
                                    <div class="reg-card-contact">📞 <?php echo htmlspecialchars($r['contact_number'] ?? ''); ?></div>
                                    <div class="reg-card-bday">🎂 <?php echo !empty($r['birthday']) ? date('M d, Y', strtotime($r['birthday'])) : ''; ?></div>
                                </div>
                                <div class="reg-card-total">
                                    <div class="reg-card-total-num"><?php echo (int)$r['total_members']; ?></div>
                                    <div class="reg-card-total-label">Total</div>
                                </div>
                            </div>

                            <div class="reg-card-members">
                                <?php foreach (['adults' => 'Adults', 'children' => 'Children', 'seniors' => 'Seniors', 'pwds' => 'PWDs'] as $field => $label): ?>
                                <div class="member-row">
                                    <span class="member-row-label"><?php echo $label; ?></span>
                                    <div class="member-row-controls">
                                        <form method="post" class="inline-adjust">
                                            <input type="hidden" name="action"  value="adjust">
                                            <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                            <input type="hidden" name="field"  value="<?php echo $field; ?>">
                                            <input type="hidden" name="delta"  value="-1">
                                            <button type="submit">−</button>
                                        </form>
                                        <span class="adjust-val"><?php echo (int)$r[$field]; ?></span>
                                        <form method="post" class="inline-adjust">
                                            <input type="hidden" name="action"  value="adjust">
                                            <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                            <input type="hidden" name="field"  value="<?php echo $field; ?>">
                                            <input type="hidden" name="delta"  value="1">
                                            <button type="submit">+</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script>
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

function adjustVal(trackingId, field, delta) {
    const valEl = document.getElementById('val-' + trackingId + '-' + field);
    const hidEl = document.getElementById('hid-' + trackingId + '-' + field);
    if (!valEl || !hidEl) return;

    const current = parseInt(valEl.textContent, 10);
    const next    = Math.max(0, current + delta);
    valEl.textContent = next;
    hidEl.value       = next;

    const fields   = ['adults', 'children', 'seniors', 'pwds'];
    let newTotal   = 0;
    fields.forEach(f => {
        const el = document.getElementById('hid-' + trackingId + '-' + f);
        if (el) newTotal += parseInt(el.value, 10) || 0;
    });

    const totalEl = document.getElementById('total-' + trackingId);
    if (totalEl) totalEl.textContent = newTotal;

    const profileEl = document.getElementById('profile-total-' + trackingId);
    const matchEl   = document.getElementById('match-'         + trackingId);
    if (profileEl && matchEl) {
        const profileAdults   = parseInt(profileEl.dataset.adults,   10);
        const profileChildren = parseInt(profileEl.dataset.children, 10);
        const profileSeniors  = parseInt(profileEl.dataset.seniors,  10);
        const profilePwds     = parseInt(profileEl.dataset.pwds,     10);

        const currentAdults   = parseInt(document.getElementById('hid-' + trackingId + '-adults').value,   10);
        const currentChildren = parseInt(document.getElementById('hid-' + trackingId + '-children').value, 10);
        const currentSeniors  = parseInt(document.getElementById('hid-' + trackingId + '-seniors').value,  10);
        const currentPwds     = parseInt(document.getElementById('hid-' + trackingId + '-pwds').value,     10);

        const isMatch = (
            currentAdults   === profileAdults   &&
            currentChildren === profileChildren &&
            currentSeniors  === profileSeniors  &&
            currentPwds     === profilePwds
        );

        if (isMatch) {
            matchEl.className = 'profile-match match-ok';
            matchEl.innerHTML = `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Matches profile`;
        } else {
            matchEl.className = 'profile-match match-diff';
            matchEl.innerHTML = `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Count adjusted`;
        }
    }
}

function confirmArrival(form) {
    const card    = form.closest('.app-arrival-card');
    const nameEl  = card.querySelector('.app-arrival-name');
    const totalEl = card.querySelector('[id^="total-"]');
    const name    = nameEl  ? nameEl.textContent.trim()    : 'this evacuee';
    const total   = totalEl ? totalEl.textContent.trim()   : '?';
    return confirm('Record arrival for ' + name + ' — ' + total + ' person(s)?\n\nThis will mark them as arrived and add them to the occupancy count.');
}

const toast = document.querySelector('.checkin-toast');
if (toast) {
    setTimeout(() => { toast.style.display = 'none'; }, 4200);
}
</script>
</body>
</html>