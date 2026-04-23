<?php
// pages/citizen_profile_action.php
// GET  ?action=get   → returns current user profile + household
// POST ?action=save  → saves name, contact, birthday, sex, household

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$pdo    = db();
$action = $_GET['action'] ?? '';

// ── GET: return current profile + household ──────────────────────
if ($action === 'get') {

    // Fetch fresh user row (current_user() may be cached in session)
    $stmt = $pdo->prepare("
        SELECT u.*, b.name AS barangay_name
          FROM users u
          LEFT JOIN barangays b ON b.id = u.barangay_id
         WHERE u.id = ?
    ");
    $stmt->execute([$user['id']]);
    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch household row
    $hhStmt = $pdo->prepare("SELECT * FROM family_profiles WHERE user_id = ?");
    $hhStmt->execute([$user['id']]);
    $hh = $hhStmt->fetch(PDO::FETCH_ASSOC);

    // Compute age from birthday if available
    $age = null;
    if (!empty($freshUser['birthday'])) {
        $birthDate = new DateTime($freshUser['birthday']);
        $today     = new DateTime();
        $age       = (int)$birthDate->diff($today)->y;
    }

    echo json_encode([
        'ok'             => true,
        'full_name'      => $freshUser['full_name']      ?? '',
        'email'          => $freshUser['email']          ?? '',
        'contact_number' => $freshUser['contact_number'] ?? '',
        'house_number'   => $freshUser['house_number']   ?? '',
        'barangay_name'  => $freshUser['barangay_name']  ?? '',
        'birthday'       => $freshUser['birthday']       ?? '',
        'sex'            => $freshUser['sex']            ?? '',
        'age'            => $age,
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

// ── POST: save profile + household ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['ok' => false, 'error' => 'Invalid input.']);
        exit;
    }

    // ── Personal fields ──────────────────────────────────────────
    $fullName      = trim($input['full_name']      ?? '');
    $contactNumber = trim($input['contact_number'] ?? '');
    $birthdayRaw   = trim($input['birthday']       ?? '');
    $sex           = trim($input['sex']            ?? '');

    // Validate name
    if (mb_strlen($fullName) < 2) {
        echo json_encode(['ok' => false, 'error' => 'Mangyaring ilagay ang iyong buong pangalan.']);
        exit;
    }

    // Validate contact number — allow empty or Philippine formats
    if ($contactNumber !== '' && !preg_match('/^(\+63|0)[0-9]{9,10}$/', $contactNumber)) {
        echo json_encode(['ok' => false, 'error' => 'Ang contact number ay dapat nasa format na 09XXXXXXXXX o +639XXXXXXXXX.']);
        exit;
    }

    // Validate birthday — must be a real date and not in the future
    $birthdaySQL = null;
    if ($birthdayRaw !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', $birthdayRaw);
        if (!$parsed || $parsed->format('Y-m-d') !== $birthdayRaw) {
            echo json_encode(['ok' => false, 'error' => 'Hindi wastong format ng petsa ng kaarawan.']);
            exit;
        }
        if ($parsed > new DateTime()) {
            echo json_encode(['ok' => false, 'error' => 'Hindi maaaring hinaharap ang petsa ng kaarawan.']);
            exit;
        }
        // Must be at least 1 year old (basic sanity check)
        $age = (int)(new DateTime())->diff($parsed)->y;
        if ($age > 120) {
            echo json_encode(['ok' => false, 'error' => 'Ang naibigay na petsa ng kaarawan ay mukhang hindi tama.']);
            exit;
        }
        $birthdaySQL = $parsed->format('Y-m-d');
    }

    // Validate sex
    $allowedSex = ['male', 'female', 'prefer_not_to_say', ''];
    if (!in_array($sex, $allowedSex, true)) {
        echo json_encode(['ok' => false, 'error' => 'Hindi wastong halaga ng kasarian.']);
        exit;
    }

    // ── Household fields ─────────────────────────────────────────
    $adults   = max(1, (int)($input['adults']   ?? 1));
    $children = max(0, (int)($input['children'] ?? 0));
    $seniors  = max(0, (int)($input['seniors']  ?? 0));
    $pwds     = max(0, (int)($input['pwds']     ?? 0));
    $total    = $adults + $children + $seniors + $pwds;

    if ($total < 1) {
        echo json_encode(['ok' => false, 'error' => 'Ang sambahayan ay dapat may hindi bababa sa 1 miyembro.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update users table — including birthday and sex
        $stmt = $pdo->prepare("
            UPDATE users
               SET full_name      = :name,
                   contact_number = :contact,
                   birthday       = :birthday,
                   sex            = :sex,
                   updated_at     = NOW()
             WHERE id = :uid
        ");
        $stmt->execute([
            ':name'     => $fullName,
            ':contact'  => $contactNumber ?: null,
            ':birthday' => $birthdaySQL,
            ':sex'      => $sex ?: null,
            ':uid'      => $user['id'],
        ]);

        // Upsert family_profiles (household)
        $hhStmt = $pdo->prepare("
            INSERT INTO family_profiles
                (user_id, adults, children, seniors, pwds, total_members)
            VALUES (:uid, :adults, :children, :seniors, :pwds, :total)
            ON DUPLICATE KEY UPDATE
                adults        = VALUES(adults),
                children      = VALUES(children),
                seniors       = VALUES(seniors),
                pwds          = VALUES(pwds),
                total_members = VALUES(total_members),
                updated_at    = NOW()
        ");
        $hhStmt->execute([
            ':uid'      => $user['id'],
            ':adults'   => $adults,
            ':children' => $children,
            ':seniors'  => $seniors,
            ':pwds'     => $pwds,
            ':total'    => $total,
        ]);

        // Also update any active evacuation_intention so coordinator
        // sees updated household count immediately
        $pdo->prepare("
            UPDATE evacuation_intentions
               SET household_size = ?,
                   updated_at     = NOW()
             WHERE user_id = ? AND status = 'going'
        ")->execute([$total, $user['id']]);

        $pdo->commit();

        // Compute age for response
        $ageResp = null;
        if ($birthdaySQL) {
            $ageResp = (int)(new DateTime())->diff(new DateTime($birthdaySQL))->y;
        }

        echo json_encode([
            'ok'            => true,
            'total_members' => $total,
            'age'           => $ageResp,
            'message'       => 'Na-save ang profile.',
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('citizen_profile_action error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'May error sa database. Subukan ulit.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Hindi wastong aksyon.']);