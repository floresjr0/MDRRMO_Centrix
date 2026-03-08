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
    <title>Coordinator dashboard - MDRRMO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Coordinator dashboard</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Coordinator)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <h2>Your assigned centers</h2>
        <?php if (!$centers): ?>
            <p>No evacuation centers are assigned to your account yet. Please contact an admin.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($centers as $c): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                        (<?php echo htmlspecialchars($c['barangay_name']); ?>) -
                        Status: <?php echo htmlspecialchars($c['status']); ?>
                        <a href="manage_center.php?id=<?php echo (int)$c['id']; ?>">Manage</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
</body>
</html>

