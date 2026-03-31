<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare(
        "UPDATE disasters SET status = 'ended', ended_at = NOW() WHERE id = ? AND status = 'ongoing'"
    );
    $stmt->execute([$id]);
}

header('Location: disasters.php');
exit;