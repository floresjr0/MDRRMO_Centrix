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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      /* Brand — matches coordinator dashboard */
      --orange:        #e8621a;
      --orange-dark:   #c44e0f;
      --orange-light:  #f97316;
      --orange-glow:   rgba(232, 98, 26, 0.18);
      --orange-pale:   rgba(232, 98, 26, 0.08);

      /* Sidebar — solid orange, no pattern */
      --sidebar-bg:    #e8621a;
      --sidebar-w:     260px;

      /* Surface */
      --bg:            #f5f4f0;
      --surface:       #ffffff;
      --surface-2:     #faf9f7;
      --border:        #e8e4dc;
      --border-strong: #d4cfc4;

      /* Text */
      --text:          #1a1714;
      --text-mid:      #5a5449;
      --text-muted:    #9a9186;

      /* Status */
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

      /* Misc */
      --radius:        12px;
      --radius-lg:     16px;
      --shadow-sm:     0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
      --shadow-md:     0 4px 16px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
      --font:          'Geist', sans-serif;
      --font-mono:     'Geist Mono', monospace;

      /* Mobile bottom nav */
      --bottom-nav-h:  68px;
    }

    html, body {
      min-height: 100%;
      background: var(--bg);
      font-family: var(--font);
      font-size: 15px;
      color: var(--text);
      -webkit-font-smoothing: antialiased;
      line-height: 1.5;
    }

    /* ════════════════════════════════════════════
       LAYOUT
    ════════════════════════════════════════════ */
    .layout {
      display: flex;
      min-height: 100vh;
    }

    /* ════════════════════════════════════════════
       DRAWER OVERLAY
    ════════════════════════════════════════════ */
    .drawer-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 90;
      opacity: 0;
      transition: opacity 0.25s;
    }

    .drawer-overlay.open {
      display: block;
      opacity: 1;
    }

    /* ════════════════════════════════════════════
       SIDEBAR — solid orange, slides from right
    ════════════════════════════════════════════ */
    .sidebar {
      position: fixed;
      top: 0; right: 0; bottom: 0;
      width: var(--sidebar-w);
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      z-index: 100;
      overflow: hidden;
      transform: translateX(100%);
      transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar.open {
      transform: translateX(0);
      box-shadow: -6px 0 32px rgba(0,0,0,0.2);
    }

    /* Sidebar header */
    .sidebar-header {
      padding: 1.25rem 1.1rem 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255,255,255,0.18);
    }

    .sidebar-brand-row {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }

    .brand-logo-sm {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .brand-logo-sm svg {
      width: 18px; height: 18px;
      stroke: #fff; fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    .brand-name-sm {
      font-size: 0.9rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      line-height: 1.2;
    }

    .brand-tagline-sm {
      font-size: 0.58rem;
      color: rgba(255,255,255,0.75);
      letter-spacing: 0.07em;
      text-transform: uppercase;
      font-weight: 500;
    }

    .sidebar-close {
      width: 32px; height: 32px;
      border-radius: 8px;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background 0.15s;
      flex-shrink: 0;
    }

    .sidebar-close:hover { background: rgba(255,255,255,0.25); }

    .sidebar-close svg {
      width: 15px; height: 15px;
      stroke: #fff; fill: none;
      stroke-width: 2; stroke-linecap: round;
    }

    /* User chip */
    .sidebar-user {
      margin: 1rem 1rem 0;
      padding: 0.75rem 1rem;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .user-avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: rgba(255,255,255,0.25);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 0.78rem; color: #fff;
      flex-shrink: 0;
    }

    .user-info { min-width: 0; }

    .user-name {
      font-size: 0.82rem; font-weight: 600; color: #fff;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .user-role {
      font-size: 0.68rem; color: rgba(255,255,255,0.8);
      font-weight: 500; text-transform: uppercase; letter-spacing: 0.06em;
    }

    /* Nav */
    .sidebar-nav {
      flex: 1;
      padding: 1rem 0.75rem;
      display: flex; flex-direction: column; gap: 2px;
    }

    .nav-label {
      font-size: 0.62rem; font-weight: 700;
      color: rgba(255,255,255,0.5);
      letter-spacing: 0.12em; text-transform: uppercase;
      padding: 0.75rem 0.6rem 0.35rem;
    }

    .nav-item {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.65rem 0.9rem;
      border-radius: var(--radius);
      color: rgba(255,255,255,0.85);
      text-decoration: none;
      font-size: 0.85rem; font-weight: 500;
      transition: background 0.15s, color 0.15s;
      position: relative; cursor: pointer;
    }

    .nav-item:hover { background: rgba(255,255,255,0.15); color: #fff; }

    .nav-item.active {
      background: rgba(255,255,255,0.22);
      color: #fff; font-weight: 700;
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0; top: 20%; bottom: 20%;
      width: 3px;
      background: #fff;
      border-radius: 0 2px 2px 0;
    }

    .nav-icon {
      width: 18px; height: 18px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
    }

    .nav-icon svg {
      width: 18px; height: 18px;
      stroke: rgba(255,255,255,0.85); fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    }

    .nav-item.active .nav-icon svg,
    .nav-item:hover .nav-icon svg { stroke: #fff; }

    /* Sidebar footer */
    .sidebar-footer {
      padding: 1rem;
      border-top: 1px solid rgba(255,255,255,0.18);
    }

    .logout-btn {
      display: flex; align-items: center; gap: 0.65rem;
      width: 100%;
      padding: 0.65rem 0.9rem;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: var(--radius);
      color: #fff;
      font-size: 0.82rem; font-weight: 600;
      cursor: pointer; text-decoration: none;
      transition: background 0.15s;
      font-family: var(--font);
    }

    .logout-btn svg {
      width: 16px; height: 16px;
      stroke: #fff; fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
      flex-shrink: 0;
    }

    .logout-btn:hover { background: rgba(255,255,255,0.2); }

    .sidebar-status {
      padding: 0.5rem 1rem 0;
      display: flex; align-items: center; gap: 0.4rem;
      font-size: 0.65rem; color: rgba(255,255,255,0.55);
    }

    .status-dot-green {
      width: 6px; height: 6px; border-radius: 50%;
      background: #bbf7d0; box-shadow: 0 0 6px #86efac; flex-shrink: 0;
    }

    /* ════════════════════════════════════════════
       BOTTOM NAV — mobile only
    ════════════════════════════════════════════ */
    .bottom-nav {
      display: none;
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: var(--bottom-nav-h);
      background: #fff;
      border-top: 1px solid var(--border);
      box-shadow: 0 -2px 16px rgba(0,0,0,0.07);
      z-index: 50;
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    .bottom-nav-inner {
      display: flex;
      height: 100%;
      width: 100%;
    }

    .bottom-nav-item {
      flex: 1;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 2px;
      text-decoration: none;
      color: var(--text-muted);
      font-size: 0.58rem; font-weight: 600;
      letter-spacing: 0;
      transition: color 0.15s;
      position: relative;
      border: none; background: none;
      cursor: pointer; font-family: var(--font);
      padding: 0 4px;
      min-width: 0;
    }

    .bottom-nav-item.active { color: var(--orange); }

    .bottom-nav-icon {
      width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .bottom-nav-icon svg {
      width: 22px; height: 22px;
      stroke: currentColor; fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    }

    /* Red dot indicator — shown below label text on active tab */
    .bottom-nav-dot {
      display: none;
      width: 5px; height: 5px;
      border-radius: 50%;
      background: #dc2626;
      flex-shrink: 0;
    }

    .bottom-nav-item.active .bottom-nav-dot { display: block; }

    /* Refresh item in bottom nav */
    .bottom-nav-refresh {
      flex: 1;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 2px;
      color: var(--text-muted);
      font-size: 0.58rem; font-weight: 600;
      letter-spacing: 0;
      border: none;
      background: none;
      cursor: pointer; font-family: var(--font);
      transition: color 0.15s;
      padding: 0 4px;
      min-width: 0;
    }

    .bottom-nav-refresh:active { color: var(--orange); }

    .bottom-nav-refresh .bn-spin {
      width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; line-height: 1;
      flex-shrink: 0;
    }

    .bottom-nav-refresh.spinning .bn-spin { animation: spin 0.7s linear infinite; }

    /* ════════════════════════════════════════════
       MAIN CONTENT
    ════════════════════════════════════════════ */
    .main {
      flex: 1;
      display: flex; flex-direction: column;
      min-height: 100vh; width: 100%;
    }

    /* ════════════════════════════════════════════
       TOPBAR — logo + name LEFT · hamburger RIGHT
    ════════════════════════════════════════════ */
    .topbar {
      position: sticky; top: 0; z-index: 40;
      background: rgba(245,244,240,0.92);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-bottom: 1px solid var(--border);
      padding: 0 1.5rem;
      height: 64px;
      display: flex; align-items: center;
      justify-content: space-between; gap: 1rem;
    }

    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      min-width: 0;
      flex: 1;
    }

    .topbar-logo {
      width: 40px; height: 40px; border-radius: 50%;
      background: linear-gradient(135deg, var(--orange), var(--orange-dark));
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 0 0 3px var(--orange-glow), 0 2px 8px rgba(232,98,26,0.28);
    }

    .topbar-logo svg {
      width: 20px; height: 20px;
      stroke: #fff; fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    .topbar-brand-text { min-width: 0; }

    .topbar-title {
      font-size: 0.92rem; font-weight: 700;
      color: var(--text); line-height: 1.2;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .topbar-subtitle { font-size: 0.68rem; color: var(--text-muted); }

    /* Right: hamburger (desktop) */
    .topbar-right {
      display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0;
    }

    /* Hamburger — hidden on mobile (bottom nav takes over) */
    .hamburger-btn {
      width: 38px; height: 38px;
      border-radius: 10px;
      background: var(--surface);
      border: 1px solid var(--border-strong);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
      flex-shrink: 0;
    }

    .hamburger-btn:hover {
      background: var(--orange-pale);
      border-color: var(--orange);
      box-shadow: 0 0 0 3px var(--orange-glow);
    }

    .hamburger-btn svg {
      width: 18px; height: 18px;
      stroke: var(--text-mid); fill: none;
      stroke-width: 2; stroke-linecap: round;
    }

    .hamburger-btn:hover svg { stroke: var(--orange-dark); }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ════════════════════════════════════════════
       DASHBOARD WRAPPER
    ════════════════════════════════════════════ */
    .dashboard {
      max-width: 860px;
      margin: 0 auto;
      padding: 2rem 1.5rem 3rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Page heading */
    .page-heading {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -0.03em;
      line-height: 1.15;
    }

    .page-heading span { color: var(--orange); }

    /* ════════════════════════════════════════════
       CARD
    ════════════════════════════════════════════ */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: box-shadow 0.2s;
    }

    .card:hover { box-shadow: var(--shadow-md); }

    .card-header {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 1rem 1.4rem;
      border-bottom: 1px solid var(--border);
      background: var(--surface-2);
    }

    .card-header-icon {
      width: 32px; height: 32px;
      border-radius: var(--radius);
      background: var(--orange-pale);
      border: 1px solid rgba(232,98,26,0.15);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .card-header-icon svg {
      width: 16px; height: 16px;
      stroke: var(--orange); fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    }

    .card-header h2 {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--text);
      letter-spacing: 0.01em;
      text-transform: uppercase;
    }

    .card-body {
      padding: 1.25rem 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    /* Info rows */
    .info-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--text-mid);
    }

    .info-row strong {
      color: var(--text);
      font-weight: 600;
      min-width: 80px;
      flex-shrink: 0;
    }

    /* Status pill */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.28rem;
      padding: 0.2rem 0.65rem;
      border-radius: 50px;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
    }

    .status-pill::before {
      content: '';
      width: 5px; height: 5px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }

    .status-available    { background: var(--green-bg);  color: var(--green);  border: 1px solid var(--green-border); }
    .status-full         { background: var(--red-bg);    color: var(--red);    border: 1px solid var(--red-border); }
    .status-closed       { background: var(--slate-bg);  color: var(--slate);  border: 1px solid var(--slate-border); }
    .status-near-capacity{ background: var(--amber-bg);  color: var(--amber);  border: 1px solid var(--amber-border); }

    /* Occupancy bar */
    .occ-bar-wrap { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.15rem; }

    .occ-bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--text-muted);
    }

    .occ-bar-track {
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
    }

    .occ-bar-fill {
      height: 100%;
      border-radius: 4px;
      transition: width 0.6s cubic-bezier(0.4,0,0.2,1);
    }

    .occ-note {
      font-size: 0.72rem;
      color: var(--text-muted);
      line-height: 1.5;
    }

    /* ════════════════════════════════════════════
       ERRORS
    ════════════════════════════════════════════ */
    .error-box {
      margin: 0 1.4rem;
      padding: 0.75rem 1rem;
      background: var(--red-bg);
      border: 1px solid var(--red-border);
      border-radius: var(--radius);
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
    }

    .error-box li {
      list-style: none;
      font-size: 0.82rem;
      color: var(--red);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .error-box li::before { content: '⚠'; font-size: 0.75rem; }

    /* ════════════════════════════════════════════
       FORM
    ════════════════════════════════════════════ */
    .form-body {
      padding: 1.25rem 1.4rem 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .form-label {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
    }

    .form-label input[type="text"],
    .form-label input[type="number"],
    .form-label select {
      font-family: var(--font);
      font-size: 0.88rem;
      font-weight: 500;
      color: var(--text);
      background: var(--surface-2);
      border: 1px solid var(--border-strong);
      border-radius: var(--radius);
      padding: 0.58rem 0.85rem;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
      width: 100%;
    }

    .form-label input:focus,
    .form-label select:focus {
      border-color: var(--orange);
      box-shadow: 0 0 0 3px var(--orange-glow);
      background: var(--surface);
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

    .btn-submit {
      align-self: flex-start;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 1.4rem;
      background: linear-gradient(135deg, var(--orange), var(--orange-dark));
      border: none;
      border-radius: 50px;
      font-family: var(--font);
      font-size: 0.82rem;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      transition: opacity 0.15s, box-shadow 0.15s, transform 0.12s;
      box-shadow: 0 2px 8px rgba(232,98,26,0.3);
      letter-spacing: 0.02em;
    }

    .btn-submit:hover {
      opacity: 0.92;
      box-shadow: 0 4px 16px rgba(232,98,26,0.4);
      transform: translateY(-1px);
    }

    .btn-submit:active { transform: translateY(0); }

    .btn-submit svg {
      width: 14px; height: 14px;
      stroke: #fff; fill: none;
      stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ════════════════════════════════════════════
       TABLE
    ════════════════════════════════════════════ */
    .table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
      min-width: 560px;
    }

    .table thead tr {
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
    }

    .table th {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-muted);
      padding: 0.7rem 1rem;
      text-align: left;
      white-space: nowrap;
    }

    .table tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.13s;
    }

    .table tbody tr:last-child { border-bottom: none; }
    .table tbody tr:hover { background: var(--orange-pale); }

    .table td {
      padding: 0.65rem 1rem;
      color: var(--text-mid);
      vertical-align: middle;
    }

    .table td.cell-head { font-weight: 700; color: var(--text); }
    .table td.cell-total { font-weight: 800; color: var(--text); font-family: var(--font-mono); }

    /* Inline adjust stepper */
    .adjust-cell {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      white-space: nowrap;
    }

    .inline-adjust { display: inline-flex; }

    .inline-adjust button {
      width: 24px; height: 24px;
      border-radius: 50%;
      border: 1px solid var(--border-strong);
      background: var(--surface);
      color: var(--text);
      font-size: 0.9rem;
      font-weight: 700;
      line-height: 1;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.12s, border-color 0.12s, transform 0.1s;
      font-family: var(--font);
    }

    .inline-adjust button:hover {
      background: var(--orange);
      border-color: var(--orange-dark);
      color: #fff;
      transform: scale(1.12);
    }

    .adjust-val {
      min-width: 22px;
      text-align: center;
      font-weight: 700;
      font-size: 0.85rem;
      color: var(--text);
      font-family: var(--font-mono);
    }

    /* No data */
    .no-data {
      padding: 2.5rem 1.4rem;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.85rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.6rem;
    }

    .no-data-icon {
      width: 48px; height: 48px;
      border-radius: var(--radius-lg);
      background: var(--orange-pale);
      border: 1px solid rgba(232,98,26,0.15);
      display: flex; align-items: center; justify-content: center;
    }

    .no-data-icon svg {
      width: 24px; height: 24px;
      stroke: var(--orange); fill: none;
      stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ════════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════════ */
    @media (max-width: 768px) {
      /* Hide hamburger on mobile — bottom nav takes over */
      .hamburger-btn { display: none; }

      /* Show bottom nav */
      .bottom-nav { display: flex; }

      /* Push content above bottom nav */
      .main { padding-bottom: var(--bottom-nav-h); }

      .topbar { padding: 0 1rem; height: 58px; }
      .dashboard { padding: 1.25rem 1rem 2rem; gap: 1.25rem; }
      .page-heading { font-size: 1.35rem; }
      .card-body { padding: 1rem; }
      .form-body { padding: 1rem; }
    }

    @media (max-width: 480px) {
      .grid-2 { grid-template-columns: 1fr 1fr; gap: 0.6rem; }
      .btn-submit { width: 100%; justify-content: center; }
    }

    @media (max-width: 360px) {
      .grid-2 { grid-template-columns: 1fr; }
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--orange); }

    </style>
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