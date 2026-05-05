<?php
// pages/citizen_profile_action.php
// GET  ?action=get   → returns current user profile + household
// POST ?action=save  → saves name fields, contact, birthday, sex, household

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$user   = current_user();
$pdo    = db();
$action = $_GET['action'] ?? '';

// ── GET: return current profile + household ──────────────────────
if ($action === 'get') {

    $stmt = $pdo->prepare("
        SELECT u.*, b.name AS barangay_name
          FROM users u
          LEFT JOIN barangays b ON b.id = u.barangay_id
         WHERE u.id = ?
    ");
    $stmt->execute([$user['id']]);
    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch household from family_profiles
    $hhStmt = $pdo->prepare("SELECT * FROM family_profiles WHERE user_id = ?");
    $hhStmt->execute([$user['id']]);
    $hh = $hhStmt->fetch(PDO::FETCH_ASSOC);

    // Compute age
    $age = null;
    if (!empty($freshUser['birthday'])) {
        $age = (int)(new DateTime($freshUser['birthday']))->diff(new DateTime())->y;
    }

    echo json_encode([
        'ok'             => true,
        // Separate name fields (new columns)
        'first_name'     => $freshUser['first_name']     ?? '',
        'last_name'      => $freshUser['last_name']      ?? '',
        'middle_name'    => $freshUser['middle_name']    ?? '',
        'suffix'         => $freshUser['suffix']         ?? '',
        // full_name is now a virtual generated column — still returned
        // so any legacy code that reads it keeps working
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

    // ── Name fields ──────────────────────────────────────────────
    $firstName  = trim($input['first_name']  ?? '');
    $lastName   = trim($input['last_name']   ?? '');
    $middleName = trim($input['middle_name'] ?? '');
    $suffix     = trim($input['suffix']      ?? '');

    // Validate — first and last name are required
    if (mb_strlen($firstName) < 1) {
        echo json_encode(['ok' => false, 'error' => 'Mangyaring ilagay ang iyong pangalan (first name).']);
        exit;
    }
    if (mb_strlen($lastName) < 1) {
        echo json_encode(['ok' => false, 'error' => 'Mangyaring ilagay ang iyong apelyido (last name).']);
        exit;
    }

    // ── Other personal fields ────────────────────────────────────
    $contactNumber = trim($input['contact_number'] ?? '');
    $birthdayRaw   = trim($input['birthday']       ?? '');
    $sex           = trim($input['sex']            ?? '');

    // Validate contact number — allow empty or Philippine formats
    if ($contactNumber !== '' && !preg_match('/^(\+63|0)[0-9]{9,10}$/', $contactNumber)) {
        echo json_encode(['ok' => false, 'error' => 'Ang contact number ay dapat nasa format na 09XXXXXXXXX o +639XXXXXXXXX.']);
        exit;
    }

    // Validate birthday
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
        $age = (int)(new DateTime())->diff($parsed)->y;
        if ($age > 120) {
            echo json_encode(['ok' => false, 'error' => 'Ang naibigay na petsa ng kaarawan ay mukhang hindi tama.']);
            exit;
        }
        $birthdaySQL = $parsed->format('Y-m-d');
    }

    // Validate sex
    if (!in_array($sex, ['male', 'female', 'prefer_not_to_say', ''], true)) {
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

        // ── Update users table with split name columns ────────────
        $stmt = $pdo->prepare("
            UPDATE users
               SET first_name     = :first_name,
                   last_name      = :last_name,
                   middle_name    = :middle_name,
                   suffix         = :suffix,
                   contact_number = :contact,
                   birthday       = :birthday,
                   sex            = :sex,
                   updated_at     = NOW()
             WHERE id = :uid
        ");
        $stmt->execute([
            ':first_name'  => $firstName,
            ':last_name'   => $lastName,
            ':middle_name' => $middleName ?: null,
            ':suffix'      => $suffix     ?: null,
            ':contact'     => $contactNumber ?: null,
            ':birthday'    => $birthdaySQL,
            ':sex'         => $sex ?: null,
            ':uid'         => $user['id'],
        ]);

        // ── Upsert family_profiles (household) ───────────────────
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

        // ── Also sync citizen_household (coordinator reads this) ──
        // This is the table the coordinator side queries for live counts.
        $pdo->prepare("
            INSERT INTO citizen_household
                (user_id, adults, children, seniors, pwds, total_members)
            VALUES (:uid, :adults, :children, :seniors, :pwds, :total)
            ON DUPLICATE KEY UPDATE
                adults        = VALUES(adults),
                children      = VALUES(children),
                seniors       = VALUES(seniors),
                pwds          = VALUES(pwds),
                total_members = VALUES(total_members),
                updated_at    = NOW()
        ")->execute([
            ':uid'      => $user['id'],
            ':adults'   => $adults,
            ':children' => $children,
            ':seniors'  => $seniors,
            ':pwds'     => $pwds,
            ':total'    => $total,
        ]);

        // ── Update active evacuation_intention so coordinator
        //    sees the new count in real-time ──────────────────────
        $pdo->prepare("
            UPDATE evacuation_intentions
               SET household_size = ?,
                   adults         = ?,
                   children       = ?,
                   seniors        = ?,
                   pwds           = ?,
                   updated_at     = NOW()
             WHERE user_id = ? AND status = 'going'
        ")->execute([$total, $adults, $children, $seniors, $pwds, $user['id']]);

        $pdo->commit();

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