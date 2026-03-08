<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo  = db();
$user = current_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$ann = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $ann = $stmt->fetch();
}

$disasters = $pdo->query("SELECT id, title FROM disasters ORDER BY status = 'ongoing' DESC, started_at DESC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $type    = $_POST['type'] ?? 'general';
    $disasterId = isset($_POST['disaster_id']) && $_POST['disaster_id'] !== ''
        ? (int)$_POST['disaster_id'] : null;
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($body === '') {
        $errors[] = 'Body is required.';
    }
    if (!in_array($type, ['general','disaster'], true)) {
        $errors[] = 'Invalid type.';
    }

    if (!$errors) {
        if ($id && $ann) {
            $stmt = $pdo->prepare("UPDATE announcements
                                   SET title = ?, body = ?, type = ?, disaster_id = ?, is_pinned = ?
                                   WHERE id = ?");
            $stmt->execute([$title, $body, $type, $disasterId, $isPinned, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO announcements
                                   (title, body, type, disaster_id, is_pinned, published_at, created_by)
                                   VALUES (?,?,?,?,?,NOW(),?)");
            $stmt->execute([$title, $body, $type, $disasterId, $isPinned, $user['id']]);
            $id = (int)$pdo->lastInsertId();
        }

        header('Location: announcements.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $id ? 'Edit announcement' : 'New announcement'; ?> - MDRRMO Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">
        <?php echo $id ? 'Edit announcement' : 'New announcement'; ?>
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
                Title
                <input type="text" name="title" required
                       value="<?php echo htmlspecialchars($_POST['title'] ?? ($ann['title'] ?? '')); ?>">
            </label>
            <label>
                Type
                <?php
                $selectedType = $_POST['type'] ?? ($ann['type'] ?? 'general');
                ?>
                <select name="type">
                    <option value="general" <?php echo $selectedType === 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="disaster" <?php echo $selectedType === 'disaster' ? 'selected' : ''; ?>>Disaster-related</option>
                </select>
            </label>
            <label>
                Linked disaster (optional)
                <?php
                $selectedDisaster = $_POST['disaster_id'] ?? ($ann['disaster_id'] ?? '');
                ?>
                <select name="disaster_id">
                    <option value="">-- None --</option>
                    <?php foreach ($disasters as $d): ?>
                        <option value="<?php echo (int)$d['id']; ?>"
                            <?php echo (string)$selectedDisaster === (string)$d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="auth-checkbox">
                <?php
                $checkedPinned = isset($_POST['is_pinned'])
                    ? (bool)$_POST['is_pinned']
                    : (isset($ann['is_pinned']) && $ann['is_pinned']);
                ?>
                <input type="checkbox" name="is_pinned" value="1" <?php echo $checkedPinned ? 'checked' : ''; ?>>
                Pin this announcement to the top
            </label>
            <label>
                Body
                <textarea name="body" rows="6" required><?php
                    echo htmlspecialchars($_POST['body'] ?? ($ann['body'] ?? ''));
                ?></textarea>
            </label>

            <button type="submit"><?php echo $id ? 'Save changes' : 'Create announcement'; ?></button>
        </form>

        <p><a href="announcements.php">Back to announcements</a></p>
    </section>
</main>
</body>
</html>

