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
    <style>
        /* ========== FULL STYLES (unchanged from original manage_center) ========== */
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
        
        /* ===== BOTTOM NAV (UPDATED) ===== */
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
        .occ-note { font-size: 0.72rem; color: var(--text-muted); line-height: 1.5; }
        .error-box { margin: 0 1.4rem; padding: 0.75rem 1rem; background: var(--red-bg); border: 1px solid var(--red-border); border-radius: var(--radius); display: flex; flex-direction: column; gap: 0.3rem; }
        .error-box li { list-style: none; font-size: 0.82rem; color: var(--red); font-weight: 600; display: flex; align-items: center; gap: 0.4rem; }
        .error-box li::before { content: '⚠'; font-size: 0.75rem; }
        .arrival-queue-empty { display: flex; align-items: center; gap: 10px; padding: 18px 20px; background: #f9fafb; border-radius: 10px; color: #9ca3af; font-size: 14px; }
        .arrival-queue-empty svg { flex-shrink: 0; opacity: .5; }
        .checkin-toast { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: #dcfce7; border: 1px solid #86efac; border-radius: 10px; color: #166534; font-size: 14px; font-weight: 500; margin-bottom: 16px; animation: fadeOut 4s forwards; }
        @keyframes fadeOut { 0%,70% { opacity: 1; } 100% { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; } }
        .app-arrivals-grid { display: flex; flex-direction: column; gap: 14px; }
        .app-arrival-card { border: 2px solid #fed7aa; border-radius: 14px; background: #fff7ed; overflow: hidden; transition: border-color .2s; }
        .app-arrival-card:hover { border-color: #fb923c; }
        .app-arrival-card-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px; gap: 10px; flex-wrap: wrap; }
        .app-arrival-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .app-arrival-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; font-size: 17px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .app-arrival-name { font-weight: 700; font-size: 15px; color: #1a1a2e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .app-arrival-meta { font-size: 12px; color: #78716c; margin-top: 1px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .app-badge-nav { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 99px; background: #dbeafe; color: #1d4ed8; font-size: 11px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
        .app-arrival-members { padding: 0 16px 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        @media (max-width: 480px) { .app-arrival-members { grid-template-columns: 1fr; } }
        .app-member-row { display: flex; align-items: center; justify-content: space-between; background: #fff; border: 1px solid #fed7aa; border-radius: 10px; padding: 8px 12px; gap: 8px; }
        .app-member-label { font-size: 13px; color: #57534e; font-weight: 500; flex: 1; }
        .app-member-controls { display: flex; align-items: center; gap: 6px; }
        .app-member-controls button { width: 28px; height: 28px; border-radius: 50%; border: 1.5px solid #fed7aa; background: #fff7ed; color: #ea580c; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s, border-color .15s; line-height: 1; padding: 0; }
        .app-member-controls button:hover { background: #f97316; border-color: #f97316; color: #fff; }
        .app-member-val { font-size: 15px; font-weight: 700; color: #1a1a2e; min-width: 22px; text-align: center; }
        .app-arrival-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px 14px; border-top: 1px solid #fed7aa; gap: 10px; flex-wrap: wrap; }
        .app-total-wrap { display: flex; align-items: baseline; gap: 4px; }
        .app-total-num { font-size: 24px; font-weight: 800; color: #ea580c; line-height: 1; }
        .app-total-label { font-size: 12px; color: #78716c; font-weight: 500; }
        .btn-record-arrival { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; border: none; border-radius: 99px; font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .2s, transform .1s; letter-spacing: .2px; }
        .btn-record-arrival:hover { opacity: .9; }
        .btn-record-arrival:active { transform: scale(.97); }
        .profile-match { display: flex; align-items: center; gap: 5px; font-size: 11.5px; padding: 3px 8px 3px 6px; border-radius: 99px; font-weight: 600; }
        .profile-match.match-ok { background: #dcfce7; color: #166534; }
        .profile-match.match-diff { background: #fef9c3; color: #854d0e; }
        .en-route-badge { display: inline-flex; align-items: center; gap: 5px; background: #fff7ed; border: 1.5px solid #fed7aa; color: #c2410c; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
        @media (max-width: 768px) { .hamburger-btn { display: none; } .bottom-nav { display: flex; } .main { padding-bottom: var(--bottom-nav-h); } .topbar { padding: 0 1rem; height: 58px; } .dashboard { padding: 1.25rem 1rem 2rem; gap: 1.25rem; } .page-heading { font-size: 1.3rem; } .card-body { padding: 1rem; } .card-header { padding: 0.85rem 1rem; } }
        @media (max-width: 480px) { .app-arrival-members { grid-template-columns: 1fr; } }
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

    <!-- BOTTOM NAVIGATION (UPDATED) -->
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