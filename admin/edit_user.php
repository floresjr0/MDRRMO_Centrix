<?php
/**
 * admin/edit_user.php
 *
 * JSON endpoint — updates a user's name, email, role, active status,
 * and optionally their password.
 *
 * Safety rules enforced server-side (mirrors the JS guards in users.php):
 *  1. An admin cannot change their own role away from 'admin'.
 *  2. An admin cannot deactivate their own account.
 *  3. Password must be at least 8 characters if supplied.
 */
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

header('Content-Type: application/json');

$pdo          = db();
$currentAdmin = current_user();

$id       = (int)trim($_POST['id']        ?? 0);
$name     = trim($_POST['full_name']      ?? '');
$email    = trim($_POST['email']          ?? '');
$role     = trim($_POST['role']           ?? 'citizen');
$active   = (int)($_POST['is_active']     ?? 1);
$password = $_POST['password']            ?? '';

// ── Basic validation ──────────────────────────────────────────────────────
if (!$id || !$name || !$email) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid email address.']);
    exit;
}

if (!in_array($role, ['citizen', 'coordinator', 'admin'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid role.']);
    exit;
}

if ($password !== '' && strlen($password) < 8) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters.']);
    exit;
}

// ── Self-edit guard ───────────────────────────────────────────────────────
$isSelf = ((int)$currentAdmin['id'] === $id);
if ($isSelf) {
    // Force own role to stay admin and keep account active
    $role   = 'admin';
    $active = 1;
}

// ── Check email uniqueness (excluding the user being edited) ─────────────
$chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$chk->execute([$email, $id]);
if ($chk->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'That email is already used by another account.']);
    exit;
}

// ── Perform update ────────────────────────────────────────────────────────
try {
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            "UPDATE users SET full_name=?, email=?, role=?, is_active=?, password_hash=? WHERE id=?"
        );
        $stmt->execute([$name, $email, $role, $active, $hash, $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?"
        );
        $stmt->execute([$name, $email, $role, $active, $id]);
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}