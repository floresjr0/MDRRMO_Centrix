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
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
    /* ==============================================
       MDRRMO – Manage Center
       Yellow & White — Responsive
       ============================================== */

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

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
      --red:           #dc2626;
      --green:         #16a34a;
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
      width: 180px; height: 100%;
      background: repeating-linear-gradient(
        -55deg, transparent, transparent 8px,
        rgba(255,255,255,0.12) 8px, rgba(255,255,255,0.12) 16px
      );
      pointer-events: none;
    }

    .topbar-title {
      font-family: var(--font-head);
      font-size: 1.05rem;
      font-weight: 900;
      color: var(--text);
      letter-spacing: -0.01em;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      display: flex;
      align-items: center;
      gap: 0.45rem;
    }

    .topbar-title::before { content: '🏫'; font-size: 1rem; }

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

    /* ── DASHBOARD ── */
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

    /* Back link */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.84rem;
      font-weight: 700;
      color: var(--text-mid);
      text-decoration: none;
      font-family: var(--font-head);
      transition: color 0.15s;
    }
    .back-link::before { content: '← '; }
    .back-link:hover { color: var(--yellow-deeper); }

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
      padding: 1.0rem 1.4rem 0.85rem;
      background: var(--yellow-pale);
      border-bottom: 1.5px solid var(--border);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-body {
      padding: 1.2rem 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
    }

    .card-body p {
      font-size: 0.88rem;
      color: var(--text-mid);
      line-height: 1.6;
    }

    .card-body p strong {
      color: var(--text);
      font-weight: 700;
    }

    /* Occupancy bar */
    .occ-bar-wrap {
      margin-top: 0.4rem;
    }
    .occ-bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-mid);
      margin-bottom: 0.3rem;
    }
    .occ-bar-track {
      height: 10px;
      background: var(--border);
      border-radius: 99px;
      overflow: hidden;
    }
    .occ-bar-fill {
      height: 100%;
      border-radius: 99px;
      transition: width 0.6s ease;
    }

    /* Status pill */
    .status-pill {
      display: inline-flex;
      align-items: center;
      padding: 0.22rem 0.75rem;
      border-radius: 50px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .status-available { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .status-full      { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .status-closed    { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    /* ── ERRORS ── */
    .auth-errors {
      margin: 0 1.4rem 0;
      padding: 0.75rem 1rem;
      background: #fff1f2;
      border: 1.5px solid #fecaca;
      border-radius: var(--radius-md);
      margin-top: 1rem;
    }
    .auth-errors ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    .auth-errors li {
      font-size: 0.84rem;
      color: #991b1b;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .auth-errors li::before { content: '⚠'; }

    /* ── FORM ── */
    .auth-form {
      padding: 1.2rem 1.4rem 1.4rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .auth-form label {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      font-size: 0.80rem;
      font-weight: 700;
      color: var(--text-mid);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .auth-form input[type="text"],
    .auth-form input[type="number"],
    .auth-form select {
      font-family: var(--font-body);
      font-size: 0.92rem;
      font-weight: 500;
      color: var(--text);
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 0.6rem 0.9rem;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
      width: 100%;
    }

    .auth-form input:focus,
    .auth-form select:focus {
      border-color: var(--yellow-dark);
      box-shadow: 0 0 0 3px rgba(245,200,0,0.18);
      background: var(--white);
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
    }

    .auth-form button[type="submit"] {
      align-self: flex-start;
      padding: 0.65rem 1.6rem;
      background: var(--yellow);
      border: 1.5px solid var(--yellow-dark);
      border-radius: 50px;
      font-family: var(--font-head);
      font-size: 0.88rem;
      font-weight: 800;
      color: var(--text);
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.12s;
      letter-spacing: 0.03em;
    }

    .auth-form button[type="submit"]:hover {
      background: var(--text);
      color: var(--yellow);
      border-color: var(--text);
      box-shadow: 0 4px 14px rgba(0,0,0,0.15);
      transform: translateY(-1px);
    }

    .auth-form button[type="submit"]:active { transform: translateY(0); }

    /* ── TABLE ── */
    .table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.84rem;
      min-width: 560px;
    }

    .table thead tr {
      background: var(--yellow-pale);
      border-bottom: 2px solid var(--border);
    }

    .table th {
      font-family: var(--font-head);
      font-weight: 800;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-mid);
      padding: 0.75rem 0.9rem;
      text-align: left;
      white-space: nowrap;
    }

    .table tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }

    .table tbody tr:last-child { border-bottom: none; }
    .table tbody tr:hover { background: var(--yellow-pale); }

    .table td {
      padding: 0.65rem 0.9rem;
      color: var(--text-mid);
      vertical-align: middle;
    }

    .table td:first-child {
      font-family: var(--font-head);
      font-weight: 700;
      color: var(--text);
    }

    /* Total column */
    .table td:last-child {
      font-family: var(--font-head);
      font-weight: 800;
      color: var(--text);
    }

    /* ── INLINE ADJUST ── */
    .adjust-cell {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      white-space: nowrap;
    }

    .inline-adjust {
      display: inline-flex;
    }

    .inline-adjust button {
      width: 26px;
      height: 26px;
      border-radius: 50%;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--text);
      font-size: 0.95rem;
      font-weight: 800;
      line-height: 1;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.13s, border-color 0.13s, transform 0.1s;
      font-family: var(--font-head);
    }

    .inline-adjust button:hover {
      background: var(--yellow);
      border-color: var(--yellow-dark);
      transform: scale(1.12);
    }

    .adjust-val {
      min-width: 20px;
      text-align: center;
      font-family: var(--font-head);
      font-weight: 700;
      font-size: 0.88rem;
      color: var(--text);
    }

    /* No data */
    .no-data {
      padding: 2rem 1.4rem;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.88rem;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 680px) {
      .topbar { padding: 0 1rem; height: 56px; }
      .topbar-title { font-size: 0.90rem; }
      .dashboard { padding: 1.1rem 0.85rem 2.5rem; }
      .card h2 { font-size: 0.92rem; padding: 0.85rem 1rem 0.75rem; }
      .card-body { padding: 1rem; }
      .auth-form { padding: 1rem 1rem 1.1rem; }
      .grid-2 { grid-template-columns: 1fr 1fr; gap: 0.6rem; }
      .table { font-size: 0.80rem; }
      .table th, .table td { padding: 0.55rem 0.65rem; }
    }

    @media (max-width: 440px) {
      .topbar-user span { display: none; }
      .grid-2 { grid-template-columns: 1fr 1fr; }
      .auth-form button[type="submit"] { width: 100%; justify-content: center; text-align: center; }
    }

    @media (min-width: 1024px) {
      .dashboard { padding: 2.5rem 1.5rem 4rem; }
    }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--off-white); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--yellow-dark); }
    </style>
