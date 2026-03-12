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
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>

    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --yellow:        #f5c800;
      --yellow-dark:   #d4a900;
      --yellow-deeper: #b38d00;
      --yellow-light:  #fff8d6;
      --yellow-pale:   #fffbe8;
      --white:         #ffffff;
      --off-white:     #fafaf7;
      --text:          #1c1a0f;
      --text-mid:      #4a4530;
      --text-muted:    #8a8060;
      --border:        #ede8cc;
      --shadow-sm:     0 1px 4px rgba(180,150,0,0.10);
      --shadow-md:     0 4px 18px rgba(180,150,0,0.14);
      --shadow-lg:     0 10px 40px rgba(180,150,0,0.18);
      --radius-md:     14px;
      --radius-lg:     20px;
      --font-head:     'Nunito', sans-serif;
      --font-body:     'DM Sans', sans-serif;
    }

    html, body {
      min-height: 100%;
      background: var(--off-white);
      font-family: var(--font-body);
      font-size: 16px;
      color: var(--text);
      -webkit-font-smoothing: antialiased;
      line-height: 1.5;
    }

    /* ── TOPBAR ── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 0 1.5rem;
      height: 62px;
      background: var(--yellow);
      box-shadow: 0 2px 12px rgba(180,150,0,0.22);
      border-bottom: 3px solid var(--yellow-dark);
      overflow: hidden;
    }

    .topbar::before {
      content: '';
      position: absolute;
      top: 0; right: 0;
      width: 180px;
      height: 100%;
      background: repeating-linear-gradient(
        -55deg,
        transparent,
        transparent 8px,
        rgba(255,255,255,0.12) 8px,
        rgba(255,255,255,0.12) 16px
      );
      pointer-events: none;
    }

    .topbar-title {
      font-family: var(--font-head);
      font-size: 1.15rem;
      font-weight: 900;
      color: var(--text);
      letter-spacing: -0.01em;
      white-space: nowrap;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .topbar-title::before {
      content: '🛡️';
      font-size: 1.1rem;
    }

    .topbar-user {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.82rem;
      font-weight: 500;
      color: var(--text-mid);
      flex-shrink: 0;
    }

    .topbar-user a {
      display: inline-flex;
      align-items: center;
      padding: 0.38rem 0.85rem;
      background: var(--white);
      border: 1.5px solid var(--yellow-dark);
      border-radius: 50px;
      color: var(--text);
      font-weight: 700;
      font-size: 0.78rem;
      text-decoration: none;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
      white-space: nowrap;
    }

    .topbar-user a::before { content: '↩ '; }

    .topbar-user a:hover {
      background: var(--text);
      color: var(--yellow);
      border-color: var(--text);
      box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    }

    /* ── MAIN ── */
    .dashboard {
      max-width: 900px;
      margin: 0 auto;
      padding: 2rem 1.25rem 3rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      animation: fadeUp 0.45s cubic-bezier(0.22,1,0.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .dashboard-heading {
      font-family: var(--font-head);
      font-size: clamp(1.4rem, 4vw, 2rem);
      font-weight: 900;
      color: var(--text);
      letter-spacing: -0.02em;
      padding-bottom: 0.25rem;
      border-bottom: 3px solid var(--yellow);
    }

    /* ── CARD ── */
    .card {
      background: var(--white);
      border-radius: var(--radius-lg);
      border: 1.5px solid var(--border);
      box-shadow: var(--shadow-md);
      overflow: hidden;
      transition: box-shadow 0.2s;
    }

    .card:hover { box-shadow: var(--shadow-lg); }

    .card-header {
      font-family: var(--font-head);
      font-size: 1.0rem;
      font-weight: 800;
      color: var(--text);
      padding: 1.1rem 1.4rem 0.9rem;
      background: var(--yellow-pale);
      border-bottom: 1.5px solid var(--border);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-icon { font-size: 1.1rem; }

    .card > p.empty {
      padding: 2rem 1.4rem;
      color: var(--text-muted);
      font-size: 0.90rem;
      text-align: center;
      line-height: 1.6;
    }

    /* ── CENTER ITEM ── */
    .center-list {
      list-style: none;
      padding: 0.6rem 0.75rem 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .center-item {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      overflow: hidden;
      position: relative;
      transition: border-color 0.18s, box-shadow 0.18s;
    }

    .center-item::before {
      content: '';
      position: absolute;
      left: 0; top: 0; bottom: 0;
      width: 4px;
      background: var(--yellow);
    }

    .center-item:hover {
      border-color: var(--yellow-dark);
      box-shadow: var(--shadow-sm);
    }

    .center-main {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.5rem 0.75rem;
      padding: 0.85rem 1rem 0.85rem 1.3rem;
      font-size: 0.88rem;
      color: var(--text-mid);
    }

    .center-name {
      font-family: var(--font-head);
      font-weight: 800;
      font-size: 0.95rem;
      color: var(--text);
      flex: 1 1 auto;
      min-width: 120px;
    }

    .center-barangay {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    /* Status badge */
    .status {
      display: inline-flex;
      align-items: center;
      padding: 0.22rem 0.65rem;
      border-radius: 50px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      white-space: nowrap;
      background: var(--yellow-light);
      color: var(--yellow-deeper);
      border: 1px solid var(--yellow-dark);
    }

    .status-available  { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .status-near_capacity { background: #fef9c3; color: #854d0e; border-color: #fde047; }
    .status-full       { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .status-closed     { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
    .status-temp_shelter { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }

    /* Expected count pill */
    .expected-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.28rem 0.85rem;
      border-radius: 50px;
      font-size: 0.78rem;
      font-weight: 800;
      font-family: var(--font-head);
      background: #fff3cd;
      color: #7a5200;
      border: 1.5px solid #f5c800;
      white-space: nowrap;
    }

    .expected-pill .pill-icon { font-size: 0.85rem; }

    .expected-pill.has-evacuees {
      background: linear-gradient(135deg, #fff3cd, #ffe082);
      color: #5a3a00;
      border-color: var(--yellow-dark);
      box-shadow: 0 2px 8px rgba(180,150,0,0.2);
    }

    .expected-pill.no-evacuees {
      background: #f3f4f6;
      color: #9ca3af;
      border-color: #e5e7eb;
    }

    /* Manage link */
    .btn-manage {
      display: inline-flex;
      align-items: center;
      padding: 0.40rem 1rem;
      background: var(--yellow);
      border: 1.5px solid var(--yellow-dark);
      border-radius: 50px;
      color: var(--text);
      font-family: var(--font-head);
      font-weight: 800;
      font-size: 0.78rem;
      letter-spacing: 0.03em;
      text-decoration: none;
      white-space: nowrap;
      margin-left: auto;
      flex-shrink: 0;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.12s;
    }

    .btn-manage::after { content: ' →'; }

    .btn-manage:hover {
      background: var(--text);
      color: var(--yellow);
      border-color: var(--text);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      transform: translateY(-1px);
    }

    /* ── BREAKDOWN TABLE ── */
    .breakdown-section {
      padding: 0 1.3rem 1rem;
      border-top: 1px dashed var(--border);
      background: var(--yellow-pale);
    }

    .breakdown-label {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      padding: 0.6rem 0 0.4rem;
    }

    .breakdown-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.80rem;
    }

    .breakdown-table th {
      text-align: left;
      font-weight: 700;
      color: var(--text-mid);
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 0.3rem 0.5rem;
      border-bottom: 1px solid var(--border);
    }

    .breakdown-table td {
      padding: 0.35rem 0.5rem;
      color: var(--text-mid);
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }

    .breakdown-table tr:last-child td { border-bottom: none; }

    .breakdown-table .count-cell {
      text-align: center;
      font-weight: 800;
      font-family: var(--font-head);
      color: var(--text);
    }

    .breakdown-bar-wrap {
      width: 100%;
      height: 6px;
      background: var(--border);
      border-radius: 3px;
      overflow: hidden;
      min-width: 60px;
    }

    .breakdown-bar {
      height: 100%;
      background: linear-gradient(90deg, var(--yellow-dark), var(--yellow));
      border-radius: 3px;
      transition: width 0.5s ease;
    }

    /* ── CAPACITY METER ── */
    .capacity-row {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.5rem 1.3rem 0.75rem;
      font-size: 0.78rem;
      color: var(--text-muted);
    }

    .capacity-label { white-space: nowrap; flex-shrink: 0; font-size: 0.72rem; }

    .cap-bar-wrap {
      flex: 1;
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
    }

    .cap-bar {
      height: 100%;
      border-radius: 4px;
      transition: width 0.5s ease;
    }

    .cap-bar.safe     { background: linear-gradient(90deg, #4ade80, #22c55e); }
    .cap-bar.warning  { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
    .cap-bar.danger   { background: linear-gradient(90deg, #f87171, #ef4444); }

    .capacity-pct {
      font-weight: 700;
      font-family: var(--font-head);
      font-size: 0.80rem;
      color: var(--text);
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* ── LAST UPDATED ── */
    .refresh-row {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.5rem;
      padding: 0.6rem 1rem 0.1rem;
      font-size: 0.72rem;
      color: var(--text-muted);
    }

    .refresh-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.28rem 0.75rem;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: 50px;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-mid);
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
      font-family: var(--font-body);
    }

    .refresh-btn:hover {
      background: var(--yellow-light);
      border-color: var(--yellow-dark);
    }

    .refresh-btn.spinning .spin-icon { animation: spin 0.7s linear infinite; }

    @keyframes spin { to { transform: rotate(360deg); } }

    .spin-icon { display: inline-block; }

    /* ── SUMMARY BAR ── */
    .summary-bar {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .summary-stat {
      flex: 1 1 auto;
      min-width: 140px;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 0.9rem 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      box-shadow: var(--shadow-sm);
    }

    .summary-icon { font-size: 1.8rem; flex-shrink: 0; }

    .summary-val {
      font-family: var(--font-head);
      font-size: 1.6rem;
      font-weight: 900;
      color: var(--text);
      line-height: 1;
    }

    .summary-desc {
      font-size: 0.72rem;
      color: var(--text-muted);
      margin-top: 2px;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
      .topbar { padding: 0 1rem; height: 56px; }
      .topbar-title { font-size: 1rem; }
      .dashboard { padding: 1.25rem 0.85rem 2.5rem; }
      .center-main { font-size: 0.84rem; }
      .btn-manage { margin-left: 0; width: 100%; justify-content: center; padding: 0.55rem 1rem; }
    }

    @media (max-width: 480px) {
      .topbar-title { font-size: 0.92rem; }
      .topbar-user span { display: none; }
      .dashboard-heading { font-size: 1.3rem; }
      .center-main { flex-direction: column; align-items: flex-start; gap: 0.45rem; }
      .summary-bar { gap: 0.5rem; }
      .summary-stat { padding: 0.75rem; }
    }

    @media (min-width: 1024px) {
      .dashboard { padding: 2.5rem 1.5rem 4rem; }
      .center-main { padding: 1rem 1.2rem 1rem 1.4rem; }
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--off-white); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--yellow-dark); }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-title">MDRRMO San Ildefonso</div>
    <div class="topbar-user">
        <span><?php echo htmlspecialchars($user['full_name']); ?> &mdash; Coordinator</span>
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">

    <h1 class="dashboard-heading">Coordinator Dashboard</h1>

    <!-- ── SUMMARY STATS ─────────────────────────────────── -->
    <?php
        $totalExpected  = array_sum(array_column($centers, 'expected_count'));
        $totalCenters   = count($centers);
        $activeCenters  = count(array_filter($centers, fn($c) => $c['status'] !== 'closed'));
    ?>
    <div class="summary-bar">
        <div class="summary-stat">
            <div class="summary-icon">🏫</div>
            <div>
                <div class="summary-val"><?php echo $totalCenters; ?></div>
                <div class="summary-desc">Assigned Centers</div>
            </div>
        </div>
        <div class="summary-stat">
            <div class="summary-icon">🚶</div>
            <div>
                <div class="summary-val" id="total-expected"><?php echo $totalExpected; ?></div>
                <div class="summary-desc">Expected Evacuees (en route)</div>
            </div>
        </div>
        <div class="summary-stat">
            <div class="summary-icon">✅</div>
            <div>
                <div class="summary-val"><?php echo $activeCenters; ?></div>
                <div class="summary-desc">Active / Open Centers</div>
            </div>
        </div>
    </div>

    <!-- ── CENTER LIST ───────────────────────────────────── -->
    <section class="card">
        <div class="card-header">
            <span class="card-icon">🏫</span>
            Your Assigned Centers
        </div>

        <div class="refresh-row">
            <span id="last-updated">Auto-refreshes every 30 s</span>
            <button class="refresh-btn" id="refreshBtn" onclick="refreshCounts()">
                <span class="spin-icon" id="spinIcon">⟳</span> Refresh
            </button>
        </div>

        <?php if (!$centers): ?>
            <p class="empty">No evacuation centers are assigned to your account yet.<br>Please contact an admin.</p>
        <?php else: ?>
            <ul class="center-list" id="centerList">
                <?php foreach ($centers as $c):
                    $centerId      = (int)$c['id'];
                    $expected      = (int)$c['expected_count'];
                    $statusClass   = 'status-' . strtolower(preg_replace('/\s+/', '-', $c['status']));
                    $hasEvacuees   = $expected > 0;
                    $pillClass     = $hasEvacuees ? 'has-evacuees' : 'no-evacuees';
                    $bdown         = $breakdown[$centerId] ?? [];
                    $maxCount      = !empty($bdown) ? max(array_column($bdown, 'citizen_count')) : 1;

                    // Capacity usage including expected
                    $maxCap        = (int)$c['max_capacity_people'];
                    $capPct        = $maxCap > 0 ? min(100, round($expected / $maxCap * 100)) : 0;
                    $capClass      = $capPct >= 85 ? 'danger' : ($capPct >= 60 ? 'warning' : 'safe');
                ?>
                <li class="center-item" data-center-id="<?php echo $centerId; ?>">
                    <div class="center-main">
                        <strong class="center-name"><?php echo htmlspecialchars($c['name']); ?></strong>
                        <span class="center-barangay"><?php echo htmlspecialchars($c['barangay_name']); ?></span>
                        <span class="status <?php echo htmlspecialchars($statusClass); ?>">
                            <?php echo htmlspecialchars($c['status']); ?>
                        </span>
                        <span class="expected-pill <?php echo $pillClass; ?>"
                              id="pill-<?php echo $centerId; ?>">
                            <span class="pill-icon">🚶</span>
                            <span class="pill-val"><?php echo $expected; ?></span>
                            expected
                        </span>
                        <a href="manage_center.php?id=<?php echo $centerId; ?>"
                           class="btn-manage">Manage</a>
                    </div>

                    <!-- Capacity bar -->
                    <?php if ($maxCap > 0): ?>
                    <div class="capacity-row">
                        <span class="capacity-label">expected evacuees</span>
                        <div class="cap-bar-wrap">
                            <div class="cap-bar <?php echo $capClass; ?>"
                                 id="capbar-<?php echo $centerId; ?>"
                                 style="width:<?php echo $capPct; ?>%"></div>
                        </div>
                        <span class="capacity-pct" id="cappct-<?php echo $centerId; ?>">
                            <?php echo $expected; ?> / <?php echo $maxCap; ?>
                            (<?php echo $capPct; ?>%)
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
                                    <th style="text-align:center;">Expected Citizens</th>
                                    <th style="min-width:80px;"></th>
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
                                        <div class="breakdown-bar-wrap">
                                            <div class="breakdown-bar" style="width:<?php echo $pct; ?>%"></div>
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
    </section>

</main>

<script>
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

// Start auto-refresh loop
function startAutoRefresh() {
    clearInterval(refreshTimer);
    refreshTimer = setInterval(refreshCounts, AUTO_REFRESH_INTERVAL);
}

startAutoRefresh();
</script>
</body>
</html>