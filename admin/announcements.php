<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$stmt = $pdo->query("SELECT a.*, d.title AS disaster_title
                     FROM announcements a
                     LEFT JOIN disasters d ON d.id = a.disaster_id
                     ORDER BY a.is_pinned DESC, a.published_at DESC, a.id DESC");
$announcements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Announcements</div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
        <div class="card-header-row">
            <h2>All announcements</h2>
            <a class="btn-primary" href="announcement_edit.php">+ New announcement</a>
        </div>

        <?php if (!$announcements): ?>
            <p>No announcements have been created yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Disaster</th>
                    <th>Pinned</th>
                    <th>Published at</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($announcements as $a): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($a['title']); ?></td>
                        <td><?php echo htmlspecialchars($a['type']); ?></td>
                        <td><?php echo htmlspecialchars($a['disaster_title'] ?? '—'); ?></td>
                        <td><?php echo $a['is_pinned'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($a['published_at']); ?></td>
                        <td>
                            <a href="announcement_edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
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

