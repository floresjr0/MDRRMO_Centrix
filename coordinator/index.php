<?php
require_once __DIR__ . '/../pages/session.php';
require_login('coordinator');

$pdo  = db();
$user = current_user();

// ── Assigned centers with expected-evacuee counts ─────────────────────────
// "expected" = citizens whose tracking status is 'navigating' for this center
$stmt = $pdo->prepare("
    SELECT
        c.*,
        b.name AS barangay_name,
        COALESCE(t.expected_count, 0) AS expected_count
    FROM evacuation_centers c
    JOIN barangays b ON b.id = c.barangay_id
    LEFT JOIN (
        SELECT center_id, COUNT(*) AS expected_count
        FROM   evac_navigation_tracking
        WHERE  status = 'navigating'
        GROUP  BY center_id
    ) t ON t.center_id = c.id
    WHERE c.coordinator_user_id = ?
");
$stmt->execute([$user['id']]);
$centers = $stmt->fetchAll();

// ── Per-center breakdown: barangay origin of navigating citizens ───────────
// Keyed by center_id → array of rows
$breakdownStmt = $pdo->prepare("
    SELECT
        nt.center_id,
        b.name  AS barangay_name,
        COUNT(*) AS citizen_count
    FROM   evac_navigation_tracking nt
    JOIN   users u  ON u.id  = nt.user_id
    JOIN   barangays b ON b.id = u.barangay_id
    WHERE  nt.status = 'navigating'
      AND  nt.center_id IN (
               SELECT id FROM evacuation_centers WHERE coordinator_user_id = ?
           )
    GROUP  BY nt.center_id, u.barangay_id
    ORDER  BY citizen_count DESC
");
$breakdownStmt->execute([$user['id']]);
$breakdownRows = $breakdownStmt->fetchAll();

// Group by center_id
$breakdown = [];
foreach ($breakdownRows as $row) {
    $breakdown[(int)$row['center_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coordinator Dashboard - MDRRMO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700;800;900&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      /* Brand */
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

      /* Status colors */
      --green:         #16a34a;
      --green-bg:      #dcfce7;
      --green-border:  #86efac;
      --amber:         #d97706;
      --amber-bg:      #fef3c7;
      --amber-border:  #fcd34d;
      --red:           #dc2626;
      --red-bg:        #fee2e2;
      --red-border:    #fca5a5;
      --slate:         #64748b;
      --slate-bg:      #f1f5f9;
      --slate-border:  #cbd5e1;
      --indigo:        #4338ca;
      --indigo-bg:     #e0e7ff;
      --indigo-border: #a5b4fc;

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
       SIDEBAR — solid orange, no pattern, slides from right
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

    .nav-item:hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
    }

    .nav-item.active {
      background: rgba(255,255,255,0.22);
      color: #fff;
      font-weight: 700;
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
       TOPBAR — logo + name LEFT · right actions
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

    /* Left: logo circle + name stacked beside it */
    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      flex-shrink: 0;
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

    .topbar-brand-text {
      display: flex; flex-direction: column;
    }

    .topbar-title {
      font-size: 0.92rem; font-weight: 700;
      color: var(--text); line-height: 1.2;
    }

    .topbar-subtitle {
      font-size: 0.68rem; color: var(--text-muted);
    }

    /* Right: clock · refresh · hamburger */
    .topbar-right {
      display: flex; align-items: center; gap: 0.6rem; flex-shrink: 0;
    }

    .topbar-date {
      font-size: 0.72rem; color: var(--text-muted);
      font-variant-numeric: tabular-nums; white-space: nowrap;
    }

    .refresh-btn {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.38rem 0.8rem;
      background: var(--surface);
      border: 1px solid var(--border-strong);
      border-radius: 50px;
      font-size: 0.73rem; font-weight: 600; color: var(--text-mid);
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
      font-family: var(--font);
    }

    .refresh-btn:hover {
      background: var(--orange-pale);
      border-color: var(--orange);
      color: var(--orange-dark);
      box-shadow: 0 0 0 3px var(--orange-glow);
    }

    .refresh-btn.spinning .spin-icon { animation: spin 0.7s linear infinite; }
    .spin-icon { display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Hamburger — desktop only */
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

    /* ── PAGE CONTENT ── */
    .page {
      padding: 2rem;
      display: flex; flex-direction: column; gap: 1.5rem;
      animation: fadeUp 0.4s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── PAGE HEADING — bare text, no container ── */
    .page-heading {
      font-size: 1.75rem; font-weight: 800;
      color: var(--text);
      letter-spacing: -0.03em; line-height: 1.15;
    }

    .page-heading span { color: var(--orange); }

    /* ── SUMMARY STATS ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
    }

    .stat-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 1.25rem 1.4rem;
      display: flex; align-items: center; gap: 1rem;
      box-shadow: var(--shadow-sm);
      transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s;
      position: relative; overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--orange), var(--orange-light));
      opacity: 0; transition: opacity 0.2s;
    }

    .stat-card:hover { box-shadow: var(--shadow-md); border-color: var(--orange); transform: translateY(-2px); }
    .stat-card:hover::before { opacity: 1; }

    .stat-icon {
      width: 46px; height: 46px;
      border-radius: var(--radius);
      background: var(--orange-pale);
      border: 1px solid rgba(232,98,26,0.18);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .stat-icon svg {
      width: 22px; height: 22px;
      stroke: var(--orange); fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    }

    .stat-val {
      font-size: 2rem; font-weight: 800; color: var(--text);
      line-height: 1; font-variant-numeric: tabular-nums;
    }

    .stat-label { font-size: 0.72rem; color: var(--text-muted); margin-top: 3px; font-weight: 500; }

    /* ── SECTION HEADER ── */
    .section-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 0.1rem;
    }

    .section-title {
      font-size: 0.8rem; font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase; letter-spacing: 0.08em;
      display: flex; align-items: center; gap: 0.5rem;
    }

    .section-title::before {
      content: '';
      display: inline-block; width: 3px; height: 14px;
      background: var(--orange); border-radius: 2px;
    }

    .last-updated { font-size: 0.72rem; color: var(--text-muted); }

    /* ── CENTER CARDS ── */
    .centers-list { list-style: none; display: flex; flex-direction: column; gap: 1rem; }

    .center-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius-lg); overflow: hidden;
      box-shadow: var(--shadow-sm); transition: box-shadow 0.2s, border-color 0.2s;
    }

    .center-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-strong); }

    .center-card-header {
      display: flex; align-items: center; flex-wrap: wrap;
      gap: 0.6rem 1rem; padding: 1.1rem 1.4rem;
      border-bottom: 1px solid var(--border);
    }

    .center-name-wrap { flex: 1 1 auto; min-width: 160px; }

    .center-name { font-size: 0.95rem; font-weight: 700; color: var(--text); }

    .center-barangay { font-size: 0.75rem; color: var(--text-muted); margin-top: 1px; }

    /* Status badge */
    .status-badge {
      display: inline-flex; align-items: center; gap: 0.28rem;
      padding: 0.22rem 0.65rem; border-radius: 50px;
      font-size: 0.68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.07em; white-space: nowrap;
    }

    .status-badge::before {
      content: ''; width: 5px; height: 5px;
      border-radius: 50%; background: currentColor; flex-shrink: 0;
    }

    .status-available    { background: var(--green-bg);  color: var(--green);  border: 1px solid var(--green-border); }
    .status-near_capacity{ background: var(--amber-bg);  color: var(--amber);  border: 1px solid var(--amber-border); }
    .status-full         { background: var(--red-bg);    color: var(--red);    border: 1px solid var(--red-border); }
    .status-closed       { background: var(--slate-bg);  color: var(--slate);  border: 1px solid var(--slate-border); }
    .status-temp_shelter { background: var(--indigo-bg); color: var(--indigo); border: 1px solid var(--indigo-border); }

    /* Expected evacuees pill */
    .expected-pill {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.3rem 0.8rem; border-radius: 50px;
      font-size: 0.78rem; font-weight: 700;
      white-space: nowrap; font-variant-numeric: tabular-nums;
    }

    .expected-pill.has-evacuees {
      background: linear-gradient(135deg, rgba(232,98,26,0.12), rgba(249,115,22,0.08));
      color: var(--orange-dark); border: 1px solid rgba(232,98,26,0.3);
    }

    .expected-pill.no-evacuees {
      background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);
    }

    .pill-icon svg {
      width: 13px; height: 13px; stroke: currentColor; fill: none;
      stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; vertical-align: middle;
    }

    .pill-count { font-size: 0.95rem; font-weight: 800; color: inherit; }

    /* Manage button */
    .btn-manage {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.45rem 1.1rem;
      background: linear-gradient(135deg, var(--orange), var(--orange-dark));
      border: none; border-radius: 50px; color: #fff;
      font-family: var(--font); font-weight: 700; font-size: 0.78rem;
      text-decoration: none; white-space: nowrap; flex-shrink: 0;
      cursor: pointer; transition: opacity 0.15s, box-shadow 0.15s, transform 0.12s;
      box-shadow: 0 2px 8px rgba(232,98,26,0.3);
    }

    .btn-manage:hover { opacity: 0.9; box-shadow: 0 4px 16px rgba(232,98,26,0.4); transform: translateY(-1px); }

    .btn-manage svg {
      width: 12px; height: 12px; stroke: currentColor; fill: none;
      stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── CAPACITY ROW ── */
    .capacity-row {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.7rem 1.4rem; border-bottom: 1px solid var(--border);
      background: var(--surface-2);
    }

    .capacity-label {
      font-size: 0.68rem; font-weight: 600; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: 0.06em; flex-shrink: 0;
    }

    .cap-bar-track { flex: 1; height: 7px; background: var(--border); border-radius: 4px; overflow: hidden; }

    .cap-bar {
      height: 100%; border-radius: 4px;
      transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .cap-bar.safe    { background: linear-gradient(90deg, #4ade80, #22c55e); }
    .cap-bar.warning { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
    .cap-bar.danger  { background: linear-gradient(90deg, #f87171, #ef4444); }

    .capacity-pct {
      font-size: 0.75rem; font-weight: 700; color: var(--text);
      font-variant-numeric: tabular-nums; white-space: nowrap;
      flex-shrink: 0; font-family: var(--font-mono);
    }

    /* ── BREAKDOWN TABLE ── */
    .breakdown-section { padding: 0.9rem 1.4rem 1rem; background: #faf9f7; }

    .breakdown-label {
      font-size: 0.68rem; font-weight: 700; color: var(--text-muted);
      text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.6rem;
      display: flex; align-items: center; gap: 0.35rem;
    }

    .breakdown-label::before {
      content: ''; width: 3px; height: 10px;
      background: var(--orange-light); border-radius: 2px; display: inline-block;
    }

    .breakdown-table { width: 100%; border-collapse: collapse; font-size: 0.80rem; }

    .breakdown-table th {
      text-align: left; font-weight: 600; color: var(--text-muted);
      font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em;
      padding: 0.3rem 0.6rem 0.5rem; border-bottom: 1px solid var(--border);
    }

    .breakdown-table td {
      padding: 0.45rem 0.6rem; color: var(--text-mid);
      border-bottom: 1px solid var(--border); vertical-align: middle;
    }

    .breakdown-table tr:last-child td { border-bottom: none; }

    .breakdown-table .count-cell {
      text-align: center; font-weight: 800; color: var(--text);
      font-family: var(--font-mono); font-size: 0.82rem;
    }

    .bar-wrap { width: 100%; height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; min-width: 70px; }

    .bar-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--orange-dark), var(--orange-light));
      border-radius: 3px; transition: width 0.5s ease;
    }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center; padding: 3rem 2rem;
      background: var(--surface); border: 1px solid var(--border);
      border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
    }

    .empty-icon {
      width: 52px; height: 52px; border-radius: var(--radius-lg);
      background: var(--orange-pale); border: 1px solid rgba(232,98,26,0.18);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem;
    }

    .empty-icon svg {
      width: 26px; height: 26px; stroke: var(--orange); fill: none;
      stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round;
    }

    .empty-title { font-size: 0.95rem; font-weight: 700; color: var(--text); margin-bottom: 0.35rem; }
    .empty-desc  { font-size: 0.82rem; color: var(--text-muted); line-height: 1.6; }

    /* ════════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════════ */
    @media (max-width: 768px) {
      /* Hide hamburger on mobile — bottom nav takes over */
      .hamburger-btn { display: none; }

      /* Show bottom nav */
      .bottom-nav { display: flex; }

      /* Push content up above bottom nav */
      .main { padding-bottom: var(--bottom-nav-h); }

      .stats-grid { grid-template-columns: 1fr 1fr; gap: 0.65rem; }
      .page { padding: 1.25rem; gap: 1.25rem; }
      .topbar { padding: 0 1rem; height: 58px; }
      .topbar-date { display: none; }
      .page-heading { font-size: 1.4rem; }
      .refresh-btn span:last-child { display: none; }
    }

    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: 1fr; }
      .center-card-header { flex-direction: column; align-items: flex-start; }
      .btn-manage { width: 100%; justify-content: center; }
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 6px; }
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

        <!-- Nav — Dashboard & Centers only -->
        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>

            <a href="#" class="nav-item active">
                <span class="nav-icon">
                    <!-- Home icon -->
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
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
    ══════════════════════════════════════ -->
    <nav class="bottom-nav" aria-label="Mobile navigation">
        <div class="bottom-nav-inner">

            <a href="#" class="bottom-nav-item active">
                <span class="bottom-nav-icon">
                    <svg viewBox="0 0 24 24"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H5a1 1 0 01-1-1V9.5z"/><polyline points="9 21 9 12 15 12 15 21"/></svg>
                </span>
                Dashboard
                <span class="bottom-nav-dot"></span>
            </a>

            <a href="#" class="bottom-nav-item">
                <span class="bottom-nav-icon">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                </span>
                Centers
                <span class="bottom-nav-dot"></span>
            </a>

            <button class="bottom-nav-refresh" id="bnRefreshBtn" onclick="mobileRefresh()" aria-label="Refresh">
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

        <!-- Top bar: logo + name LEFT · right actions -->
        <header class="topbar">

            <!-- Left: round orange logo + name beside it -->
            <div class="topbar-brand">
                <div class="topbar-logo" aria-hidden="true">
                    <!-- Replace src with your actual logo path e.g. ../assets/img/logo.png -->
                    <img src="../img/mdrrmo.png" alt="MDRRMO Logo"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
                </div>
                <div class="topbar-brand-text">
                    <div class="topbar-title">Coordinator Dashboard</div>
                    <div class="topbar-subtitle">San Ildefonso, Bulacan — MDRRMO</div>
                </div>
            </div>

            <!-- Right: clock · refresh · hamburger (desktop only) -->
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

            <!-- ── SUMMARY STATS ─────────────────────────────── -->
            <?php
                $totalExpected  = array_sum(array_column($centers, 'expected_count'));
                $totalCenters   = count($centers);
                $activeCenters  = count(array_filter($centers, fn($c) => $c['status'] !== 'closed'));
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <!-- Building icon -->
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?php echo $totalCenters; ?></div>
                        <div class="stat-label">Assigned Centers</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <!-- Person walking icon -->
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="4" r="2"/><path d="M10 9h4l1 5-3 1v5"/><path d="M10 9l-1 5 3 1"/></svg>
                    </div>
                    <div>
                        <div class="stat-val" id="total-expected"><?php echo $totalExpected; ?></div>
                        <div class="stat-label">Expected Evacuees (en route)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <!-- Check-circle icon -->
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?php echo $activeCenters; ?></div>
                        <div class="stat-label">Active / Open Centers</div>
                    </div>
                </div>
            </div>

            <!-- ── CENTER LIST ──────────────────────────────── -->
            <div class="section-header">
                <div class="section-title">Center Overview</div>
                <span class="last-updated" id="last-updated">Auto-refreshes every 30s</span>
            </div>

            <?php if (!$centers): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 21V9h6v12"/><path d="M3 9h18"/></svg>
                    </div>
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

                        // Capacity
                        $maxCap   = (int)$c['max_capacity_people'];
                        $capPct   = $maxCap > 0 ? min(100, round($expected / $maxCap * 100)) : 0;
                        $capClass = $capPct >= 85 ? 'danger' : ($capPct >= 60 ? 'warning' : 'safe');
                    ?>
                    <li class="center-card" data-center-id="<?php echo $centerId; ?>">

                        <!-- Header row -->
                        <div class="center-card-header">
                            <div class="center-name-wrap">
                                <div class="center-name">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </div>
                                <div class="center-barangay">
                                    📍 <?php echo htmlspecialchars($c['barangay_name']); ?>
                                </div>
                            </div>

                            <span class="status-badge status-<?php echo htmlspecialchars($statusSlug); ?>">
                                <?php echo htmlspecialchars($c['status']); ?>
                            </span>

                            <span class="expected-pill <?php echo $pillClass; ?>"
                                  id="pill-<?php echo $centerId; ?>">
                                <span class="pill-icon">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="4" r="2"/><path d="M10 9h4l1 5-3 1v5"/><path d="M10 9l-1 5 3 1"/></svg>
                                </span>
                                <span class="pill-count pill-val"><?php echo $expected; ?></span>
                                expected
                            </span>

                            <a href="manage_center.php?id=<?php echo $centerId; ?>"
                               class="btn-manage">
                                Manage
                                <svg viewBox="0 0 16 16"><polyline points="6 3 11 8 6 13"/></svg>
                            </a>
                        </div>

                        <!-- Capacity bar -->
                        <?php if ($maxCap > 0): ?>
                        <div class="capacity-row">
                            <span class="capacity-label">Capacity</span>
                            <div class="cap-bar-track">
                                <div class="cap-bar <?php echo $capClass; ?>"
                                     id="capbar-<?php echo $centerId; ?>"
                                     style="width:<?php echo $capPct; ?>%"></div>
                            </div>
                            <span class="capacity-pct" id="cappct-<?php echo $centerId; ?>">
                                <?php echo $expected; ?> / <?php echo $maxCap; ?>
                                &nbsp;(<?php echo $capPct; ?>%)
                            </span>
                        </div>
                        <?php endif; ?>

                        <!-- Per-barangay breakdown -->
                        <?php if ($hasEvacuees): ?>
                        <div class="breakdown-section">
                            <div class="breakdown-label">Breakdown by Barangay of Origin</div>
                            <table class="breakdown-table">
                                <thead>
                                    <tr>
                                        <th>Barangay</th>
                                        <th style="text-align:center;">Citizens</th>
                                        <th style="min-width:90px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bdown as $brow):
                                        $pct = $maxCount > 0 ? round((int)$brow['citizen_count'] / $maxCount * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($brow['barangay_name']); ?></td>
                                        <td class="count-cell"><?php echo (int)$brow['citizen_count']; ?></td>
                                        <td>
                                            <div class="bar-wrap">
                                                <div class="bar-fill" style="width:<?php echo $pct; ?>%"></div>
                                            </div>
                                        </td>
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
// ── Live clock ──────────────────────────────────────────────────────────────
function updateClock() {
    const el = document.getElementById('topbar-clock');
    if (!el) return;
    const now = new Date();
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    el.textContent =
        days[now.getDay()] + ', ' +
        months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear() +
        '  ·  ' +
        now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

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

// ── Auto-refresh expected counts via AJAX ──────────────────────────────────
// Calls a lightweight JSON endpoint that returns counts per center.
// This keeps the page live without a full reload every 30 seconds.

const AUTO_REFRESH_INTERVAL = 30000; // 30 s
let   refreshTimer           = null;

function refreshCounts() {
    const btn      = document.getElementById('refreshBtn');
    const spinIcon = document.getElementById('spinIcon');

    btn.disabled = true;
    btn.classList.add('spinning');
    spinIcon.style.transform = '';

    fetch('expected_counts.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;

            let total = 0;
            data.centers.forEach(c => {
                const pill    = document.getElementById('pill-' + c.id);
                const capBar  = document.getElementById('capbar-' + c.id);
                const capPct  = document.getElementById('cappct-' + c.id);

                if (pill) {
                    const val = pill.querySelector('.pill-val');
                    if (val) val.textContent = c.expected_count;
                    pill.className = 'expected-pill ' + (c.expected_count > 0 ? 'has-evacuees' : 'no-evacuees');
                }

                if (capBar && c.max_capacity_people > 0) {
                    const pct = Math.min(100, Math.round(c.expected_count / c.max_capacity_people * 100));
                    capBar.style.width = pct + '%';
                    capBar.className   = 'cap-bar ' + (pct >= 85 ? 'danger' : (pct >= 60 ? 'warning' : 'safe'));
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

// ── Mobile bottom-nav refresh button ───────────────────────────────────────
function mobileRefresh() {
    const btn  = document.getElementById('bnRefreshBtn');
    const icon = document.getElementById('bnSpinIcon');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.classList.add('spinning');
    // Reuse the main refreshCounts logic, then re-enable the mobile button
    const origFinally = refreshCounts.toString();
    refreshCounts();
    // Re-enable after a short delay matching the fetch cycle
    const poll = setInterval(() => {
        const mainBtn = document.getElementById('refreshBtn');
        if (mainBtn && !mainBtn.disabled) {
            btn.disabled = false;
            btn.classList.remove('spinning');
            clearInterval(poll);
        }
    }, 200);
}

// Start auto-refresh loop
function startAutoRefresh() {
    clearInterval(refreshTimer);
    refreshTimer = setInterval(refreshCounts, AUTO_REFRESH_INTERVAL);
}

startAutoRefresh();
</script>
</body>
</html>