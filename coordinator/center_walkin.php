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
    <style>
        /* ========== FULL STYLES (same as before) ========== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --orange: #e8621a; --orange-dark: #c44e0f; --orange-light: #f97316;
            --orange-glow: rgba(232,98,26,0.18); --orange-pale: rgba(232,98,26,0.08);
            --sidebar-bg: #e8621a; --sidebar-w: 260px;
            --bg: #f5f4f0; --surface: #ffffff; --surface-2: #faf9f7;
            --border: #e8e4dc; --border-strong: #d4cfc4;
            --text: #1a1714; --text-mid: #5a5449; --text-muted: #9a9186;
            --green: #16a34a; --green-bg: #dcfce7; --green-border: #86efac;
            --red: #dc2626; --red-bg: #fee2e2; --red-border: #fca5a5;
            --amber: #d97706; --amber-bg: #fef3c7; --amber-border: #fcd34d;
            --slate: #64748b; --slate-bg: #f1f5f9; --slate-border: #cbd5e1;
            --radius: 12px; --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
            --font: 'Geist', sans-serif; --font-mono: 'Geist Mono', monospace;
            --bottom-nav-h: 68px;
        }
        html, body { min-height: 100%; background: var(--bg); font-family: var(--font); font-size: 15px; color: var(--text); -webkit-font-smoothing: antialiased; line-height: 1.5; }
        .layout { display: flex; min-height: 100vh; }
        .drawer-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 90; opacity: 0; transition: opacity 0.25s; }
        .drawer-overlay.open { display: block; opacity: 1; }
        .sidebar { position: fixed; top: 0; right: 0; bottom: 0; width: var(--sidebar-w); background: var(--sidebar-bg); display: flex; flex-direction: column; z-index: 100; overflow: hidden; transform: translateX(100%); transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar.open { transform: translateX(0); box-shadow: -6px 0 32px rgba(0,0,0,0.2); }
        .sidebar-header { padding: 1.25rem 1.1rem 1rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.18); }
        .sidebar-brand-row { display: flex; align-items: center; gap: 0.65rem; }
        .brand-logo-sm { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .brand-logo-sm img { width:100%; height:100%; object-fit:cover; border-radius:50%; display:block; }
        .brand-name-sm { font-size: 0.9rem; font-weight: 800; color: #fff; letter-spacing: 0.1em; text-transform: uppercase; line-height: 1.2; }
        .brand-tagline-sm { font-size: 0.58rem; color: rgba(255,255,255,0.75); letter-spacing: 0.07em; text-transform: uppercase; font-weight: 500; }
        .sidebar-close { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s; flex-shrink: 0; }
        .sidebar-close svg { width: 15px; height: 15px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; }
        .sidebar-user { margin: 1rem 1rem 0; padding: 0.75rem 1rem; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: var(--radius); display: flex; align-items: center; gap: 0.75rem; }
        .user-avatar { width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,0.25); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.78rem; color: #fff; flex-shrink: 0; }
        .user-info { min-width: 0; }
        .user-name { font-size: 0.82rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.68rem; color: rgba(255,255,255,0.8); font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em; }
        .sidebar-nav { flex: 1; padding: 1rem 0.75rem; display: flex; flex-direction: column; gap: 2px; }
        .nav-label { font-size: 0.62rem; font-weight: 700; color: rgba(255,255,255,0.5); letter-spacing: 0.12em; text-transform: uppercase; padding: 0.75rem 0.6rem 0.35rem; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.9rem; border-radius: var(--radius); color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: background 0.15s, color 0.15s; position: relative; cursor: pointer; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: #fff; }
        .nav-item.active { background: rgba(255,255,255,0.22); color: #fff; font-weight: 700; }
        .nav-icon { width: 18px; height: 18px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .nav-icon svg { width: 18px; height: 18px; stroke: rgba(255,255,255,0.85); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.18); }
        .logout-btn { display: flex; align-items: center; gap: 0.65rem; width: 100%; padding: 0.65rem 0.9rem; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); border-radius: var(--radius); color: #fff; font-size: 0.82rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.15s; font-family: var(--font); }
        .logout-btn svg { width: 16px; height: 16px; stroke: #fff; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
        .logout-btn:hover { background: rgba(255,255,255,0.2); }
        .sidebar-status { padding: 0.5rem 1rem 0; display: flex; align-items: center; gap: 0.4rem; font-size: 0.65rem; color: rgba(255,255,255,0.55); }
        .status-dot-green { width: 6px; height: 6px; border-radius: 50%; background: #bbf7d0; box-shadow: 0 0 6px #86efac; flex-shrink: 0; }
        
        .bottom-nav { display: none; position: fixed; bottom: 0; left: 0; right: 0; height: var(--bottom-nav-h); background: #fff; border-top: 1px solid var(--border); box-shadow: 0 -2px 16px rgba(0,0,0,0.07); z-index: 50; padding-bottom: env(safe-area-inset-bottom, 0px); }
        .bottom-nav-inner { display: flex; height: 100%; width: 100%; }
        .bottom-nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; text-decoration: none; color: var(--text-muted); font-size: 0.58rem; font-weight: 600; letter-spacing: 0; transition: color 0.15s; position: relative; border: none; background: none; cursor: pointer; font-family: var(--font); padding: 0 4px; min-width: 0; }
        .bottom-nav-item.active { color: var(--orange); }
        .bottom-nav-icon { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .bottom-nav-icon svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .bottom-nav-dot { display: none; width: 5px; height: 5px; border-radius: 50%; background: #dc2626; flex-shrink: 0; }
        .bottom-nav-item.active .bottom-nav-dot { display: block; }
        
        .main { flex: 1; display: flex; flex-direction: column; min-height: 100vh; width: 100%; }
        .topbar { position: sticky; top: 0; z-index: 40; background: rgba(245,244,240,0.92); backdrop-filter: blur(14px); border-bottom: 1px solid var(--border); padding: 0 1.5rem; height: 64px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .topbar-brand { display: flex; align-items: center; gap: 0.75rem; min-width: 0; flex: 1; }
        .topbar-logo { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--orange), var(--orange-dark)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 0 0 3px var(--orange-glow), 0 2px 8px rgba(232,98,26,0.28); }
        .topbar-logo img { width:100%; height:100%; object-fit:cover; border-radius:50%; display:block; }
        .topbar-brand-text { min-width: 0; }
        .topbar-title { font-size: 0.92rem; font-weight: 700; color: var(--text); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-subtitle { font-size: 0.68rem; color: var(--text-muted); }
        .topbar-right { display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0; }
        .hamburger-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border-strong); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.15s, border-color 0.15s, box-shadow 0.15s; flex-shrink: 0; }
        .hamburger-btn:hover { background: var(--orange-pale); border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }
        .hamburger-btn svg { width: 18px; height: 18px; stroke: var(--text-mid); fill: none; stroke-width: 2; stroke-linecap: round; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .dashboard { max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem 3rem; display: flex; flex-direction: column; gap: 1.5rem; animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        .page-heading { font-size: 1.6rem; font-weight: 800; color: var(--text); letter-spacing: -0.03em; line-height: 1.15; }
        .page-heading span { color: var(--orange); }
        .page-subnav { display: flex; gap: .7rem; margin: 0.5rem 0 0; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; }
        .page-subnav a { font-size: 0.85rem; font-weight: 600; color: var(--text-mid); text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 40px; transition: all 0.15s; }
        .page-subnav a.active { background: var(--orange-pale); color: var(--orange); border: 1px solid rgba(232,98,26,0.2); }
        .page-subnav a:hover { background: var(--orange-pale); color: var(--orange-dark); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); transition: box-shadow 0.2s; }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { display: flex; align-items: center; gap: 0.6rem; padding: 1rem 1.4rem; border-bottom: 1px solid var(--border); background: var(--surface-2); }
        .card-header-icon { width: 32px; height: 32px; border-radius: var(--radius); background: var(--orange-pale); border: 1px solid rgba(232,98,26,0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .card-header-icon svg { width: 16px; height: 16px; stroke: var(--orange); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .card-header h2 { font-size: 0.88rem; font-weight: 700; color: var(--text); letter-spacing: 0.01em; text-transform: uppercase; }
        .card-body { padding: 1.25rem 1.4rem; display: flex; flex-direction: column; gap: 0.75rem; }
        .info-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-mid); }
        .info-row strong { color: var(--text); font-weight: 600; min-width: 80px; flex-shrink: 0; }
        .status-pill { display: inline-flex; align-items: center; gap: 0.28rem; padding: 0.2rem 0.65rem; border-radius: 50px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; }
        .status-pill::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .status-available { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-border); }
        .status-full { background: var(--red-bg); color: var(--red); border: 1px solid var(--red-border); }
        .status-closed { background: var(--slate-bg); color: var(--slate); border: 1px solid var(--slate-border); }
        .status-near-capacity { background: var(--amber-bg); color: var(--amber); border: 1px solid var(--amber-border); }
        .occ-bar-wrap { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.15rem; }
        .occ-bar-label { display: flex; justify-content: space-between; font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
        .occ-bar-track { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
        .occ-bar-fill { height: 100%; border-radius: 4px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); }
        .form-body { padding: 1.25rem 1.4rem 1.4rem; display: flex; flex-direction: column; gap: 1rem; }
        .form-label { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.07em; }
        .form-label input, .form-label select { font-family: var(--font); font-size: 0.88rem; font-weight: 500; color: var(--text); background: var(--surface-2); border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 0.58rem 0.85rem; outline: none; transition: border-color 0.15s, box-shadow 0.15s, background 0.15s; width: 100%; }
        .form-label input:focus, .form-label select:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); background: var(--surface); }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .btn-submit { justify-content: center; display: flex; align-items: center; gap: 0.5rem; padding: 3.55rem 4.4rem; background: linear-gradient(135deg, var(--orange), var(--orange-dark)); border: none; border-radius: 50px; font-family: var(--font); font-size: 1.10rem; font-weight: 700; color: #fff; cursor: pointer; transition: opacity 0.15s, box-shadow 0.15s, transform 0.12s; box-shadow: 0 2px 8px rgba(232,98,26,0.3); letter-spacing: 0.02em; }
        .btn-submit:hover { opacity: 0.92; box-shadow: 0 4px 16px rgba(232,98,26,0.4); transform: translateY(-1px); }
        .error-box { margin: 0 1.4rem; padding: 0.75rem 1rem; background: var(--red-bg); border: 1px solid var(--red-border); border-radius: var(--radius); display: flex; flex-direction: column; gap: 0.3rem; }
        .error-box li { list-style: none; font-size: 0.82rem; color: var(--red); font-weight: 600; }
        .success-toast { display: flex; align-items: center; gap: 10px; padding: 5px 0px; background: #dcfce7; border: 1px solid #86efac; border-radius: 10px; color: #166534; font-size: 14px; font-weight: 500; animation: fadeOut 4s forwards; }
        @keyframes fadeOut { 0%,70% { opacity: 1; } 100% { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; } }
        @media (max-width: 768px) { .hamburger-btn { display: none; } .bottom-nav { display: flex; } .main { padding-bottom: var(--bottom-nav-h); } .topbar { padding: 0 1rem; height: 58px; } .dashboard { padding: 1.25rem 1rem 2rem; gap: 1.25rem; } .page-heading { font-size: 1.3rem; } .card-body { padding: 1rem; } .grid-2 { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) {
            .btn-submit {
                display: inline-flex;
                width: auto;
                align-self: center;
                padding: 0.55rem 1.2rem;
                min-width: 0;
            }
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="drawer-overlay" id="drawerOverlay" onclick="closeMenu()"></div>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><div class="sidebar-brand-row"><div class="brand-logo-sm"><img src="../img/mdrrmo.png" alt="MDRRMO Logo"></div><div><div class="brand-name-sm">MDRRMO</div><div class="brand-tagline-sm">#BidaAngLagingHanda</div></div></div><button class="sidebar-close" onclick="closeMenu()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
        <div class="sidebar-user"><div class="user-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($user['full_name'], 0, 1))); ?></div><div class="user-info"><div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div><div class="user-role">Coordinator</div></div></div>
        <nav class="sidebar-nav"><div class="nav-label">Navigation</div><a href="index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>Dashboard</a><a href="index.php" class="nav-item active"><span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg></span>Centers</a></nav>
        <div class="sidebar-status"><span class="status-dot-green"></span>SYSTEM ONLINE</div><div class="sidebar-footer"><a href="../pages/logout.php" class="logout-btn"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out</a></div>
    </aside>

    <nav class="bottom-nav">
        <div class="bottom-nav-inner">
            <a href="index.php" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg></span>Dashboard<span class="bottom-nav-dot"></span></a>
            <a href="center_app_arrivals.php?id=<?php echo $centerId; ?>" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg></span>App Arrivals<span class="bottom-nav-dot"></span></a>
            <a href="center_walkin.php?id=<?php echo $centerId; ?>" class="bottom-nav-item active"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>Walk-in<span class="bottom-nav-dot"></span></a>
            <a href="center_registrations.php?id=<?php echo $centerId; ?>" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>Registrations<span class="bottom-nav-dot"></span></a>
            <a href="../pages/logout.php" class="bottom-nav-item"><span class="bottom-nav-icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Logout<span class="bottom-nav-dot"></span></a>
        </div>
    </nav>

    <div class="main">
        <header class="topbar"><div class="topbar-brand"><div class="topbar-logo"><img src="../img/mdrrmo.png" alt="MDRRMO Logo"></div><div class="topbar-brand-text"><div class="topbar-title"><?php echo htmlspecialchars($center['name']); ?></div><div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div></div></div><div class="topbar-right"><button class="hamburger-btn" onclick="openMenu()"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button></div></header>
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
<script>function openMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('drawerOverlay').classList.add('open');document.body.style.overflow='hidden';}function closeMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('drawerOverlay').classList.remove('open');document.body.style.overflow='';}document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu();});const toast=document.querySelector('.success-toast');if(toast){setTimeout(()=>{toast.style.display='none';},4200);}</script>
</body>
</html>