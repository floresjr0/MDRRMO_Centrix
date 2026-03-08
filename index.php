<?php
require_once __DIR__ . '/pages/db.php';
require_once __DIR__ . '/pages/session.php';

$pdo = db();

if (current_user()) {
    redirect_by_role();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // $lat      = $_POST['lat'] ?? null;
    // $lng      = $_POST['lng'] ?? null;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    // if (!$lat || !$lng) {
    //     $errors[] = 'Location access is required to log in.';
    // }

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT u.*, b.municipality, b.province
                               FROM users u
                               JOIN barangays b ON b.id = u.barangay_id
                               WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {

            $errors[] = 'Invalid email or password.';

        } elseif ((int)$user['is_active'] !== 1) {

            $errors[] = 'Account is disabled.';

        } elseif ((int)$user['is_email_verified'] !== 1) {

            $errors[] = 'Please verify your email before logging in.';

        } elseif ($user['municipality'] !== 'San Ildefonso' || $user['province'] !== 'Bulacan') {

            $errors[] = 'Access is restricted to residents of San Ildefonso, Bulacan.';

        } else {

            $_SESSION['user_id'] = $user['id'];
            redirect_by_role();

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/index.css">
</head>

<body>

<div class="auth-container">

<h1>Log in</h1>

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
Email
<input type="email" name="email" required
value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
</label>

<label>
Password
<input type="password" name="password" required>
</label>

<input type="hidden" name="lat" id="lat">
<input type="hidden" name="lng" id="lng">

<button type="submit">Log in</button>

</form>

<p>Don’t have an account? <a href="pages/signup.php">Sign up</a></p>

</div>

<!-- 
<script>

const allowedMunicipality = "san ildefonso";
const allowedProvince = "bulacan";

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

        const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

        try{

            const res = await fetch(url);
            const data = await res.json();

            const addr = data.address;

            let municipality = addr.town || addr.city || addr.municipality || "";
            let province = addr.state || "";

            municipality = municipality.toLowerCase();
            province = province.toLowerCase();

            if(
                !municipality.includes(allowedMunicipality) ||
                !province.includes(allowedProvince)
            ){

                alert("Login is only allowed inside San Ildefonso, Bulacan.");

                document.querySelector("button[type=submit]").disabled = true;

            }

        }catch(e){
            console.log("Location verification failed.");
        }

    }, function(){

        alert("Location permission is required to login.");

    });

}

detectLocation();

</script> -->

</body>
</html>