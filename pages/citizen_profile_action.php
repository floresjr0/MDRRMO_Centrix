<?php
// pages/citizen_profile_action.php
// Handles: GET  ?action=get   → returns current user profile + household
//          POST ?action=save  → saves name, contact, household breakdown

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$pdo    = db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET: return current profile + household ──────────────────────────────
if ($action === 'get') {
    // Fetch household row (may not exist yet)
    $stmt = $pdo->prepare("SELECT * FROM citizen_household WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $hh = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'             => true,
        'full_name'      => $user['full_name']      ?? '',
        'contact_number' => $user['contact_number'] ?? '',
        'house_number'   => $user['house_number']   ?? '',
        'barangay_name'  => $user['barangay_name']  ?? '',
        'household'      => $hh ? [
            'adults'        => (int)$hh['adults'],
            'children'      => (int)$hh['children'],
            'seniors'       => (int)$hh['seniors'],
            'pwds'          => (int)$hh['pwds'],
            'total_members' => (int)$hh['total_members'],
        ] : [
            'adults'        => 1,
            'children'      => 0,
            'seniors'       => 0,
            'pwds'          => 0,
            'total_members' => 1,
        ],
    ]);
    exit;
}

// ── POST: save profile + household ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    // Sanitize user fields
    $fullName      = trim($input['full_name']      ?? '');
    $contactNumber = trim($input['contact_number'] ?? '');

    // Sanitize household fields — all non-negative integers
    $adults   = max(0, (int)($input['adults']   ?? 1));
    $children = max(0, (int)($input['children'] ?? 0));
    $seniors  = max(0, (int)($input['seniors']  ?? 0));
    $pwds     = max(0, (int)($input['pwds']     ?? 0));
    $total    = $adults + $children + $seniors + $pwds;

    // Require at least 1 person
    if ($total < 1) {
        echo json_encode(['ok' => false, 'error' => 'Household must have at least 1 member.']);
        exit;
    }

    // Validate name
    if (mb_strlen($fullName) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Please enter your full name.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update users table
        $stmt = $pdo->prepare("
            UPDATE users
               SET full_name      = ?,
                   contact_number = ?,
                   updated_at     = NOW()
             WHERE id = ?
        ");
        $stmt->execute([$fullName, $contactNumber ?: null, $user['id']]);

        // Upsert citizen_household
        $stmt = $pdo->prepare("
            INSERT INTO citizen_household
                (user_id, adults, children, seniors, pwds, total_members)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                adults        = VALUES(adults),
                children      = VALUES(children),
                seniors       = VALUES(seniors),
                pwds          = VALUES(pwds),
                total_members = VALUES(total_members),
                updated_at    = NOW()
        ");
        $stmt->execute([$user['id'], $adults, $children, $seniors, $pwds, $total]);

        // Also update any active evacuation_intention for this user
        // so the coordinator immediately sees the updated breakdown
        $stmt = $pdo->prepare("
            UPDATE evacuation_intentions
               SET household_size = ?,
                   adults         = ?,
                   children       = ?,
                   seniors        = ?,
                   pwds           = ?,
                   updated_at     = NOW()
             WHERE user_id = ?
               AND status   = 'going'
        ");
        $stmt->execute([$total, $adults, $children, $seniors, $pwds, $user['id']]);

        $pdo->commit();

        echo json_encode([
            'ok'            => true,
            'total_members' => $total,
            'message'       => 'Profile saved successfully.',
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action.']);