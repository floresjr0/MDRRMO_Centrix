<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: disasters.php?error=invalid');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM disasters WHERE id = ?");
$stmt->execute([$id]);
$disaster = $stmt->fetch();

if (!$disaster) {
    header('Location: disasters.php?error=notfound');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM disasters WHERE id = ?");
$stmt->execute([$id]);

header('Location: disasters.php?deleted=1');
exit;