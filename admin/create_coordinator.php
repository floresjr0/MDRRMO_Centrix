<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();

$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $houseNo    = trim($_POST['house_number'] ?? '');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$barangayId) {
        $errors[] = 'Please select a barangay.';
    }
    if ($houseNo === '') {
        $errors[] = 'House number is required.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected barangay is not valid.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (
                full_name,
                email,
                password_hash,
                role,
                barangay_id,
                house_number,
                is_email_verified,
                otp_code_hash,
                otp_expires_at,
                is_active
            ) VALUES (
                ?, ?, ?, 'coordinator', ?, ?, 1, NULL, NULL, ?
            )
        ");

        try {
            $stmt->execute([$fullName, $email, $passwordHash, $barangayId, $houseNo, $isActive]);
            header('Location: users.php?created=coordinator');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Failed to create coordinator account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create coordinator - Admin</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-title">Create coordinator</div>
    <div class="topbar-user">
        <a href="index.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="../pages/logout.php">Logout</a>
    </div>
</header>
<main class="dashboard admin-dashboard">
    <section class="card">
        <h2>New coordinator account</h2>

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
                Full name
                <input type="text" name="full_name" required
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </label>
            <label>
                Email
                <input type="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </label>
            <label>
                Password
                <input type="password" name="password" required minlength="8">
            </label>
            <label>
                Confirm password
                <input type="password" name="confirm_password" required minlength="8">
            </label>
            <label>
                Barangay
                <select name="barangay_id" required>
                    <option value="">-- Select barangay --</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>"
                            <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                House number
                <input type="text" name="house_number" required
                       value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
            </label>
            <label class="auth-checkbox">
                <input type="checkbox" name="is_active" value="1"
                    <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                Active account
            </label>

            <button type="submit" class="btn-primary">Create coordinator</button>
        </form>
    </section>
</main>
</body>
</html>