</head>
<body>

<header class="topbar">
    <div class="topbar-title"><?php echo htmlspecialchars($center['name']); ?></div>
    <div class="topbar-user">
        <span><?php echo htmlspecialchars($user['full_name']); ?> &mdash; Coordinator</span>
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">

    <!-- CENTER STATUS -->
    <section class="card">
        <h2>Center Status</h2>
        <div class="card-body">
            <p>
                <strong>Barangay:</strong> <?php echo htmlspecialchars($center['barangay_name']); ?>
            </p>
            <p>
                <strong>Status:</strong>
                <?php
                $sc = 'status-' . strtolower(preg_replace('/\s+/', '-', $center['status']));
                ?>
                <span class="status-pill <?php echo htmlspecialchars($sc); ?>">
                    <?php echo htmlspecialchars($center['status']); ?>
                </span>
            </p>
            <div class="occ-bar-wrap">
                <div class="occ-bar-label">
                    <span>Occupancy</span>
                    <span><?php echo $occ['current']; ?> / <?php echo $occ['max']; ?> people (<?php echo $pct; ?>%)</span>
                </div>
                <div class="occ-bar-track">
                    <div class="occ-bar-fill" style="width:<?php echo min(100,$pct); ?>%; background:<?php echo $barColor; ?>;"></div>
                </div>
            </div>
            <p style="font-size:0.80rem; color:var(--text-muted); margin-top:0.2rem;">
                When capacity reaches 100%, status is set to <strong>full</strong> and new arrivals should be redirected to another center.
            </p>
        </div>
    </section>

    <!-- ADD FAMILY FORM -->
    <section class="card">
        <h2>Add Arriving Family / Group</h2>

        <?php if ($errors): ?>
            <div class="auth-errors">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="action" value="add_family">

            <label>
                Head of Family Name
                <input type="text" name="family_head_name" required
                       value="<?php echo htmlspecialchars($_POST['family_head_name'] ?? ''); ?>">
            </label>

            <label>
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
                <label>
                    Adults
                    <input type="number" name="adults" min="0" value="<?php echo (int)($_POST['adults'] ?? 0); ?>">
                </label>
                <label>
                    Children
                    <input type="number" name="children" min="0" value="<?php echo (int)($_POST['children'] ?? 0); ?>">
                </label>
                <label>
                    Seniors
                    <input type="number" name="seniors" min="0" value="<?php echo (int)($_POST['seniors'] ?? 0); ?>">
                </label>
                <label>
                    PWDs
                    <input type="number" name="pwds" min="0" value="<?php echo (int)($_POST['pwds'] ?? 0); ?>">
                </label>
            </div>

            <button type="submit">Record Arrival</button>
        </form>
    </section>

    <!-- REGISTRATIONS TABLE -->
    <section class="card">
        <h2>Registered Families / Groups</h2>
        <?php if (!$registrations): ?>
            <p class="no-data">No families have been registered yet.</p>
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
                            <td><?php echo htmlspecialchars($r['family_head_name']); ?></td>
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

                            <td><?php echo (int)$r['total_members']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <a href="index.php" class="back-link">Back to Coordinator Dashboard</a>

</main>
</body>
</html>