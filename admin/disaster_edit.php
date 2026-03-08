<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$disaster = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM disasters WHERE id = ?");
    $stmt->execute([$id]);
    $disaster = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'] ?? 'typhoon';
    $level  = (int)($_POST['level'] ?? 1);
    $status = $_POST['status'] ?? 'planned';
    $title  = trim($_POST['title'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $start  = trim($_POST['started_at'] ?? '');
    $end    = trim($_POST['ended_at'] ?? '');

    $validTypes = ['typhoon','flood','earthquake','heat','landslide','other'];
    $validStatus = ['planned','ongoing','resolved'];

    if (!in_array($type, $validTypes, true)) {
        $errors[] = 'Invalid disaster type.';
    }
    if ($level < 1 || $level > 5) {
        $errors[] = 'Level must be between 1 and 5.';
    }
    if (!in_array($status, $validStatus, true)) {
        $errors[] = 'Invalid status.';
    }
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!$errors) {
        if ($id && $disaster) {
            $stmt = $pdo->prepare("UPDATE disasters
                                   SET type = ?, level = ?, status = ?, title = ?,
                                       description = ?, started_at = ?, ended_at = ?
                                   WHERE id = ?");
            $stmt->execute([$type, $level, $status, $title, $desc ?: null, $start ?: null, $end ?: null, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO disasters
                                   (type, level, status, title, description, started_at, ended_at)
                                   VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$type, $level, $status, $title, $desc ?: null, $start ?: null, $end ?: null]);
            $id = (int)$pdo->lastInsertId();
        }

        header('Location: disasters.php');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit disaster' : 'New disaster'; ?> - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">
        <?php echo $id ? 'Edit disaster/event' : 'New disaster/event'; ?>
    </div>
    <div class="topbar-user">
        <?php echo htmlspecialchars($user['full_name']); ?> (Admin)
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>

<main class="dashboard">
    <section class="card">
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
            <label>
                Type
                <?php
                $selectedType = $_POST['type'] ?? ($disaster['type'] ?? 'typhoon');
                ?>
                <select name="type">
                    <?php foreach (['typhoon','flood','earthquake','heat','landslide','other'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $selectedType === $opt ? 'selected' : ''; ?>>
                            <?php echo ucfirst($opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Level (1–5)
                <input type="number" name="level" min="1" max="5" required
                       value="<?php echo htmlspecialchars($_POST['level'] ?? ($disaster['level'] ?? 1)); ?>">
            </label>
            <label>
                Status
                <?php
                $selectedStatus = $_POST['status'] ?? ($disaster['status'] ?? 'planned');
                ?>
                <select name="status">
                    <?php foreach (['planned','ongoing','resolved'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $selectedStatus === $st ? 'selected' : ''; ?>>
                            <?php echo ucfirst($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Title
                <input type="text" name="title" required
                       value="<?php echo htmlspecialchars($_POST['title'] ?? ($disaster['title'] ?? '')); ?>">
            </label>
            <label>
                Description (optional)
                <textarea name="description" rows="4"><?php
                    echo htmlspecialchars($_POST['description'] ?? ($disaster['description'] ?? ''));
                ?></textarea>
            </label>
            <label>
                Start time (YYYY-MM-DD HH:MM:SS, optional)
                <input type="text" name="started_at"
                       value="<?php echo htmlspecialchars($_POST['started_at'] ?? ($disaster['started_at'] ?? '')); ?>">
            </label>
            <label>
                End time (YYYY-MM-DD HH:MM:SS, optional)
                <input type="text" name="ended_at"
                       value="<?php echo htmlspecialchars($_POST['ended_at'] ?? ($disaster['ended_at'] ?? '')); ?>">
            </label>

            <button type="submit"><?php echo $id ? 'Save changes' : 'Create disaster'; ?></button>
        </form>

        <p><a href="disasters.php">Back to list</a></p>
    </section>
</main>
</body>
</html>

