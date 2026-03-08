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
        $headName = trim($_POST['family_head_name'] ?? '');
        $barangayId = (int)($_POST['barangay_id'] ?? 0);
        $adults  = max(0, (int)($_POST['adults'] ?? 0));
        $children = max(0, (int)($_POST['children'] ?? 0));
        $seniors = max(0, (int)($_POST['seniors'] ?? 0));
        $pwds    = max(0, (int)($_POST['pwds'] ?? 0));
        $total   = $adults + $children + $seniors + $pwds;

        if ($headName === '') {
            $errors[] = 'Head of family name is required.';
        }
        if (!$barangayId) {
            $errors[] = 'Barangay is required.';
        }
        if ($total <= 0) {
            $errors[] = 'Please specify at least one member.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO evac_registrations
                (center_id, family_head_name, barangay_id, adults, children, seniors, pwds, total_members, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $centerId, $headName, $barangayId,
                $adults, $children, $seniors, $pwds, $total,
                $user['id']
            ]);

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
                $newVal = max(0, (int)$reg[$field] + $delta);
                $adults  = $field === 'adults'  ? $newVal : (int)$reg['adults'];
                $children = $field === 'children' ? $newVal : (int)$reg['children'];
                $seniors = $field === 'seniors' ? $newVal : (int)$reg['seniors'];
                $pwds    = $field === 'pwds'    ? $newVal : (int)$reg['pwds'];
                $total   = $adults + $children + $seniors + $pwds;

                $upd = $pdo->prepare("UPDATE evac_registrations
                                      SET adults = ?, children = ?, seniors = ?, pwds = ?, total_members = ?
                                      WHERE id = ?");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage center - <?php echo htmlspecialchars($center['name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Manage <?php echo htmlspecialchars($center['name']); ?></div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Coordinator)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <h2>Center status</h2>
        <p>
            Barangay: <?php echo htmlspecialchars($center['barangay_name']); ?><br>
            Capacity: <?php echo $occ['current']; ?> / <?php echo $occ['max']; ?> people
            (<?php echo round($occ['percent']); ?>%)
        </p>
        <p>
            Current status: <strong><?php echo htmlspecialchars($center['status']); ?></strong><br>
            When capacity reaches 100%, status is set to <strong>full</strong> and new arrivals
            should be redirected to another center.
        </p>
    </section>

    <section class="card">
        <h2>Add arriving family/group</h2>
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
                Head of family name
                <input type="text" name="family_head_name" required>
            </label>
            <label>
                Barangay
                <select name="barangay_id" required>
                    <option value="">-- Select barangay --</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>">
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="grid-2">
                <label>
                    Adults
                    <input type="number" name="adults" min="0" value="0">
                </label>
                <label>
                    Children
                    <input type="number" name="children" min="0" value="0">
                </label>
                <label>
                    Seniors
                    <input type="number" name="seniors" min="0" value="0">
                </label>
                <label>
                    PWDs
                    <input type="number" name="pwds" min="0" value="0">
                </label>
            </div>
            <button type="submit">Record arrival</button>
        </form>
    </section>

    <section class="card">
        <h2>Registered families/groups</h2>
        <?php if (!$registrations): ?>
            <p>No families have been registered yet.</p>
        <?php else: ?>
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
                        <td>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="adults">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit">-</button>
                            </form>
                            <?php echo (int)$r['adults']; ?>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="adults">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit">+</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="children">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit">-</button>
                            </form>
                            <?php echo (int)$r['children']; ?>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="children">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit">+</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="seniors">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit">-</button>
                            </form>
                            <?php echo (int)$r['seniors']; ?>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="seniors">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit">+</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="pwds">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit">-</button>
                            </form>
                            <?php echo (int)$r['pwds']; ?>
                            <form method="post" class="inline-adjust">
                                <input type="hidden" name="action" value="adjust">
                                <input type="hidden" name="reg_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="hidden" name="field" value="pwds">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit">+</button>
                            </form>
                        </td>
                        <td><?php echo (int)$r['total_members']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <p><a href="index.php">Back to coordinator dashboard</a></p>
</main>
</body>
</html>

