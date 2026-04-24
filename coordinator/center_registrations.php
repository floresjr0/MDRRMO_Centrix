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
    <style>
        /* ========== FULL STYLES (same as center_app_arrivals.php) ========== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --orange:        #e8621a;
            --orange-dark:   #c44e0f;
            --orange-light:  #f97316;
            --orange-glow:   rgba(232, 98, 26, 0.18);
            --orange-pale:   rgba(232, 98, 26, 0.08);
            --sidebar-bg:    #e8621a;
            --sidebar-w:     260px;
            --bg:            #f5f4f0;
            --surface:       #ffffff;
            --surface-2:     #faf9f7;
            --border:        #e8e4dc;
            --border-strong: #d4cfc4;
            --text:          #1a1714;
            --text-mid:      #5a5449;
            --text-muted:    #9a9186;
            --green:         #16a34a;
            --green-bg:      #dcfce7;
            --green-border:  #86efac;
            --red:           #dc2626;
            --red-bg:        #fee2e2;
            --red-border:    #fca5a5;
            --amber:         #d97706;
            --amber-bg:      #fef3c7;
            --amber-border:  #fcd34d;
            --slate:         #64748b;
            --slate-bg:      #f1f5f9;
            --slate-border:  #cbd5e1;
            --radius:        12px;
            --radius-lg:     16px;
            --shadow-sm:     0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:     0 4px 16px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
            --font:          'Geist', sans-serif;
            --font-mono:     'Geist Mono', monospace;
            --bottom-nav-h:  68px;
        }

        html, body { min-height: 100%; background: var(--bg); font-family: var(--font); font-size: 15px; color: var(--text); -webkit-font-smoothing: antialiased; line-height: 1.5; }
        .layout { display: flex; min-height: 100vh; }
        .drawer-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 90; opacity: 0; transition: opacity 0.25s; }
        .drawer-overlay.open { display: block; opacity: 1; }
        
        /* Sidebar (unchanged) */
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
        
        /* Bottom navigation (5 items) */
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
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.82rem; min-width: 560px; }
        .table thead tr { background: var(--surface-2); border-bottom: 1px solid var(--border); }
        .table th { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); padding: 0.7rem 1rem; text-align: left; white-space: nowrap; }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.13s; }
        .table tbody tr:hover { background: var(--orange-pale); }
        .table td { padding: 0.65rem 1rem; color: var(--text-mid); vertical-align: middle; }
        .table td.cell-head { font-weight: 700; color: var(--text); }
        .adjust-cell { display: flex; align-items: center; gap: 0.3rem; white-space: nowrap; }
        .inline-adjust { display: inline-flex; }
        .inline-adjust button { width: 24px; height: 24px; border-radius: 50%; border: 1px solid var(--border-strong); background: var(--surface); color: var(--text); font-size: 0.9rem; font-weight: 700; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.12s, border-color 0.12s, transform 0.1s; font-family: var(--font); }
        .inline-adjust button:hover { background: var(--orange); border-color: var(--orange-dark); color: #fff; transform: scale(1.12); }
        .adjust-val { min-width: 22px; text-align: center; font-weight: 700; font-size: 0.85rem; color: var(--text); font-family: var(--font-mono); }
        .reg-cards { display: none; }
        .reg-card { border-bottom: 1px solid var(--border); padding: 1rem 1rem 1.1rem; }
        .reg-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem; }
        .reg-card-name { font-size: 0.9rem; font-weight: 700; color: var(--text); line-height: 1.25; }
        .reg-card-barangay { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem; font-weight: 500; }
        .reg-card-contact, .reg-card-bday { font-size: 0.7rem; color: var(--text-muted); margin-top: 0.2rem; }
        .reg-card-total { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
        .reg-card-total-num { font-size: 1.4rem; font-weight: 800; color: var(--orange); font-family: var(--font-mono); line-height: 1; }
        .reg-card-total-label { font-size: 0.6rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; }
        .reg-card-members { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 0.75rem; }
        .member-row { display: flex; align-items: center; justify-content: space-between; background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: 0.45rem 0.6rem; }
        .member-row-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.07em; }
        .member-row-controls { display: flex; align-items: center; gap: 0.25rem; }
        .member-row .inline-adjust button { width: 28px; height: 28px; font-size: 1rem; }
        .no-data { padding: 2.5rem 1.4rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; display: flex; flex-direction: column; align-items: center; gap: 0.6rem; }
        .no-data-icon { width: 48px; height: 48px; border-radius: var(--radius-lg); background: var(--orange-pale); border: 1px solid rgba(232,98,26,0.15); display: flex; align-items: center; justify-content: center; }
        .no-data-icon svg { width: 24px; height: 24px; stroke: var(--orange); fill: none; stroke-width: 1.7; }
        
        @media (max-width: 768px) { .hamburger-btn { display: none; } .bottom-nav { display: flex; } .main { padding-bottom: var(--bottom-nav-h); } .topbar { padding: 0 1rem; height: 58px; } .dashboard { padding: 1.25rem 1rem 2rem; gap: 1.25rem; } .page-heading { font-size: 1.3rem; } .card-body { padding: 1rem; } .table-wrap { display: none; } .reg-cards { display: block; } }
        @media (max-width: 480px) { .reg-card-members { grid-template-columns: 1fr; } }
    </style>
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