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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_app_arrival') {
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
        header('Location: center_app_arrivals.php?id=' . $centerId . '&checkin=1');
        exit;
    } else {
        $errors[] = 'Could not record arrival — record may no longer be active.';
    }
}

// Fetch app arrivals
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

$occ      = get_center_occupancy($centerId);
$pct      = round($occ['percent']);
$barColor = $pct >= 100 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#16a34a');
$justCheckedIn = isset($_GET['checkin']) && $_GET['checkin'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>App Arrivals – <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/center_app_arrivals.css">
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
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span> Walk-in</a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span> Arrivals</a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span> Registrations</a>
        </nav>
        <div class="sidebar-status"><span class="status-dot-green"></span>SYSTEM ONLINE</div>
        <div class="sidebar-footer">
            <a href="../pages/logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </a>
            <!-- Collaboration logos -->
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
            <a href="index.php" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>
                Dashboard
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="bottom-nav-item active">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span>
                App Arrivals
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                Walk-in
                <span class="bottom-nav-dot"></span>
            </a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="bottom-nav-item">
                <span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
                Registrations
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
                <h1 class="page-heading">App <span>Arrivals</span></h1>
                <div class="page-subnav">
                    <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="active">App Arrivals</a>
                    <a href="center_walkin.php?id=<?php echo $centerId; ?>">Walk-in Family</a>
                    <a href="center_registrations.php?id=<?php echo $centerId; ?>">Registered Families</a>
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

            <!-- App Arrivals Section -->
            <section class="card">
                <div class="card-header"><div class="card-header-icon" style="background:linear-gradient(135deg,#f97316,#ea580c);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></div><h2>Citizens en Route</h2><?php if ($appArrivals): ?><span class="en-route-badge" style="margin-left:auto;"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg><?php echo count($appArrivals); ?> en route</span><?php endif; ?></div>
                <div class="card-body">
                    <?php if ($justCheckedIn): ?><div class="checkin-toast"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Evacuee recorded successfully!</div><?php endif; ?>
                    <?php if (!$appArrivals): ?><div class="arrival-queue-empty"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>No citizens are currently navigating to this center via the app.</div><?php else: ?>
                    <div class="app-arrivals-grid">
                        <?php foreach ($appArrivals as $a): $initial = mb_strtoupper(mb_substr($a['full_name'], 0, 1)); $profileTotal = (int)$a['total_members']; ?>
                        <div class="app-arrival-card" id="arrival-card-<?php echo (int)$a['tracking_id']; ?>">
                            <div class="app-arrival-card-header"><div class="app-arrival-person"><div class="app-arrival-avatar"><?php echo htmlspecialchars($initial); ?></div><div><div class="app-arrival-name"><?php echo htmlspecialchars($a['full_name']); ?></div><div class="app-arrival-meta"><svg viewBox="0 0 14 14" width="10" height="10" fill="#d45f10"><path d="M7 1C4.79 1 3 2.79 3 5c0 3.25 4 8 4 8s4-4.75 4-8c0-2.21-1.79-4-4-4Z"/></svg><?php echo htmlspecialchars($a['barangay_name']); ?> <span class="dot">·</span> House #<?php echo htmlspecialchars($a['house_number']); ?> <span class="dot">·</span> Profile: <?php echo $profileTotal; ?> person<?php echo $profileTotal != 1 ? 's' : ''; ?></div></div></div><span class="app-badge-nav"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>En Route</span></div>
                            <form method="post" id="form-arrival-<?php echo (int)$a['tracking_id']; ?>" onsubmit="return confirmArrival(this)">
                                <input type="hidden" name="action" value="record_app_arrival"><input type="hidden" name="tracking_id" value="<?php echo (int)$a['tracking_id']; ?>"><input type="hidden" name="nav_user_id" value="<?php echo (int)$a['user_id']; ?>">
                                <div class="app-arrival-members">
                                    <?php foreach (['adults'=>'Adults','children'=>'Children','seniors'=>'Seniors','pwds'=>'PWDs'] as $field=>$label): $val = (int)$a[$field]; ?>
                                    <div class="app-member-row"><span class="app-member-label"><?php echo $label; ?></span><div class="app-member-controls"><button type="button" onclick="adjustVal(<?php echo (int)$a['tracking_id']; ?>, '<?php echo $field; ?>', -1)">−</button><span class="app-member-val" id="val-<?php echo (int)$a['tracking_id']; ?>-<?php echo $field; ?>"><?php echo $val; ?></span><button type="button" onclick="adjustVal(<?php echo (int)$a['tracking_id']; ?>, '<?php echo $field; ?>', 1)">+</button></div><input type="hidden" name="<?php echo $field; ?>" id="hid-<?php echo (int)$a['tracking_id']; ?>-<?php echo $field; ?>" value="<?php echo $val; ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="app-arrival-footer"><div class="app-total-wrap"><div class="app-total-num" id="total-<?php echo (int)$a['tracking_id']; ?>"><?php echo $profileTotal; ?></div><div class="app-total-label">total physically present</div></div><span class="profile-match match-ok" id="match-<?php echo (int)$a['tracking_id']; ?>"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Matches profile</span><button type="submit" class="btn-record-arrival"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>Record as Arrived</button></div>
                                <input type="hidden" id="profile-total-<?php echo (int)$a['tracking_id']; ?>" value="<?php echo $profileTotal; ?>" data-adults="<?php echo (int)$a['adults']; ?>" data-children="<?php echo (int)$a['children']; ?>" data-seniors="<?php echo (int)$a['seniors']; ?>" data-pwds="<?php echo (int)$a['pwds']; ?>">
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div><?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function openMenu() { document.getElementById('sidebar').classList.add('open'); document.getElementById('drawerOverlay').classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeMenu() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('drawerOverlay').classList.remove('open'); document.body.style.overflow = ''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

function adjustVal(trackingId, field, delta) {
    const valEl = document.getElementById('val-' + trackingId + '-' + field);
    const hidEl = document.getElementById('hid-' + trackingId + '-' + field);
    if (!valEl || !hidEl) return;
    let current = parseInt(valEl.textContent, 10);
    let next = Math.max(0, current + delta);
    valEl.textContent = next;
    hidEl.value = next;
    let newTotal = 0;
    ['adults','children','seniors','pwds'].forEach(f => {
        let el = document.getElementById('hid-' + trackingId + '-' + f);
        if (el) newTotal += parseInt(el.value, 10) || 0;
    });
    document.getElementById('total-' + trackingId).textContent = newTotal;
    let profileEl = document.getElementById('profile-total-' + trackingId);
    let matchEl = document.getElementById('match-' + trackingId);
    if (profileEl && matchEl) {
        let profileAdults = parseInt(profileEl.dataset.adults,10), profileChildren = parseInt(profileEl.dataset.children,10), profileSeniors = parseInt(profileEl.dataset.seniors,10), profilePwds = parseInt(profileEl.dataset.pwds,10);
        let currentAdults = parseInt(document.getElementById('hid-' + trackingId + '-adults').value,10), currentChildren = parseInt(document.getElementById('hid-' + trackingId + '-children').value,10), currentSeniors = parseInt(document.getElementById('hid-' + trackingId + '-seniors').value,10), currentPwds = parseInt(document.getElementById('hid-' + trackingId + '-pwds').value,10);
        let isMatch = (currentAdults === profileAdults && currentChildren === profileChildren && currentSeniors === profileSeniors && currentPwds === profilePwds);
        if (isMatch) { matchEl.className = 'profile-match match-ok'; matchEl.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Matches profile'; }
        else { matchEl.className = 'profile-match match-diff'; matchEl.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Count adjusted'; }
    }
}
function confirmArrival(form) { let card = form.closest('.app-arrival-card'); let nameEl = card.querySelector('.app-arrival-name'); let totalEl = card.querySelector('[id^="total-"]'); let name = nameEl ? nameEl.textContent.trim() : 'this evacuee'; let total = totalEl ? totalEl.textContent.trim() : '?'; return confirm('Record arrival for ' + name + ' — ' + total + ' person(s)?\n\nThis will mark them as arrived and add them to the occupancy count.'); }
let toast = document.querySelector('.checkin-toast'); if (toast) { setTimeout(() => { toast.style.display = 'none'; }, 4200); }
</script>
</body>
</html>