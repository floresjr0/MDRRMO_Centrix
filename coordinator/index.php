<?php
require_once __DIR__ . '/../pages/session.php';
require_login('coordinator');

$pdo  = db();
$user = current_user();

$stmt = $pdo->prepare("SELECT c.*, b.name AS barangay_name
                       FROM evacuation_centers c
                       JOIN barangays b ON b.id = c.barangay_id
                       WHERE c.coordinator_user_id = ?");
$stmt->execute([$user['id']]);
$centers = $stmt->fetchAll();
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
      max-width: 860px;
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

    .dashboard::before {
      content: 'Coordinator Dashboard';
      display: block;
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

    .card h2 {
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

    .card h2::before { content: '🏫'; font-size: 1rem; }

    .card > p {
      padding: 2rem 1.4rem;
      color: var(--text-muted);
      font-size: 0.90rem;
      text-align: center;
      line-height: 1.6;
    }

    /* ── LIST ── */
    .list {
      list-style: none;
      padding: 0.6rem 0.75rem 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .list li {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.5rem 0.75rem;
      padding: 0.85rem 1rem 0.85rem 1.3rem;
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      font-size: 0.88rem;
      color: var(--text-mid);
      position: relative;
      transition: border-color 0.18s, background 0.18s, box-shadow 0.18s, transform 0.18s;
    }

    .list li::before {
      content: '';
      position: absolute;
      left: 0; top: 20%; bottom: 20%;
      width: 4px;
      border-radius: 0 2px 2px 0;
      background: var(--yellow);
      transition: background 0.18s;
    }

    .list li:hover {
      border-color: var(--yellow-dark);
      background: var(--yellow-light);
      box-shadow: var(--shadow-sm);
      transform: translateX(2px);
    }

    .list li:hover::before { background: var(--yellow-dark); }

    .list li strong {
      font-family: var(--font-head);
      font-weight: 800;
      font-size: 0.95rem;
      color: var(--text);
      flex: 1 1 auto;
      min-width: 120px;
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

    .status-available { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .status-full      { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .status-closed    { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }

    /* Manage link */
    .list li a {
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

    .list li a::after { content: ' →'; }

    .list li a:hover {
      background: var(--text);
      color: var(--yellow);
      border-color: var(--text);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      transform: translateY(-1px);
    }

    .list li a:active { transform: translateY(0); }

    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
      .topbar { padding: 0 1rem; height: 56px; }
      .topbar-title { font-size: 1rem; }
      .dashboard { padding: 1.25rem 0.85rem 2.5rem; }
      .card h2 { font-size: 0.95rem; padding: 0.9rem 1rem 0.75rem; }
      .list { padding: 0.5rem 0.5rem 0.6rem; }
      .list li { padding: 0.75rem 0.85rem 0.75rem 1.1rem; font-size: 0.84rem; }
    }

    @media (max-width: 480px) {
      .topbar-title { font-size: 0.92rem; }
      .topbar-user span { display: none; }
      .dashboard::before { font-size: 1.3rem; }
      .list li { flex-direction: column; align-items: flex-start; gap: 0.45rem; }
      .list li a { margin-left: 0; width: 100%; justify-content: center; padding: 0.55rem 1rem; }
    }

    @media (min-width: 1024px) {
      .dashboard { padding: 2.5rem 1.5rem 4rem; }
      .list li { padding: 1rem 1.2rem 1rem 1.4rem; }
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
    <section class="card">
        <h2>Your Assigned Centers</h2>
        <?php if (!$centers): ?>
            <p>No evacuation centers are assigned to your account yet.<br>Please contact an admin.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($centers as $c):
                    $statusClass = 'status-' . strtolower(preg_replace('/\s+/', '-', $c['status']));
                ?>
                    <li>
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                        <?php echo htmlspecialchars($c['barangay_name']); ?>
                        <span class="status <?php echo htmlspecialchars($statusClass); ?>">
                            <?php echo htmlspecialchars($c['status']); ?>
                        </span>
                        <a href="manage_center.php?id=<?php echo (int)$c['id']; ?>">Manage</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

</body>
</html>