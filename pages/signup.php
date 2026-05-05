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
    $firstName  = trim($_POST['first_name']  ?? '');
    $lastName   = trim($_POST['last_name']   ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');   // optional
    $suffix     = trim($_POST['suffix']      ?? '');   // optional
    $email      = trim($_POST['email']       ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $barangayId = (int)($_POST['barangay_id'] ?? 0);
    $houseNo    = trim($_POST['house_number'] ?? '');
    $terms      = isset($_POST['terms']);

    // ── Validation ──────────────────────────────────────────
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
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
        // Ensure barangay exists and is active
        $stmt = $pdo->prepare("SELECT id FROM barangays WHERE id = ? AND is_active = 1");
        $stmt->execute([$barangayId]);
        if (!$stmt->fetch()) {
            $errors[] = 'The selected barangay is not valid for San Ildefonso.';
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
        $expiresAt    = date('Y-m-d H:i:s', time() + 15 * 60);

        // Build display name for the OTP email
        $displayName = trim($firstName . ' ' . $lastName);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users
                    (first_name, last_name, middle_name, suffix,
                     email, password_hash, role,
                     barangay_id, house_number,
                     is_email_verified, otp_code_hash, otp_expires_at)
                VALUES (?, ?, ?, ?, ?, ?, 'citizen', ?, ?, 0, ?, ?)
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                $middleName !== '' ? $middleName : null,
                $suffix     !== '' ? $suffix     : null,
                $email,
                $passwordHash,
                $barangayId,
                $houseNo,
                $otpHash,
                $expiresAt,
            ]);

            send_otp_email($email, $displayName, $otp);

            $pdo->commit();

            header('Location: verify_otp.php?email=' . urlencode($email));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Account creation failed. Please try again.';
        }
    }
}

