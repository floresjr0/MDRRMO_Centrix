<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$stmt = $pdo->query("SELECT * FROM disasters ORDER BY status = 'ongoing' DESC, level DESC, started_at DESC, id DESC");
$disasters = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disasters & events - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Disasters & events</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <div class="card-header-row">
            <h2>Recorded disasters</h2>
            <a class="btn-primary" href="disaster_edit.php">+ New disaster</a>
        </div>

        <?php if (!$disasters): ?>
            <p>No disasters have been recorded yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Type</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Title</th>
                    <th>Start</th>
                    <th>End</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($disasters as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(ucfirst($d['type'])); ?></td>
                        <td><?php echo (int)$d['level']; ?></td>
                        <td><?php echo htmlspecialchars($d['status']); ?></td>
                        <td><?php echo htmlspecialchars($d['title']); ?></td>
                        <td><?php echo htmlspecialchars($d['started_at']); ?></td>
                        <td><?php echo htmlspecialchars($d['ended_at']); ?></td>
                        <td>
                            <a href="disaster_edit.php?id=<?php echo (int)$d['id']; ?>">Edit</a>
                        </td>
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

