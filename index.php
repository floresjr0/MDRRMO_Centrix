<?php
require_once __DIR__ . '/pages/db.php';
require_once __DIR__ . '/pages/session.php';

$pdo = db();

if (current_user()) {
    redirect_by_role();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $fingerprint = trim($_POST['device_fingerprint'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {

        $stmt = $pdo->prepare("SELECT u.*, b.municipality, b.province
                               FROM users u
                               JOIN barangays b ON b.id = u.barangay_id
                               WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {

            $errors[] = 'Incorrect email or password.';

        } elseif ((int)$user['is_active'] !== 1) {

            $errors[] = 'This account is inactive.';

        } elseif ((int)$user['is_email_verified'] !== 1) {

            $errors[] = 'Please verify your email before logging in.';

        } elseif ($user['municipality'] !== 'San Ildefonso' || $user['province'] !== 'Bulacan') {

            $errors[] = 'This access is only for residents of San Ildefonso, Bulacan.';

        } elseif ($user['role'] === 'admin') {

            // ============================================================
            // ADMIN ONLY: Device Binding Security (Methods 1, 2 & 3)
            // ============================================================

            $cookieToken = $_COOKIE['mdrrmo_device_token'] ?? '';

            if ((int)$user['is_device_trusted'] === 1) {

                // --- Device already registered: verify token + fingerprint ---
                $tokenMatch       = !empty($cookieToken) && hash_equals($user['device_token'], $cookieToken);
                $fingerprintMatch = !empty($fingerprint) && hash_equals($user['device_fingerprint'], $fingerprint);

                if (!$tokenMatch || !$fingerprintMatch) {

                    // Log unauthorized attempt
                    error_log(
                        "[MDRRMO SECURITY] Unauthorized device login attempt" .
                        " | Admin: {$user['email']}" .
                        " | IP: {$_SERVER['REMOTE_ADDR']}" .
                        " | Time: " . date('Y-m-d H:i:s') .
                        " | Token match: " . ($tokenMatch ? 'YES' : 'NO') .
                        " | Fingerprint match: " . ($fingerprintMatch ? 'YES' : 'NO')
                    );

                    // Optional: email alert to admin
                    // mail(
                    //     $user['email'],
                    //     '[MDRRMO ALERT] Unauthorized Admin Login Attempt',
                    //     "Someone tried to access the admin panel from an unrecognized device.\n\n" .
                    //     "Time: " . date('Y-m-d H:i:s') . "\n" .
                    //     "IP Address: {$_SERVER['REMOTE_ADDR']}\n\n" .
                    //     "If this was you, please use your registered device or request a device reset."
                    // );

                    $errors[] = 'Unauthorized device detected. Access is restricted to the registered device only.';

                } else {

                    // Trusted device matched — allow login and refresh cookie
                    $_SESSION['user_id'] = $user['id'];

                    setcookie('mdrrmo_device_token', $user['device_token'], [
                        'expires'  => time() + (30 * 24 * 60 * 60), // 30 days
                        'path'     => '/',
                        'secure'   => false, // Set to TRUE when deployed with HTTPS
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);

                    redirect_by_role();
                }

            } else {

                // --- First time admin login: register this device ---
                $newToken = bin2hex(random_bytes(32)); // 64-char cryptographically secure token

                $upd = $pdo->prepare("UPDATE users SET
                    device_token         = ?,
                    device_fingerprint   = ?,
                    device_registered_at = NOW(),
                    is_device_trusted    = 1
                    WHERE id = ?");
                $upd->execute([$newToken, $fingerprint, $user['id']]);

                setcookie('mdrrmo_device_token', $newToken, [
                    'expires'  => time() + (30 * 24 * 60 * 60), // 30 days
                    'path'     => '/',
                    'secure'   => false, // Set to TRUE when deployed with HTTPS
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);

                $_SESSION['user_id'] = $user['id'];

                // Store a flag so the admin sees a "Device Registered" notice on dashboard
                $_SESSION['device_just_registered'] = true;

                redirect_by_role();
            }

        } else {

            // Citizens and coordinators: normal login, no device check
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;500;600;700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./asset/css/userlogin.css">
</head>
<body>

<!-- ================================================
     MOBILE: Splash Screen
     ================================================ -->
<div id="splash" onclick="goToLogin()">

  <div class="bg-base"></div>
  <div class="bg-pulse"></div>
  <div class="bg-drift"></div>
  <div class="honeycomb"></div>

  <div class="particle" style="width:6px;height:6px;left:12%;animation-duration:10s;animation-delay:0s;"></div>
  <div class="particle" style="width:4px;height:4px;left:28%;animation-duration:13s;animation-delay:2.5s;"></div>
  <div class="particle" style="width:7px;height:7px;left:52%;animation-duration:8s;animation-delay:1s;"></div>
  <div class="particle" style="width:5px;height:5px;left:70%;animation-duration:11s;animation-delay:3.5s;"></div>
  <div class="particle" style="width:3px;height:3px;left:85%;animation-duration:14s;animation-delay:0.5s;"></div>
  <div class="particle" style="width:5px;height:5px;left:40%;animation-duration:9s;animation-delay:5s;"></div>

  <div class="splash-content">
    <div class="seal-shine-wrap">
      <canvas id="orbitCanvas" width="280" height="280"></canvas>
      <div class="seal-wrap">
        <img class="seal-img"
             src="./img/mdrrmo.png"
             alt="MDRRMO Seal"
             onerror="this.style.display='none'; document.querySelector('.seal-fallback').style.display='grid';">
        <div class="seal-fallback">
          <div class="sq tl">🌳</div>
          <div class="sq tr">🌊</div>
          <div class="sq bl">🌍</div>
          <div class="sq br">🔥</div>
        </div>
      </div>
    </div>

    <div class="splash-title">MDRRMO</div>
    <div class="splash-hashtag">BidaAngLagingHanda</div>
    <div class="tap-hint">Tap anywhere to continue</div>
  </div>

</div>


<!-- ================================================
     MOBILE: Login Page
     ================================================ -->
<div id="login-page">

  <div class="login-shell">

    <div class="hero">
      <div class="logo-row" id="logoRow">
        <div class="logo-circle">
          <img src="./img/mdrrmo.png" alt="MDRRMO"
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
        Login to<br>your account
      </div>
    </div>

    <div class="card" id="card">

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

        <div class="field">
          <label class="field-label" for="email">Email / Username</label>
          <input type="email" id="email" name="email"
                 placeholder="Enter your email" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <!-- Mobile password field with eye toggle -->
        <div class="field password-field">
          <label class="field-label" for="mob-password">Password</label>
          <div class="password-wrapper">
            <input type="password" id="mob-password" name="password"
                   placeholder="Enter your password" required>
            <button type="button" class="toggle-password" data-target="mob-password" aria-label="Show password">
              <svg class="eye-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="forgot-row">
          <a href="#">Forgot Password?</a>
        </div>

        <!-- Device fingerprint hidden input (auto-filled by JS) -->
        <input type="hidden" name="device_fingerprint" id="mob-device-fingerprint" value="">

        <input type="hidden" name="lat" id="lat">
        <input type="hidden" name="lng" id="lng">

        <button type="submit" class="btn-signin">Login</button>

      </form>

      <p class="signup-row">Don't have an account? <a href="pages/signup.php">Sign up</a></p>

    </div>

  </div>

</div>


<!-- ================================================
     DESKTOP: Centered Card Layout
     ================================================ -->
<div id="desktop-page">

  <!-- CENTERED CARD -->
  <div class="dt-card">

    <!-- LEFT: Branding -->
    <div class="dt-card-left">

      <div class="dt-seal-wrap">
        <img src="./img/mdrrmo.png" alt="MDRRMO Seal"
             onerror="this.style.display='none'">
      </div>

      <div class="dt-agency">MDRRMO</div>
      <div class="dt-tagline">BidaAngLagingHanda</div>
      <div class="dt-bottom-badge">Municipal Government of San Ildefonso</div>

    </div><!-- /.dt-card-left -->

    <!-- RIGHT: Login Form -->
    <div class="dt-card-right">

      <div class="dt-form-header">
        <div class="dt-welcome">Welcome Back</div>
        <div class="dt-form-title">Login to<br>Your Account</div>
        <div class="dt-form-subtitle">Stay prepared and informed.<br>Access your MDRRMO account.</div>
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

      <form method="post">

        <div class="dt-field">
          <label for="dt-email">Email Address</label>
          <input type="email" id="dt-email" name="email"
                 placeholder="yourname@example.com" required
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <!-- Desktop password field with eye toggle -->
        <div class="dt-field">
          <label for="dt-password">Password</label>
          <div class="dt-password-wrapper">
            <input type="password" id="dt-password" name="password"
                   placeholder="Enter your password" required>
            <button type="button" class="toggle-password" data-target="dt-password" aria-label="Show password">
              <svg class="eye-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="dt-forgot">
          <a href="#">Forgot Password?</a>
        </div>

        <!-- Device fingerprint hidden input (auto-filled by JS) -->
        <input type="hidden" name="device_fingerprint" id="dt-device-fingerprint" value="">

        <input type="hidden" name="lat">
        <input type="hidden" name="lng">

        <button type="submit" class="dt-btn-signin">Login</button>

      </form>

      <div class="dt-divider"><span>New here?</span></div>

      <p class="dt-signup-row">Don't have an account? <a href="pages/signup.php">Sign up</a></p>

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
     DEVICE FINGERPRINTING (Methods 1 & 2)
     Generates a unique browser/device fingerprint
     and fills all hidden device_fingerprint inputs.
     ================================================ */

  async function generateFingerprint() {
    try {
      var components = [
        navigator.userAgent        || '',
        navigator.language         || '',
        (screen.width  || 0) + 'x' + (screen.height || 0),
        (screen.colorDepth         || 0).toString(),
        new Date().getTimezoneOffset().toString(),
        (navigator.hardwareConcurrency || 0).toString(),
        navigator.platform         || ''
      ];

      // Canvas fingerprint (Method 1 — visual rendering differs per device/GPU/OS)
      try {
        var canvas = document.createElement('canvas');
        var ctx    = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font         = '14px Arial';
        ctx.fillStyle    = '#f35a00';
        ctx.fillRect(120, 1, 60, 20);
        ctx.fillStyle    = '#002a5e';
        ctx.fillText('MDRRMO-device-fp', 2, 2);
        ctx.fillStyle    = 'rgba(180,80,20,0.7)';
        ctx.fillText('SanIldefonso2026', 4, 17);
        components.push(canvas.toDataURL());
      } catch (e) {
        components.push('canvas-unavailable');
      }

      // Hash all components together with SHA-256
      var raw        = components.join('|||');
      var encoded    = new TextEncoder().encode(raw);
      var hashBuffer = await crypto.subtle.digest('SHA-256', encoded);
      var hashArray  = Array.from(new Uint8Array(hashBuffer));
      return hashArray.map(function(b){ return b.toString(16).padStart(2,'0'); }).join('');

    } catch (e) {
      // Fallback: use a simpler string if SubtleCrypto is unavailable
      return (navigator.userAgent + screen.width + screen.height + navigator.language)
        .split('').reduce(function(a,c){ return ((a<<5)-a)+c.charCodeAt(0)|0; }, 0)
        .toString(16);
    }
  }

  // Fill fingerprint into all forms as soon as DOM is ready
  document.addEventListener('DOMContentLoaded', async function() {
    var fp = await generateFingerprint();
    var inputs = document.querySelectorAll('input[name="device_fingerprint"]');
    inputs.forEach(function(el){ el.value = fp; });
  });


  /* ================================================
     SPLASH ONCE — show only on first visit per
     browser session.
     ================================================ */

  var SPLASH_KEY = 'mdrrmo_splash_shown';
  var splashAlreadySeen = sessionStorage.getItem(SPLASH_KEY) === '1';

  if (splashAlreadySeen) {
    var splashEl = document.getElementById('splash');
    if (splashEl) splashEl.style.display = 'none';

    var loginEl = document.getElementById('login-page');
    if (loginEl) {
      loginEl.classList.add('visible');
      requestAnimationFrame(function() {
        setTimeout(function() {
          var lr = document.getElementById('logoRow');
          var hh = document.getElementById('heroHeadline');
          if (lr) lr.classList.add('visible');
          if (hh) hh.classList.add('visible');
        }, 60);
        setTimeout(function() {
          var c = document.getElementById('card');
          if (c) c.classList.add('visible');
        }, 180);
      });
    }
  }


  /* ================================================
     MOBILE: Original orbit canvas animation
     ================================================ */

  var canvas = document.getElementById('orbitCanvas');
  var ctx    = canvas ? canvas.getContext('2d') : null;

  var CX       = 140;
  var CY       = 140;
  var R        = 114;
  var TAIL_RAD = (220 * Math.PI) / 180;
  var TOTAL    = Math.PI * 2 * 1.25;
  var DURATION = 7000;

  var startTime = null;
  var rafId     = null;

  if (!splashAlreadySeen && ctx) {
    setTimeout(function() {
      startTime = performance.now();
      rafId = requestAnimationFrame(draw);
    }, 1100);
  }

  function draw(now) {
    var elapsed  = now - startTime;
    var progress = Math.min(elapsed / DURATION, 1);

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    var eased     = easeInOutCubic(progress);
    var headAngle = -Math.PI / 2 + eased * TOTAL;
    var tailAngle = headAngle - TAIL_RAD;

    var alpha = 1;
    if (progress < 0.10) alpha = progress / 0.10;
    else if (progress > 0.72) alpha = 1 - (progress - 0.72) / 0.28;

    var STEPS = 100;
    for (var i = 0; i < STEPS; i++) {
      var frac = i / STEPS;
      var a0   = tailAngle + frac * TAIL_RAD;
      var a1   = tailAngle + (frac + 1 / STEPS) * TAIL_RAD;
      var segOpacity = Math.pow(frac, 1.8) * alpha;
      var w    = 1 + frac * 5.5;
      var rr   = 255;
      var gg   = Math.round(160 + frac * 90);
      var bb   = Math.round(20  + frac * 200);

      ctx.beginPath();
      ctx.arc(CX, CY, R, a0, a1);
      ctx.strokeStyle = 'rgba(' + rr + ',' + gg + ',' + bb + ',' + segOpacity + ')';
      ctx.lineWidth   = w;
      ctx.lineCap     = 'round';
      ctx.stroke();
    }

    var hx = CX + R * Math.cos(headAngle);
    var hy = CY + R * Math.sin(headAngle);

    var g = ctx.createRadialGradient(hx, hy, 0, hx, hy, 28);
    g.addColorStop(0,    'rgba(255,250,200,' + (0.85 * alpha) + ')');
    g.addColorStop(0.25, 'rgba(255,210,100,' + (0.55 * alpha) + ')');
    g.addColorStop(0.6,  'rgba(255,140, 30,' + (0.18 * alpha) + ')');
    g.addColorStop(1,    'rgba(255, 80,  0,0)');
    ctx.beginPath();
    ctx.arc(hx, hy, 28, 0, Math.PI * 2);
    ctx.fillStyle = g;
    ctx.fill();

    var core = ctx.createRadialGradient(hx, hy, 0, hx, hy, 6);
    core.addColorStop(0,   'rgba(255,255,255,' + alpha + ')');
    core.addColorStop(0.6, 'rgba(255,240,160,' + (0.7 * alpha) + ')');
    core.addColorStop(1,   'rgba(255,200,80,0)');
    ctx.beginPath();
    ctx.arc(hx, hy, 6, 0, Math.PI * 2);
    ctx.fillStyle = core;
    ctx.fill();

    if (progress < 1) {
      rafId = requestAnimationFrame(draw);
    } else {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
  }

  function easeInOutCubic(t) {
    return t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t+2, 3)/2;
  }

  function goToLogin() {
    if (rafId) cancelAnimationFrame(rafId);

    sessionStorage.setItem(SPLASH_KEY, '1');

    document.getElementById('splash').classList.add('exit');

    setTimeout(function() {
      var splash = document.getElementById('splash');
      splash.style.display = 'none';

      var loginPage = document.getElementById('login-page');
      loginPage.classList.add('visible');

      requestAnimationFrame(function() {
        setTimeout(function() {
          document.getElementById('logoRow').classList.add('visible');
          document.getElementById('heroHeadline').classList.add('visible');
        }, 60);

        setTimeout(function() {
          document.getElementById('card').classList.add('visible');
        }, 180);
      });

    }, 420);
  }

  if (!splashAlreadySeen) {
    setTimeout(goToLogin, 8500);
  }


  /* ================================================
     MOBILE: Input focus styles
     ================================================ */
  document.querySelectorAll('.field input').forEach(function(inp) {
    inp.addEventListener('focus', function() {
      this.style.setProperty('border-bottom', '1.5px solid #c0391e', 'important');
    });
    inp.addEventListener('blur', function() {
      this.style.setProperty('border-bottom', '1.5px solid #c8c8c8', 'important');
    });
  });


  /* ================================================
     MOBILE: Login button ripple
     ================================================ */
  var btn = document.querySelector('.btn-signin');
  if (btn) {
    btn.addEventListener('click', function(e) {
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
      requestAnimationFrame(function() {
        rpl.style.transform = 'scale(2.6)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function() { rpl.remove(); }, 600);
    });
  }


  /* ================================================
     DESKTOP: Sign-in button ripple
     ================================================ */
  var dtBtn = document.querySelector('.dt-btn-signin');
  if (dtBtn) {
    dtBtn.addEventListener('click', function(e) {
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
      requestAnimationFrame(function() {
        rpl.style.transform = 'scale(2.8)';
        rpl.style.opacity   = '0';
      });
      setTimeout(function() { rpl.remove(); }, 600);
    });
  }


  /* ================================================
     SHARED: Eye / password toggle
     ================================================ */
  document.querySelectorAll('.toggle-password').forEach(function(button) {
    button.addEventListener('click', function(e) {
      e.preventDefault();

      var targetId = this.getAttribute('data-target');
      var input    = document.getElementById(targetId);
      if (!input) return;

      var isPassword = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPassword ? 'text' : 'password');

      var svg = this.querySelector('.eye-icon');
      if (isPassword) {
        svg.innerHTML =
          '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8' +
          'a18.45 18.45 0 0 1 5.06-5.94' +
          'M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8' +
          'a18.5 18.5 0 0 1-2.16 3.19' +
          'm-6.72-1.07a3 3 0 1 1-4.24-4.24"/>' +
          '<line x1="1" y1="1" x2="23" y2="23"/>';
        this.setAttribute('aria-label', 'Hide password');
      } else {
        svg.innerHTML =
          '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
          '<circle cx="12" cy="12" r="3"/>';
        this.setAttribute('aria-label', 'Show password');
      }
    });
  });

    /* ================================================
     GEOLOCATION (kept inactive — uncomment to enable)
     ================================================

  const allowedMunicipality = "san ildefonso";
  const allowedProvince     = "bulacan";

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

  */

</script>

</body>
</html>