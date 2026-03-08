<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

require_once __DIR__ . '/../pages/center_helpers.php';

$user    = current_user();
$pdo     = db();
$centers = get_centers_with_occupancy();
$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

// Map barangay id to name for quick lookup
$barangayById = [];
foreach ($barangays as $b) {
    $barangayById[$b['id']] = $b['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Evacuation centers - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Evacuation centers</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <div class="card-header-row">
            <h2>Centers</h2>
            <a href="center_edit.php" class="btn-primary">+ Add center</a>
        </div>

        <?php if (!$centers): ?>
            <p>No centers defined yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Barangay</th>
                    <th>Status</th>
                    <th>Capacity (people)</th>
                    <th>Current evacuees</th>
                    <th>Utilization</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($centers as $c): ?>
                    <?php
                    $max = (int)$c['max_capacity_people'];
                    $cur = (int)$c['current_occupancy'];
                    $percent = $max > 0 ? round(($cur / $max) * 100) : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><?php echo htmlspecialchars($c['barangay_name']); ?></td>
                        <td>
                            <span class="status-pill status-<?php echo htmlspecialchars($c['status']); ?>">
                                <?php echo htmlspecialchars($c['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $max; ?></td>
                        <td><?php echo $cur; ?></td>
                        <td><?php echo $percent; ?>%</td>
                        <td><a href="center_edit.php?id=<?php echo (int)$c['id']; ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p><a href="index.php">Back to dashboard</a></p>
    </section>
</main>
</body>
</html>

