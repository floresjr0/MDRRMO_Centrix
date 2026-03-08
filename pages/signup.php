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
<title>Sign Up - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- All styles are inline below — no external CSS needed -->
<style>

/* ==============================================
   MDRRMO San Ildefonso — Sign Up Page
   Same design as Login · Scrollable Card
   ============================================== */

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  width: 100%;
  height: 100%;
  font-family: 'Poppins', sans-serif;
  -webkit-font-smoothing: antialiased;
  overflow: hidden; /* body itself doesn't scroll — card does */
}

/* ── OUTER WRAPPER ── */
body {
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    radial-gradient(ellipse 70% 60% at 65% 25%, rgba(160,70,5,0.80) 0%, transparent 65%),
    linear-gradient(160deg, #5c1800 0%, #3a0e02 40%, #1c0600 100%);
  min-height: 100vh;
}

/* ── PHONE SHELL ── */
.signup-shell {
  width: 100%;
  max-width: 420px;
  height: 100vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

/* ── HERO — same as login ── */
.hero {
  position: relative;
  flex-shrink: 0;
  padding: 2.2rem 1.8rem 5.5rem 1.8rem;
  overflow: hidden;
  background:
    radial-gradient(ellipse 75% 65% at 90% 10%, rgba(180,80,10,0.75) 0%, transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='64' viewBox='0 0 56 64'%3E%3Cpolygon points='28,2 54,16 54,48 28,62 2,48 2,16' fill='none' stroke='rgba(255,255,255,0.07)' stroke-width='1.2'/%3E%3C/svg%3E") repeat top left / 56px 64px,
    linear-gradient(155deg, #5c1800 0%, #3a0e02 50%, #1c0600 100%);
}

/* Logo row */
.logo-row {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  margin-bottom: 1.8rem;
  opacity: 0;
  transform: translateY(-14px);
  transition: opacity 0.55s ease, transform 0.55s ease;
}
.logo-row.visible { opacity: 1; transform: translateY(0); }

.logo-circle {
  width: 48px; height: 48px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(255,255,255,0.25);
  flex-shrink: 0;
  background: rgba(255,255,255,0.08);
  display: flex; align-items: center; justify-content: center;
}
.logo-circle img { width: 100%; height: 100%; object-fit: cover; }
.logo-circle svg { width: 24px; height: 24px; fill: rgba(255,255,255,0.80); }

.logo-text strong {
  display: block;
  font-size: 0.58rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(255,255,255,0.90);
  line-height: 1.35;
}
.logo-text span {
  display: block;
  font-size: 0.54rem;
  font-weight: 400;
  color: rgba(255,255,255,0.55);
  margin-top: 2px;
}

/* Big headline */
.hero-headline {
  font-size: clamp(1.85rem, 6.5vw, 2.6rem);
  font-weight: 800;
  line-height: 1.10;
  color: #fff;
  text-shadow: 0 3px 18px rgba(0,0,0,0.35);
  opacity: 0;
  transform: translateY(10px);
  transition: opacity 0.6s ease 0.15s, transform 0.6s ease 0.15s;
}
.hero-headline.visible { opacity: 1; transform: translateY(0); }

/* ── WHITE CARD — rises from bottom, SCROLLABLE ── */
.card {
  position: relative;
  z-index: 5;
  background: #ffffff;
  border-radius: 28px 28px 0 0;
  margin-top: -2.8rem;
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden; /* clip children, scroll handled by inner */
  box-shadow: 0 -6px 36px rgba(0,0,0,0.13);
  opacity: 0;
  transform: translateY(100%);
  transition: opacity 0.85s cubic-bezier(0.16, 1, 0.3, 1),
              transform 0.85s cubic-bezier(0.16, 1, 0.3, 1);
  will-change: transform, opacity;
}
.card.visible { opacity: 1; transform: translateY(0); }

/* Scrollable form area — everything ABOVE the fixed button */
.card-scroll {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 2rem 1.8rem 1rem;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none; /* Firefox */
}
.card-scroll::-webkit-scrollbar { display: none; } /* Chrome/Safari */

/* ── SECTION DIVIDER (Personal Info / Account Info) ── */
.section-divider {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin-bottom: 1.3rem;
  margin-top: 0.2rem;
}
.section-divider::before,
.section-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e0e0e0;
}
.section-divider span {
  font-size: 0.70rem;
  font-weight: 700;
  color: #c0391e;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  white-space: nowrap;
}

/* ── ERROR BOX ── */
.auth-errors {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 0.7rem 1rem;
  margin-bottom: 1.3rem;
}
.auth-errors ul { list-style: none; }
.auth-errors li {
  font-size: 0.78rem;
  color: #b91c1c;
  font-weight: 500;
  line-height: 1.5;
}

/* ── FORM FIELDS ── */
.auth-form { display: flex; flex-direction: column; }

.field {
  position: relative;
  margin-bottom: 1.3rem;
}

.field-label {
  display: block;
  font-size: 0.73rem;
  font-weight: 600;
  color: #c0391e;
  margin-bottom: 0.35rem;
  letter-spacing: 0.01em;
}
.field-label .req { color: #c0391e; margin-left: 1px; }

/* Underline-only inputs */
.field input,
.field select {
  width: 100%;
  border: none !important;
  border-bottom: 1.5px solid #c8c8c8 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0.45rem 0;
  font-size: 0.90rem;
  font-family: 'Poppins', sans-serif;
  font-weight: 400;
  color: #1a1a1a;
  background: transparent !important;
  outline: none !important;
  transition: border-color 0.22s;
  -webkit-appearance: none;
  appearance: none;
}
.field input:focus,
.field select:focus {
  border-bottom: 1.5px solid #c0391e !important;
  box-shadow: none !important;
  outline: none !important;
}
.field input::placeholder {
  color: #c8c8c8;
  font-size: 0.85rem;
  font-weight: 300;
}
.field input[readonly] {
  color: #888;
}

/* Dropdown chevron */
.select-wrap {
  position: relative;
}
.select-wrap::after {
  content: '';
  position: absolute;
  right: 4px;
  top: 50%;
  transform: translateY(-50%);
  width: 0; height: 0;
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-top: 6px solid #c0391e;
  pointer-events: none;
}
.select-wrap select {
  padding-right: 1.4rem;
  cursor: pointer;
}

/* Checkbox row */
.checkbox-field {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  margin-bottom: 1.3rem;
}
.checkbox-field input[type="checkbox"] {
  width: 16px !important;
  height: 16px !important;
  min-width: 16px;
  border: 1.5px solid #c8c8c8 !important;
  border-radius: 3px !important;
  margin-top: 2px;
  accent-color: #c0391e;
  cursor: pointer;
  flex-shrink: 0;
}
.checkbox-field label {
  font-size: 0.73rem;
  color: #555;
  line-height: 1.5;
  cursor: pointer;
}

/* ── FIXED BOTTOM AREA — Sign Up button + login link ── */
.card-footer {
  flex-shrink: 0;
  padding: 0.9rem 1.8rem 1.1rem;
  background: #fff;
  border-top: 1px solid #f0f0f0;
}

.btn-signup {
  width: 100%;
  padding: 0.95rem;
  border: none;
  border-radius: 50px;
  background: linear-gradient(135deg, #c0391e 0%, #a83010 55%, #8f2608 100%);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 0.97rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  cursor: pointer;
  box-shadow: 0 6px 22px rgba(140,40,10,0.42);
  position: relative;
  overflow: hidden;
  transition: transform 0.16s ease, box-shadow 0.16s ease, filter 0.16s ease;
  margin-bottom: 0.6rem;
}
/* Shine sweep */
.btn-signup::before {
  content: '';
  position: absolute;
  top: 0; left: -80%;
  width: 55%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
  transform: skewX(-18deg);
  transition: left 0.55s ease;
}
.btn-signup:hover::before { left: 160%; }
.btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 28px rgba(140,40,10,0.52);
  filter: brightness(1.08);
}
.btn-signup:active {
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(140,40,10,0.30);
}

.login-link {
  text-align: center;
  font-size: 0.75rem;
  color: #aaa;
}
.login-link a {
  color: #c0391e;
  font-weight: 600;
  text-decoration: none;
}
.login-link a:hover { text-decoration: underline; }

/* ===============================================
   RESPONSIVE — Tablet / Desktop (≥ 600px)
   =============================================== */
@media (min-width: 600px) {
  body {
    padding: 2rem;
    align-items: center;
    overflow: auto;
  }
  .signup-shell {
    height: auto;
    max-height: 92vh;
    border-radius: 26px;
    box-shadow: 0 32px 100px rgba(0,0,0,0.60);
    overflow: hidden;
  }
  .hero { padding: 2.2rem 2.2rem 5.5rem 2.2rem; }
  .card {
    transform: translateY(60px);
    border-radius: 28px 28px 26px 26px;
    flex: 1;
    min-height: 0;
  }
  .card.visible { transform: translateY(0); }
  .card-footer {
    border-radius: 0 0 26px 26px;
  }
}

@media (min-width: 900px) {
  .signup-shell { max-width: 420px; }
  .hero { padding: 2.5rem 2.6rem 5.8rem 2.6rem; }
  .card-scroll { padding: 2rem 2.4rem 1rem; }
  .card-footer { padding: 0.9rem 2.4rem 1.2rem; }
}

</style>
</head>
<body>

<div class="signup-shell">

  <!-- ── HERO ── -->
  <div class="hero">
    <div class="logo-row" id="logoRow">
      <div class="logo-circle">
        <img src="../img/mdrrmo.png" alt="MDRRMO"
             onerror="this.style.display='none'">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/>
        </svg>
      </div>
      <div class="logo-text">
        <strong>Office of the Municipal Disaster Risk Reduction<br>and Management Office</strong>
        <span>San Ildefonso, Bulacan</span>
      </div>
    </div>

    <div class="hero-headline" id="heroHeadline">
      Create your<br>account
    </div>
  </div>

  <!-- ── WHITE CARD ── -->
  <div class="card" id="card">


    <div class="card-scroll">

      <?php if ($errors): ?>
      <div class="auth-errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" class="auth-form" id="signupForm">

        <div class="section-divider"><span>Personal Information</span></div>

        <div class="field">
          <label class="field-label" for="full_name">Full Name <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name" required
                 placeholder="Juan Dela Cruz"
                 value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="barangay_id">Barangay <span class="req">*</span></label>
          <div class="select-wrap">
            <select id="barangay_id" name="barangay_id" required>
              <option value="">Select Your Barangay</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?php echo (int)$b['id']; ?>"
                  <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($b['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="house_number">House Number <span class="req">*</span></label>
          <input type="text" id="house_number" name="house_number" required
                 placeholder="e.g. 123"
                 value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="address">Detected Address</label>
          <input type="text" id="address" name="detected_address" readonly
                 placeholder="Detecting location...">
        </div>

     
        <input type="hidden" id="lat">
        <input type="hidden" id="lng">


        <div class="section-divider" style="margin-top:0.5rem;"><span>Account Information</span></div>

        <div class="field">
          <label class="field-label" for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email" required
                 placeholder="juandelacruz@gmail.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password <span class="req">*</span></label>
          <input type="password" id="password" name="password" required minlength="8"
                 placeholder="At least 8 characters">
        </div>

        <div class="field">
          <label class="field-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                 placeholder="Re-enter password">
        </div>

        <!-- Terms checkbox -->
        <div class="checkbox-field">
          <input type="checkbox" name="terms" id="terms" value="1"
                 <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
          <label for="terms">
            I confirm I am a resident of San Ildefonso, Bulacan and agree to the MDRRMO data policy.
          </label>
        </div>

      </form>
    </div>


    <div class="card-footer">
      <button type="submit" form="signupForm" class="btn-signup">Sign up</button>
      <p class="login-link">Already have an account? <a href="../index.php">Log in</a></p>
    </div>

  </div><!-- /card -->

</div>

<script>

 
  window.addEventListener('DOMContentLoaded', function () {
    requestAnimationFrame(function () {
      setTimeout(function () {
        document.getElementById('logoRow').classList.add('visible');
        document.getElementById('heroHeadline').classList.add('visible');
      }, 60);
      setTimeout(function () {
        document.getElementById('card').classList.add('visible');
      }, 180);
    });
  });

 
  document.querySelectorAll('.field input, .field select').forEach(function (inp) {
    inp.addEventListener('focus', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c0391e', 'important');
    });
    inp.addEventListener('blur', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c8c8c8', 'important');
    });
  });

  
  var btn = document.querySelector('.btn-signup');
  if (btn) {
    btn.addEventListener('click', function (e) {
      var r   = btn.getBoundingClientRect();
      var sz  = Math.max(r.width, r.height);
      var rpl = document.createElement('span');
      rpl.style.cssText =
        'position:absolute;border-radius:50%;pointer-events:none;' +
        'width:'  + sz + 'px;height:' + sz + 'px;' +
        'left:'   + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'    + (e.clientY - r.top  - sz / 2) + 'px;' +
        'background:rgba(255,255,255,0.20);' +
        'transform:scale(0);opacity:1;' +
        'transition:transform 0.55s ease,opacity 0.55s ease;';
      btn.appendChild(rpl);
      requestAnimationFrame(function () {
        rpl.style.transform = 'scale(2.6)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function () { rpl.remove(); }, 600);
    });
  }

  
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