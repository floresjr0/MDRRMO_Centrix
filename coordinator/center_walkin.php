<?php
require_once __DIR__ . '/../pages/session.php';
require_login('coordinator');
require_once __DIR__ . '/../pages/center_helpers.php';

$pdo  = db();
$user = current_user();

$centerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
$successAdded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_family') {
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
        $ins = $pdo->prepare("INSERT INTO evac_registrations
            (center_id, family_head_name, contact_number, birthday, barangay_id,
             adults, children, seniors, pwds, total_members, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $centerId, $headName, $contactNumber, $birthday, $barangayId,
            $adults, $children, $seniors, $pwds, $total, $user['id']
        ]);
        refresh_center_status($centerId);
        header('Location: center_walkin.php?id=' . $centerId . '&added=1');
        exit;
    }
}

$successAdded = isset($_GET['added']) && $_GET['added'] == '1';
$occ = get_center_occupancy($centerId);
$pct = round($occ['percent']);
$barColor = $pct >= 100 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#16a34a');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Walk-in Family – <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../asset/css/center_walkin.css">    
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
        <div class="sidebar-user">
            <div class="user-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'], 0, 1))); ?></div>
            <div class="user-info"><div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div><div class="user-role">Coordinator</div></div>
        </div>
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
            <a href="index.php" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>Dashboard<span class="bottom-nav-dot"></span></a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span>App Arrivals<span class="bottom-nav-dot"></span></a>
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="bottom-nav-item active"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>Walk-in<span class="bottom-nav-dot"></span></a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>Registrations<span class="bottom-nav-dot"></span></a>
        </div>
    </nav>

    <div class="main">
        <header class="topbar">
            <div class="topbar-brand"><div class="topbar-logo"><img src="../img/mdrrmo.png" alt="MDRRMO Logo"></div><div class="topbar-brand-text"><div class="topbar-title"><?php echo htmlspecialchars($center['name']); ?></div><div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div></div></div>
            <div class="topbar-right"><button class="hamburger-btn" onclick="openMenu()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button></div>
        </header>
        <main class="dashboard">
            <div><h1 class="page-heading">Walk-in <span>Family</span></h1><div class="page-subnav"><a href="center_app_arrivals.php?id=<?php echo $centerId; ?>">App Arrivals</a><a href="center_walkin.php?id=<?php echo $centerId; ?>" class="active">Walk-in Family</a><a href="center_registrations.php?id=<?php echo $centerId; ?>">Registered Families</a></div></div>
            <section class="card"><div class="card-header"><div class="card-header-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><h2>Center Status</h2></div><div class="card-body"><div class="info-row"><strong>Barangay</strong> <?php echo htmlspecialchars($center['barangay_name']); ?></div><div class="info-row"><strong>Status</strong> <span class="status-pill status-<?php echo strtolower(preg_replace('/\s+/', '-', $center['status'])); ?>"><?php echo htmlspecialchars($center['status']); ?></span></div><div class="occ-bar-wrap"><div class="occ-bar-label"><span>Occupancy</span><span><?php echo $occ['current']; ?> / <?php echo $occ['max']; ?> people (<?php echo $pct; ?>%)</span></div><div class="occ-bar-track"><div class="occ-bar-fill" style="width:<?php echo min(100,$pct); ?>%; background:<?php echo $barColor; ?>;"></div></div></div><p class="occ-note">When capacity reaches 100%, status is set to <strong>full</strong>.</p></div></section>
            <section class="card"><div class="card-header"><div class="card-header-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div><h2>Register Walk-in Family</h2></div>
                <?php if ($successAdded): ?><div class="success-toast"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Family registered successfully!</div><?php endif; ?>
                <?php if ($errors): ?><ul class="error-box"><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php endforeach; ?></ul><?php endif; ?>
                <form method="post" class="form-body"><input type="hidden" name="action" value="add_family">
                    <label class="form-label">Family Head Name <input type="text" name="family_head_name" required value="<?php echo htmlspecialchars($_POST['family_head_name'] ?? ''); ?>"></label>
                    <div class="grid-2"><label class="form-label">Contact Number <input type="tel" name="contact_number" required value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>"></label><label class="form-label">Birthday (Head) <input type="date" name="birthday" required value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>"></label></div>
                    <label class="form-label">Barangay <select name="barangay_id" required><option value="">-- Select Barangay --</option><?php foreach ($barangays as $b): ?><option value="<?php echo (int)$b['id']; ?>" <?php echo (isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="grid-2"><label class="form-label">Adults <input type="number" name="adults" min="0" value="<?php echo (int)($_POST['adults'] ?? 0); ?>"></label><label class="form-label">Children <input type="number" name="children" min="0" value="<?php echo (int)($_POST['children'] ?? 0); ?>"></label><label class="form-label">Seniors <input type="number" name="seniors" min="0" value="<?php echo (int)($_POST['seniors'] ?? 0); ?>"></label><label class="form-label">PWDs <input type="number" name="pwds" min="0" value="<?php echo (int)($_POST['pwds'] ?? 0); ?>"></label></div>
                    <button type="submit" class="btn-submit">Record Arrival</button>
                </form>
            </section>
        </main>
    </div>
</div>
<script>
function openMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('drawerOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('drawerOverlay').classList.remove('open');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});
const toast=document.querySelector('.success-toast');if(toast){setTimeout(()=>{toast.style.display='none';},4200);}
</script>
</body>
</html>