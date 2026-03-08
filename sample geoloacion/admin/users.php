<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role,
           u.is_active, u.is_email_verified,
           b.name AS barangay_name
    FROM users u
    JOIN barangays b ON b.id = u.barangay_id
    ORDER BY u.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users - Admin</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Users</div>
    <div class="topbar-user">
        <a href="index.php">Dashboard</a>
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>
<main class="dashboard admin-dashboard">
    <section class="card">
        <h2>All accounts</h2>
        <p>
            <a href="create_coordinator.php" class="btn-primary">Add coordinator</a>
        </p>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Email</th>
                    <th>Role</th><th>Barangay</th>
                    <th>Verified</th><th>Active</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                        <td><?php echo htmlspecialchars($u['barangay_name']); ?></td>
                        <td><?php echo $u['is_email_verified'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>