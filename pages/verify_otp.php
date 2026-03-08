<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

$pdo = db();

// If already verified and logged in, send to dashboard
if (current_user()) {
    redirect_by_role();
}

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if ($otp === '') {
        $errors[] = 'Please enter the code sent to your email.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'Account not found.';
        } elseif ((int)$user['is_email_verified'] === 1) {
            $message = 'Email already verified. You can log in.';
        } elseif (empty($user['otp_code_hash']) || empty($user['otp_expires_at'])) {
            $errors[] = 'No active verification code. Please sign up again.';
        } elseif (strtotime($user['otp_expires_at']) < time()) {
            $errors[] = 'Verification code has expired. Please sign up again.';
        } elseif (!password_verify($otp, $user['otp_code_hash'])) {
            $errors[] = 'Incorrect verification code.';
        } else {
            $upd = $pdo->prepare("UPDATE users SET is_email_verified = 1, otp_code_hash = NULL, otp_expires_at = NULL WHERE id = ?");
            $upd->execute([$user['id']]);
            $message = 'Email verified successfully. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify email - MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<div class="auth-container">
    <h1>Email verification</h1>
    <p>We sent a 6-digit verification code to your email address.</p>

    <?php if ($errors): ?>
        <div class="auth-errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="auth-message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="post" class="auth-form">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <label>
            Verification code
            <input type="text" name="otp" maxlength="6" required>
        </label>
        <button type="submit">Verify</button>
    </form>

    <p><a href="../index.php">Go to login</a></p>
</div>
</body>
</html>

