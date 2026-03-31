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

    if ($action === 'add_family') {
        $headName   = trim($_POST['family_head_name'] ?? '');
        $barangayId = (int)($_POST['barangay_id'] ?? 0);
        $adults     = max(0, (int)($_POST['adults']   ?? 0));
        $children   = max(0, (int)($_POST['children'] ?? 0));
        $seniors    = max(0, (int)($_POST['seniors']  ?? 0));
        $pwds       = max(0, (int)($_POST['pwds']     ?? 0));
        $total      = $adults + $children + $seniors + $pwds;

        if ($headName === '')  $errors[] = 'Head of family name is required.';
        if (!$barangayId)      $errors[] = 'Barangay is required.';
        if ($total <= 0)       $errors[] = 'Please specify at least one member.';

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO evac_registrations
                (center_id, family_head_name, barangay_id, adults, children, seniors, pwds, total_members, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$centerId, $headName, $barangayId,
                            $adults, $children, $seniors, $pwds, $total, $user['id']]);

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

// Reload registrations and occupancy
$regsStmt = $pdo->prepare("SELECT r.*, b.name AS barangay_name
                           FROM evac_registrations r
                           JOIN barangays b ON b.id = r.barangay_id
                           WHERE r.center_id = ?
                           ORDER BY r.created_at DESC");
$regsStmt->execute([$centerId]);
$registrations = $regsStmt->fetchAll();

$occ = get_center_occupancy($centerId);
$pct = round($occ['percent']);
$barColor = $pct >= 100 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#16a34a');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Center – <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="../asset/css/manage_center.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>

<!-- Overlay for drawer -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeMenu()"></div>

<div class="layout">

    <!-- ══════════════════════════════════════
         SIDEBAR DRAWER — solid orange, slides from right
    ══════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">
            <div class="sidebar-brand-row">
                <div class="brand-logo-sm">
                    <!-- Replace src with your actual logo path e.g. ../assets/img/logo.png -->
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

        <!-- User chip -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'], 0, 1))); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="user-role">Coordinator</div>
            </div>
        </div>

        <!-- Nav — Centers is active since we're managing a center -->
        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>

            <a href="index.php" class="nav-item">
                <span class="nav-icon">
                    <!-- Home icon -->
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
            </a>

            <a href="index.php" class="nav-item active">
                <span class="nav-icon">
                    <!-- Building icon -->
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                </span>
                Centers
            </a>
        </nav>

        <!-- Footer -->
        <div class="sidebar-status">
            <span class="status-dot-green"></span>
            SYSTEM ONLINE
        </div>
        <div class="sidebar-footer">
            <a href="../pages/logout.php" class="logout-btn">
                <!-- Logout icon -->
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </a>
        </div>

    </aside>

    <!-- ══════════════════════════════════════
         BOTTOM NAV — mobile only
         Centers tab is active on this page
    ══════════════════════════════════════ -->
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

    <!-- ══════════════════════════════════════
         MAIN
    ══════════════════════════════════════ -->
    <div class="main">

        <!-- Top bar: logo + center name LEFT · hamburger RIGHT -->
        <header class="topbar">

            <div class="topbar-brand">
                <div class="topbar-logo" aria-hidden="true">
                    <!-- Replace src with your actual logo path e.g. ../assets/img/logo.png -->
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

        <!-- Page content -->
        <main class="dashboard">

            <h1 class="page-heading">Manage <span><?php echo htmlspecialchars($center['name']); ?></span></h1>

            <!-- CENTER STATUS -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <!-- Activity / status icon -->
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
                        <?php
                        $sc = 'status-' . strtolower(preg_replace('/\s+/', '-', $center['status']));
                        ?>
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

            <!-- ADD FAMILY FORM -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <!-- User plus icon -->
                        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    </div>
                    <h2>Add Arriving Family / Group</h2>
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
                        Head of Family Name
                        <input type="text" name="family_head_name" required
                               value="<?php echo htmlspecialchars($_POST['family_head_name'] ?? ''); ?>">
                    </label>

                    <label class="form-label">
                        Barangay
                        <select name="barangay_id" required>
                            <option value="">-- Select barangay --</option>
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
                            Adults
                            <input type="number" name="adults" min="0" value="<?php echo (int)($_POST['adults'] ?? 0); ?>">
                        </label>
                        <label class="form-label">
                            Children
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

            <!-- REGISTRATIONS TABLE -->
            <section class="card">
                <div class="card-header">
                    <div class="card-header-icon">
                        <!-- List icon -->
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
                <?php endif; ?>
            </section>

        </main>
    </div>
</div>

<script>
// ── Hamburger menu open / close ─────────────────────────────────────────────
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

// Close drawer on Escape key
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
</script>
</body>
</html>