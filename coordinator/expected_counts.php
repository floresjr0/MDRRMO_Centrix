<?php
/**
 * coordinator/expected_counts.php
 *
 * Lightweight JSON endpoint polled every 30 s by coordinator/index.php.
 * Returns expected evacuee counts for each center assigned to the
 * currently logged-in coordinator.
 *
 * Response:
 *   { "ok": true, "centers": [ { "id": 1, "expected_count": 5, "max_capacity_people": 100 }, … ] }
 */

require_once __DIR__ . '/../pages/session.php';   // coordinator/ → ../pages/
require_login('coordinator');

header('Content-Type: application/json');

$pdo  = db();
$user = current_user();

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.max_capacity_people,
        COALESCE(t.expected_count, 0) AS expected_count
    FROM evacuation_centers c
    LEFT JOIN (
        SELECT center_id, COUNT(*) AS expected_count
        FROM   evac_navigation_tracking
        WHERE  status = 'navigating'
        GROUP  BY center_id
    ) t ON t.center_id = c.id
    WHERE c.coordinator_user_id = ?
");
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$centers = array_map(fn($r) => [
    'id'                   => (int)$r['id'],
    'expected_count'       => (int)$r['expected_count'],
    'max_capacity_people'  => (int)$r['max_capacity_people'],
], $rows);

echo json_encode(['ok' => true, 'centers' => $centers]);