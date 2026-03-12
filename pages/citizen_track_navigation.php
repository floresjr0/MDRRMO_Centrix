<?php
/**
 * pages/citizen_track_navigation.php
 *
 * Called via fetch() (POST, JSON) whenever a citizen:
 *   - Selects / confirms an evacuation center  → action: "select"
 *   - Switches to a different center           → action: "select"
 *   - Cancels navigation                       → action: "cancel"
 *   - Marks themselves as arrived              → action: "arrived"
 *
 * Request body (JSON):
 *   { "action": "select"|"cancel"|"arrived", "center_id": <int> }
 *   center_id is required only for "select".
 *
 * Response (JSON):
 *   { "ok": true }  or  { "ok": false, "error": "…" }
 */

require_once __DIR__ . '/session.php';   // pages/session.php  ← same folder
require_login();

header('Content-Type: application/json');

$pdo    = db();
$user   = current_user();
$userId = (int) $user['id'];

// ── Parse request ──────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
$action = $body['action'] ?? '';

if (!in_array($action, ['select', 'cancel', 'arrived'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action.']);
    exit;
}

// ── Find active disaster (optional link) ───────────────────────
$disasterRow = $pdo
    ->query("SELECT id FROM disasters WHERE status = 'ongoing' ORDER BY level DESC LIMIT 1")
    ->fetch();
$disasterId = $disasterRow ? (int) $disasterRow['id'] : null;

// ── Handle each action ─────────────────────────────────────────
try {
    if ($action === 'select') {
        $centerId = isset($body['center_id']) ? (int) $body['center_id'] : 0;
        if ($centerId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'center_id required for select.']);
            exit;
        }

        // Verify center exists
        $chk = $pdo->prepare("SELECT id FROM evacuation_centers WHERE id = ?");
        $chk->execute([$centerId]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Center not found.']);
            exit;
        }

        // Upsert: one row per user (UNIQUE KEY on user_id)
        $stmt = $pdo->prepare("
            INSERT INTO evac_navigation_tracking
                (user_id, center_id, disaster_id, status)
            VALUES
                (:uid, :cid, :did, 'navigating')
            ON DUPLICATE KEY UPDATE
                center_id   = VALUES(center_id),
                disaster_id = VALUES(disaster_id),
                status      = 'navigating',
                updated_at  = NOW()
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':cid' => $centerId,
            ':did' => $disasterId,
        ]);

    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("
            UPDATE evac_navigation_tracking
            SET    status = 'cancelled', updated_at = NOW()
            WHERE  user_id = ?
        ");
        $stmt->execute([$userId]);

    } elseif ($action === 'arrived') {
        $stmt = $pdo->prepare("
            UPDATE evac_navigation_tracking
            SET    status = 'arrived', updated_at = NOW()
            WHERE  user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    echo json_encode(['ok' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
}