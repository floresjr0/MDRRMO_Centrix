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
        $errors[] = 'Kailangan ang buong pangalan.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Kailangan ng wastong email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Ang password ay dapat hindi bababa sa 8 karakter.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Hindi magkatugma ang mga password.';
    }
    if (!$barangayId) {
        $errors[] = 'Pakipili ang iyong barangay sa San Ildefonso.';
    }
    if ($houseNo === '') {
        $errors[] = 'Kailangan ang numero ng bahay.';
    }
    if (!$terms) {
        $errors[] = 'Kailangan mong sumang-ayon sa mga tuntunin.';
    }

    if (!$errors) {
        // Ensure barangay exists and is active (extra safety)
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'Ang napiling barangay ay hindi wasto para sa San Ildefonso.';
        }
    }

    if (!$errors) {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Mayroon nang account na may ganitong email.';
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
            $errors[] = 'Hindi nagawa ang account. Pakisubukang muli.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
<meta charset="UTF-8">
<title>Sign Up - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="stylesheet" href="../asset/css/usersignup.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<!-- All styles are inline below — no external CSS needed -->
<style>

</style>
</head>
<body>

<!-- ================================================
     MOBILE: Signup Shell
     ================================================ -->
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
      Gumawa ng<br>Account
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

        <div class="section-divider"><span>Personal na Impormasyon</span></div>

        <div class="field">
          <label class="field-label" for="full_name">Buong Pangalan <span class="req">*</span></label>
          <input type="text" id="full_name" name="full_name" required
                 placeholder="Juan Dela Cruz"
                 value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="barangay_id">Barangay <span class="req">*</span></label>
          <div class="select-wrap">
            <select id="barangay_id" name="barangay_id" required>
              <option value="">Piliin ang Barangay</option>
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
          <label class="field-label" for="house_number">Numero ng Bahay <span class="req">*</span></label>
          <input type="text" id="house_number" name="house_number" required
                 placeholder="hal. 123"
                 value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="address">Nakitang Tirahan</label>
          <input type="text" id="address" name="detected_address" readonly
                 placeholder="Kinukuha ang lokasyon...">
        </div>

        <input type="hidden" id="lat">
        <input type="hidden" id="lng">

        <div class="section-divider" style="margin-top:0.5rem;"><span>Impormasyon ng Account</span></div>

        <div class="field">
          <label class="field-label" for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email" required
                 placeholder="juandelacruz@gmail.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="field">
          <label class="field-label" for="password">Password <span class="req">*</span></label>
          <input type="password" id="password" name="password" required minlength="8"
                 placeholder="Hindi bababa sa 8 karakter">
        </div>

        <div class="field">
          <label class="field-label" for="confirm_password">Kumpirmahin ang Password <span class="req">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                 placeholder="Ulitin ang password">
        </div>

        <!-- Terms checkbox -->
        <div class="checkbox-field">
          <input type="checkbox" name="terms" id="terms" value="1"
                 <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
          <label for="terms">
            Kinukumpirma ko na ako ay residente ng San Ildefonso, Bulacan at sumasang-ayon sa patakaran ng MDRRMO ukol sa datos.
          </label>
        </div>

      </form>
    </div>

    <div class="card-footer">
      <button type="submit" form="signupForm" class="btn-signup">Mag-sign Up</button>
      <p class="login-link">May account na? <a href="../index.php">Mag-login</a></p>
    </div>

  </div><!-- /card -->

</div><!-- /.signup-shell -->


<!-- ================================================
     DESKTOP: Centered Card Layout
     ================================================ -->
<div id="desktop-page">

  <!-- CENTERED CARD -->
  <div class="dt-card">

    <!-- LEFT: Branding -->
    <div class="dt-card-left">

      <div class="dt-seal-wrap">
        <img src="../img/mdrrmo.png" alt="MDRRMO Seal"
             onerror="this.style.display='none'">
      </div>

      <div class="dt-agency">MDRRMO</div>
      <div class="dt-tagline">#BidaAngLagingHanda</div>

      <div class="dt-info-pills">
        <div class="dt-pill">
          <div class="dt-pill-icon">🛡️</div>
          <div class="dt-pill-text">
            <strong>Disaster Risk Reduction</strong>
            <span>Proactive community preparedness & mitigation</span>
          </div>
        </div>
        <div class="dt-pill">
          <div class="dt-pill-icon">🚨</div>
          <div class="dt-pill-text">
            <strong>Emergency Response</strong>
            <span>Rapid coordination during crisis events</span>
          </div>
        </div>
        <div class="dt-pill">
          <div class="dt-pill-icon">📍</div>
          <div class="dt-pill-text">
            <strong>San Ildefonso, Bulacan</strong>
            <span>Serving all barangays of the municipality</span>
          </div>
        </div>
      </div>

      <div class="dt-bottom-badge">Municipal Government of San Ildefonso</div>

    </div><!-- /.dt-card-left -->

    <!-- RIGHT: Signup Form -->
    <div class="dt-card-right">

      <div class="dt-form-scroll">

        <div class="dt-form-header">
          <div class="dt-welcome">Sumali sa Komunidad</div>
          <div class="dt-form-title">Gumawa ng<br>Account</div>
          <div class="dt-form-subtitle">Mag-rehistro bilang residente ng San Ildefonso, Bulacan<br>para manatiling handa at may kaalaman.</div>
        </div>

        <?php if ($errors): ?>
        <div class="dt-errors">
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="post" id="dtSignupForm">

          <div class="dt-section-divider"><span>Personal na Impormasyon</span></div>

          <div class="dt-fields-grid">

            <div class="dt-field dt-field-full">
              <label for="dt-full_name">Buong Pangalan *</label>
              <input type="text" id="dt-full_name" name="full_name" required
                     placeholder="Juan Dela Cruz"
                     value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="dt-field">
              <label for="dt-barangay_id">Barangay *</label>
              <div class="dt-select-wrap">
                <select id="dt-barangay_id" name="barangay_id" required>
                  <option value="">Piliin ang Barangay</option>
                  <?php foreach ($barangays as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>"
                      <?php echo isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="dt-field">
              <label for="dt-house_number">Numero ng Bahay *</label>
              <input type="text" id="dt-house_number" name="house_number" required
                     placeholder="hal. 123"
                     value="<?php echo htmlspecialchars($_POST['house_number'] ?? ''); ?>">
            </div>

            <div class="dt-field dt-field-full">
              <label for="dt-address">Nakitang Tirahan</label>
              <input type="text" id="dt-address" name="detected_address" readonly
                     placeholder="Kinukuha ang lokasyon...">
            </div>

          </div><!-- /.dt-fields-grid -->

          <input type="hidden" id="dt-lat">
          <input type="hidden" id="dt-lng">

          <div class="dt-section-divider" style="margin-top:8px;"><span>Impormasyon ng Account</span></div>

          <div class="dt-fields-grid">

            <div class="dt-field dt-field-full">
              <label for="dt-email">Email *</label>
              <input type="email" id="dt-email" name="email" required
                     placeholder="juandelacruz@gmail.com"
                     value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="dt-field">
              <label for="dt-password">Password *</label>
              <input type="password" id="dt-password" name="password" required minlength="8"
                     placeholder="Hindi bababa sa 8 karakter">
            </div>

            <div class="dt-field">
              <label for="dt-confirm_password">Kumpirmahin ang Password *</label>
              <input type="password" id="dt-confirm_password" name="confirm_password" required minlength="8"
                     placeholder="Ulitin ang password">
            </div>

          </div><!-- /.dt-fields-grid -->

          <!-- Terms checkbox -->
          <div class="dt-checkbox-field">
            <input type="checkbox" name="terms" id="dt-terms" value="1"
                   <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
            <label for="dt-terms">
              Kinukumpirma ko na ako ay residente ng San Ildefonso, Bulacan at sumasang-ayon sa patakaran ng MDRRMO ukol sa datos.
            </label>
          </div>

        </form>
      </div><!-- /.dt-form-scroll -->

      <!-- Fixed footer -->
      <div class="dt-card-footer">
        <button type="submit" form="dtSignupForm" class="dt-btn-signup">Gumawa ng Account</button>
        <p class="dt-login-link">May account na? <a href="../index.php">Mag-login</a></p>
      </div>

    </div><!-- /.dt-card-right -->

  </div><!-- /.dt-card -->

  <!-- Status bar -->
  <div class="dt-status-bar">
    <span><span class="dt-status-dot"></span>System Online</span>
    <span>·</span>
    <span>MDRRMO · San Ildefonso, Bulacan</span>
  </div>

</div><!-- /#desktop-page -->


<script>

  /* ================================================
     MOBILE: Entrance animations
     ================================================ */
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

  /* Mobile: underline focus highlight */
  document.querySelectorAll('.field input, .field select').forEach(function (inp) {
    inp.addEventListener('focus', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c0391e', 'important');
    });
    inp.addEventListener('blur', function () {
      this.style.setProperty('border-bottom', '1.5px solid #c8c8c8', 'important');
    });
  });

  /* Mobile: button ripple */
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

  /* Desktop: button ripple */
  var dtBtn = document.querySelector('.dt-btn-signup');
  if (dtBtn) {
    dtBtn.addEventListener('click', function (e) {
      var r   = dtBtn.getBoundingClientRect();
      var sz  = Math.max(r.width, r.height);
      var rpl = document.createElement('span');
      rpl.style.cssText =
        'position:absolute;border-radius:50%;pointer-events:none;' +
        'width:'  + sz + 'px;height:' + sz + 'px;' +
        'left:'   + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'    + (e.clientY - r.top  - sz / 2) + 'px;' +
        'background:rgba(255,255,255,0.18);' +
        'transform:scale(0);opacity:1;' +
        'transition:transform 0.55s ease,opacity 0.55s ease;';
      dtBtn.appendChild(rpl);
      requestAnimationFrame(function () {
        rpl.style.transform = 'scale(2.8)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function () { rpl.remove(); }, 600);
    });
  }

  /* ================================================
     GEOLOCATION — shared for both mobile and desktop
     ================================================ */
  const allowedMunicipality = "San Ildefonso";
  const allowedProvince = "Bulacan";

  function detectLocation() {

    if (!navigator.geolocation) {
      alert("Hindi sinusuportahan ng iyong browser ang geolocation.");
      return;
    }

    navigator.geolocation.getCurrentPosition(async function(position){

      const lat = position.coords.latitude;
      const lon = position.coords.longitude;

      // Populate hidden lat/lng for both mobile and desktop forms
      document.getElementById("lat").value = lat;
      document.getElementById("lng").value = lon;
      document.getElementById("dt-lat").value = lat;
      document.getElementById("dt-lng").value = lon;

      // Reverse geocode using OpenStreetMap
      const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

      const res = await fetch(url);
      const data = await res.json();

      const addr = data.address;

      let municipality = addr.town || addr.city || addr.municipality || "";
      let province = addr.state || "";

      const fullAddress = data.display_name;

      // Populate detected address for both forms
      document.getElementById("address").value = fullAddress;
      document.getElementById("dt-address").value = fullAddress;

      if (
        !municipality.toLowerCase().includes("san ildefonso") ||
        !province.toLowerCase().includes("bulacan")
      ) {

        alert("Ang pagpaparehistro ay para lamang sa mga residente ng San Ildefonso, Bulacan.");

        document.querySelector("button[type=submit]").disabled = true;
      }

    }, function(){
      alert("Kailangan ang access sa lokasyon para sa pagpaparehistro.");
    });
  }

  detectLocation();

</script>
</body>
</html>