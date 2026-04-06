<?php
require_once __DIR__ . '/../pages/session.php';
require_login('admin');

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: announcements.php?error=invalid');
    exit;
}

// Check if announcement exists
$stmt = $pdo->prepare("SELECT id, title FROM announcements WHERE id = ?");
$stmt->execute([$id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    header('Location: announcements.php?error=notfound');
    exit;
}

// Delete the announcement
$stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
$stmt->execute([$id]);

header('Location: announcements.php?deleted=1');
exit;