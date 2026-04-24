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

// Handle adjustments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    $regId = (int)($_POST['reg_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $delta = (int)($_POST['delta'] ?? 0);

    if (in_array($field, ['adults','children','seniors','pwds'], true) && in_array($delta, [-1, 1], true)) {
        $check = $pdo->prepare("SELECT * FROM evac_registrations WHERE id = ? AND center_id = ?");
        $check->execute([$regId, $centerId]);
        $reg = $check->fetch();
        if ($reg) {
            $newVal   = max(0, (int)$reg[$field] + $delta);
            $adults   = $field === 'adults'   ? $newVal : (int)$reg['adults'];
            $children = $field === 'children' ? $newVal : (int)$reg['children'];
            $seniors  = $field === 'seniors'  ? $newVal : (int)$reg['seniors'];
            $pwds     = $field === 'pwds'     ? $newVal : (int)$reg['pwds'];
            $total    = $adults + $children + $seniors + $pwds;

            $upd = $pdo->prepare("UPDATE evac_registrations SET adults=?, children=?, seniors=?, pwds=?, total_members=? WHERE id=?");
            $upd->execute([$adults, $children, $seniors, $pwds, $total, $regId]);
            refresh_center_status($centerId);
        }
    }
    header('Location: center_registrations.php?id=' . $centerId);
    exit;
}

// Fetch registrations
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Families – <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/center_registrations.css">
</head>
<body>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeMenu()"></div>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand-row">
                <div class="brand-logo-sm"><img src="../img/mdrrmo.png" alt="MDRRMO Logo"></div>
                <div><div class="brand-name-sm">MDRRMO</div><div class="brand-tagline-sm">#BidaAngLagingHanda</div></div>
            </div>
            <button class="sidebar-close" onclick="closeMenu()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="sidebar-user"><div class="user-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'], 0, 1))); ?></div><div class="user-info"><div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div><div class="user-role">Coordinator</div></div></div>
        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>
            <a href="index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>Dashboard</a>
            <a href="index.php" class="nav-item active"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg></span>Centers</a>
        </nav>
        <div class="sidebar-status"><span class="status-dot-green"></span>SYSTEM ONLINE</div>
        <div class="sidebar-footer"><a href="../pages/logout.php" class="logout-btn"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a></div>
    </aside>

    <!-- BOTTOM NAVIGATION (5 items, "Registrations" active) -->
    <nav class="bottom-nav">
        <div class="bottom-nav-inner">
            <a href="index.php" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>
                Dashboard
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span>
                App Arrivals
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                Walk-in
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="bottom-nav-item active">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                Registrations
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="../pages/logout.php" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                Logout
                <span class="bottom-nav-dot"></span>
            </a>
        </div>
    </nav>

    <div class="main">
        <header class="topbar">
            <div class="topbar-brand"><div class="topbar-logo"><img src="../img/mdrrmo.png" alt="MDRRMO Logo"></div><div class="topbar-brand-text"><div class="topbar-title"><?php echo htmlspecialchars($center['name']); ?></div><div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div></div></div>
            <div class="topbar-right"><button class="hamburger-btn" onclick="openMenu()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button></div>
        </header>

        <main class="dashboard">
            <div>
                <h1 class="page-heading">Registered <span>Families</span></h1>
                <div class="page-subnav">
                    <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>">App Arrivals</a>
                    <a href="center_walkin.php?id=<?php echo $centerId; ?>">Walk-in Family</a>
                    <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="active">Registered Families</a>
                </div>
            </div>

            <!-- Center Status Card -->
            <section class="card">
                <div class="card-header"><div class="card-header-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><h2>Center Status</h2></div>
                <div class="card-body">
                    <div class="info-row"><strong>Barangay</strong> <?php echo htmlspecialchars($center['barangay_name']); ?></div>
                    <div class="info-row"><strong>Status</strong> <span class="status-pill status-<?php echo strtolower(preg_replace('/\s+/', '-', $center['status'])); ?>"><?php echo htmlspecialchars($center['status']); ?></span></div>
                    <div class="occ-bar-wrap"><div class="occ-bar-label"><span>Occupancy</span><span><?php echo $occ['current']; ?> / <?php echo $occ['max']; ?> people (<?php echo $pct; ?>%)</span></div><div class="occ-bar-track"><div class="occ-bar-fill" style="width:<?php echo min(100,$pct); ?>%; background:<?php echo $barColor; ?>;"></div></div></div>
                    <p class="occ-note">When capacity reaches 100%, status is set to <strong>full</strong> and new arrivals should be redirected.</p>
                </div>
            </section>

            <!-- Registered Families Table + Mobile Cards -->
            <section class="card">
                <div class="card-header"><div class="card-header-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div><h2>Occupant List</h2></div>
                <?php if (!$registrations): ?>
                    <div class="no-data"><div class="no-data-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>No families have been registered yet.</div>
                <?php else: ?>
                    <!-- Desktop table -->
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Head</th><th>Contact</th><th>Birthday</th><th>Barangay</th><th>Adults</th><th>Children</th><th>Seniors</th><th>PWDs</th><th>Total</th></tr>
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
                                                <input type="hidden" name="action" value="adjust">
                                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="field" value="<?php echo $field; ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit">−</button>
                                            </form>
                                            <span class="adjust-val"><?php echo (int)$r[$field]; ?></span>
                                            <form method="post" class="inline-adjust">
                                                <input type="hidden" name="action" value="adjust">
                                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="field" value="<?php echo $field; ?>">
                                                <input type="hidden" name="delta" value="1">
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

                    <!-- Mobile cards -->
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
                                <?php foreach (['adults'=>'Adults','children'=>'Children','seniors'=>'Seniors','pwds'=>'PWDs'] as $field=>$label): ?>
                                <div class="member-row">
                                    <span class="member-row-label"><?php echo $label; ?></span>
                                    <div class="member-row-controls">
                                        <form method="post" class="inline-adjust">
                                            <input type="hidden" name="action" value="adjust">
                                            <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                            <input type="hidden" name="field" value="<?php echo $field; ?>">
                                            <input type="hidden" name="delta" value="-1">
                                            <button type="submit">−</button>
                                        </form>
                                        <span class="adjust-val"><?php echo (int)$r[$field]; ?></span>
                                        <form method="post" class="inline-adjust">
                                            <input type="hidden" name="action" value="adjust">
                                            <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                            <input type="hidden" name="field" value="<?php echo $field; ?>">
                                            <input type="hidden" name="delta" value="1">
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
</script>
</body>
</html>