// Helper to preserve POST values safely
function old(string $key, string $default = ''): string {
    return htmlspecialchars($_POST[$key] ?? $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up - MDRRMO San Ildefonso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="stylesheet" href="../asset/css/usersignup.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
      Create an<br>Account
    </div>
  </div>

  <!-- ── WHITE CARD ── -->
  <div class="card" id="card">
    <div class="card-scroll">

      <?php if ($errors): ?>
      <div class="auth-errors">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="post" class="auth-form" id="signupForm">

        <!-- ── Personal Information ── -->
        <div class="section-divider"><span>Personal Information</span></div>

        <!-- First Name -->
        <div class="field">
          <label class="field-label" for="first_name">First Name <span class="req">*</span></label>
          <input type="text" id="first_name" name="first_name" required
                 placeholder="Juan"
                 value="<?= old('first_name') ?>">
        </div>

        <!-- Middle Name (optional) -->
        <div class="field">
          <label class="field-label" for="middle_name">
            Middle Name <span class="optional">(optional)</span>
          </label>
          <input type="text" id="middle_name" name="middle_name"
                 placeholder="Santos"
                 value="<?= old('middle_name') ?>">
        </div>

        <!-- Last Name -->
        <div class="field">
          <label class="field-label" for="last_name">Last Name <span class="req">*</span></label>
          <input type="text" id="last_name" name="last_name" required
                 placeholder="Dela Cruz"
                 value="<?= old('last_name') ?>">
        </div>

        <!-- Suffix (optional) -->
        <div class="field">
          <label class="field-label" for="suffix">
            Suffix <span class="optional">(optional)</span>
          </label>
          <div class="select-wrap">
            <select id="suffix" name="suffix">
              <option value="">— None —</option>
              <?php foreach (['Jr.','Sr.','II','III','IV','V'] as $sfx): ?>
                <option value="<?= $sfx ?>" <?= old('suffix') === $sfx ? 'selected' : '' ?>>
                  <?= $sfx ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Barangay -->
        <div class="field">
          <label class="field-label" for="barangay_id">Barangay <span class="req">*</span></label>
          <div class="select-wrap">
            <select id="barangay_id" name="barangay_id" required>
              <option value="">Select Barangay</option>
              <?php foreach ($barangays as $b): ?>
                <option value="<?= (int)$b['id'] ?>"
                  <?= (isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- House Number -->
        <div class="field">
          <label class="field-label" for="house_number">House Number <span class="req">*</span></label>
          <input type="text" id="house_number" name="house_number" required
                 placeholder="e.g. 123"
                 value="<?= old('house_number') ?>">
        </div>

        <!-- Detected Address (read-only) -->
        <div class="field">
          <label class="field-label" for="address">Detected Address</label>
          <input type="text" id="address" name="detected_address" readonly
                 placeholder="Getting location...">
        </div>

        <input type="hidden" id="lat">
        <input type="hidden" id="lng">

        <!-- ── Account Information ── -->
        <div class="section-divider" style="margin-top:0.5rem;"><span>Account Information</span></div>

        <div class="field">
          <label class="field-label" for="email">Email <span class="req">*</span></label>
          <input type="email" id="email" name="email" required
                 placeholder="juandelacruz@gmail.com"
                 value="<?= old('email') ?>">
        </div>

        <!-- Password -->
        <div class="field">
          <label class="field-label" for="password">Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="password" name="password" required minlength="8"
                   placeholder="At least 8 characters">
            <button type="button" class="pw-toggle" data-target="password" aria-label="Toggle password visibility">
              <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   style="display:none;">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="field">
          <label class="field-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                   placeholder="Repeat password">
            <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Toggle confirm password visibility">
              <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   style="display:none;">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Terms -->
        <div class="checkbox-field">
          <input type="checkbox" name="terms" id="terms" value="1"
                 <?= isset($_POST['terms']) ? 'checked' : '' ?>>
          <label for="terms">
            I confirm that I am a resident of San Ildefonso, Bulacan and agree to MDRRMO's data policy.
          </label>
        </div>

      </form>
    </div><!-- /.card-scroll -->

    <div class="card-footer">
      <button type="submit" form="signupForm" class="btn-signup">Sign Up</button>
      <p class="login-link">Already have an account? <a href="../index.php">Login</a></p>
    </div>

  </div><!-- /card -->

</div><!-- /.signup-shell -->


<!-- ================================================
     DESKTOP: Centered Card Layout
     ================================================ -->
<div id="desktop-page">

  <div class="dt-card">

    <!-- LEFT: Branding -->
    <div class="dt-card-left">
      <div class="dt-seal-wrap">
        <img src="../img/mdrrmo.png" alt="MDRRMO Seal"
             onerror="this.style.display='none'">
      </div>
      <div class="dt-agency">MDRRMO</div>
      <div class="dt-tagline">#BidaAngLagingHanda</div>
      <div class="dt-bottom-badge">Municipal Government of San Ildefonso</div>
    </div>

    <!-- RIGHT: Signup Form -->
    <div class="dt-card-right">
      <div class="dt-form-scroll">

        <div class="dt-form-header">
          <div class="dt-welcome">Join the Community</div>
          <div class="dt-form-title">Create an<br>Account</div>
          <div class="dt-form-subtitle">Register as a resident of San Ildefonso, Bulacan<br>to stay prepared and informed.</div>
        </div>

        <?php if ($errors): ?>
        <div class="dt-errors">
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <form method="post" id="dtSignupForm">

          <div class="dt-section-divider"><span>Personal Information</span></div>

          <div class="dt-fields-grid">

            <!-- First Name -->
            <div class="dt-field">
              <label for="dt-first_name">First Name *</label>
              <input type="text" id="dt-first_name" name="first_name" required
                     placeholder="Juan"
                     value="<?= old('first_name') ?>">
            </div>

            <!-- Last Name -->
            <div class="dt-field">
              <label for="dt-last_name">Last Name *</label>
              <input type="text" id="dt-last_name" name="last_name" required
                     placeholder="Dela Cruz"
                     value="<?= old('last_name') ?>">
            </div>

            <!-- Middle Name (optional) — half width -->
            <div class="dt-field">
              <label for="dt-middle_name">
                Middle Name <span class="dt-optional">(optional)</span>
              </label>
              <input type="text" id="dt-middle_name" name="middle_name"
                     placeholder="Santos"
                     value="<?= old('middle_name') ?>">
            </div>

            <!-- Suffix (optional) — half width -->
            <div class="dt-field">
              <label for="dt-suffix">
                Suffix <span class="dt-optional">(optional)</span>
              </label>
              <div class="dt-select-wrap">
                <select id="dt-suffix" name="suffix">
                  <option value="">— None —</option>
                  <?php foreach (['Jr.','Sr.','II','III','IV','V'] as $sfx): ?>
                    <option value="<?= $sfx ?>" <?= old('suffix') === $sfx ? 'selected' : '' ?>>
                      <?= $sfx ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Barangay -->
            <div class="dt-field">
              <label for="dt-barangay_id">Barangay *</label>
              <div class="dt-select-wrap">
                <select id="dt-barangay_id" name="barangay_id" required>
                  <option value="">Select Barangay</option>
                  <?php foreach ($barangays as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"
                      <?= (isset($_POST['barangay_id']) && (int)$_POST['barangay_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($b['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- House Number -->
            <div class="dt-field">
              <label for="dt-house_number">House Number *</label>
              <input type="text" id="dt-house_number" name="house_number" required
                     placeholder="e.g. 123"
                     value="<?= old('house_number') ?>">
            </div>

            <!-- Detected Address -->
            <div class="dt-field dt-field-full">
              <label for="dt-address">Detected Address</label>
              <input type="text" id="dt-address" name="detected_address" readonly
                     placeholder="Getting location...">
            </div>

          </div><!-- /.dt-fields-grid -->

          <input type="hidden" id="dt-lat">
          <input type="hidden" id="dt-lng">

          <div class="dt-section-divider" style="margin-top:8px;"><span>Account Information</span></div>

          <div class="dt-fields-grid">

            <div class="dt-field dt-field-full">
              <label for="dt-email">Email *</label>
              <input type="email" id="dt-email" name="email" required
                     placeholder="juandelacruz@gmail.com"
                     value="<?= old('email') ?>">
            </div>

            <!-- Password -->
            <div class="dt-field">
              <label for="dt-password">Password *</label>
              <div class="dt-pw-wrap">
                <input type="password" id="dt-password" name="password" required minlength="8"
                       placeholder="At least 8 characters">
                <button type="button" class="dt-pw-toggle" data-target="dt-password" aria-label="Toggle password visibility">
                  <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                       style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="dt-field">
              <label for="dt-confirm_password">Confirm Password *</label>
              <div class="dt-pw-wrap">
                <input type="password" id="dt-confirm_password" name="confirm_password" required minlength="8"
                       placeholder="Repeat password">
                <button type="button" class="dt-pw-toggle" data-target="dt-confirm_password" aria-label="Toggle confirm password visibility">
                  <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg class="icon-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                       style="display:none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
            </div>

          </div><!-- /.dt-fields-grid -->

          <!-- Terms -->
          <div class="dt-checkbox-field">
            <input type="checkbox" name="terms" id="dt-terms" value="1"
                   <?= isset($_POST['terms']) ? 'checked' : '' ?>>
            <label for="dt-terms">
              I confirm that I am a resident of San Ildefonso, Bulacan and agree to MDRRMO's data policy.
            </label>
          </div>

        </form>
      </div><!-- /.dt-form-scroll -->

      <div class="dt-card-footer">
        <button type="submit" form="dtSignupForm" class="dt-btn-signup">Create Account</button>
        <p class="dt-login-link">Already have an account? <a href="../index.php">Login</a></p>
      </div>

    </div><!-- /.dt-card-right -->

  </div><!-- /.dt-card -->

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
        'width:' + sz + 'px;height:' + sz + 'px;' +
        'left:' + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'  + (e.clientY - r.top  - sz / 2) + 'px;' +
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
        'width:' + sz + 'px;height:' + sz + 'px;' +
        'left:' + (e.clientX - r.left - sz / 2) + 'px;' +
        'top:'  + (e.clientY - r.top  - sz / 2) + 'px;' +
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
     PASSWORD TOGGLE — shared for mobile & desktop
     ================================================ */
  document.querySelectorAll('.pw-toggle, .dt-pw-toggle').forEach(function (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var targetId   = this.getAttribute('data-target');
      var inputEl    = document.getElementById(targetId);
      var iconEye    = this.querySelector('.icon-eye');
      var iconEyeOff = this.querySelector('.icon-eye-off');

      if (!inputEl) return;

      if (inputEl.type === 'password') {
        inputEl.type             = 'text';
        iconEye.style.display    = 'none';
        iconEyeOff.style.display = 'block';
        this.setAttribute('aria-label', 'Hide password');
      } else {
        inputEl.type             = 'password';
        iconEye.style.display    = 'block';
        iconEyeOff.style.display = 'none';
        this.setAttribute('aria-label', 'Show password');
      }

      inputEl.focus();
    });
  });

   // /* ================================================
  //    GEOLOCATION — shared for both mobile and desktop
  //    ================================================ */
  // const allowedMunicipality = "San Ildefonso";
  // const allowedProvince = "Bulacan";

  // function detectLocation() {

  //   if (!navigator.geolocation) {
  //     alert("Geolocation is not supported by your browser.");
  //     return;
  //   }

  //   navigator.geolocation.getCurrentPosition(async function(position){

  //     const lat = position.coords.latitude;
  //     const lon = position.coords.longitude;

  //     // Populate hidden lat/lng for both mobile and desktop forms
  //     document.getElementById("lat").value = lat;
  //     document.getElementById("lng").value = lon;
  //     document.getElementById("dt-lat").value = lat;
  //     document.getElementById("dt-lng").value = lon;

  //     // Reverse geocode using OpenStreetMap
  //     const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`;

  //     const res = await fetch(url);
  //     const data = await res.json();

  //     const addr = data.address;

  //     let municipality = addr.town || addr.city || addr.municipality || "";
  //     let province = addr.state || "";

  //     const fullAddress = data.display_name;

  //     // Populate detected address for both forms
  //     document.getElementById("address").value = fullAddress;
  //     document.getElementById("dt-address").value = fullAddress;

  //     if (
  //       !municipality.toLowerCase().includes("san ildefonso") ||
  //       !province.toLowerCase().includes("bulacan")
  //     ) {

  //       alert("Registration is only allowed for residents of San Ildefonso, Bulacan.");

  //       document.querySelector("button[type=submit]").disabled = true;
  //     }

  //   }, function(){
  //     alert("Location access is required for registration.");
  //   });
  // }

  // detectLocation();


</script>
</body>
</html>