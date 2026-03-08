<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/session.php';

$pdo = db();

// If already logged in, send to appropriate dashboard
if (current_user()) {
    redirect_by_role();
}

$errors = [];

// Load active barangays (San Ildefonso only, enforced at DB level)
$barangays = $pdo->query("SELECT id, name FROM barangays WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $houseNo    = trim($_POST['house_number'] ?? '');
    $terms      = isset($_POST['terms']);

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$barangayId) {
        $errors[] = 'Please select your barangay in San Ildefonso.';
    }
    if ($houseNo === '') {
        $errors[] = 'House number is required.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the terms.';
    }

    if (!$errors) {
        // Ensure barangay exists and is active (extra safety)
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected barangay is not valid for San Ildefonso.';
        }
    }

    if (!$errors) {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $otp          = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash      = password_hash($otp, PASSWORD_DEFAULT);
        $expiresAt    = date('Y-m-d H:i:s', time() + 15 * 60); // 15 minutes

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, barangay_id, house_number, is_email_verified, otp_code_hash, otp_expires_at)
                                   VALUES (?, ?, ?, 'citizen', ?, ?, 0, ?, ?)");
            $stmt->execute([$fullName, $email, $passwordHash, $barangayId, $houseNo, $otpHash, $expiresAt]);

            // Send OTP (currently logged to otp_test.log)
            send_otp_email($email, $fullName, $otp);

            $pdo->commit();

            header('Location: verify_otp.php?email=' . urlencode($email));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to create account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign up - MDRRMO San Ildefonso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<div class="auth-container">
    <h1>Create citizen account</h1>
    <?php if ($errors): ?>
        <div class="auth-errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="auth-form">
        <label>
            Full name
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </label>
        <label>
            Email
            <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </label>
        <label>
            Password
            <input type="password" name="password" required minlength="8">
        </label>
        <label>
            Confirm password
            <input type="password" name="confirm_password" required minlength="8">
        </label>
        <label>
            Barangay (San Ildefonso)
            <select name="barangay_id" required>
                <option value="">-- Select barangay --</option>
                <?php foreach ($barangays as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>"
                        <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Detected address
            <input type="text" id="address" name="detected_address" readonly placeholder="Detecting location...">
        </label>

<input type="hidden" id="lat">
<input type="hidden" id="lng">
        <label>
            House number
            <input type="text" name="house_number" required value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
        </label>
        <label class="auth-checkbox">
            <input type="checkbox" name="terms" value="1" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
            I confirm I am a resident of San Ildefonso, Bulacan and agree to the MDRRMO data policy.
        </label>

        <button type="submit">Sign up</button>
    </form>

    <p>Already have an account? <a href="../index.php">Log in</a></p>
</div>

<script>
const allowedMunicipality = "San Ildefonso";
const allowedProvince = "Bulacan";

function detectLocation() {

    if (!navigator.geolocation) {
        alert("Geolocation is not supported by your browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(async function(position){

        const lat = position.coords.latitude;
        const lon = position.coords.longitude;

        document.getElementById("lat").value = lat;
        document.getElementById("lng").value = lon;

        // Reverse geocode using OpenStreetMap
        const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

        const res = await fetch(url);
        const data = await res.json();

        const addr = data.address;

        let municipality = addr.town || addr.city || addr.municipality || "";
        let province = addr.state || "";

        const fullAddress = data.display_name;

        document.getElementById("address").value = fullAddress;

        if (
            !municipality.toLowerCase().includes("san ildefonso") ||
            !province.toLowerCase().includes("bulacan")
        ) {

            alert("Registration is only allowed for residents of San Ildefonso, Bulacan.");

            document.querySelector("button[type=submit]").disabled = true;
        }

    }, function(){
        alert("Location access is required for registration.");
    });
}

detectLocation();
</script>
</body>
</html>

